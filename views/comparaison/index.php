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
    <?php $opts = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT; ?>
    <section class="carte">
        <h2>Activités par type d'activité</h2>
        <p class="aide">
            <?= e($resultat['a']['titre']) ?> : <?= (int) $resultat['total_a'] ?> activité(s) —
            <?= e($resultat['b']['titre']) ?> : <?= (int) $resultat['total_b'] ?> activité(s)
        </p>
        <canvas id="graphique" height="110"></canvas>
        <div class="actions-formulaire">
            <button type="button" class="bouton" id="export-png">Exporter en PNG</button>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const types = <?= json_encode($resultat['types'], $opts) ?>;
        const serieA = <?= json_encode($resultat['serie_a'], $opts) ?>;
        const serieB = <?= json_encode($resultat['serie_b'], $opts) ?>;
        const nomA = <?= json_encode($resultat['a']['titre'], $opts) ?>;
        const nomB = <?= json_encode($resultat['b']['titre'], $opts) ?>;

        // Une couleur par type d'activité : la période A en couleur pleine, la B en version claire
        const palette = ['#1c5fb4', '#e69a00', '#2ec27e', '#c01c28', '#813d9c', '#865e3c', '#26a269', '#e01b24'];
        const couleurs = types.map((t, i) => palette[i % palette.length]);
        const couleursClaires = couleurs.map(c => c + '88');

        const graphique = new Chart(document.getElementById('graphique'), {
            type: 'bar',
            data: {
                labels: types,
                datasets: [
                    { label: nomA, data: serieA, backgroundColor: couleurs, borderColor: couleurs, borderWidth: 2 },
                    { label: nomB, data: serieB, backgroundColor: couleursClaires, borderColor: couleurs, borderWidth: 2 }
                ]
            },
            options: {
                animation: false,
                // Barres moyennes : épaisseur plafonnée
                maxBarThickness: 56,
                barPercentage: 0.9,
                categoryPercentage: 0.6,
                plugins: {
                    legend: {
                        labels: {
                            // Légende : A = plein, B = clair (les couleurs identifient les types)
                            generateLabels: function (c) {
                                return [
                                    { text: nomA + ' (couleur pleine)', fillStyle: '#555', strokeStyle: '#555', lineWidth: 0, datasetIndex: 0 },
                                    { text: nomB + ' (couleur claire)', fillStyle: '#bbb', strokeStyle: '#555', lineWidth: 1, datasetIndex: 1 }
                                ];
                            }
                        },
                        onClick: null
                    }
                },
                scales: {
                    x: { title: { display: true, text: "Type d'activité" } },
                    y: { beginAtZero: true, max: Math.max(...serieA, ...serieB) + 1, ticks: { precision: 0 }, title: { display: true, text: "Nombre d'activités" } }
                }
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
</style>