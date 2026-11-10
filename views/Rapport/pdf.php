<?php
// views/Rapport/pdf.php
// Gabarit HTML converti en PDF par Dompdf. Variables : $activites, $contexte.
$personnel = $contexte['personnel'];
$gabarit = $contexte['gabarit'];
$nomAffiche = trim(
    ($personnel['prenom'] ?? '') . ' ' . (($personnel['nom_usage'] ?? '') ?: ($personnel['nom'] ?? ''))
);
$nombreActivites = count($activites);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 20mm 16mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #1f2933; }
        h1 { margin: 0 0 1mm; font-size: 17pt; }
        h2 { margin: 6mm 0 2mm; font-size: 12pt; }
        p { margin: 0 0 2mm; }
        .entete { padding-bottom: 3mm; margin-bottom: 4mm; border-bottom: 2px solid #1f5f8b; }
        .meta { color: #52606d; font-size: 9pt; }
        .annexe { margin-top: 5mm; padding: 3mm; background: #f4f5f7; border-left: 3px solid #1f5f8b; }
        .annexe h2 { margin-top: 0; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f4f5f7; font-size: 9pt; text-align: left; }
        th, td { padding: 2mm; border: 1px solid #d3d9e0; vertical-align: top; }
        td.date { width: 22mm; white-space: nowrap; }
        .description { color: #52606d; font-size: 8.5pt; }
        .vide { padding: 4mm; background: #f4f5f7; text-align: center; }
        .pied { margin-top: 6mm; color: #52606d; font-size: 8pt; text-align: center; }
    </style>
</head>
<body>
<header class="entete">
    <h1>Rapport d'activités</h1>
    <p class="meta"><?= e($nomAffiche) ?> — <?= e($personnel['statut'] ?? '') ?></p>
</header>

<p><strong>Période couverte :</strong> du <?= e(dateFr($contexte['date_debut'])) ?> au <?= e(dateFr($contexte['date_fin'])) ?></p>
<p><strong>Gabarit :</strong> <?= e($gabarit['libelle']) ?> <span class="meta">(<?= e($gabarit['statut_requis']) ?>)</span></p>

<?php if (trim((string) ($gabarit['description'] ?? '')) !== ''): ?>
    <section class="annexe">
        <h2>Annexe liée à votre statut</h2>
        <p><?= e($gabarit['description']) ?></p>
    </section>
<?php endif; ?>

<h2><?= $nombreActivites ?> activité<?= $nombreActivites > 1 ? 's' : '' ?> confirmée<?= $nombreActivites > 1 ? 's' : '' ?></h2>

<?php if ($nombreActivites === 0): ?>
    <p class="vide">Aucune activité confirmée sur cette période.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Titre</th>
                <th>Type</th>
                <th>Nature</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($activites as $activite): ?>
                <tr>
                    <td class="date"><?= e(dateFr($activite['date_activite'])) ?></td>
                    <td>
                        <?= e($activite['titre']) ?>
                        <?php if (trim((string) ($activite['description'] ?? '')) !== ''): ?>
                            <div class="description"><?= nl2br(e($activite['description'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= e($activite['type_libelle'] ?? '') ?></td>
                    <td><?= $activite['est_collective'] ? 'Collective' : 'Individuelle' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p class="pied">Rapport généré le <?= e(dateFr(date('Y-m-d'))) ?>.</p>
</body>
</html>
