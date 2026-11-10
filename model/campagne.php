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
        'SELECT id, titre, description, date_debut, date_fin, est_actif
         FROM campagne ORDER BY date_debut DESC, id DESC'
    )->fetchAll();
}

/** Clôture une campagne avant terme : elle n'envoie plus de rappel. Renvoie false si elle est déjà clôturée. */
function fermerCampagne(PDO $pdo, int $id): bool
{
    $requete = $pdo->prepare(
        'UPDATE campagne SET est_actif = FALSE, mis_a_jour_le = NOW()
         WHERE id = :id AND est_actif = TRUE'
    );
    $requete->execute(['id' => $id]);
    return $requete->rowCount() > 0;
}