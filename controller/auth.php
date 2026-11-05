<?php
// controller/auth.php
// Connexion et déconnexion sécurisées par identifiant et mot de passe.

require_once __DIR__ . '/../model/personnel.php';

function pageConnexion(PDO $pdo): void
{
    if (moi() !== null) {
        rediriger('activites');
    }

    $erreur = '';
    $valeurs = ['identifiant' => ''];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $identifiant = post('identifiant');
        $password = post('password');
        $valeurs['identifiant'] = $identifiant;

        if (empty($identifiant) || empty($password)) {
            $erreur = "Veuillez renseigner votre identifiant et votre mot de passe.";
        } else {
            // Recherche de l'utilisateur par son identifiant scientifique
            // (Vérifie bien que cette fonction existe dans ton model/personnel.php)
            $personnel = trouverPersonnelParIdentifiant($pdo, $identifiant);

            // Correction : Utilisation de 'est_actif' (comme défini dans ton modèle)
            if ($personnel && isset($personnel['est_actif']) && $personnel['est_actif'] == false) {
                $erreur = "Ce compte a été désactivé.";
            } elseif ($personnel && isset($personnel['password_hash']) && $password === $personnel['password_hash']) {
                session_regenerate_id(true);
                $_SESSION['personnel_id'] = $personnel['id'];
                rediriger('activites');
            } else {
                $erreur = "Identifiant ou mot de passe incorrect.";
            }
        }
    }

    afficher('auth/connexion', [
        'erreur' => $erreur,
        'valeurs' => $valeurs
    ]);
}

function deconnexion(PDO $pdo): void
{
    exigerPost();
    unset($_SESSION['personnel_id'], $_SESSION['csrf']);
    session_regenerate_id(true);
    rediriger('connexion');
}