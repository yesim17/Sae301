<?php
// controller/campagne.php
// Création de campagnes (permission CAMPAGNE_CREATE, vérifiée dans index.php).

require_once __DIR__ . '/../model/campagne.php';

function validerCampagne(array $v): array
{
    $erreurs = [];

    if ($v['titre'] === '') {
        $erreurs['titre'] = 'Le titre est obligatoire.';
    } elseif (tropLong($v['titre'], 255)) {
        $erreurs['titre'] = 'Le titre ne doit pas dépasser 255 caractères.';
    }
    if (!dateValide($v['date_debut'])) {
        $erreurs['date_debut'] = 'La date de début est obligatoire.';
    }
    if (!dateValide($v['date_fin'])) {
        $erreurs['date_fin'] = 'La date de fin est obligatoire.';
    }
    if (!isset($erreurs['date_debut']) && !isset($erreurs['date_fin']) && $v['date_fin'] < $v['date_debut']) {
        $erreurs['date_fin'] = 'La date de fin ne peut pas précéder la date de début.';
    }
    return $erreurs;
}

function pageCampagnes(PDO $pdo): void
{
    $erreurs = [];
    $valeurs = ['titre' => '', 'description' => '', 'date_debut' => '', 'date_fin' => ''];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $valeurs = [
            'titre' => post('titre'),
            'description' => post('description'),
            'date_debut' => post('date_debut'),
            'date_fin' => post('date_fin'),
        ];
        $erreurs = validerCampagne($valeurs);

        if (!$erreurs) {
            createCampagne(
                $pdo,
                $valeurs['titre'],
                $valeurs['description'],
                $valeurs['date_debut'],
                $valeurs['date_fin'],
                moi()['id']
            );
            flash('succes', 'La campagne « ' . $valeurs['titre'] . ' » a été créée.');
            rediriger('campagnes');
        }
    }

    afficher('campagne/index', [
        'valeurs' => $valeurs,
        'erreurs' => $erreurs,
        'campagnes' => listerCampagnes($pdo),
    ]);
}
