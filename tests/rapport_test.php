<?php
// tests/rapport_test.php
// Tests fonctionnels du module rapport. Lancement : php tests/rapport_test.php
//
// La base est une base SQLite en mémoire : le schéma et les données de test sont recréés à chaque
// scénario, ce qui évite toute dépendance à une base PostgreSQL locale. Le SQL testé reste celui
// du modèle (aucune requête n'est réécrite pour les tests).

declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../model/rapport.php';
require_once __DIR__ . '/../model/personnel.php';
require_once __DIR__ . '/../controller/Gestionrapport.php';

$GLOBALS['total'] = 0;
$GLOBALS['echecs'] = 0;

function test(string $libelle, callable $scenario): void
{
    $GLOBALS['total']++;
    try {
        $scenario();
        echo "  OK    " . $libelle . "\n";
    } catch (Throwable $e) {
        $GLOBALS['echecs']++;
        echo "  ECHEC " . $libelle . "\n        " . $e->getMessage() . "\n";
    }
}

function egal(mixed $attendu, mixed $obtenu, string $contexte = ''): void
{
    if ($attendu !== $obtenu) {
        throw new RuntimeException(
            ($contexte !== '' ? $contexte . ' : ' : '')
            . 'attendu ' . var_export($attendu, true) . ', obtenu ' . var_export($obtenu, true)
        );
    }
}

function verifie(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Une exception est attendue : renvoie son message pour vérification. */
function exceptionAttendue(callable $action, string $classe = Throwable::class): string
{
    try {
        $action();
    } catch (Throwable $e) {
        if ($e instanceof $classe) {
            return $e->getMessage();
        }
        throw new RuntimeException('Exception de type inattendu : ' . get_class($e) . ' (' . $e->getMessage() . ')');
    }
    throw new RuntimeException('Aucune exception levée alors qu\'une erreur était attendue.');
}

/**
 * Base de test prête à l'emploi : schéma réduit aux tables du rapport, et jeu de données couvrant
 * les cas à vérifier (brouillon, autres personnels, bornes de période, statuts de genre).
 */
function baseDeTest(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // SQLite ne connaît pas NOW() : on l'aliaste pour que le SQL du modèle reste inchangé.
    $pdo->sqliteCreateFunction('NOW', fn () => date('Y-m-d H:i:s'));

    $pdo->exec(
        "CREATE TABLE personnel (
            id INTEGER PRIMARY KEY,
            nom TEXT NOT NULL,
            nom_usage TEXT,
            prenom TEXT NOT NULL,
            statut TEXT NOT NULL,
            identifiant_scientifique TEXT UNIQUE,
            est_actif INTEGER NOT NULL DEFAULT 1,
            structure_id INTEGER,
            cree_le TEXT,
            mis_a_jour_le TEXT
        );
        CREATE TABLE type_activite (
            id INTEGER PRIMARY KEY,
            code TEXT NOT NULL UNIQUE,
            libelle TEXT NOT NULL,
            description TEXT,
            est_actif INTEGER NOT NULL DEFAULT 1
        );
        CREATE TABLE activite (
            id INTEGER PRIMARY KEY,
            personnel_id INTEGER NOT NULL,
            titre TEXT NOT NULL,
            description TEXT,
            date_activite TEXT NOT NULL,
            est_collective INTEGER NOT NULL DEFAULT 0,
            statut TEXT NOT NULL DEFAULT 'brouillon',
            type_activite_id INTEGER,
            structure_id INTEGER,
            cree_le TEXT,
            mis_a_jour_le TEXT
        );
        CREATE TABLE gabarit (
            id INTEGER PRIMARY KEY,
            code TEXT NOT NULL UNIQUE,
            libelle TEXT NOT NULL,
            statut_requis TEXT NOT NULL,
            description TEXT
        );
        CREATE TABLE personnel_statut_historique (
            id INTEGER PRIMARY KEY,
            personnel_id INTEGER NOT NULL,
            statut TEXT NOT NULL,
            date_debut TEXT NOT NULL,
            date_fin TEXT,
            cree_le TEXT
        );"
    );

    $pdo->exec(
        "INSERT INTO personnel (id, nom, prenom, statut, identifiant_scientifique, cree_le, mis_a_jour_le) VALUES
            (1, 'Dupont', 'Jean', 'Maître de conférences', 'JDUPONT2026', '2025-01-01', '2025-01-01'),
            (2, 'Haddad', 'Nadia', 'Maîtresse de conférences', 'NHADDAD2026', '2025-01-01', '2025-01-01'),
            (3, 'Petit', 'Thomas', 'Enseignant-chercheur', 'TPETIT2026', '2025-01-01', '2025-01-01');
        INSERT INTO type_activite (id, code, libelle) VALUES
            (1, 'ENS', 'Enseignement'),
            (2, 'RECH', 'Recherche');
        INSERT INTO activite (id, personnel_id, titre, description, date_activite, est_collective, statut, type_activite_id) VALUES
            (1, 1, 'Cours magistraux', 'TD de 20h', '2026-02-10', 0, 'soumise', 1),
            (2, 1, 'Article publié', NULL, '2026-03-05', 0, 'soumise', 2),
            (3, 1, 'Brouillon en cours', NULL, '2026-02-15', 0, 'brouillon', 1),
            (4, 2, 'Cours de Nadia', NULL, '2026-02-20', 0, 'soumise', 1),
            (5, 1, 'Hors période', NULL, '2025-12-31', 0, 'soumise', 2),
            (6, 1, 'Borne de fin', NULL, '2026-06-30', 0, 'soumise', 1);
        INSERT INTO gabarit (id, code, libelle, statut_requis, description) VALUES
            (1, 'ENS_COURS_MCF', 'Cours magistraux et TD', 'Maître de conférences', 'Déclaration des heures de cours'),
            (2, 'ENS_COURS_MCF_F', 'Cours magistraux et TD', 'Maîtresse de conférences', 'Déclaration des heures de cours'),
            (3, 'ENS_RECH_PUBLI', 'Enseignement et publications', 'Enseignant-chercheur', 'Enseignement et recherche'),
            (4, 'RECH_DOCTORAT', 'Travaux de thèse', 'Doctorante', 'Travaux de doctorat'),
            (5, 'ADM_SYSTEME', 'Administration système', 'Ingénieure système', 'Infrastructure'),
            (6, 'DEV_PROJET', 'Développement de projet', 'Etudiant/Dev', 'Projet pédagogique');
        INSERT INTO personnel_statut_historique (personnel_id, statut, date_debut, date_fin) VALUES
            (1, 'Maître de conférences', '2025-01-01', NULL),
            (2, 'Maîtresse de conférences', '2025-01-01', NULL),
            (3, 'Enseignant-chercheur', '2025-01-01', NULL);"
    );

    return $pdo;
}

