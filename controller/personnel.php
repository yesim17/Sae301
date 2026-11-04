<?php
// controller/personnel.php
// Gestion administrative des profils : création, correction d'identité, statut, désactivation.

require_once __DIR__ . '/../model/personnel.php';
require_once __DIR__ . '/../model/reference.php';

function pageListePersonnels(PDO $pdo): void
{
    afficher('personnel/liste', ['personnels' => listerPersonnels($pdo)]);
}

/** Statuts proposés : la liste métier, plus le statut actuel du profil s'il n'en fait pas partie. */
function statutsProposes(?array $profil): array
{
    $statuts = STATUTS_PERSONNEL;
    if ($profil !== null && !in_array($profil['statut'], $statuts, true)) {
        $statuts[] = $profil['statut'];
    }
    return $statuts;
}

function validerProfil(PDO $pdo, array $v, array $statuts, array $structures, int $id): array
{
    $erreurs = [];

    if ($v['nom'] === '') {
        $erreurs['nom'] = 'Le nom est obligatoire.';
    } elseif (tropLong($v['nom'], 100)) {
        $erreurs['nom'] = 'Le nom ne doit pas dépasser 100 caractères.';
    }
    if ($v['prenom'] === '') {
        $erreurs['prenom'] = 'Le prénom est obligatoire.';
    } elseif (tropLong($v['prenom'], 100)) {
        $erreurs['prenom'] = 'Le prénom ne doit pas dépasser 100 caractères.';
    }
    if (tropLong($v['nom_usage'], 100)) {
        $erreurs['nom_usage'] = "Le nom d'usage ne doit pas dépasser 100 caractères.";
    }
    if (!in_array($v['statut'], $statuts, true)) {
        $erreurs['statut'] = 'Le statut est obligatoire.';
    }
    if ($v['structure_id'] !== 0
        && !in_array($v['structure_id'], array_map('intval', array_column($structures, 'id')), true)) {
        $erreurs['structure_id'] = "Cette structure n'existe pas.";
    }

    if (tropLong($v['identifiant_scientifique'], 100)) {
        $erreurs['identifiant_scientifique'] = "L'identifiant ne doit pas dépasser 100 caractères.";
    } elseif ($v['identifiant_scientifique'] !== ''
        && identifiantDejaPris($pdo, $v['identifiant_scientifique'], $id)) {
        $erreurs['identifiant_scientifique'] = 'Cet identifiant scientifique est déjà utilisé par un autre profil.';
    } elseif ($v['identifiant_scientifique'] === '' && $v['nom'] !== '' && $v['prenom'] !== '') {
        $homonymes = chercherHomonymes($pdo, $v['nom'], $v['nom_usage'], $v['prenom'], $id);
        if ($homonymes) {
            $noms = implode(', ', array_map('libellePersonnel', $homonymes));
            $erreurs['identifiant_scientifique'] = 'Homonyme exact détecté (' . $noms
                . ') : un identifiant scientifique distinctif est obligatoire.';
        }
    }
    return $erreurs;
}

function pageFormulairePersonnel(PDO $pdo): void
{
    $id = idDepuis(get('id'));
    $profil = null;
    if (get('id') !== '') {
        $profil = trouverPersonnel($pdo, $id);
        if ($profil === null) {
            erreur(404, "Ce profil n'existe pas.");
        }
    }

    $structures = structures($pdo);
    $roles = roles($pdo);
    $statuts = statutsProposes($profil);
    $idsRoles = array_map('intval', array_column($roles, 'id'));

    // Seul un administrateur titulaire attribue les rôles. Sinon, un gestionnaire pourrait se donner
    // le rôle d'administrateur, et un délégataire conserver ses droits après la fin de la délégation.
    $gereRoles = estTitulaire('ADMIN_FONCTIONNEL');
    $rolesCoches = $profil !== null ? rolesDuPersonnel($pdo, $id) : [];

    $erreurs = [];
    $valeurs = [
        'nom' => $profil['nom'] ?? '',
        'nom_usage' => $profil['nom_usage'] ?? '',
        'prenom' => $profil['prenom'] ?? '',
        'statut' => $profil['statut'] ?? '',
        'identifiant_scientifique' => $profil['identifiant_scientifique'] ?? '',
        'structure_id' => (int) ($profil['structure_id'] ?? 0),
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $valeurs = [
            'nom' => post('nom'),
            'nom_usage' => post('nom_usage'),
            'prenom' => post('prenom'),
            'statut' => post('statut'),
            'identifiant_scientifique' => post('identifiant_scientifique'),
            'structure_id' => idDepuis(post('structure_id')),
        ];
        $erreurs = validerProfil($pdo, $valeurs, $statuts, $structures, $id);

        if ($gereRoles) {
            $recus = is_array($_POST['roles'] ?? null) ? $_POST['roles'] : [];
            $recus = array_map(function ($valeur) {
                return is_string($valeur) ? idDepuis($valeur) : 0;
            }, $recus);
            $nouveauxRoles = array_values(array_intersect($idsRoles, $recus));

            // Empêche un administrateur de perdre lui-même l'accès à l'administration.
            $roleAdmin = 0;
            foreach ($roles as $role) {
                if ($role['code'] === 'ADMIN_FONCTIONNEL') {
                    $roleAdmin = (int) $role['id'];
                }
            }
            if ($id === (int) moi()['id'] && !in_array($roleAdmin, $nouveauxRoles, true)) {
                $erreurs['roles'] = "Vous ne pouvez pas retirer votre propre rôle d'administrateur.";
            }
            $rolesCoches = $nouveauxRoles;
        }

        if (!$erreurs) {
            if ($profil === null) {
                // Sans droit sur les rôles, un nouveau profil reçoit le rôle de base PERSONNEL.
                $rolesInitiaux = $gereRoles ? $nouveauxRoles : idsRolePersonnel($roles);
                creerPersonnel($pdo, $valeurs, $rolesInitiaux);
                flash('succes', 'Profil créé.');
            } else {
                modifierPersonnel($pdo, $id, $valeurs, $gereRoles ? $nouveauxRoles : null);
                flash('succes', 'Profil mis à jour.');
            }
            rediriger('personnels');
        }
    }

    afficher('personnel/formulaire', [
        'profil' => $profil,
        'valeurs' => $valeurs,
        'erreurs' => $erreurs,
        'statuts' => $statuts,
        'structures' => $structures,
        'roles' => $roles,
        'gereRoles' => $gereRoles,
        'rolesCoches' => $rolesCoches,
    ]);
}

function idsRolePersonnel(array $roles): array
{
    foreach ($roles as $role) {
        if ($role['code'] === 'PERSONNEL') {
            return [(int) $role['id']];
        }
    }
    return [];
}

/** Désactive ou réactive un profil. Ses activités et participations restent en base. */
function changerActivation(PDO $pdo): void
{
    exigerPost();

    $profil = trouverPersonnel($pdo, idDepuis(post('id')));
    if ($profil === null) {
        erreur(404, "Ce profil n'existe pas.");
    }

    $activer = post('action') === 'reactiver';
    if (!$activer && (int) $profil['id'] === (int) moi()['id']) {
        flash('erreur', 'Vous ne pouvez pas désactiver votre propre profil.');
        rediriger('personnels');
    }

    definirActivation($pdo, (int) $profil['id'], $activer);
    flash('succes', 'Le profil de ' . nomComplet($profil) . ' a été '
        . ($activer ? 'réactivé.' : 'désactivé. Son historique est conservé.'));
    rediriger('personnels');
}
