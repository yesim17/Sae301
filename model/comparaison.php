<?php
function agregerActivites(PDO $pdo, string $debut, string $fin): array
{
    $requete = $pdo->prepare(
        "SELECT (EXTRACT(YEAR FROM age(a.date_activite, CAST(:debut1 AS date))) * 12
               + EXTRACT(MONTH FROM age(a.date_activite, CAST(:debut2 AS date))))::int + 1 AS mois,
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE a.est_collective) AS collectives,
                COUNT(*) FILTER (WHERE NOT a.est_collective) AS individuelles
         FROM activite a
         WHERE a.date_activite BETWEEN CAST(:debut3 AS date) AND CAST(:fin AS date)
         GROUP BY mois
         ORDER BY mois"
    );
    $requete->execute(['debut1' => $debut, 'debut2' => $debut, 'debut3' => $debut, 'fin' => $fin]);
    return $requete->fetchAll();
}