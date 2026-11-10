<?php
// model/activite.php
// Déclaration, recherche et consultation des activités.

require_once __DIR__ . '/participation.php';

const STATUTS_ACTIVITE = [
    'brouillon' => 'Brouillon',
    'soumise'   => 'Soumise',
];

/**
 * Champs propres à chaque type du catalogue (table type_activite, colonne code).
 * Un type absent de cette liste n'affiche que les champs communs.
 * La BDD n'a pas de colonnes pour ces champs : ils sont enregistrés en tête de la description.
 */
const CHAMPS_PAR_TYPE = [
    'ENS' => [
        'volume_horaire' => ['libelle' => 'Volume horaire (heures)', 'format' => 'nombre', 'obligatoire' => true],
        'formation'      => ['libelle' => 'Formation ou groupe', 'format' => 'texte', 'obligatoire' => false],
    ],
    'RECH' => [
        'publication' => ['libelle' => 'Publication ou projet', 'format' => 'texte', 'obligatoire' => true],
        'reference'   => ['libelle' => 'Référence (DOI, URL)', 'format' => 'texte', 'obligatoire' => false],
    ],
    'ADM' => [
        'mission' => ['libelle' => 'Mission ou instance', 'format' => 'texte', 'obligatoire' => true],
    ],
];

/** Colonnes autorisées pour le tri (liste blanche : ces valeurs vont directement dans le SQL). */
const TRIS_ACTIVITE = [
    'date'   => 'a.date_activite',
    'titre'  => 'LOWER(a.titre)',
    'type'   => 'LOWER(t.libelle)',
    'nature' => 'a.est_collective',
    'statut' => 'a.statut',
];

/** Expression SQL qui retire les accents et les majuscules, sans extension PostgreSQL. */
function sansAccents(string $expression): string
{
    return "LOWER(translate($expression, 'àâäéèêëîïôöùûüÿçÀÂÄÉÈÊËÎÏÔÖÙÛÜŸÇ', 'aaaeeeeiioouuuycaaaeeeeiioouuuyc'))";
}

/**
 * Dossier personnel : activités déclarées par la personne, ou auxquelles elle a confirmé sa participation.
 * Une participation en attente ou refusée n'y figure donc pas.
 */
function rechercherMesActivites(PDO $pdo, int $moi, array $filtres): array
{
    $sql = "SELECT a.id, a.titre, a.date_activite, a.est_collective, a.statut, a.personnel_id,
                   t.libelle AS type_libelle
            FROM activite a
            LEFT JOIN type_activite t ON t.id = a.type_activite_id
            WHERE (a.personnel_id = :moi1 OR EXISTS (
                      SELECT 1 FROM activite_participant ap
                      WHERE ap.activite_id = a.id AND ap.personnel_id = :moi2 AND ap.statut = 'CONFIRME'
                  ))";
    $params = ['moi1' => $moi, 'moi2' => $moi];

    if ($filtres['q'] !== '') {
        // La recherche plein texte gère les variantes de mots, LIKE les mots partiels et sans accents.
        $motifSql = sansAccents(':motif1');
        $motifSql2 = sansAccents(':motif2');
        $sql .= " AND (to_tsvector('french', a.titre) @@ plainto_tsquery('french', :q)
                       OR " . sansAccents('a.titre') . " LIKE $motifSql
                       OR " . sansAccents('a.description') . " LIKE $motifSql2)";
        $motif = '%' . addcslashes($filtres['q'], '%_\\') . '%';
        $params += ['q' => $filtres['q'], 'motif1' => $motif, 'motif2' => $motif];
    }
    if ($filtres['du'] !== '') {
        $sql .= ' AND a.date_activite >= :du';
        $params['du'] = $filtres['du'];
    }
    if ($filtres['au'] !== '') {
        $sql .= ' AND a.date_activite <= :au';
        $params['au'] = $filtres['au'];
    }
    if ($filtres['nature'] !== '') {
        $sql .= $filtres['nature'] === 'collective' ? ' AND a.est_collective' : ' AND NOT a.est_collective';
    }
    if ($filtres['type'] !== '') {
        $sql .= ' AND a.type_activite_id = :type';
        $params['type'] = $filtres['type'];
    }
    if ($filtres['statut'] !== '') {
        $sql .= ' AND a.statut = :statut';
        $params['statut'] = $filtres['statut'];
    }

    $colonne = TRIS_ACTIVITE[$filtres['tri']];
    $sens = $filtres['sens'] === 'asc' ? 'ASC' : 'DESC';
    $sql .= " ORDER BY $colonne $sens, a.id DESC";

    $requete = $pdo->prepare($sql);
    $requete->execute($params);
    return $requete->fetchAll();
}

