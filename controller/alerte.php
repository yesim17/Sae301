<?php
// controller/alerte.php
// Alertes de participation : confirmer ou refuser.

function pageAlertes(PDO $pdo): void
{
    afficher('alerte/liste', ['alertes' => alertesDe($pdo, moi()['id'])]);
}

function repondreAlerte(PDO $pdo): void
{
    exigerPost();

    $reponses = ['confirmer' => 'CONFIRME', 'refuser' => 'REFUSE'];
    $reponse = post('reponse');
    if (!isset($reponses[$reponse])) {
        erreur(400, "Cette réponse n'est pas valide.");
    }

    // La requête ne modifie que les participations de la personne connectée, et encore en attente.
    if (repondreParticipation($pdo, idDepuis(post('participation_id')), moi()['id'], $reponses[$reponse])) {
        flash('succes', $reponse === 'confirmer'
            ? 'Participation confirmée : l\'activité figure maintenant dans votre dossier.'
            : 'Participation refusée : l\'activité ne figurera pas dans votre dossier.');
    } else {
        flash('erreur', "Cette participation n'est plus en attente de réponse.");
    }
    rediriger('alertes');
}
