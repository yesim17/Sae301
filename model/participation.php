<?php
// model/participation.php
// Co-participants d'une activité collective et alertes associées.

const STATUTS_PARTICIPATION = [
    'EN_ATTENTE' => 'En attente',
    'CONFIRME'   => 'Confirmé',
    'REFUSE'     => 'Refusé',
];

/** Ajoute un co-participant "en attente" et lui envoie une alerte. */
function ajouterParticipant(
    PDO $pdo,
    int $activiteId,
    string $titreActivite,
    int $personnelId,
    string $role,
    string $nomDeclarant
): void {
    $requete = $pdo->prepare(
        'INSERT INTO activite_participant (activite_id, personnel_id, role)
         VALUES (:activite, :personnel, :role) RETURNING id'
    );
    $requete->execute(['activite' => $activiteId, 'personnel' => $personnelId, 'role' => $role]);
    $participationId = (int) $requete->fetchColumn();

    $requete = $pdo->prepare(
        "INSERT INTO notification (personnel_id, titre, message, type_notification, activite_participant_id)
         VALUES (:personnel, :titre, :message, 'PARTICIPATION', :participation)"
    );
    $requete->execute([
        'personnel' => $personnelId,
        'titre' => 'Participation à confirmer',
        'message' => $nomDeclarant . ' vous propose de participer à l\'activité « ' . $titreActivite
            . ' » avec le rôle « ' . $role . ' ».',
        'participation' => $participationId,
    ]);
}

/**
 * Retire un participant non confirmé. Seul le déclarant de l'activité peut le faire.
 * L'alerte liée est supprimée par la BDD (ON DELETE CASCADE).
 */
function retirerParticipant(PDO $pdo, int $participationId, int $declarantId): bool
{
    $requete = $pdo->prepare(
        "DELETE FROM activite_participant ap
         USING activite a
         WHERE ap.id = :id AND a.id = ap.activite_id
           AND a.personnel_id = :declarant AND ap.statut <> 'CONFIRME'"
    );
    $requete->execute(['id' => $participationId, 'declarant' => $declarantId]);
    return $requete->rowCount() > 0;
}

/** Renvoie les participants de plusieurs activités : [idActivite => [participants]]. */
function participantsDesActivites(PDO $pdo, array $activiteIds): array
{
    if (!$activiteIds) {
        return [];
    }
    $marques = implode(',', array_fill(0, count($activiteIds), '?'));
    $requete = $pdo->prepare(
        "SELECT ap.id, ap.activite_id, ap.personnel_id, ap.role, ap.statut,
                p.nom, p.nom_usage, p.prenom, p.identifiant_scientifique
         FROM activite_participant ap
         JOIN personnel p ON p.id = ap.personnel_id
         WHERE ap.activite_id IN ($marques)
         ORDER BY LOWER(p.nom), LOWER(p.prenom)"
    );
    $requete->execute(array_values($activiteIds));

    $parActivite = [];
    foreach ($requete as $ligne) {
        $parActivite[$ligne['activite_id']][] = $ligne;
    }
    return $parActivite;
}

// ---------- Alertes ----------

function compterAlertesNonLues(PDO $pdo, int $personnelId): int
{
    $requete = $pdo->prepare('SELECT COUNT(*) FROM notification WHERE personnel_id = :id AND NOT est_lue');
    $requete->execute(['id' => $personnelId]);
    return (int) $requete->fetchColumn();
}

function alertesDe(PDO $pdo, int $personnelId): array
{
    $requete = $pdo->prepare(
        'SELECT n.id, n.titre, n.message, n.est_lue, n.cree_le,
                ap.id AS participation_id, ap.statut, ap.activite_id
         FROM notification n
         LEFT JOIN activite_participant ap ON ap.id = n.activite_participant_id
         WHERE n.personnel_id = :id
         ORDER BY n.est_lue, n.cree_le DESC, n.id DESC'
    );
    $requete->execute(['id' => $personnelId]);
    return $requete->fetchAll();
}

/**
 * Enregistre la réponse d'un personnel à sa propre participation, si elle est encore en attente.
 * Les colonnes "statut" et "est_confirme" de la BDD sont mises à jour ensemble.
 */
function repondreParticipation(PDO $pdo, int $participationId, int $personnelId, string $statut): bool
{
    $pdo->beginTransaction();
    $requete = $pdo->prepare(
        "UPDATE activite_participant
         SET statut = :statut, est_confirme = :confirme
         WHERE id = :id AND personnel_id = :personnel AND statut = 'EN_ATTENTE'"
    );
    $requete->execute([
        'statut' => $statut,
        'confirme' => $statut === 'CONFIRME' ? 'true' : 'false',
        'id' => $participationId,
        'personnel' => $personnelId,
    ]);
    $modifie = $requete->rowCount() > 0;

    if ($modifie) {
        $requete = $pdo->prepare(
            'UPDATE notification SET est_lue = TRUE
             WHERE activite_participant_id = :id AND personnel_id = :personnel'
        );
        $requete->execute(['id' => $participationId, 'personnel' => $personnelId]);
    }
    $pdo->commit();
    return $modifie;
}
