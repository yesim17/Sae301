<?php
function agregerActivitesParType(PDO $pdo, string $debut, string $fin): array
{
    $requete = $pdo->prepare(
        "SELECT COALESCE(t.libelle, 'Sans type') AS type,
                COUNT(*) AS total
         FROM activite a
         LEFT JOIN type_activite t ON t.id = a.type_activite_id
         WHERE a.date_activite BETWEEN CAST(:debut AS date) AND CAST(:fin AS date)
         GROUP BY type
         ORDER BY type"
    );
    $requete->execute(['debut' => $debut, 'fin' => $fin]);
    return $requete->fetchAll();
}