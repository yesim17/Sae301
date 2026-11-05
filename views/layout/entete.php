<?php
// Menu : une entrée n'apparaît que si la personne connectée a le droit d'ouvrir la page.
$menu = [
    'activites'   => 'Mon dossier',
    'declarer'    => 'Déclarer',
    'bilan'       => "Bilan d'équipe",
    'alertes'     => 'Alertes',
    'delegations' => 'Délégations',
    'personnels'  => 'Profils',
    'grille'      => 'Grille des droits',
    'campagnes'   => 'Campagnes',
    'comparaison' => 'Comparaison',
];
$utilisateurConnecte = moi();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Suivi des activités</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="entete">
    <a class="marque" href="index.php">Suivi des activités</a>
    <?php if ($utilisateurConnecte !== null): ?>
        <nav class="menu" aria-label="Menu principal">
            <?php foreach ($menu as $cible => $libelle): ?>
                <?php if (peutAcceder($cible)): ?>
                    <a href="<?= e(lien($cible)) ?>"<?= ($GLOBALS['page'] ?? '') === $cible ? ' aria-current="page"' : '' ?>>
                        <?= e($libelle) ?>
                        <?php if ($cible === 'alertes' && ($GLOBALS['nbAlertes'] ?? 0) > 0): ?>
                            <span class="pastille"><?= (int) $GLOBALS['nbAlertes'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <form class="session" method="post" action="<?= e(lien('deconnexion')) ?>">
            <?= champCsrf() ?>
            <span><?= e(nomComplet($utilisateurConnecte)) ?></span>
            <button type="submit" class="bouton-lien">Se déconnecter</button>
        </form>
    <?php endif; ?>
</header>
<main class="contenu">
    <?php foreach (lireFlashs() as $flash): ?>
        <div class="message message-<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'erreur' ? 'alert' : 'status' ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endforeach; ?>