<?php
// index.php
// Point d'entrée unique : toutes les pages passent par ici (index.php?page=...).
// La connexion, les droits et le jeton CSRF sont vérifiés ici, avant d'appeler le contrôleur.

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();


require_once __DIR__ . '/config/connexion.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/model/droits.php';
require_once __DIR__ . '/model/participation.php';
require_once __DIR__ . '/model/personnel.php'; 

// Même fuseau pour PHP et PostgreSQL : les dates de délégation sont saisies en heure française.
date_default_timezone_set('Europe/Paris');
$pdo->exec("SET TIME ZONE 'Europe/Paris'");

$routes = require __DIR__ . '/config/routes.php';
$moi = null;
$nbAlertes = 0;
$page = '';

try {
    // Les délégations arrivées à échéance sont révoquées à chaque requête.
    revoquerDelegationsEchues($pdo);

    // Les droits sont rechargés à chaque requête : une révocation s'applique immédiatement.
    if (isset($_SESSION['personnel_id'])) {
        $moi = chargerUtilisateur($pdo, (int) $_SESSION['personnel_id']);
        if ($moi === null) {
            unset($_SESSION['personnel_id']);
            flash('erreur', 'Votre profil a été désactivé : vous avez été déconnecté.');
        } else {
            $nbAlertes = compterAlertesNonLues($pdo, $moi['id']);
        }
    }

    $page = get('page') ?: ($moi ? 'activites' : 'connexion');

    if (!isset($routes[$page])) {
        erreur(404, "Cette page n'existe pas.");
    }
    [$controleur, $action, $droit] = $routes[$page];

    if (!droitAccorde($droit)) {
        if ($moi === null) {
            rediriger('connexion');
        }
        erreur(403, "Vous n'avez pas les droits nécessaires pour accéder à cette page.");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValide()) {
        erreur(403, 'Le formulaire a expiré. Rechargez la page puis réessayez.');
    }

    require_once __DIR__ . '/controller/' . $controleur . '.php';
    $action($pdo);
} catch (PDOException $e) {
   echo "<h1>Erreur SQL Critique :</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
    exit();
}