// ---------- Tâche 1 : service de génération ----------

echo "Tâche 1 - service de génération\n";

test('exclut les brouillons du rapport définitif', function (): void {
    $activites = getActivitesConfirmees(baseDeTest(), 1, '2026-01-01', '2026-06-30');
    egal([1, 2, 6], array_column($activites, 'id'), 'activités retenues');
    foreach ($activites as $activite) {
        verifie($activite['statut'] !== STATUT_ACTIVITE_BROUILLON, 'un brouillon figure dans le rapport');
    }
});

test('ne retient que les activités du personnel demandé', function (): void {
    $activites = getActivitesConfirmees(baseDeTest(), 2, '2026-01-01', '2026-06-30');
    egal([4], array_column($activites, 'id'), 'activités de Nadia');
});

test('applique les bornes de période, incluses', function (): void {
    $activites = getActivitesConfirmees(baseDeTest(), 1, '2026-02-10', '2026-03-05');
    egal([1, 2], array_column($activites, 'id'), 'bornes incluses');
});

test('joint le libellé du type d\'activité', function (): void {
    $activites = getActivitesConfirmees(baseDeTest(), 1, '2026-02-10', '2026-02-10');
    egal('Enseignement', $activites[0]['type_libelle'] ?? null, 'libellé du type');
});

test('refuse une période inversée', function (): void {
    $message = exceptionAttendue(
        fn () => getActivitesConfirmees(baseDeTest(), 1, '2026-06-30', '2026-01-01'),
        InvalidArgumentException::class
    );
    verifie($message !== '', 'message d\'erreur vide');
});

