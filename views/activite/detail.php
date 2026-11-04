<div class="titre-page">
    <h1><?= e($activite['titre']) ?></h1>
    <a class="bouton bouton-secondaire" href="<?= e(lien('activites')) ?>">Retour au dossier</a>
</div>

<section class="carte">
    <dl class="fiche">
        <div><dt>Date</dt><dd><?= e(dateFr($activite['date_activite'])) ?></dd></div>
        <div><dt>Type</dt><dd><?= e($activite['type_libelle']) ?></dd></div>
        <div><dt>Nature</dt><dd><?= $activite['est_collective'] ? 'Collective' : 'Individuelle' ?></dd></div>
        <div><dt>Statut</dt><dd><span class="badge badge-<?= e($activite['statut']) ?>"><?= e(STATUTS_ACTIVITE[$activite['statut']] ?? $activite['statut']) ?></span></dd></div>
        <div><dt>Déclarée par</dt><dd><?= e(nomComplet($activite)) ?></dd></div>
        <div><dt>Structure</dt><dd><?= e($activite['structure_nom'] ?: 'Aucune') ?></dd></div>
    </dl>
    <?php if ($activite['description'] !== null && $activite['description'] !== ''): ?>
        <h2>Description</h2>
        <p class="texte-libre"><?= e($activite['description']) ?></p>
    <?php endif; ?>
</section>

<?php if ($activite['est_collective']): ?>
    <section class="carte">
        <h2>Co-participants</h2>
        <?php if (!$participants): ?>
            <p>Aucun co-participant pour le moment.</p>
        <?php else: ?>
            <div class="tableau-defilant">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Personne</th>
                            <th scope="col">Rôle</th>
                            <th scope="col">Réponse</th>
                            <?php if ($peutModifierParticipants): ?><th scope="col"><span class="visuellement-cache">Actions</span></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $participant): ?>
                            <tr>
                                <td><?= e(libellePersonnel($participant)) ?></td>
                                <td><?= e($participant['role']) ?></td>
                                <td><span class="badge badge-<?= e($participant['statut']) ?>"><?= e(STATUTS_PARTICIPATION[$participant['statut']] ?? $participant['statut']) ?></span></td>
                                <?php if ($peutModifierParticipants): ?>
                                    <td>
                                        <?php if ($participant['statut'] !== 'CONFIRME'): ?>
                                            <form method="post" action="<?= e(lien('participant-retirer')) ?>">
                                                <?= champCsrf() ?>
                                                <input type="hidden" name="activite_id" value="<?= (int) $activite['id'] ?>">
                                                <input type="hidden" name="participation_id" value="<?= (int) $participant['id'] ?>">
                                                <button type="submit" class="bouton bouton-secondaire">Retirer</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($peutModifierParticipants): ?>
            <h3>Ajouter un co-participant</h3>
            <form class="ligne-participant" method="post" action="<?= e(lien('participant-ajouter')) ?>">
                <?= champCsrf() ?>
                <input type="hidden" name="activite_id" value="<?= (int) $activite['id'] ?>">
                <div class="champ">
                    <label for="ajout-personnel">Membre du personnel</label>
                    <select id="ajout-personnel" name="personnel_id">
                        <option value="">Choisir une personne</option>
                        <?php foreach ($collegues as $collegue): ?>
                            <option value="<?= (int) $collegue['id'] ?>"><?= e(libellePersonnel($collegue)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="champ">
                    <label for="ajout-role">Rôle <span class="requis">*</span></label>
                    <input type="text" id="ajout-role" name="role" maxlength="100">
                </div>
                <button type="submit" class="bouton">Ajouter</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>
