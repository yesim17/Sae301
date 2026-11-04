<div class="titre-page">
    <h1>Grille des droits</h1>
</div>
<p class="aide">Cochez les permissions accordées à chaque rôle. Une page non autorisée disparaît du menu et son accès direct est refusé.</p>

<form method="post" action="<?= e(lien('grille')) ?>">
    <?= champCsrf() ?>
    <div class="tableau-defilant carte">
        <table>
            <thead>
                <tr>
                    <th scope="col">Permission</th>
                    <?php foreach ($roles as $role): ?>
                        <th scope="col" class="colonne-centree"><?= e($role['libelle']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permissions as $permission): ?>
                    <tr>
                        <th scope="row"><?= e($permission['libelle']) ?></th>
                        <?php foreach ($roles as $role): ?>
                            <?php $cle = $role['id'] . '-' . $permission['id']; ?>
                            <td class="colonne-centree">
                                <input type="checkbox" name="cases[<?= e($cle) ?>]" value="1"
                                       aria-label="<?= e($permission['libelle'] . ' : ' . $role['libelle']) ?>"
                                       <?= isset($cases[$cle]) ? 'checked' : '' ?>>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="actions-formulaire">
        <button type="submit" class="bouton">Enregistrer la grille</button>
    </div>
</form>
