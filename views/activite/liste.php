<?php
/** Lien de tri d'une colonne : conserve tous les filtres et inverse le sens si la colonne est déjà triée. */
$enteteTri = function (string $cle, string $libelle) use ($filtres): string {
    $estActive = $filtres['tri'] === $cle;
    $sens = ($estActive && $filtres['sens'] === 'asc') ? 'desc' : 'asc';
    $parametres = array_merge($filtres, ['tri' => $cle, 'sens' => $sens]);
    $aria = $estActive ? ' aria-sort="' . ($filtres['sens'] === 'asc' ? 'ascending' : 'descending') . '"' : '';
    $fleche = $estActive ? ($filtres['sens'] === 'asc' ? ' ▲' : ' ▼') : '';
    return '<th scope="col"' . $aria . '><a href="' . e(lien('activites', $parametres)) . '">'
        . e($libelle) . $fleche . '</a></th>';
};
$peutSoumettre = peutAcceder('soumettre');
?>
<div class="titre-page">
    <h1>Mon dossier d'activités</h1>
    <?php if (peutAcceder('declarer')): ?>
        <a class="bouton" href="<?= e(lien('declarer')) ?>">Déclarer une activité</a>
    <?php endif; ?>
</div>

<form class="filtres carte" method="get" action="index.php">
    <input type="hidden" name="page" value="activites">
    <input type="hidden" name="tri" value="<?= e($filtres['tri']) ?>">
    <input type="hidden" name="sens" value="<?= e($filtres['sens']) ?>">

    <div class="champ champ-large">
        <label for="q">Rechercher</label>
        <input type="search" id="q" name="q" value="<?= e($filtres['q']) ?>" placeholder="Titre ou description">
    </div>
    <div class="champ">
        <label for="du">Du</label>
        <input type="date" id="du" name="du" value="<?= e($filtres['du']) ?>">
    </div>
    <div class="champ">
        <label for="au">Au</label>
        <input type="date" id="au" name="au" value="<?= e($filtres['au']) ?>">
    </div>
    <div class="champ">
        <label for="nature">Nature</label>
        <select id="nature" name="nature">
            <option value="">Toutes</option>
            <option value="individuelle"<?= $filtres['nature'] === 'individuelle' ? ' selected' : '' ?>>Individuelle</option>
            <option value="collective"<?= $filtres['nature'] === 'collective' ? ' selected' : '' ?>>Collective</option>
        </select>
    </div>
    <div class="champ">
        <label for="type">Type</label>
        <select id="type" name="type">
            <option value="">Tous</option>
            <?php foreach ($types as $type): ?>
                <option value="<?= (int) $type['id'] ?>"<?= $filtres['type'] === (string) $type['id'] ? ' selected' : '' ?>><?= e($type['libelle']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="champ">
        <label for="statut">Statut</label>
        <select id="statut" name="statut">
            <option value="">Tous</option>
            <?php foreach (STATUTS_ACTIVITE as $code => $libelle): ?>
                <option value="<?= e($code) ?>"<?= $filtres['statut'] === $code ? ' selected' : '' ?>><?= e($libelle) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="actions-filtres">
        <button type="submit" class="bouton">Appliquer</button>
        <a class="bouton bouton-secondaire" href="<?= e(lien('activites')) ?>">Réinitialiser</a>
    </div>
</form>

<?php if (!$activites): ?>
    <div class="carte vide" role="status">
        <?php if ($filtresActifs): ?>
            <p><strong>Aucune activité ne correspond à votre recherche<?= $filtres['q'] !== '' ? ' « ' . e($filtres['q']) . ' »' : '' ?>.</strong></p>
            <p>Modifiez vos critères ou <a href="<?= e(lien('activites')) ?>">réinitialisez les filtres</a>.</p>
        <?php else: ?>
            <p><strong>Votre dossier ne contient encore aucune activité.</strong></p>
            <?php if (peutAcceder('declarer')): ?>
                <p><a href="<?= e(lien('declarer')) ?>">Déclarer ma première activité</a></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <form method="post" action="<?= e(lien('soumettre')) ?>">
        <?= champCsrf() ?>
        <?php foreach ($filtres as $cle => $valeur): ?>
            <input type="hidden" name="retour[<?= e($cle) ?>]" value="<?= e($valeur) ?>">
        <?php endforeach; ?>

        <div class="barre-liste">
            <p><?= count($activites) ?> activité<?= count($activites) > 1 ? 's' : '' ?></p>
            <?php if ($peutSoumettre): ?>
                <button type="submit" class="bouton bouton-secondaire">Soumettre la sélection</button>
            <?php endif; ?>
        </div>

        <div class="tableau-defilant carte">
            <table>
                <thead>
                    <tr>
                        <?php if ($peutSoumettre): ?>
                            <th scope="col" class="colonne-case">
                                <input type="checkbox" id="tout-selectionner" aria-label="Sélectionner tous les brouillons">
                            </th>
                        <?php endif; ?>
                        <?= $enteteTri('date', 'Date') ?>
                        <?= $enteteTri('titre', 'Titre') ?>
                        <?= $enteteTri('type', 'Type') ?>
                        <?= $enteteTri('nature', 'Nature') ?>
                        <?= $enteteTri('statut', 'Statut') ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activites as $activite): ?>
                        <?php $soumettable = $activite['statut'] === 'brouillon' && (int) $activite['personnel_id'] === (int) moi()['id']; ?>
                        <tr>
                            <?php if ($peutSoumettre): ?>
                                <td class="colonne-case">
                                    <?php if ($soumettable): ?>
                                        <input type="checkbox" class="case-activite" name="selection[]" value="<?= (int) $activite['id'] ?>" aria-label="Sélectionner <?= e($activite['titre']) ?>">
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="sans-retour"><?= e(dateFr($activite['date_activite'])) ?></td>
                            <td><a href="<?= e(lien('activite', ['id' => $activite['id']])) ?>"><?= e($activite['titre']) ?></a></td>
                            <td><?= e($activite['type_libelle']) ?></td>
                            <td><?= $activite['est_collective'] ? 'Collective' : 'Individuelle' ?></td>
                            <td><span class="badge badge-<?= e($activite['statut']) ?>"><?= e(STATUTS_ACTIVITE[$activite['statut']] ?? $activite['statut']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
<?php endif; ?>
