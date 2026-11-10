<?php
// model/rapport.php
// Rapport d'activités individuel : activités confirmées sur une période, gabarits liés au statut,
// contrôle de compatibilité et génération des exports.

/** Statut d'activité exclu du rapport définitif : un brouillon n'a pas encore été soumis. */
const STATUT_ACTIVITE_BROUILLON = 'brouillon';

/**
 * Activités confirmées d'un personnel sur la période [dateDebut, dateFin], bornes incluses.
 * Les brouillons sont exclus et le libellé du type d'activité est joint à chaque ligne.
 *
 * Les dates sont au format AAAA-MM-JJ ; une période inversée ou mal formée lève une exception
 * (le contrôleur valide les entrées avant l'appel, cette vérification reste une garde).
 */
function getActivitesConfirmees(PDO $pdo, int $personnelId, string $dateDebut, string $dateFin): array
{
    if (!dateValide($dateDebut) || !dateValide($dateFin)) {
        throw new InvalidArgumentException('Les dates du rapport ne sont pas valides.');
    }
    if ($dateDebut > $dateFin) {
        throw new InvalidArgumentException('La date de début doit précéder ou égaler la date de fin.');
    }

    $requete = $pdo->prepare(
        'SELECT a.id, a.titre, a.description, a.date_activite, a.est_collective, a.statut,
                t.libelle AS type_libelle
         FROM activite a
         LEFT JOIN type_activite t ON t.id = a.type_activite_id
         WHERE a.personnel_id = :personnel
           AND a.date_activite BETWEEN :debut AND :fin
           AND a.statut <> :brouillon
         ORDER BY a.date_activite ASC, a.id ASC'
    );
    $requete->execute([
        'personnel' => $personnelId,
        'debut' => $dateDebut,
        'fin' => $dateFin,
        'brouillon' => STATUT_ACTIVITE_BROUILLON,
    ]);
    return $requete->fetchAll();
}

// ---------- Statuts et gabarits ----------

/** Accents et ligatures retirés avant comparaison des statuts. */
const ACCENTS_STATUT = [
    'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a',
    'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
    'î' => 'i', 'ï' => 'i', 'í' => 'i',
    'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o',
    'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
    'ÿ' => 'y', 'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae', 'ñ' => 'n',
];

/** Marques d'écriture inclusive laissées seules après découpage : « Enseignant·e », « Maître(sse) ». */
const MOTS_INCLUSIFS_SEULS = ['e', 'es', 's', 'se', 'sse', 'ne', 'nes', 'ere'];

/**
 * Forme canonique d'un statut, pour que la correspondance gabarit / profil fonctionne quelle que
 * soit la variante stockée : casse, accents, genre (« Maîtresse de conférences ») et écriture
 * inclusive (« Enseignant·e·s », « Docteur(e) »).
 */
function normaliserStatut(string $statut): string
{
    $texte = mb_strtolower(trim($statut), 'UTF-8');
    $texte = strtr($texte, ACCENTS_STATUT);
    // Séparateurs : tiret, barre oblique (« Etudiant/Dev »), parenthèses et points médians inclusifs.
    $texte = strtr($texte, [
        '·' => ' ', '•' => ' ', '’' => ' ', "'" => ' ', '(' => ' ', ')' => ' ',
        '[' => ' ', ']' => ' ', '/' => ' ', '_' => ' ', '-' => ' ',
    ]);
    $texte = preg_replace('/\s+/u', ' ', $texte) ?? $texte;

    $mots = [];
    foreach (explode(' ', trim($texte)) as $mot) {
        if ($mot === '') {
            continue;
        }
        $mot = motAuMasculin($mot);
        if (in_array($mot, MOTS_INCLUSIFS_SEULS, true)) {
            continue;
        }
        $mots[] = $mot;
    }
    return implode(' ', $mots);
}

/** Ramène un mot à sa forme masculine de référence. L'ordre des règles compte (formes longues d'abord). */
function motAuMasculin(string $mot): string
{
    $regles = [
        'ienne' => 'ien',   // technicienne -> technicien
        'trice' => 'teur',  // administratrice -> administrateur
        'euse'  => 'eur',   // chercheuse -> chercheur
        'esse'  => 'e',     // maitresse -> maitre
        'ante'  => 'ant',   // enseignante, doctorante, etudiante
        'ente'  => 'ent',   // presidente -> president
        'eure'  => 'eur',   // ingenieure -> ingenieur
        'iere'  => 'ier',   // infirmiere -> infirmier
        'ive'   => 'if',    // applicative -> applicatif
    ];
    foreach ($regles as $feminin => $masculin) {
        $longueur = mb_strlen($feminin);
        if (mb_strlen($mot) > $longueur + 1 && mb_substr($mot, -$longueur) === $feminin) {
            return mb_substr($mot, 0, -$longueur) . $masculin;
        }
    }
    return $mot;
}

