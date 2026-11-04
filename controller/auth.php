<?php
// controller/auth.php
// Connexion et déconnexion.
//
// ATTENTION : la table "personnel" n'a pas de mot de passe. On choisit donc son profil dans une liste,
// ce qui suffit pour développer et faire une démonstration, mais pas pour une mise en production.

require_once __DIR__ . '/../model/personnel.php';

function pageConnexion(PDO $pdo): void
{
    if (moi() !== null) {
        rediriger('activites');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = idDepuis(post('personnel_id'));
        if (chargerUtilisateur($pdo, $id) !== null) {
            session_regenerate_id(true);
            $_SESSION['personnel_id'] = $id;
            rediriger('activites');
        }
        flash('erreur', 'Choisissez un profil actif dans la liste.');
        rediriger('connexion');
    }

    afficher('auth/connexion', ['personnels' => personnelsActifs($pdo)]);
}

function deconnexion(PDO $pdo): void
{
    exigerPost();
    unset($_SESSION['personnel_id'], $_SESSION['csrf']);
    session_regenerate_id(true);
    rediriger('connexion');
}