test('refuse une date mal formée', function (): void {
    exceptionAttendue(
        fn () => getActivitesConfirmees(baseDeTest(), 1, '01/01/2026', '2026-06-30'),
        InvalidArgumentException::class
    );
});

// ---------- Tâche 2 : statut et filtrage des gabarits ----------

echo "\nTâche 2 - statut et filtrage des gabarits\n";

test('normalise les variantes de genre d\'un statut', function (): void {
    $reference = normaliserStatut('Maître de conférences');
    egal($reference, normaliserStatut('Maîtresse de conférences'), 'maître / maîtresse');
    egal($reference, normaliserStatut('MAÎTRESSE DE CONFÉRENCES'), 'casse et accents');
    egal('enseignant chercheur', normaliserStatut('Enseignante-chercheuse'), 'enseignante-chercheuse');
    egal('enseignant chercheur', normaliserStatut('Enseignant·e·s-chercheur·e·s'), 'écriture inclusive');
    egal('doctorant', normaliserStatut('Doctorante'), 'doctorante');
    egal('ingenieur systeme', normaliserStatut('Ingénieure système'), 'ingénieure système');
    egal('administrateur applicatif', normaliserStatut('Administratrice applicative'), 'administratrice applicative');
    egal('etudiant dev', normaliserStatut('Étudiant/Dev'), 'étudiant/dev');
});

test('charge dynamiquement les gabarits du statut courant', function (): void {
    $gabarits = gabaritsDisponibles(baseDeTest(), 1, 'Maître de conférences');
    egal(
        ['ENS_COURS_MCF', 'ENS_COURS_MCF_F'],
        array_column($gabarits, 'code'),
        'gabarits de Jean (les deux variantes de genre du même statut)'
    );
    foreach ($gabarits as $gabarit) {
        verifie($gabarit['retroactif'] === false, 'un gabarit courant est marqué rétroactif');
    }
});

test('ne propose pas un gabarit d\'un statut jamais détenu', function (): void {
    $codes = array_column(gabaritsDisponibles(baseDeTest(), 3, 'Enseignant-chercheur'), 'code');
    egal(['ENS_RECH_PUBLI'], $codes, 'gabarits de Thomas');
    verifie(!in_array('RECH_DOCTORAT', $codes, true), 'gabarit de doctorant proposé à un enseignant-chercheur');
});

test('accepte un gabarit saisi avec une autre variante de genre', function (): void {
    $pdo = baseDeTest();
    $pdo->exec("INSERT INTO gabarit (code, libelle, statut_requis) VALUES ('ENS_CHEUSE', 'Recherche', 'Enseignante-chercheuse')");
    $codes = array_column(gabaritsDisponibles($pdo, 3, 'Enseignant-chercheur'), 'code');
    verifie(in_array('ENS_CHEUSE', $codes, true), 'variante féminine non reconnue');
});

test('marque rétroactif le gabarit d\'un statut antérieur', function (): void {
    $pdo = baseDeTest();
    // Jean était doctorant jusqu'au 31/12/2025, puis maître de conférences.
    $pdo->exec("UPDATE personnel_statut_historique SET date_fin = '2025-12-31' WHERE personnel_id = 1");
    $pdo->exec("INSERT INTO personnel_statut_historique (personnel_id, statut, date_debut, date_fin) VALUES (1, 'Doctorant', '2024-09-01', '2025-12-31')");

    $gabarits = gabaritsDisponibles($pdo, 1, 'Maître de conférences');
    $retroactifs = array_column(array_filter($gabarits, fn (array $g): bool => $g['retroactif']), 'code');
    egal(['RECH_DOCTORAT'], array_values($retroactifs), 'gabarits rétroactifs');
});

// ---------- Tâche 3 : compatibilité des gabarits ----------

echo "\nTâche 3 - compatibilité des gabarits\n";

test('autorise le gabarit du statut actuel', function (): void {
    $resultat = verifierCompatibiliteGabarit(baseDeTest(), 'ENS_COURS_MCF', 1, 'Maître de conférences');
    egal(true, $resultat['autorise'], 'autorisation');
    egal(false, $resultat['retroactif'], 'rétroactivité');
});

