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

<section class="carte">
    <h2>Campagnes</h2>
    <?php if (!$campagnes): ?>
        <p>Aucune campagne pour le moment.</p>
    <?php else: ?>
        <?php $aujourdhui = date('Y-m-d'); ?>
        <div class="tableau-defilant">
            <table>
                <thead>
                <tr>
                    <th scope="col">Titre</th>
                    <th scope="col">Nature</th>
                    <th scope="col">Période</th>
                    <th scope="col">Statut</th>
                    <th scope="col"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($campagnes as $campagne): ?>
                    <?php
                    if (!$campagne['est_actif']) {
                        $statut = 'Clôturée';
                    } elseif ($campagne['date_fin'] < $aujourdhui) {
                        $statut = 'Terminée';
                    } elseif ($campagne['date_debut'] > $aujourdhui) {
                        $statut = 'À venir';
                    } else {
                        $statut = 'En cours';
                    }
                    $cloturable = $campagne['est_actif'] && $campagne['date_fin'] >= $aujourdhui;
                    ?>
                    <tr>
                        <td><?= e($campagne['titre']) ?></td>
                        <td><?= e($campagne['nature']) ?></td>
                        <td class="sans-retour"><?= e(dateFr($campagne['date_debut'])) ?> → <?= e(dateFr($campagne['date_fin'])) ?></td>
                        <td><?= e($statut) ?></td>
                        <td>
                            <?php if ($cloturable): ?>
                                <form method="post" action="<?= e(lien('campagne-cloturer')) ?>"
                                      onsubmit="return confirm('Clôturer cette campagne avant son terme ? Cette action est définitive.');">
                                    <?= champCsrf() ?>
                                    <input type="hidden" name="campagne_id" value="<?= (int) $campagne['id'] ?>">
                                    <button type="submit" class="bouton bouton-secondaire">Clôturer</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>