/**
 * Statuts détenus par une personne, du plus récent au plus ancien.
 * L'historique est la source de vérité ; à défaut (profil antérieur à la migration), le statut
 * courant du profil est utilisé.
 */
function statutsDuPersonnel(PDO $pdo, int $personnelId): array
{
    $requete = $pdo->prepare(
        'SELECT statut FROM personnel_statut_historique
         WHERE personnel_id = :personnel
         ORDER BY date_debut DESC, id DESC'
    );
    $requete->execute(['personnel' => $personnelId]);
    $statuts = $requete->fetchAll(PDO::FETCH_COLUMN);

    if (!$statuts) {
        $requete = $pdo->prepare('SELECT statut FROM personnel WHERE id = :personnel');
        $requete->execute(['personnel' => $personnelId]);
        $statuts = array_filter([$requete->fetchColumn()]);
    }
    return array_values(array_unique(array_filter($statuts, 'is_string')));
}

/**
 * Gabarits utilisables par une personne : ceux de son statut actuel, puis ceux de ses statuts
 * antérieurs (marqués « retroactif »). Un gabarit d'un statut jamais détenu n'est pas proposé.
 */
function gabaritsDisponibles(PDO $pdo, int $personnelId, string $statutActuel): array
{
    $canoniques = array_map('normaliserStatut', array_merge(statutsDuPersonnel($pdo, $personnelId), [$statutActuel]));
    $actuel = normaliserStatut($statutActuel);

    $gabarits = [];
    $requete = $pdo->query('SELECT id, code, libelle, statut_requis, description FROM gabarit ORDER BY libelle, code');
    foreach ($requete as $gabarit) {
        $canonique = normaliserStatut($gabarit['statut_requis']);
        if (!in_array($canonique, $canoniques, true)) {
            continue;
        }
        $gabarit['retroactif'] = $canonique !== $actuel;
        $gabarits[] = $gabarit;
    }

    usort($gabarits, function (array $a, array $b): int {
        return [$a['retroactif'], mb_strtolower($a['libelle'], 'UTF-8'), $a['code']]
            <=> [$b['retroactif'], mb_strtolower($b['libelle'], 'UTF-8'), $b['code']];
    });
    return $gabarits;
}

/** Gabarit par code, ou null s'il n'existe pas. Sert à revérifier côté serveur le code reçu. */
function gabaritParCode(PDO $pdo, string $code): ?array
{
    if ($code === '') {
        return null;
    }
    $requete = $pdo->prepare('SELECT id, code, libelle, statut_requis, description FROM gabarit WHERE code = :code');
    $requete->execute(['code' => $code]);
    return $requete->fetch() ?: null;
}

/**
 * Vrai si le statut demandé a été détenu pendant au moins un jour de la période.
 * C'est cette justification qui autorise un gabarit rétroactif : une période entièrement postérieure
 * au changement de statut ne le justifie pas.
 */
function statutJustifieSurPeriode(PDO $pdo, int $personnelId, string $statutRequis, string $dateDebut, string $dateFin): bool
{
    $canonique = normaliserStatut($statutRequis);
    $requete = $pdo->prepare(
        'SELECT statut FROM personnel_statut_historique
         WHERE personnel_id = :personnel
           AND date_debut <= :fin
           AND (date_fin IS NULL OR date_fin >= :debut)'
    );
    $requete->execute(['personnel' => $personnelId, 'debut' => $dateDebut, 'fin' => $dateFin]);

    foreach ($requete as $ligne) {
        if (normaliserStatut($ligne['statut']) === $canonique) {
            return true;
        }
    }
    return false;
}

/**
 * Contrôle la compatibilité entre un gabarit et le profil, côté serveur : le code reçu du formulaire
 * n'est jamais cru sur parole.
 *
 * Renvoie ['autorise' => bool, 'message' => string, 'gabarit' => ?array, 'retroactif' => bool].
 * Un gabarit d'un autre statut n'est accepté que si la période fournie justifie ce statut (rétroactif) ;
 * sinon la génération doit être bloquée et le message affiché à l'utilisateur.
 */
function verifierCompatibiliteGabarit(
    PDO $pdo,
    string $gabaritCode,
    int $personnelId,
    string $statutActuel,
    ?string $dateDebut = null,
    ?string $dateFin = null
): array {
    $gabarit = gabaritParCode($pdo, $gabaritCode);
    if ($gabarit === null) {
        return [
            'autorise' => false,
            'message' => "Signal d'alerte : le gabarit demandé n'existe pas.",
            'gabarit' => null,
            'retroactif' => false,
        ];
    }

    if (normaliserStatut($gabarit['statut_requis']) === normaliserStatut($statutActuel)) {
        return ['autorise' => true, 'message' => '', 'gabarit' => $gabarit, 'retroactif' => false];
    }

    $periodeExploitable = $dateDebut !== null && $dateFin !== null
        && dateValide($dateDebut) && dateValide($dateFin) && $dateDebut <= $dateFin;

    if ($periodeExploitable && statutJustifieSurPeriode($pdo, $personnelId, $gabarit['statut_requis'], $dateDebut, $dateFin)) {
        return ['autorise' => true, 'message' => '', 'gabarit' => $gabarit, 'retroactif' => true];
    }

    return [
        'autorise' => false,
        'message' => "Signal d'alerte : le gabarit « " . $gabarit['libelle'] . " » est réservé au statut « "
            . $gabarit['statut_requis'] . ' » : il ne correspond ni à votre statut actuel (' . $statutActuel
            . ') ni à la période choisie.',
        'gabarit' => null,
        'retroactif' => false,
    ];
}

