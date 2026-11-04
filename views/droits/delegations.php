<?php
$erreurChamp = function (string $nom) use ($erreurs): string {
    return isset($erreurs[$nom]) ? '<p class="erreur-champ">' . e($erreurs[$nom]) . '</p>' : '';
};
?>
<div class="titre-page">
    <h1>Délégations de droits</h1>
</div>

<?php require __DIR__ . '/../layout/erreurs.php'; ?>

<section class="carte">
    <h2>Déléguer un rôle à un collègue</h2>
    <?php if (!$roles): ?>
        <p>Vous ne détenez aucun rôle à déléguer.</p>
    <?php else: ?>
        <form method="post" action="<?= e(lien('delegations')) ?>" novalidate>
            <?= champCsrf() ?>
            <div class="grille-champs">
                <div class="champ">
                    <label for="delegataire_id">Collègue <span class="requis">*</span></label>
                    <select id="delegataire_id" name="delegataire_id">
                        <option value="">Choisir une personne</option>
                        <?php foreach ($collegues as $collegue): ?>
                            <option value="<?= (int) $collegue['id'] ?>"<?= $valeurs['delegataire_id'] === (int) $collegue['id'] ? ' selected' : '' ?>><?= e(libellePersonnel($collegue)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $erreurChamp('delegataire_id') ?>
                </div>
                <div class="champ">
                    <label for="role_id">Rôle délégué <span class="requis">*</span></label>
                    <select id="role_id" name="role_id">
                        <option value="">Choisir un rôle</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int) $role['id'] ?>"<?= $valeurs['role_id'] === (int) $role['id'] ? ' selected' : '' ?>><?= e($role['libelle']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $erreurChamp('role_id') ?>
                </div>
                <div class="champ">
                    <label for="date_debut">Début <span class="requis">*</span></label>
                    <input type="datetime-local" id="date_debut" name="date_debut" value="<?= e($valeurs['date_debut']) ?>">
                    <?= $erreurChamp('date_debut') ?>
                </div>
                <div class="champ">
                    <label for="date_fin">Fin <span class="requis">*</span></label>
                    <input type="datetime-local" id="date_fin" name="date_fin" value="<?= e($valeurs['date_fin']) ?>">
                    <?= $erreurChamp('date_fin') ?>
                </div>
            </div>
            <div class="actions-formulaire">
                <button type="submit" class="bouton">Créer la délégation</button>
            </div>
        </form>
    <?php endif; ?>
</section>

<section class="carte">
    <h2>Délégations que j'ai données</h2>
    <?php if (!$delegationsDonnees): ?>
        <p>Aucune délégation donnée.</p>
    <?php else: ?>
        <div class="tableau-defilant">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Collègue</th>
                        <th scope="col">Rôle</th>
                        <th scope="col">Période</th>
                        <th scope="col">État</th>
                        <th scope="col"><span class="visuellement-cache">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($delegationsDonnees as $delegation): ?>
                        <tr>
                            <td><?= e(trim($delegation['delegataire_prenom'] . ' ' . ($delegation['delegataire_nom_usage'] ?: $delegation['delegataire_nom']))) ?></td>
                            <td><?= e($delegation['role']) ?></td>
                            <td><?= e(dateHeureFr($delegation['date_debut'])) ?><br>au <?= e(dateHeureFr($delegation['date_fin'])) ?></td>
                            <td><?= e($delegation['etat']) ?></td>
                            <td>
                                <?php if ($delegation['est_actif']): ?>
                                    <form method="post" action="<?= e(lien('delegation-revoquer')) ?>">
                                        <?= champCsrf() ?>
                                        <input type="hidden" name="id" value="<?= (int) $delegation['id'] ?>">
                                        <button type="submit" class="bouton bouton-secondaire">Révoquer</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="carte">
    <h2>Délégations que j'ai reçues</h2>
    <?php if (!$delegationsRecues): ?>
        <p>Aucune délégation reçue.</p>
    <?php else: ?>
        <div class="tableau-defilant">
            <table>
                <thead>
                    <tr>
                        <th scope="col">De la part de</th>
                        <th scope="col">Rôle</th>
                        <th scope="col">Période</th>
                        <th scope="col">État</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($delegationsRecues as $delegation): ?>
                        <tr>
                            <td><?= e(trim($delegation['donneur_prenom'] . ' ' . ($delegation['donneur_nom_usage'] ?: $delegation['donneur_nom']))) ?></td>
                            <td><?= e($delegation['role']) ?></td>
                            <td><?= e(dateHeureFr($delegation['date_debut'])) ?><br>au <?= e(dateHeureFr($delegation['date_fin'])) ?></td>
                            <td><?= e($delegation['etat']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
