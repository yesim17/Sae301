<div class="titre-page">
    <h1>Génération du rapport individuel et des annexes</h1>
</div>

<?php if (!empty($messageErreur)): ?>
    <div class="alerte alerte-erreur" style="background: #fdf2f2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #fecaca;">
        <?= e($messageErreur) ?>
    </div>
<?php endif; ?>

<?php if (!empty($messageSucces)): ?>
    <div class="alerte alerte-succes" style="background: #f0fdf4; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
        <?= e($messageSucces) ?>
    </div>
<?php endif; ?>

<form class="carte" method="POST" action="<?= e(lien('generer_rapport')) ?>">
    <?= champCsrf() ?>

    <div class="champ">
        <label for="date_debut">Date de début *</label>
        <input type="date" id="date_debut" name="date_debut" required>
    </div>

    <div class="champ">
        <label for="date_fin">Date de fin *</label>
        <input type="date" id="date_fin" name="date_fin" required>
    </div>

    <div class="champ">
        <label for="gabarit">Choix du gabarit *</label>
        <select id="gabarit" name="gabarit" required>
            <option value="">-- Sélectionnez un gabarit --</option>
            <?php foreach ($gabarits as $gabarit): ?>
                <option value="<?= e($gabarit['code']) ?>">
                    <?= e($gabarit['libelle']) ?> (Statut requis : <?= e($gabarit['statut_requis']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="champ">
        <label for="format">Format d'export *</label>
        <select id="format" name="format" required>
            <option value="pdf">Document PDF</option>
            <option value="csv">Fichier CSV</option>
        </select>
    </div>

    <div class="actions-formulaire" style="margin-top: 20px;">
        <button type="submit" class="bouton">Générer le rapport et les annexes</button>
    </div>
</form>