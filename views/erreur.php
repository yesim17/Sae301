<section class="carte erreur-page">
    <p class="code-erreur">Erreur <?= (int) $code ?></p>
    <h1><?= $code === 403 ? 'Accès refusé' : 'Action impossible' ?></h1>
    <p><?= e($message) ?></p>
    <p><a href="index.php">Revenir à l'accueil</a></p>
</section>
