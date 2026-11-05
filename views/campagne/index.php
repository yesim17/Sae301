<form action="index.php?page=campagnes" method="POST">
    <?= champCsrf() ?>

    <div class="form-group mb-3">
        <label for="titre">Titre *</label>
        <input type="text" id="titre" name="titre" class="form-control" 
               value="<?= htmlspecialchars($valeurs['titre'] ?? '') ?>" required>
        <?php if (isset($erreurs['titre'])): ?>
            <div class="text-danger"><?= $erreurs['titre'] ?></div>
        <?php endif; ?>
    </div>

    <div class="form-group mb-3">
        <label for="periode_reference">Période de référence *</label>
        <input type="text" id="periode_reference" name="periode_reference" class="form-control" 
               value="<?= htmlspecialchars($valeurs['periode_reference'] ?? '') ?>" required>
        <?php if (isset($erreurs['periode_reference'])): ?>
            <div class="text-danger"><?= $erreurs['periode_reference'] ?></div>
        <?php endif; ?>
    </div>

    <div class="form-group mb-3">
        <label for="nature">Nature *</label>
        <select id="nature" name="nature" class="form-control" required>
            <option value="">-- Sélectionner --</option>
            <option value="Saisie des activités" <?= ($valeurs['nature'] ?? '') === 'Saisie des activités' ? 'selected' : '' ?>>Saisie des activités</option>
            <option value="Validation annuelle" <?= ($valeurs['nature'] ?? '') === 'Validation annuelle' ? 'selected' : '' ?>>Validation annuelle</option>
            <option value="Autre" <?= ($valeurs['nature'] ?? '') === 'Autre' ? 'selected' : '' ?>>Autre</option>
        </select>
        <?php if (isset($erreurs['nature'])): ?>
            <div class="text-danger"><?= $erreurs['nature'] ?></div>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-md-6 form-group mb-3">
            <label for="date_debut">Date de début *</label>
            <input type="date" id="date_debut" name="date_debut" class="form-control" 
                   value="<?= htmlspecialchars($valeurs['date_debut'] ?? '') ?>" required>
            <?php if (isset($erreurs['date_debut'])): ?>
                <div class="text-danger"><?= $erreurs['date_debut'] ?></div>
            <?php endif; ?>
        </div>
        <div class="col-md-6 form-group mb-3">
            <label for="date_fin">Date de fin *</label>
            <input type="date" id="date_fin" name="date_fin" class="form-control" 
                   value="<?= htmlspecialchars($valeurs['date_fin'] ?? '') ?>" required>
            <?php if (isset($erreurs['date_fin'])): ?>
                <div class="text-danger"><?= $erreurs['date_fin'] ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-group mb-3">
        <label for="description">Description</label>
        <textarea id="description" name="description" class="form-control" rows="3"><?= htmlspecialchars($valeurs['description'] ?? '') ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Enregistrer</button>
</form>