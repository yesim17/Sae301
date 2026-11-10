<?php
// controller/relance.php
// US-22 : relances d'inactivité et rappels d'échéance (permission RELANCE_SEND, vérifiée dans index.php).

require_once __DIR__ . '/../model/relance.php';

const TYPES_RELANCE_LIBELLES = [
    'INACTIVITE' => "Relance d'inactivité",
    'RAPPEL_ECHEANCE' => "Rappel d'échéance",
];

function pageRelances(PDO $pdo): void
{
    afficher('relance/index', [
        'inactifs' => personnelsInactifsARelancer($pdo),
        'echeances' => rappelsEcheanceARelancer($pdo),
        'historique' => dernieresRelances($pdo),
    ]);
}

function envoyerRelances(PDO $pdo): void
{
    exigerPost();
    $type = post('type');
    $moiId = moi()['id'];
    $envoyees = 0;

    // Les destinataires sont recalculés ici : on ne se fie jamais à la liste affichée.
    if ($type === 'inactivite') {
        foreach (personnelsInactifsARelancer($pdo) as $p) {
            $ok = envoyerRelance(
                $pdo,
                (int) $p['id'],
                'INACTIVITE',
                null,
                'Relance : aucune activité déclarée',
                'Vous n\'avez déclaré aucune activité depuis plus de ' . SEUIL_INACTIVITE_JOURS
                    . ' jours. Pensez à saisir vos activités.',
                $moiId
            );
            $envoyees += $ok ? 1 : 0;
        }
    } elseif ($type === 'echeance') {
        foreach (rappelsEcheanceARelancer($pdo) as $p) {
            $ok = envoyerRelance(
                $pdo,
                (int) $p['id'],
                'RAPPEL_ECHEANCE',
                (int) $p['campagne_id'],
                'Rappel : échéance de la campagne « ' . $p['campagne_titre'] . ' »',
                'La campagne « ' . $p['campagne_titre'] . ' » se termine le ' . dateFr($p['date_fin'])
                    . '. Vous n\'avez pas encore soumis d\'activité sur cette période.',
                $moiId
            );
            $envoyees += $ok ? 1 : 0;
        }
    } else {
        erreur(400, "Ce type de relance n'est pas valide.");
    }

    if ($envoyees > 0) {
        flash('succes', $envoyees . ($envoyees > 1 ? ' relances envoyées.' : ' relance envoyée.'));
    } else {
        flash('erreur', "Aucune relance n'a été envoyée : il n'y a personne à relancer pour le moment.");
    }
    rediriger('relances');
}
