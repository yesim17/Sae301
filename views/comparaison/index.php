<div class="titre-page">
    <h1>Comparaison de périodes</h1>
</div>
<p class="aide">Comparez l'évolution des activités déclarées entre deux campagnes.</p>

<?php foreach ($erreurs as $texte): ?>
    <div class="message message-erreur" role="alert"><?= e($texte) ?></div>
<?php endforeach; ?>
<?php foreach ($alertes as $texte): ?>
    <div class="message message-alerte" role="status"><?= e($texte) ?></div>
<?php endforeach; ?>

<?php if (!$campagnes): ?>
    <div class="carte vide" role="status">
        <p><strong>Aucune campagne n'existe pour le moment.</strong></p>
    </div>
<?php else: ?>
    <form class="carte" method="get" action="index.php">
        <input type="hidden" name="page" value="comparaison">
        <div class="grille-champs">
            <div class="champ">
                <label for="a">Période A <span class="requis">*</span></label>
                <select id="a" name="a" required>
                    <option value="">-- Choisir une campagne --</option>
                    <?php foreach ($campagnes as $campagne): ?>
                        <option value="<?= (int) $campagne['id'] ?>"<?= $idA === (int) $campagne['id'] ? ' selected' : '' ?>>
                            <?= e($campagne['titre']) ?> (<?= e(dateFr($campagne['date_debut'])) ?> → <?= e(dateFr($campagne['date_fin'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="champ">
                <label for="b">Période B <span class="requis">*</span></label>
                <select id="b" name="b" required>
                    <option value="">-- Choisir une campagne --</option>
                    <?php foreach ($campagnes as $campagne): ?>
                        <option value="<?= (int) $campagne['id'] ?>"<?= $idB === (int) $campagne['id'] ? ' selected' : '' ?>>
                            <?= e($campagne['titre']) ?> (<?= e(dateFr($campagne['date_debut'])) ?> → <?= e(dateFr($campagne['date_fin'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="actions-formulaire">
            <button type="submit" class="bouton" name="comparer" value="1">Comparer</button>
        </div>
    </form>
<?php endif; ?>

<?php if ($insuffisant): ?>
    <div class="carte vide" role="status">
        <p><strong>Données insuffisantes</strong> pour comparer ces deux périodes.</p>
    </div>
<?php elseif ($resultat !== null): ?>
    <section class="carte">
        <h2>Résultats côte à côte</h2>
        <div class="tableau-defilant">
            <table>
                <thead>
                <tr>
                    <th scope="col"></th>
                    <th scope="col"><?= e($resultat['a']['titre']) ?><br><span class="aide"><?= e(dateFr($resultat['a']['date_debut'])) ?> → <?= e(dateFr($resultat['a']['date_fin'])) ?></span></th>
                    <th scope="col"><?= e($resultat['b']['titre']) ?><br><span class="aide"><?= e(dateFr($resultat['b']['date_debut'])) ?> → <?= e(dateFr($resultat['b']['date_fin'])) ?></span></th>
                </tr>
                </thead>
                <tbody>
                <tr><th scope="row">Total d'activités</th><td><?= (int) $resultat['total_a'] ?></td><td><?= (int) $resultat['total_b'] ?></td></tr>
                <tr><th scope="row">Individuelles</th><td><?= (int) $resultat['individuelles_a'] ?></td><td><?= (int) $resultat['individuelles_b'] ?></td></tr>
                <tr><th scope="row">Collectives</th><td><?= (int) $resultat['collectives_a'] ?></td><td><?= (int) $resultat['collectives_b'] ?></td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="carte">
        <h2>Tendance des activités par mois</h2>
        <canvas id="graphique" height="110"></canvas>
        <div class="actions-formulaire">
            <button type="button" class="bouton" id="export-png">Exporter en PNG</button>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const graphique = new Chart(document.getElementById('graphique'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($resultat['libelles'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                datasets: [
                    { label: <?= json_encode($resultat['a']['titre'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, data: <?= json_encode($resultat['serie_a'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, backgroundColor: '#1c5fb4' },
                    { label: <?= json_encode($resultat['b']['titre'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, data: <?= json_encode($resultat['serie_b'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, backgroundColor: '#e69a00' }
                ]
            },
            options: {
                animation: false,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: "Nombre d'activités" } } }
            },
            plugins: [{
                id: 'fondBlanc',
                beforeDraw(c) {
                    const x = c.ctx;
                    x.save();
                    x.globalCompositeOperation = 'destination-over';
                    x.fillStyle = '#fff';
                    x.fillRect(0, 0, c.width, c.height);
                    x.restore();
                }
            }]
        });

        document.getElementById('export-png').addEventListener('click', function () {
            const lien = document.createElement('a');
            lien.href = graphique.toBase64Image('image/png', 1);
            lien.download = 'comparaison_periodes.png';
            lien.click();
        });
    </script>
<?php endif; ?>

<style>
    .message-alerte { background: var(--attente-fond); color: var(--attente); }
    th, td { text-align: center; }
    thead th:first-child, tbody th { text-align: left; }
</style>