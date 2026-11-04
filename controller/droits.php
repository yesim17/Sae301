<?php
// controller/droits.php
// Grille de permissions et délégations temporaires.

require_once __DIR__ . '/../model/personnel.php';
require_once __DIR__ . '/../model/reference.php';

// ---------- Grille de permissions ----------

function pageGrille(PDO $pdo): void
{
    $roles = roles($pdo);
    $permissions = permissions($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cochees = is_array($_POST['cases'] ?? null) ? $_POST['cases'] : [];

        // On parcourt les rôles et permissions existants : une valeur inventée est ignorée.
        $cases = [];
        foreach ($roles as $role) {
            foreach ($permissions as $permission) {
                if (isset($cochees[$role['id'] . '-' . $permission['id']])) {
                    $cases[] = [(int) $role['id'], (int) $permission['id']];
                }
            }
        }
        enregistrerGrille($pdo, $cases);
        flash('succes', 'Grille de permissions enregistrée.');
        rediriger('grille');
    }

    afficher('droits/grille', [
        'roles' => $roles,
        'permissions' => $permissions,
        'cases' => casesGrille($pdo),
    ]);
}

// ---------- Délégations ----------

function validerDelegation(array $v, array $collegues, array $roles): array
{
    $erreurs = [];

    if (!in_array($v['delegataire_id'], array_map('intval', array_column($collegues, 'id')), true)) {
        $erreurs['delegataire_id'] = 'Choisissez un collègue actif.';
    }
    if (!in_array($v['role_id'], array_map('intval', array_column($roles, 'id')), true)) {
        $erreurs['role_id'] = 'Choisissez un rôle que vous détenez.';
    }
    if (!dateHeureValide($v['date_debut'])) {
        $erreurs['date_debut'] = 'La date de début est obligatoire.';
    }
    if (!dateHeureValide($v['date_fin'])) {
        $erreurs['date_fin'] = 'La date de fin est obligatoire.';
    }
    if (!isset($erreurs['date_debut']) && !isset($erreurs['date_fin'])) {
        if ($v['date_fin'] <= $v['date_debut']) {
            $erreurs['date_fin'] = 'La date de fin doit être postérieure à la date de début.';
        } elseif ($v['date_fin'] <= date('Y-m-d\TH:i')) {
            $erreurs['date_fin'] = 'La date de fin doit être dans le futur.';
        }
    }
    return $erreurs;
}

function pageDelegations(PDO $pdo): void
{
    $utilisateur = moi();
    $collegues = personnelsActifs($pdo, $utilisateur['id']);
    $roles = rolesDelegables($pdo, $utilisateur['id']);
    $erreurs = [];
    $valeurs = ['delegataire_id' => 0, 'role_id' => 0, 'date_debut' => '', 'date_fin' => ''];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $valeurs = [
            'delegataire_id' => idDepuis(post('delegataire_id')),
            'role_id' => idDepuis(post('role_id')),
            'date_debut' => post('date_debut'),
            'date_fin' => post('date_fin'),
        ];
        $erreurs = validerDelegation($valeurs, $collegues, $roles);

        if (!$erreurs) {
            creerDelegation(
                $pdo,
                $utilisateur['id'],
                $valeurs['delegataire_id'],
                $valeurs['role_id'],
                str_replace('T', ' ', $valeurs['date_debut']),
                str_replace('T', ' ', $valeurs['date_fin'])
            );
            flash('succes', 'Délégation créée. Elle sera révoquée automatiquement à son échéance.');
            rediriger('delegations');
        }
    }

    afficher('droits/delegations', [
        'collegues' => $collegues,
        'roles' => $roles,
        'valeurs' => $valeurs,
        'erreurs' => $erreurs,
        'delegationsDonnees' => delegationsDe($pdo, 'donneur_ordre_id', $utilisateur['id']),
        'delegationsRecues' => delegationsDe($pdo, 'delegataire_id', $utilisateur['id']),
    ]);
}

function annulerDelegation(PDO $pdo): void
{
    exigerPost();
    if (revoquerDelegation($pdo, idDepuis(post('id')), moi()['id'])) {
        flash('succes', 'Délégation révoquée.');
    } else {
        flash('erreur', 'Cette délégation ne peut pas être révoquée.');
    }
    rediriger('delegations');
}
