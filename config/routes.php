<?php
// config/routes.php
// page => [fichier du contrôleur, fonction à appeler, droit requis]
//
// Droit requis :
//   'public'             : accessible sans connexion
//   'connecte'           : tout personnel connecté
//   'titulaire:CODE'     : rôle détenu en propre (une délégation ne suffit pas)
//   'CODE_PERMISSION'    : permission de la table "permission" (rôle propre ou délégué)
//
// Cette table sert aussi à construire le menu : une page non autorisée n'y apparaît pas.

return [
    'connexion'            => ['auth', 'pageConnexion', 'public'],
    'deconnexion'          => ['auth', 'deconnexion', 'connecte'],

    'activites'            => ['activite', 'pageMesActivites', 'connecte'],
    'soumettre'            => ['activite', 'soumettreSelection', 'ACTIVITE_CREATE'],
    'declarer'             => ['activite', 'pageDeclarer', 'ACTIVITE_CREATE'],
    'activite'             => ['activite', 'pageDetail', 'connecte'],
    'participant-ajouter'  => ['activite', 'ajouterCoParticipant', 'ACTIVITE_CREATE'],
    'participant-retirer'  => ['activite', 'retirerCoParticipant', 'ACTIVITE_CREATE'],
    'bilan'                => ['activite', 'pageBilanEquipe', 'connecte'],

    'alertes'              => ['alerte', 'pageAlertes', 'connecte'],
    'alerte-repondre'      => ['alerte', 'repondreAlerte', 'connecte'],

    'personnels'           => ['personnel', 'pageListePersonnels', 'PROFIL_DISABLE'],
    'personnel-form'       => ['personnel', 'pageFormulairePersonnel', 'PROFIL_DISABLE'],
    'personnel-activation' => ['personnel', 'changerActivation', 'PROFIL_DISABLE'],

    'delegations'          => ['droits', 'pageDelegations', 'connecte'],
    'delegation-revoquer'  => ['droits', 'annulerDelegation', 'connecte'],
    'grille'               => ['droits', 'pageGrille', 'titulaire:ADMIN_FONCTIONNEL'],

    'campagnes'            => ['campagne', 'pageCampagnes', 'CAMPAGNE_CREATE'],
    'comparaison'          => ['comparaison', 'pageComparaison', 'ACTIVITE_VALIDATE'],

    'generer_rapport' => ['Gestionrapport', 'afficherRapport', 'connecte'],
    ];