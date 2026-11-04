<?php
$erreurChamp = function (string $nom) use ($erreurs): string {
    return isset($erreurs[$nom]) ? '<p class="erreur-champ">' . e($erreurs[$nom]) . '</p>' : '';
};

// Après une erreur portant seulement sur les co-participants, on rouvre directement leur onglet.
$erreursAutres = array_filter(array_keys($erreurs), function ($cle) {
    return strpos($cle, 'participant') !== 0;
});
$ouvrirCoParticipants = $valeurs['mode'] === 'collective' && $erreurs && !$erreursAutres;
?>
<div class="titre-page">
    <h1>Déclarer une activité</h1>
</div>

<?php require __DIR__ . '/../layout/erreurs.php'; ?>

<form id="form-activite" class="carte" method="post" action="<?= e(lien('declarer')) ?>" novalidate
      data-onglet="<?= $ouvrirCoParticipants ? 'co' : 'infos' ?>">
    <?= champCsrf() ?>

    <fieldset class="choix-nature">
        <legend>Nature de l'activité</legend>
        <label><input type="radio" name="mode" value="individuelle"<?= $valeurs['mode'] === 'individuelle' ? ' checked' : '' ?>> Individuelle</label>
        <label><input type="radio" name="mode" value="collective"<?= $valeurs['mode'] === 'collective' ? ' checked' : '' ?>> Collective</label>
    </fieldset>

    <div class="onglets" role="tablist" aria-label="Sections de la déclaration">
        <button type="button" role="tab" id="onglet-infos" aria-controls="panneau-infos" aria-selected="true">Informations</button>
        <button type="button" role="tab" id="onglet-co" aria-controls="panneau-co" aria-selected="false"
                <?= $valeurs['mode'] === 'collective' ? '' : 'disabled' ?>>Co-participants</button>
    </div>

    <div id="panneau-infos" role="tabpanel" aria-labelledby="onglet-infos">
        <div class="champ">
            <label for="type_activite_id">Type d'activité <span class="requis">*</span></label>
            <select id="type_activite_id" name="type_activite_id">
                <option value="">Choisir dans le catalogue</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?= (int) $type['id'] ?>"<?= $valeurs['type_activite_id'] === (int) $type['id'] ? ' selected' : '' ?>><?= e($type['libelle']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $erreurChamp('type_activite_id') ?>
        </div>

        <div class="champ">
            <label for="titre">Titre <span class="requis">*</span></label>
            <input type="text" id="titre" name="titre" maxlength="255" value="<?= e($valeurs['titre']) ?>">
            <?= $erreurChamp('titre') ?>
        </div>

        <div class="champ">
            <label for="date_activite">Date <span class="requis">*</span></label>
            <input type="date" id="date_activite" name="date_activite" value="<?= e($valeurs['date_activite']) ?>">
            <?= $erreurChamp('date_activite') ?>
        </div>

        <div class="champ">
            <label for="structure_id">Structure</label>
            <select id="structure_id" name="structure_id">
                <option value="">Aucune</option>
                <?php foreach ($structures as $structure): ?>
                    <option value="<?= (int) $structure['id'] ?>"<?= $valeurs['structure_id'] === (int) $structure['id'] ? ' selected' : '' ?>><?= e($structure['nom']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $erreurChamp('structure_id') ?>
        </div>

        <?php // Champs propres à chaque type : un seul bloc est visible à la fois. ?>
        <?php foreach ($types as $type): ?>
            <?php $champs = CHAMPS_PAR_TYPE[$type['code']] ?? []; ?>
            <?php if ($champs): ?>
                <div class="champs-type" data-champs-type="<?= (int) $type['id'] ?>"<?= $valeurs['type_activite_id'] === (int) $type['id'] ? '' : ' hidden' ?>>
                    <?php foreach ($champs as $nom => $champ): ?>
                        <div class="champ">
                            <label for="specifique-<?= e($nom) ?>">
                                <?= e($champ['libelle']) ?>
                                <?php if ($champ['obligatoire']): ?><span class="requis">*</span><?php endif; ?>
                            </label>
                            <input id="specifique-<?= e($nom) ?>" name="specifiques[<?= e($nom) ?>]"
                                   type="<?= $champ['format'] === 'nombre' ? 'number' : 'text' ?>"
                                   <?= $champ['format'] === 'nombre' ? 'min="0" step="0.5"' : 'maxlength="500"' ?>
                                   value="<?= e($valeurs['specifiques'][$nom] ?? '') ?>">
                            <?= $erreurChamp('specifique_' . $nom) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="champ">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"><?= e($valeurs['description']) ?></textarea>
        </div>
    </div>

    <div id="panneau-co" role="tabpanel" aria-labelledby="onglet-co" hidden>
        <p class="aide">Chaque personne ajoutée reçoit une alerte pour confirmer ou refuser sa participation.</p>
        <?= $erreurChamp('participants') ?>

        <div id="liste-participants">
            <?php foreach ($valeurs['participants'] as $index => $ligne): ?>
                <?php $erreurLigne = $erreurs['participant_' . $index] ?? ''; ?>
                <?php require __DIR__ . '/_ligne_participant.php'; ?>
            <?php endforeach; ?>
        </div>
        <button type="button" id="ajouter-participant" class="bouton bouton-secondaire"
                data-prochain="<?= count($valeurs['participants']) ?>">Ajouter un co-participant</button>

        <template id="modele-participant">
            <?php
            $index = '__i__';
            $ligne = ['personnel_id' => 0, 'role' => ''];
            $erreurLigne = '';
            require __DIR__ . '/_ligne_participant.php';
            ?>
        </template>
    </div>

    <div class="actions-formulaire">
        <button type="submit" class="bouton">Enregistrer la déclaration</button>
        <a class="bouton bouton-secondaire" href="<?= e(lien('activites')) ?>">Annuler</a>
    </div>
</form>
