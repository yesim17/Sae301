<?php
// model/campagne.php
// Campagnes de déclaration.

/** Enregistre une campagne et renvoie son identifiant. */
function createCampagne(PDO $pdo, string $titre, string $description, string $debut, string $fin, int $creePar): int
{
    $requete = $pdo->prepare(
        'INSERT INTO campagne (titre, description, date_debut, date_fin, cree_par)
         VALUES (:titre, :description, :debut, :fin, :cree_par)
         RETURNING id'
    );
    $requete->execute([
        'titre' => $titre,
        'description' => $description !== '' ? $description : null,
        'debut' => $debut,
        'fin' => $fin,
        'cree_par' => $creePar,
    ]);
    return (int) $requete->fetchColumn();
}

function listerCampagnes(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, titre, description, date_debut, date_fin
         FROM campagne ORDER BY date_debut DESC, id DESC'
    )->fetchAll();
}
