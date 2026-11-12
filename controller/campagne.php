<?php
// controller/campagne.php
require_once __DIR__ . '/../model/campagne.php';

function validerCampagne(array $v, PDO $pdo): array
{
    $erreurs = [];

    // Erreurs sur le titre
    if ($v['titre'] === '') {
        $erreurs['titre'] = "Le champ 'Titre' est vide. Veuillez saisir un nom explicite pour identifier cette campagne.";
    } elseif (tropLong($v['titre'], 255)) {
        $erreurs['titre'] = "Le titre saisi est trop long. Il doit contenir au maximum 255 caractères.";
    }

    // Erreurs sur la période et la nature
    if ($v['periode_reference'] === '') {
        $erreurs['periode_reference'] = "Veuillez indiquer une période de référence (par exemple : '2026-2027' ou 'S1').";
    }
    if ($v['nature'] === '') {
        $erreurs['nature'] = "Veuillez sélectionner la nature de la campagne dans la liste déroulante.";
    }

    // Erreurs d'oubli sur les dates
    if (!dateValide($v['date_debut'])) {
        $erreurs['date_debut'] = "La date d'ouverture de la campagne doit obligatoirement être renseignée.";
    }
    if (!dateValide($v['date_fin'])) {
        $erreurs['date_fin'] = "La date de clôture de la campagne doit obligatoirement être renseignée.";
    }

    // Règle US : La clôture antérieure à l'ouverture est refusée
    if (!isset($erreurs['date_debut']) && !isset($erreurs['date_fin']) && $v['date_fin'] < $v['date_debut']) {
        $erreurs['date_fin'] = "Erreur de paramétrage : la date de clôture de la campagne doit être postérieure ou égale à sa date d'ouverture.";
    }

    // Règle US : Chevauchement bloqué pour une même nature
    if (empty($erreurs) && verifierChevauchementCampagne($pdo, $v['nature'], $v['date_debut'], $v['date_fin'])) {
        $erreurs['nature'] = "Conflit de dates : une campagne de nature « {$v['nature']} » est déjà programmée sur cette même période. Le chevauchement est interdit pour des natures identiques.";
    }

    return $erreurs;
}
function pageCampagnes(PDO $pdo): void
{
    $erreurs = [];
    $valeurs = ['titre' => '', 'description' => '', 'periode_reference' => '', 'nature' => '', 'date_debut' => '', 'date_fin' => ''];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $valeurs = [
            'titre' => post('titre'),
            'description' => post('description'),
            'periode_reference' => post('periode_reference'),
            'nature' => post('nature'),
            'date_debut' => post('date_debut'),
            'date_fin' => post('date_fin'),
        ];

        $erreurs = validerCampagne($valeurs, $pdo);

        if (!$erreurs) {
            ajouterCampagne(
                $pdo,
                $valeurs['titre'],
                $valeurs['description'],
                $valeurs['periode_reference'],
                $valeurs['nature'],
                $valeurs['date_debut'],
                $valeurs['date_fin'],
                moi()['id']
            );
            flash('succes', 'Succès : La campagne « ' . $valeurs['titre'] . ' » a été créée et enregistrée dans le système.');
            rediriger('campagnes');
        } else {
            // Message global plus clair en haut de page
            flash('erreur', 'L\'enregistrement a échoué. Veuillez vérifier les indications en rouge sous les champs du formulaire.');
        }
    }

    afficher('campagne/index', [
        'valeurs' => $valeurs,
        'erreurs' => $erreurs,
        'campagnes' => listerCampagnes($pdo),
    ]);
}


function cloturerCampagne(PDO $pdo): void
{
    exigerPost();
    if (fermerCampagne($pdo, idDepuis(post('campagne_id')))) {
        flash('succes', 'La campagne a été clôturée.');
    } else {
        flash('erreur', 'Cette campagne est introuvable ou déjà clôturée.');
    }
    rediriger('campagnes');
}