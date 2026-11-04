<div class="titre-page">
    <h1>Profils du personnel</h1>
    <a class="bouton" href="<?= e(lien('personnel-form')) ?>">Créer un profil</a>
</div>

<div class="tableau-defilant carte">
    <table>
        <thead>
            <tr>
                <th scope="col">Nom</th>
                <th scope="col">Statut</th>
                <th scope="col">Identifiant scientifique</th>
                <th scope="col">Structure</th>
                <th scope="col">Rôles</th>
                <th scope="col">État</th>
                <th scope="col"><span class="visuellement-cache">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($personnels as $personnel): ?>
                <tr class="<?= $personnel['est_actif'] ? '' : 'ligne-inactive' ?>">
                    <td>
                        <?= e($personnel['nom']) ?> <?= e($personnel['prenom']) ?>
                        <?php if ($personnel['nom_usage']): ?>
                            <br><span class="aide">Nom d'usage : <?= e($personnel['nom_usage']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($personnel['statut']) ?></td>
                    <td><?= e($personnel['identifiant_scientifique']) ?></td>
                    <td><?= e($personnel['structure_nom']) ?></td>
                    <td><?= e($personnel['roles']) ?></td>
                    <td><span class="badge <?= $personnel['est_actif'] ? 'badge-CONFIRME' : 'badge-REFUSE' ?>"><?= $personnel['est_actif'] ? 'Actif' : 'Désactivé' ?></span></td>
                    <td>
                        <div class="actions-ligne">
                            <a class="bouton bouton-secondaire" href="<?= e(lien('personnel-form', ['id' => $personnel['id']])) ?>">Modifier</a>
                            <?php if (!$personnel['est_actif'] || (int) $personnel['id'] !== (int) moi()['id']): ?>
                                <form method="post" action="<?= e(lien('personnel-activation')) ?>">
                                    <?= champCsrf() ?>
                                    <input type="hidden" name="id" value="<?= (int) $personnel['id'] ?>">
                                    <input type="hidden" name="action" value="<?= $personnel['est_actif'] ? 'desactiver' : 'reactiver' ?>">
                                    <button type="submit" class="bouton bouton-secondaire"><?= $personnel['est_actif'] ? 'Désactiver' : 'Réactiver' ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
