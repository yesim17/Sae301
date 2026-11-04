<div class="titre-page">
    <h1>Mes alertes</h1>
</div>

<?php if (!$alertes): ?>
    <div class="carte vide" role="status">
        <p><strong>Vous n'avez aucune alerte.</strong></p>
    </div>
<?php else: ?>
    <?php foreach ($alertes as $alerte): ?>
        <section class="carte alerte<?= $alerte['est_lue'] ? '' : ' alerte-non-lue' ?>">
            <div class="alerte-entete">
                <h2><?= e($alerte['titre']) ?></h2>
                <?php if ($alerte['statut'] !== null): ?>
                    <span class="badge badge-<?= e($alerte['statut']) ?>"><?= e(STATUTS_PARTICIPATION[$alerte['statut']] ?? $alerte['statut']) ?></span>
                <?php endif; ?>
            </div>
            <p><?= e($alerte['message']) ?></p>
            <p class="aide">Reçue le <?= e(dateHeureFr($alerte['cree_le'])) ?></p>

            <div class="actions-alerte">
                <?php if ($alerte['statut'] === 'EN_ATTENTE'): ?>
                    <form method="post" action="<?= e(lien('alerte-repondre')) ?>">
                        <?= champCsrf() ?>
                        <input type="hidden" name="participation_id" value="<?= (int) $alerte['participation_id'] ?>">
                        <button type="submit" class="bouton" name="reponse" value="confirmer">Confirmer</button>
                        <button type="submit" class="bouton bouton-secondaire" name="reponse" value="refuser">Refuser</button>
                    </form>
                <?php endif; ?>
                <?php if ($alerte['activite_id'] !== null): ?>
                    <a href="<?= e(lien('activite', ['id' => $alerte['activite_id']])) ?>">Voir l'activité</a>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
