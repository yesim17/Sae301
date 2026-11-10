<?php
$erreurChamp = function (string $nom) use ($erreurs): string {
    return isset($erreurs[$nom]) ? '<p class="erreur-champ">' . e($erreurs[$nom]) . '</p>' : '';
};
?>
<div class="titre-page">
    <h1>Générer mon rapport d'activités</h1>
</div>

<?php require __DIR__ . '/../layout/erreurs.php'; ?>

<?php if (!$gabarits): ?>
    <div class="message message-erreur" role="alert">
        <p>
            Aucun gabarit ne correspond à votre statut (<?= e(moi()['statut'] ?? '') ?>).
            Contactez un administrateur fonctionnel pour vérifier les gabarits disponibles.
        </p>
    </div>
<?php endif; ?>

<form class="carte" method="post" action="<?= e(lien('generer_rapport')) ?>" novalidate>
    <?= champCsrf() ?>

    <div class="grille-champs">
        <div class="champ">
            <label for="date_debut">Date de début <span class="requis">*</span></label>
            <input type="date" id="date_debut" name="date_debut" value="<?= e($valeurs['date_debut']) ?>">
            <?= $erreurChamp('date_debut') ?>
        </div>

        <div class="champ">
            <label for="date_fin">Date de fin <span class="requis">*</span></label>
            <input type="date" id="date_fin" name="date_fin" value="<?= e($valeurs['date_fin']) ?>">
            <?= $erreurChamp('date_fin') ?>
        </div>
    </div>

    <div class="champ">
        <label for="gabarit">Gabarit et annexes <span class="requis">*</span></label>
        <select id="gabarit" name="gabarit">
            <option value="">Choisir un gabarit</option>
            <?php foreach ($gabarits as $gabarit): ?>
                <option value="<?= e($gabarit['code']) ?>"<?= $valeurs['gabarit'] === $gabarit['code'] ? ' selected' : '' ?>>
                    <?= e($gabarit['libelle']) ?> — <?= e($gabarit['statut_requis']) ?><?= $gabarit['retroactif'] ? ' (statut antérieur, rétroactif)' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= $erreurChamp('gabarit') ?>
        <p class="aide">
            Seuls les gabarits liés à votre statut sont proposés. Un gabarit de statut antérieur reste
            sélectionnable si la période choisie correspond à ce statut.
        </p>
    </div>

    <div class="champ">
        <label for="format">Format d'export <span class="requis">*</span></label>
        <select id="format" name="format">
            <?php foreach (FORMATS_EXPORT as $code => $libelle): ?>
                <option value="<?= e($code) ?>"<?= $valeurs['format'] === $code ? ' selected' : '' ?>><?= e($libelle) ?></option>
            <?php endforeach; ?>
        </select>
        <?= $erreurChamp('format') ?>
    </div>

    <p class="aide">
        Le rapport définitif ne contient que vos activités confirmées : les brouillons sont exclus.
    </p>

    <div class="actions-formulaire">
        <button type="submit" class="bouton"<?= !$gabarits ? ' disabled' : '' ?>>Générer et télécharger</button>
        <a class="bouton bouton-secondaire" href="<?= e(lien('activites')) ?>">Retour à mon dossier</a>
    </div>
</form>
