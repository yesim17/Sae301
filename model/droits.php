<?php
// model/droits.php
// Grille de permissions (rôles x permissions) et délégations temporaires.

/**
 * Charge un personnel actif avec ses droits effectifs : ceux de ses rôles,
 * plus ceux des délégations en cours. Renvoie null si le profil est absent ou désactivé.
 *
 * Une délégation ne compte que si le donneur est actif et détient encore le rôle.
 */
function chargerUtilisateur(PDO $pdo, int $id): ?array
{
    $requete = $pdo->prepare(
        'SELECT id, nom, nom_usage, prenom, statut, structure_id
         FROM personnel WHERE id = :id AND est_actif'
    );
    $requete->execute(['id' => $id]);
    $personnel = $requete->fetch();
    if (!$personnel) {
        return null;
    }

    $requete = $pdo->prepare(
        "SELECT r.code AS role, p.code AS permission, re.titulaire
         FROM (
             SELECT role_id, TRUE AS titulaire
             FROM personnel_role WHERE personnel_id = :id1
             UNION ALL
             SELECT d.role_id, FALSE
             FROM delegation_droit d
             JOIN personnel donneur ON donneur.id = d.donneur_ordre_id AND donneur.est_actif
             JOIN personnel_role pr ON pr.personnel_id = d.donneur_ordre_id AND pr.role_id = d.role_id
             WHERE d.delegataire_id = :id2 AND d.est_actif
               AND NOW() >= d.date_debut AND NOW() < d.date_fin
         ) re
         JOIN role r ON r.id = re.role_id
         LEFT JOIN role_permission rp ON rp.role_id = re.role_id
         LEFT JOIN permission p ON p.id = rp.permission_id"
    );
    $requete->execute(['id1' => $id, 'id2' => $id]);

    $personnel['roles_titulaires'] = [];
    $personnel['permissions'] = [];
    foreach ($requete as $ligne) {
        if ($ligne['titulaire'] && !in_array($ligne['role'], $personnel['roles_titulaires'], true)) {
            $personnel['roles_titulaires'][] = $ligne['role'];
        }
        if ($ligne['permission'] !== null && !in_array($ligne['permission'], $personnel['permissions'], true)) {
            $personnel['permissions'][] = $ligne['permission'];
        }
    }
    return $personnel;
}

// ---------- Grille de permissions ----------

function permissions(PDO $pdo): array
{
    return $pdo->query('SELECT id, code, libelle FROM permission ORDER BY libelle')->fetchAll();
}

/** Renvoie les cases cochées de la grille, sous la forme ['idRole-idPermission' => true]. */
function casesGrille(PDO $pdo): array
{
    $cases = [];
    foreach ($pdo->query('SELECT role_id, permission_id FROM role_permission') as $ligne) {
        $cases[$ligne['role_id'] . '-' . $ligne['permission_id']] = true;
    }
    return $cases;
}

/** Remplace la grille. $cases est une liste de couples [idRole, idPermission] déjà vérifiés. */
function enregistrerGrille(PDO $pdo, array $cases): void
{
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM role_permission');
    $insertion = $pdo->prepare('INSERT INTO role_permission (role_id, permission_id) VALUES (:role, :permission)');
    foreach ($cases as [$role, $permission]) {
        $insertion->execute(['role' => $role, 'permission' => $permission]);
    }
    $pdo->commit();
}

// ---------- Délégations ----------

/** Révocation automatique : toute délégation dont la date de fin est passée devient inactive. */
function revoquerDelegationsEchues(PDO $pdo): void
{
    $pdo->exec('UPDATE delegation_droit SET est_actif = FALSE WHERE est_actif AND date_fin <= NOW()');
}

/** Rôles détenus en propre : ce sont les seuls que l'on peut déléguer. */
function rolesDelegables(PDO $pdo, int $personnelId): array
{
    $requete = $pdo->prepare(
        'SELECT r.id, r.code, r.libelle
         FROM personnel_role pr JOIN role r ON r.id = pr.role_id
         WHERE pr.personnel_id = :id ORDER BY r.libelle'
    );
    $requete->execute(['id' => $personnelId]);
    return $requete->fetchAll();
}

function creerDelegation(PDO $pdo, int $donneur, int $delegataire, int $role, string $debut, string $fin): void
{
    $requete = $pdo->prepare(
        'INSERT INTO delegation_droit (donneur_ordre_id, delegataire_id, role_id, date_debut, date_fin)
         VALUES (:donneur, :delegataire, :role, :debut, :fin)'
    );
    $requete->execute([
        'donneur' => $donneur,
        'delegataire' => $delegataire,
        'role' => $role,
        'debut' => $debut,
        'fin' => $fin,
    ]);
}

/** Délégations d'une personne. $colonne vaut 'donneur_ordre_id' ou 'delegataire_id'. */
function delegationsDe(PDO $pdo, string $colonne, int $personnelId): array
{
    $colonne = $colonne === 'donneur_ordre_id' ? 'donneur_ordre_id' : 'delegataire_id';
    $requete = $pdo->prepare(
        "SELECT d.id, d.date_debut, d.date_fin, d.est_actif, r.libelle AS role,
                dn.prenom AS donneur_prenom, dn.nom AS donneur_nom, dn.nom_usage AS donneur_nom_usage,
                dl.prenom AS delegataire_prenom, dl.nom AS delegataire_nom, dl.nom_usage AS delegataire_nom_usage,
                CASE WHEN d.est_actif AND NOW() < d.date_debut THEN 'À venir'
                     WHEN d.est_actif THEN 'En cours'
                     WHEN d.date_fin <= NOW() THEN 'Expirée'
                     ELSE 'Révoquée' END AS etat
         FROM delegation_droit d
         JOIN role r ON r.id = d.role_id
         JOIN personnel dn ON dn.id = d.donneur_ordre_id
         JOIN personnel dl ON dl.id = d.delegataire_id
         WHERE d.$colonne = :id
         ORDER BY d.est_actif DESC, d.date_fin DESC"
    );
    $requete->execute(['id' => $personnelId]);
    return $requete->fetchAll();
}

/** Seul le donneur peut révoquer sa délégation. Renvoie false si rien n'a été révoqué. */
function revoquerDelegation(PDO $pdo, int $id, int $donneur): bool
{
    $requete = $pdo->prepare(
        'UPDATE delegation_droit SET est_actif = FALSE
         WHERE id = :id AND donneur_ordre_id = :donneur AND est_actif'
    );
    $requete->execute(['id' => $id, 'donneur' => $donneur]);
    return $requete->rowCount() > 0;
}