// ---------- Export ----------

/** Formats d'export proposés dans le formulaire. */
const FORMATS_EXPORT = [
    'pdf' => 'Document PDF',
    'csv' => 'Fichier CSV',
];

/**
 * Neutralise une cellule qui commencerait par un opérateur de formule : Excel l'interpréterait
 * comme un calcul (injection CSV). Le texte reste lisible, précédé d'une apostrophe.
 */
function protegerCelluleCsv(string $valeur): string
{
    return preg_match('/^[=+\-@\t\r]/', $valeur) === 1 ? "'" . $valeur : $valeur;
}

/**
 * Contenu CSV du rapport : UTF-8 avec BOM (Excel), séparateur « ; », échappement RFC 4180 assuré
 * par fputcsv (guillemets doublés). Les activités reçues sont déjà filtrées (brouillons exclus).
 */
function contenuCsv(array $activites): string
{
    $flux = fopen('php://temp', 'r+');
    if ($flux === false) {
        throw new RuntimeException('Impossible de préparer le fichier CSV.');
    }

    fwrite($flux, "\xEF\xBB\xBF");
    fputcsv($flux, ['Date', 'Titre', 'Type', 'Nature', 'Statut', 'Description'], ';', '"', '');
    foreach ($activites as $activite) {
        fputcsv($flux, [
            protegerCelluleCsv((string) $activite['date_activite']),
            protegerCelluleCsv((string) $activite['titre']),
            protegerCelluleCsv((string) ($activite['type_libelle'] ?? '')),
            $activite['est_collective'] ? 'Collective' : 'Individuelle',
            protegerCelluleCsv(ucfirst((string) $activite['statut'])),
            protegerCelluleCsv((string) ($activite['description'] ?? '')),
        ], ';', '"', '');
    }

    rewind($flux);
    $contenu = (string) stream_get_contents($flux);
    fclose($flux);
    return $contenu;
}

/**
 * Contenu PDF du rapport, produit par Dompdf à partir du gabarit HTML views/Rapport/pdf.php.
 * La librairie est installée par Composer (composer.json) ; son absence est signalée clairement.
 */
function contenuPdf(array $activites, array $contexte): string
{
    if (!class_exists(\Dompdf\Dompdf::class)) {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
    }
    if (!class_exists(\Dompdf\Dompdf::class)) {
        throw new RuntimeException('La librairie Dompdf est absente : lancez « composer install ».');
    }

    ob_start();
    extract(['activites' => $activites, 'contexte' => $contexte], EXTR_SKIP);
    require __DIR__ . '/../views/Rapport/pdf.php';
    $html = (string) ob_get_clean();

    $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    return (string) $dompdf->output();
}

/**
 * Prépare le fichier à télécharger : type MIME, nom de fichier et contenu.
 * $contexte contient le personnel, la période et le gabarit retenu (annexe liée au statut).
 *
 * Renvoie ['type_mime' => string, 'nom_fichier' => string, 'contenu' => string].
 */
function genererExport(array $activites, array $contexte, string $format): array
{
    if (!isset(FORMATS_EXPORT[$format])) {
        throw new InvalidArgumentException("Format d'export inconnu.");
    }

    $nomBase = 'rapport_activites_' . $contexte['date_debut'] . '_' . $contexte['date_fin'];
    if ($format === 'csv') {
        return [
            'type_mime' => 'text/csv; charset=UTF-8',
            'nom_fichier' => $nomBase . '.csv',
            'contenu' => contenuCsv($activites),
        ];
    }

    return [
        'type_mime' => 'application/pdf',
        'nom_fichier' => $nomBase . '.pdf',
        'contenu' => contenuPdf($activites, $contexte),
    ];
}

/** En-têtes HTTP du téléchargement forcé, dans l'ordre attendu par le navigateur. */
function entetesTelechargement(array $export): array
{
    return [
        'Content-Type: ' . $export['type_mime'],
        'Content-Disposition: attachment; filename="' . $export['nom_fichier'] . '"',
        'Content-Length: ' . strlen($export['contenu']),
        'Cache-Control: private, max-age=0, must-revalidate',
        'Pragma: public',
    ];
}
