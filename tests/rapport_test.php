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

echo "\n" . ($GLOBALS['total'] - $GLOBALS['echecs']) . '/' . $GLOBALS['total'] . " tests réussis.\n";
exit($GLOBALS['echecs'] === 0 ? 0 : 1);
