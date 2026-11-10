
<div class="titre-page">
    <h1>Relances et rappels</h1>
</div>

<?php require __DIR__ . '/../layout/erreurs.php'; ?>

<p class="aide">
    Les comptes désactivés ne reçoivent rien, et une personne ne reçoit qu'une seule relance
    tous les <?= (int) DELAI_ENTRE_RELANCES_JOURS ?> jours.
</p>

<section class="carte">
    <h2>Relance d'inactivité</h2>
    <p class="aide">Personnels actifs sans aucune activité déclarée depuis plus de <?= (int) SEUIL_INACTIVITE_JOURS ?> jours.</p>
    <?php if (!$inactifs): ?>
        <p>Personne à relancer.</p>
    <?php else: ?>
        <div class="tableau-defilant">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Personnel</th>
                        <th scope="col">Dernière activité</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inactifs as $p): ?>
                        <tr>
                            <td><?= e(libellePersonnel($p)) ?></td>
                            <td class="sans-retour"><?= $p['derniere_activite'] ? e(dateFr($p['derniere_activite'])) : 'Aucune' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="post" action="<?= e(lien('relances-envoyer')) ?>" class="actions-formulaire">
            <?= champCsrf() ?>
            <input type="hidden" name="type" value="inactivite">
            <button type="submit" class="bouton">Envoyer la relance (<?= count($inactifs) ?>)</button>
        </form>
    <?php endif; ?>
</section>

<section class="carte">
    <h2>Rappel d'échéance</h2>
    <p class="aide">
        Campagnes ouvertes qui se terminent dans les <?= (int) FENETRE_RAPPEL_JOURS ?> prochains jours,
        pour les personnels qui n'ont encore rien soumis. Une campagne terminée ou clôturée n'envoie plus de rappel.
    </p>
    <?php if (!$echeances): ?>
        <p>Aucun rappel à envoyer.</p>
    <?php else: ?>
        <div class="tableau-defilant">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Personnel</th>
                        <th scope="col">Campagne</th>
                        <th scope="col">Échéance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($echeances as $p): ?>
                        <tr>
                            <td><?= e(libellePersonnel($p)) ?></td>
                            <td><?= e($p['campagne_titre']) ?></td>
                            <td class="sans-retour"><?= e(dateFr($p['date_fin'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="post" action="<?= e(lien('relances-envoyer')) ?>" class="actions-formulaire">
            <?= champCsrf() ?>
            <input type="hidden" name="type" value="echeance">
            <button type="submit" class="bouton">Envoyer les rappels (<?= count($echeances) ?>)</button>
        </form>
    <?php endif; ?>
</section>

<section class="carte">
    <h2>Dernières relances envoyées</h2>
    <?php if (!$historique): ?>
        <p>Aucune relance envoyée pour le moment.</p>
    <?php else: ?>
        <div class="tableau-defilant">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Personnel</th>
                        <th scope="col">Type</th>
                        <th scope="col">Campagne</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historique as $r): ?>
                        <tr>
                            <td class="sans-retour"><?= e(dateHeureFr($r['envoyee_le'])) ?></td>
                            <td><?= e(libellePersonnel($r)) ?></td>
                            <td><?= e(TYPES_RELANCE_LIBELLES[$r['type_relance']] ?? $r['type_relance']) ?></td>
                            <td><?= e($r['campagne_titre']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
