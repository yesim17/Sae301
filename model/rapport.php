<?php
// model/rapport.php

function getActivitesConfirmees($pdo, $personnelId, $dateDebut, $dateFin) {
    $sql = "SELECT a.*, t.libelle as type_libelle 
            FROM activite a
            LEFT JOIN type_activite t ON a.type_activite_id = t.id
            WHERE a.personnel_id = :personnel_id 
              AND a.date_activite BETWEEN :date_debut AND :date_fin 
              AND a.statut IN ('soumise', 'validee')
            ORDER BY a.date_activite ASC";
              
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'personnel_id' => $personnelId,
        'date_debut' => $dateDebut,
        'date_fin' => $dateFin
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function verifierCompatibiliteGabarit($pdo, $gabaritCode, $userStatut) {
    $stmt = $pdo->prepare("SELECT * FROM gabarit WHERE code = :code");
    $stmt->execute(['code' => $gabaritCode]);
    $gabarit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gabarit) {
        return ['autorise' => false, 'message' => "Erreur : Gabarit inconnu."];
    }

    $statutRequis = trim($gabarit['statut_requis']);
    $statutUser = trim($userStatut);

    $estCompatible = ($statutRequis === $statutUser) || 
                     (($statutRequis === 'Maître de conférences' || $statutRequis === 'Maîtresse de conférences') && 
                      ($statutUser === 'Maître de conférences' || $statutUser === 'Maîtresse de conférences'));

    if (!$estCompatible) {
        return [
            'autorise' => false, 
            'message' => "Signal d'alerte : Ce gabarit (« {$gabarit['libelle']} ») est inapplicable à votre statut actuel ($userStatut)."
        ];
    }

    return ['autorise' => true, 'gabarit' => $gabarit];
}

function getGabaritsCompatibles($pdo, $userStatut) {
    $stmt = $pdo->prepare("SELECT * FROM gabarit WHERE statut_requis = :statut OR statut_requis IN ('Maître de conférences', 'Maîtresse de conférences', 'Enseignant-chercheur') ORDER BY libelle ASC");
    $stmt->execute(['statut' => $userStatut]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}