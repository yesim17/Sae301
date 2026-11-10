<?php
// model/campagne.php
// Campagnes de déclaration.

/** Enregistre une campagne et renvoie son identifiant. */
function ajouterCampagne($pdo, $titre, $description, $periode_reference, $nature, $date_debut, $date_fin, $cree_par) {
    $sql = "INSERT INTO campagne (titre, description, periode_reference, nature, date_debut, date_fin, cree_par) 
            VALUES (:titre, :description, :periode, :nature, :debut, :fin, :cree_par)";
            
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':titre' => $titre,
        ':description' => $description,
        ':periode' => $periode_reference,
        ':nature' => $nature,
        ':debut' => $date_debut,
        ':fin' => $date_fin,
        ':cree_par' => $cree_par
    ]);
}

function listerCampagnes(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, titre, description, date_debut, date_fin
         FROM campagne ORDER BY date_debut DESC, id DESC'
    )->fetchAll();
}

function verifierChevauchementCampagne($pdo, $nature, $date_debut, $date_fin) {
    $sql = "SELECT COUNT(*) FROM campagne 
            WHERE nature = :nature 
            AND date_debut <= :date_fin 
            AND date_fin >= :date_debut";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nature' => $nature,
        ':date_debut' => $date_debut,
        ':date_fin' => $date_fin
    ]);
    
    return $stmt->fetchColumn() > 0;
}