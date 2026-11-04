<?php
// Une ligne de co-participant. Variables : $index, $ligne, $collegues, $erreurLigne.
// Sert aux lignes déjà saisies et au modèle que le JavaScript recopie pour en ajouter.
?>
<div class="ligne-participant">
    <div class="champ">
        <label for="participant-<?= e($index) ?>-personnel">Membre du personnel</label>
        <select id="participant-<?= e($index) ?>-personnel" name="participants[<?= e($index) ?>][personnel_id]">
            <option value="">Choisir une personne</option>
            <?php foreach ($collegues as $collegue): ?>
                <option value="<?= (int) $collegue['id'] ?>"<?= (int) $ligne['personnel_id'] === (int) $collegue['id'] ? ' selected' : '' ?>><?= e(libellePersonnel($collegue)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="champ">
        <label for="participant-<?= e($index) ?>-role">Rôle <span class="requis">*</span></label>
        <input type="text" id="participant-<?= e($index) ?>-role" name="participants[<?= e($index) ?>][role]" maxlength="100" value="<?= e($ligne['role']) ?>" placeholder="Ex. : co-auteur, animateur">
    </div>
    <button type="button" class="bouton bouton-secondaire retirer-ligne">Retirer</button>
    <?php if ($erreurLigne !== ''): ?>
        <p class="erreur-champ"><?= e($erreurLigne) ?></p>
    <?php endif; ?>
</div>