test('accepte le gabarit d\'une variante de genre du même statut', function (): void {
    $resultat = verifierCompatibiliteGabarit(baseDeTest(), 'ENS_COURS_MCF', 2, 'Maîtresse de conférences');
    egal(true, $resultat['autorise'], 'autorisation');
    egal('Cours magistraux et TD', $resultat['gabarit']['libelle'] ?? null, 'gabarit retenu');
});

test('bloque un gabarit d\'un autre statut et fournit un message', function (): void {
    $resultat = verifierCompatibiliteGabarit(
        baseDeTest(),
        'RECH_DOCTORAT',
        1,
        'Maître de conférences',
        '2026-01-01',
        '2026-06-30'
    );
    egal(false, $resultat['autorise'], 'autorisation');
    verifie($resultat['message'] !== '', 'message d\'alerte absent');
    egal(null, $resultat['gabarit'], 'gabarit renvoyé malgré le blocage');
});

test('bloque un code de gabarit inconnu', function (): void {
    $resultat = verifierCompatibiliteGabarit(baseDeTest(), 'GABARIT_INEXISTANT', 1, 'Maître de conférences');
    egal(false, $resultat['autorise'], 'autorisation');
    egal(null, $resultat['gabarit'], 'gabarit');
});

test('autorise un gabarit rétroactif justifié par la période', function (): void {
    $pdo = baseDeTest();
    $pdo->exec("UPDATE personnel_statut_historique SET date_fin = '2025-12-31' WHERE personnel_id = 1");
    $pdo->exec("INSERT INTO personnel_statut_historique (personnel_id, statut, date_debut, date_fin) VALUES (1, 'Doctorant', '2024-09-01', '2025-12-31')");

    $resultat = verifierCompatibiliteGabarit($pdo, 'RECH_DOCTORAT', 1, 'Maître de conférences', '2025-01-01', '2025-12-31');
    egal(true, $resultat['autorise'], 'autorisation');
    egal(true, $resultat['retroactif'], 'rétroactivité');
});

test('bloque un gabarit rétroactif hors de la période du statut', function (): void {
    $pdo = baseDeTest();
    $pdo->exec("UPDATE personnel_statut_historique SET date_fin = '2025-12-31' WHERE personnel_id = 1");
    $pdo->exec("INSERT INTO personnel_statut_historique (personnel_id, statut, date_debut, date_fin) VALUES (1, 'Doctorant', '2024-09-01', '2025-12-31')");

    $resultat = verifierCompatibiliteGabarit($pdo, 'RECH_DOCTORAT', 1, 'Maître de conférences', '2026-01-01', '2026-06-30');
    egal(false, $resultat['autorise'], 'autorisation');
});

test('historise le changement de statut du profil', function (): void {
    $pdo = baseDeTest();
    modifierPersonnel($pdo, 1, [
        'nom' => 'Dupont',
        'nom_usage' => '',
        'prenom' => 'Jean',
        'statut' => 'Professeur des universités',
        'identifiant_scientifique' => 'JDUPONT2026',
        'structure_id' => 0,
    ], null);

    $lignes = $pdo->query('SELECT statut, date_debut, date_fin FROM personnel_statut_historique WHERE personnel_id = 1 ORDER BY id')->fetchAll();
    egal(2, count($lignes), 'nombre de périodes');
    egal('Maître de conférences', $lignes[0]['statut'], 'ancien statut');
    verifie($lignes[0]['date_fin'] !== null, 'ancienne période non clôturée');
    egal('Professeur des universités', $lignes[1]['statut'], 'nouveau statut');
    egal(null, $lignes[1]['date_fin'], 'nouvelle période ouverte');
});

test('n\'ouvre pas de période quand le statut ne change pas', function (): void {
    $pdo = baseDeTest();
    modifierPersonnel($pdo, 1, [
        'nom' => 'Dupont',
        'nom_usage' => '',
        'prenom' => 'Jean',
        'statut' => 'Maître de conférences',
        'identifiant_scientifique' => 'JDUPONT2026',
        'structure_id' => 0,
    ], null);

    $nombre = (int) $pdo->query('SELECT COUNT(*) FROM personnel_statut_historique WHERE personnel_id = 1')->fetchColumn();
    egal(1, $nombre, 'périodes d\'historique');
});

// ---------- Tâche 4 : export des documents ----------

echo "\nTâche 4 - export des documents\n";