/**
 * Crée l'activité, ses co-participants et leurs alertes : tout est enregistré, ou rien.
 * $a contient : personnel_id, titre, description, date_activite, est_collective, type_activite_id, structure_id.
 */
function creerActivite(PDO $pdo, array $a, array $participants, string $nomDeclarant): int
{
    $pdo->beginTransaction();
    $requete = $pdo->prepare(
        'INSERT INTO activite (personnel_id, titre, description, date_activite, est_collective,
                               type_activite_id, structure_id)
         VALUES (:personnel, :titre, :description, :date, :collective, :type, :structure)
         RETURNING id'
    );
    $requete->execute([
        'personnel' => $a['personnel_id'],
        'titre' => $a['titre'],
        'description' => $a['description'],
        'date' => $a['date_activite'],
        'collective' => $a['est_collective'] ? 'true' : 'false',
        'type' => $a['type_activite_id'],
        'structure' => $a['structure_id'],
    ]);
    $id = (int) $requete->fetchColumn();

    foreach ($participants as $participant) {
        ajouterParticipant($pdo, $id, $a['titre'], $participant['personnel_id'], $participant['role'], $nomDeclarant);
    }
    $pdo->commit();
    return $id;
}

/**
 * Renvoie une activité si la personne a le droit de la voir, sinon null :
 * déclarant, participant, ou membre de la structure pour une activité collective.
 */
function trouverActiviteVisible(PDO $pdo, int $id, int $moi, ?int $structure): ?array
{
    $requete = $pdo->prepare(
        "SELECT a.*, t.libelle AS type_libelle, s.nom AS structure_nom,
                d.prenom, d.nom, d.nom_usage
         FROM activite a
         LEFT JOIN type_activite t ON t.id = a.type_activite_id
         LEFT JOIN structure s ON s.id = a.structure_id
         LEFT JOIN personnel d ON d.id = a.personnel_id
         WHERE a.id = :id AND (
             a.personnel_id = :moi1
             OR EXISTS (SELECT 1 FROM activite_participant ap
                        WHERE ap.activite_id = a.id AND ap.personnel_id = :moi2)
             OR (a.est_collective AND a.structure_id = :structure)
         )"
    );
    $requete->execute(['id' => $id, 'moi1' => $moi, 'moi2' => $moi, 'structure' => $structure]);
    return $requete->fetch() ?: null;
}

/**
 * Bilan d'équipe : activités collectives de la structure de la personne, ou auxquelles elle est liée.
 * Contrairement au dossier personnel, les participations en attente y figurent.
 */function bilanEquipe(PDO $pdo, int $personnelId, ?int $structureId = null): array
{
    $requete = $pdo->prepare(
        "SELECT DISTINCT a.*,
                t.libelle AS type_libelle,
                s.nom AS structure_nom,
                p.nom,
                p.nom_usage,
                p.prenom,
                p.identifiant_scientifique,
                p.nom AS declarant_nom,
                p.nom_usage AS declarant_nom_usage,
                p.prenom AS declarant_prenom,
                p.identifiant_scientifique AS declarant_identifiant
         FROM activite a
         LEFT JOIN type_activite t ON t.id = a.type_activite_id
         LEFT JOIN structure s ON s.id = a.structure_id
         JOIN personnel p ON p.id = a.personnel_id
         LEFT JOIN activite_participant ap ON ap.activite_id = a.id
         WHERE a.est_collective = TRUE
           AND (a.personnel_id = :personnel_id OR ap.personnel_id = :personnel_id)
         ORDER BY a.date_activite DESC, a.id DESC"
    );
    $requete->execute(['personnel_id' => $personnelId]);
    return $requete->fetchAll();
}

/** Soumet plusieurs brouillons en une fois. Seuls les brouillons du déclarant sont modifiés. */
function soumettreActivites(PDO $pdo, array $ids, int $declarant): int
{
    if (!$ids) {
        return 0;
    }
    $marques = implode(',', array_fill(0, count($ids), '?'));
    $requete = $pdo->prepare(
        "UPDATE activite SET statut = 'soumise', mis_a_jour_le = NOW()
         WHERE personnel_id = ? AND statut = 'brouillon' AND id IN ($marques)"
    );
    $requete->execute(array_merge([$declarant], array_values($ids)));
    return $requete->rowCount();
}
