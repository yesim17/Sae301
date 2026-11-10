<div class="titre-page">
    <h1>Activités collectives</h1>
</div>
<p class="aide">Activités collectives que vous avez déclarées ou auxquelles vous participez. Une activité y figure dès sa déclaration, même si des participations sont en attente.</p>

<?php if (!$activites): ?>
    <div class="carte vide" role="status">
        <p><strong>Aucune activité collective ne vous concerne pour le moment.</strong></p>
    </div>
<?php else: ?>
    <?php foreach ($activites as $activite): ?>
        <section class="carte">
            <h2><a href="<?= e(lien('activite', ['id' => $activite['id']])) ?>"><?= e($activite['titre']) ?></a></h2>
            <p class="aide">
                <?= e(dateFr($activite['date_activite'])) ?>
                · <?= e($activite['type_libelle']) ?>
                · déclarée par <?= e(nomComplet($activite)) ?>
            </p>
            <ul class="liste-participants">
                <?php foreach ($participants[$activite['id']] ?? [] as $participant): ?>
                    <li>
                        <span><?= e(libellePersonnel($participant)) ?> — <?= e($participant['role']) ?></span>
                        <span class="badge badge-<?= e($participant['statut']) ?>"><?= e(STATUTS_PARTICIPATION[$participant['statut']] ?? $participant['statut']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