/** Contexte d'export correspondant au jeu de données de test. */
function contexteDeTest(): array
{
    return [
        'personnel' => ['nom' => 'Dupont', 'nom_usage' => null, 'prenom' => 'Jean', 'statut' => 'Maître de conférences'],
        'date_debut' => '2026-01-01',
        'date_fin' => '2026-06-30',
        'gabarit' => [
            'code' => 'ENS_COURS_MCF',
            'libelle' => 'Cours magistraux et TD',
            'statut_requis' => 'Maître de conférences',
            'description' => 'Déclaration des heures de cours',
        ],
    ];
}

test('le CSV est en UTF-8 avec BOM et séparateur point-virgule', function (): void {
    $csv = contenuCsv(getActivitesConfirmees(baseDeTest(), 1, '2026-01-01', '2026-06-30'));
    egal("\xEF\xBB\xBF", substr($csv, 0, 3), 'BOM UTF-8');

    $lignes = explode("\n", substr($csv, 3));
    egal('Date;Titre;Type;Nature;Statut;Description', $lignes[0] ?? null, 'en-tête');
    verifie(count($lignes) >= 4, 'lignes manquantes dans le CSV');
});

test('le CSV ne contient que les activités confirmées du personnel', function (): void {
    $csv = contenuCsv(getActivitesConfirmees(baseDeTest(), 1, '2026-01-01', '2026-06-30'));
    verifie(strpos($csv, 'Brouillon en cours') === false, 'un brouillon figure dans le CSV');
    verifie(strpos($csv, 'Cours de Nadia') === false, 'activité d\'un autre personnel dans le CSV');
    verifie(strpos($csv, 'Cours magistraux') !== false, 'activité confirmée absente du CSV');
    verifie(strpos($csv, 'Hors période') === false, 'activité hors période dans le CSV');
});

test('le CSV échappe les séparateurs et les guillemets', function (): void {
    $csv = contenuCsv([
        [
            'date_activite' => '2026-02-10',
            'titre' => 'Déjeuner; "département"',
            'type_libelle' => 'Réunion',
            'est_collective' => 0,
            'statut' => 'soumise',
            'description' => "Ligne 1\nLigne 2",
        ],
    ]);
    verifie(
        strpos($csv, '"Déjeuner; ""département"""') !== false,
        'échappement CSV incorrect : ' . $csv
    );
    verifie(strpos($csv, "\"Ligne 1\nLigne 2\"") !== false, 'saut de ligne non échappé');
});

test('le CSV neutralise une cellule interprétée comme formule', function (): void {
    $csv = contenuCsv([[
        'date_activite' => '2026-02-10',
        'titre' => '=1+1',
        'type_libelle' => 'Test',
        'est_collective' => 0,
        'statut' => 'soumise',
        'description' => '',
    ]]);
    verifie(strpos($csv, "'=1+1") !== false, 'formule non neutralisée');
});

test('le PDF est un document binaire non vide', function (): void {
    $pdf = contenuPdf(getActivitesConfirmees(baseDeTest(), 1, '2026-01-01', '2026-06-30'), contexteDeTest());
    egal('%PDF-', substr($pdf, 0, 5), 'signature PDF');
    verifie(strlen($pdf) > 1000, 'PDF trop petit : ' . strlen($pdf) . ' octets');
    verifie(strpos($pdf, '%%EOF') !== false, 'PDF non terminé');
});

test('les en-têtes de téléchargement sont ceux du format demandé', function (): void {
    $csv = genererExport([], contexteDeTest(), 'csv');
    egal('text/csv; charset=UTF-8', $csv['type_mime'], 'type MIME CSV');
    egal('rapport_activites_2026-01-01_2026-06-30.csv', $csv['nom_fichier'], 'nom du fichier CSV');

    $entetes = entetesTelechargement($csv);
    verifie(in_array('Content-Disposition: attachment; filename="rapport_activites_2026-01-01_2026-06-30.csv"', $entetes, true), 'en-tête de téléchargement CSV');
    verifie(in_array('Content-Length: ' . strlen($csv['contenu']), $entetes, true), 'taille annoncée');
});

test('refuse un format d\'export inconnu', function (): void {
    exceptionAttendue(fn () => genererExport([], contexteDeTest(), 'docx'), InvalidArgumentException::class);
});

