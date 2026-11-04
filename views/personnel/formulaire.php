<?php
$erreurChamp = function (string $nom) use ($erreurs): string {
    return isset($erreurs[$nom]) ? '<p class="erreur-champ">' . e($erreurs[$nom]) . '</p>' : '';
};
$cible = $profil !== null ? lien('personnel-form', ['id' => $profil['id']]) : lien('personnel-form');
?>
<div class="titre-page">
    <h1><?= $profil !== null ? 'Modifier un profil' : 'Créer un profil' ?></h1>
</div>

<?php require __DIR__ . '/../layout/erreurs.php'; ?>

<form class="carte" method="post" action="<?= e($cible) ?>" novalidate>
    <?= champCsrf() ?>

    <div class="champ">
        <label for="nom">Nom <span class="requis">*</span></label>
        <input type="text" id="nom" name="nom" maxlength="100" value="<?= e($valeurs['nom']) ?>">
        <?= $erreurChamp('nom') ?>
    </div>
    <div class="champ">
        <label for="nom_usage">Nom d'usage</label>
        <input type="text" id="nom_usage" name="nom_usage" maxlength="100" value="<?= e($valeurs['nom_usage']) ?>">
        <?= $erreurChamp('nom_usage') ?>
    </div>
    <div class="champ">
        <label for="prenom">Prénom <span class="requis">*</span></label>
        <input type="text" id="prenom" name="prenom" maxlength="100" value="<?= e($valeurs['prenom']) ?>">
        <?= $erreurChamp('prenom') ?>
    </div>
    <div class="champ">
        <label for="statut">Statut <span class="requis">*</span></label>
        <select id="statut" name="statut">
            <option value="">Choisir un statut</option>
            <?php foreach ($statuts as $statut): ?>
                <option value="<?= e($statut) ?>"<?= $valeurs['statut'] === $statut ? ' selected' : '' ?>><?= e($statut) ?></option>
            <?php endforeach; ?>
        </select>
        <?= $erreurChamp('statut') ?>
    </div>
    <div class="champ">
        <label for="identifiant_scientifique">Identifiant scientifique</label>
        <input type="text" id="identifiant_scientifique" name="identifiant_scientifique" maxlength="100" value="<?= e($valeurs['identifiant_scientifique']) ?>">
        <p class="aide">Obligatoire si un homonyme exact existe déjà.</p>
        <?= $erreurChamp('identifiant_scientifique') ?>
    </div>
    <div class="champ">
        <label for="structure_id">Structure</label>
        <select id="structure_id" name="structure_id">
            <option value="">Aucune</option>
            <?php foreach ($structures as $structure): ?>
                <option value="<?= (int) $structure['id'] ?>"<?= $valeurs['structure_id'] === (int) $structure['id'] ? ' selected' : '' ?>><?= e($structure['nom']) ?></option>
            <?php endforeach; ?>
        </select>
        <?= $erreurChamp('structure_id') ?>
    </div>

    <?php if ($gereRoles): ?>
        <fieldset>
            <legend>Rôles</legend>
            <?php foreach ($roles as $role): ?>
                <label class="case">
                    <input type="checkbox" name="roles[]" value="<?= (int) $role['id'] ?>"<?= in_array((int) $role['id'], $rolesCoches, true) ? ' checked' : '' ?>>
                    <?= e($role['libelle']) ?>
                </label>
            <?php endforeach; ?>
            <?= $erreurChamp('roles') ?>
        </fieldset>
    <?php elseif ($profil === null): ?>
        <p class="aide">Ce profil recevra le rôle « Personnel standard ». Les autres rôles sont attribués par un administrateur.</p>
    <?php endif; ?>

    <div class="actions-formulaire">
        <button type="submit" class="bouton">Enregistrer</button>
        <a class="bouton bouton-secondaire" href="<?= e(lien('personnels')) ?>">Annuler</a>
    </div>
</form>
