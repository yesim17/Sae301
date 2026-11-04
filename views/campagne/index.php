<?php
$erreurChamp = function (string $nom) use ($erreurs): string {
    return isset($erreurs[$nom]) ? '<p class="erreur-champ">' . e($erreurs[$nom]) . '</p>' : '';
};
?>
<div class="titre-page">
    <h1>Campagnes de déclaration</h1>
</div>

<?php require __DIR__ . '/../layout/erreurs.php'; ?>

<form class="carte" method="post" action="<?= e(lien('campagnes')) ?>" novalidate>
    <?= champCsrf() ?>
    <h2>Nouvelle campagne</h2>
    <div class="champ">
        <label for="titre">Titre <span class="requis">*</span></label>
        <input type="text" id="titre" name="titre" maxlength="255" value="<?= e($valeurs['titre']) ?>">
        <?= $erreurChamp('titre') ?>
    </div>
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
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3"><?= e($valeurs['description']) ?></textarea>
    </div>
    <div class="actions-formulaire">
        <button type="submit" class="bouton">Créer la campagne</button>
    </div>
</form>

<section class="carte">
    <h2>Campagnes existantes</h2>
    <?php if (!$campagnes): ?>
        <p>Aucune campagne pour le moment.</p>
    <?php else: ?>
        <div class="tableau-defilant">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Titre</th>
                        <th scope="col">Début</th>
                        <th scope="col">Fin</th>
                        <th scope="col">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campagnes as $campagne): ?>
                        <tr>
                            <td><?= e($campagne['titre']) ?></td>
                            <td class="sans-retour"><?= e(dateFr($campagne['date_debut'])) ?></td>
                            <td class="sans-retour"><?= e(dateFr($campagne['date_fin'])) ?></td>
                            <td><?= e($campagne['description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