// ---------- Tâche 5 : formulaire et contrôleur ----------

echo "\nTâche 5 - formulaire et contrôleur\n";

/** Personnel connecté du jeu de test (Jean, maître de conférences). */
function utilisateurDeTest(): array
{
    return ['id' => 1, 'nom' => 'Dupont', 'nom_usage' => null, 'prenom' => 'Jean', 'statut' => 'Maître de conférences'];
}

function formulaireValide(): array
{
    return ['date_debut' => '2026-01-01', 'date_fin' => '2026-06-30', 'gabarit' => 'ENS_COURS_MCF', 'format' => 'csv'];
}

test('valide les champs obligatoires du formulaire', function (): void {
    $erreurs = validerRapport(['date_debut' => '', 'date_fin' => '', 'gabarit' => '', 'format' => 'docx']);
    verifie(isset($erreurs['date_debut']), 'date de début non contrôlée');
    verifie(isset($erreurs['date_fin']), 'date de fin non contrôlée');
    verifie(isset($erreurs['gabarit']), 'gabarit non contrôlé');
    verifie(isset($erreurs['format']), 'format non contrôlé');
});

test('refuse une période inversée', function (): void {
    $erreurs = validerRapport(['date_debut' => '2026-06-30', 'date_fin' => '2026-01-01', 'gabarit' => 'ENS_COURS_MCF', 'format' => 'pdf']);
    verifie(isset($erreurs['date_fin']), 'période inversée acceptée');
});

test('accepte un formulaire complet et valide', function (): void {
    egal([], validerRapport(formulaireValide()), 'erreurs signalées');
});

test('bloque la génération et ne produit aucun fichier pour un gabarit étranger', function (): void {
    $valeurs = formulaireValide();
    $valeurs['gabarit'] = 'RECH_DOCTORAT';

    $resultat = preparerRapport(baseDeTest(), utilisateurDeTest(), $valeurs);
    verifie($resultat['alerte'] !== null, 'aucune alerte pour un gabarit incompatible');
    egal(null, $resultat['export'], 'un fichier a été généré malgré le blocage');
});

test('pose le message de blocage en session', function (): void {
    $_SESSION = [];
    enregistrerAlerteRapport("Signal d'alerte : ce gabarit est inapplicable à votre statut.");

    egal('erreur', $_SESSION['flash'][0]['type'] ?? null, 'type de message');
    egal(
        "Signal d'alerte : ce gabarit est inapplicable à votre statut.",
        $_SESSION['flash'][0]['message'] ?? null,
        'message de session'
    );
});

test('génère le fichier demandé pour un gabarit compatible', function (): void {
    $resultat = preparerRapport(baseDeTest(), utilisateurDeTest(), formulaireValide());
    egal(null, $resultat['alerte'], 'alerte inattendue');
    egal('text/csv; charset=UTF-8', $resultat['export']['type_mime'] ?? null, 'type MIME');
    verifie(substr($resultat['export']['contenu'] ?? '', 0, 3) === "\xEF\xBB\xBF", 'BOM absent');
});

test('n\'exporte que les activités du personnel connecté', function (): void {
    $resultat = preparerRapport(baseDeTest(), utilisateurDeTest(), formulaireValide());
    $contenu = $resultat['export']['contenu'];
    verifie(strpos($contenu, 'Cours de Nadia') === false, 'activité d\'un autre personnel exportée');
    verifie(strpos($contenu, 'Brouillon en cours') === false, 'brouillon exporté');
    verifie(strpos($contenu, 'Cours magistraux') !== false, 'activité du personnel absenté');
});

test('génère un PDF quand ce format est demandé', function (): void {
    $valeurs = formulaireValide();
    $valeurs['format'] = 'pdf';

    $resultat = preparerRapport(baseDeTest(), utilisateurDeTest(), $valeurs);
    egal('application/pdf', $resultat['export']['type_mime'] ?? null, 'type MIME');
    egal('%PDF-', substr($resultat['export']['contenu'] ?? '', 0, 5), 'signature PDF');
});

echo "\n" . ($GLOBALS['total'] - $GLOBALS['echecs']) . '/' . $GLOBALS['total'] . " tests réussis.\n";
exit($GLOBALS['echecs'] === 0 ? 0 : 1);
