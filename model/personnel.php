<?php
// model/personnel.php
// Profils du personnel. Aucune fonction ne supprime de profil : la désactivation conserve l'historique.

/** Statuts proposés dans le formulaire. Liste à ajuster selon les règles métier du client. */
const STATUTS_PERSONNEL = [
    'Enseignant-chercheur',
    'Enseignant',
    'Doctorant',
    'Vacataire',
    'BIATSS',
];

function listerPersonnels(PDO $pdo): array
{
    return $pdo->query(
        "SELECT p.id, p.nom, p.nom_usage, p.prenom, p.statut, p.identifiant_scientifique, p.est_actif,
                s.nom AS structure_nom,
                COALESCE(string_agg(r.libelle, ', ' ORDER BY r.libelle), '') AS roles
         FROM personnel p
         LEFT JOIN structure s ON s.id = p.structure_id
         LEFT JOIN personnel_role pr ON pr.personnel_id = p.id
         LEFT JOIN role r ON r.id = pr.role_id
         GROUP BY p.id, s.nom
         ORDER BY p.est_actif DESC, LOWER(p.nom), LOWER(p.prenom)"
    )->fetchAll();
}

/** Personnels actifs pour les listes déroulantes, en excluant éventuellement une personne. */
function personnelsActifs(PDO $pdo, int $sauf = 0): array
{
    $requete = $pdo->prepare(
        'SELECT id, nom, nom_usage, prenom, identifiant_scientifique
         FROM personnel WHERE est_actif AND id <> :sauf
         ORDER BY LOWER(nom), LOWER(prenom)'
    );
    $requete->execute(['sauf' => $sauf]);
    return $requete->fetchAll();
}

function trouverPersonnel(PDO $pdo, int $id): ?array
{
    $requete = $pdo->prepare('SELECT * FROM personnel WHERE id = :id');
    $requete->execute(['id' => $id]);
    $personnel = $requete->fetch();

    if (!$personnel) {
        return null;
    }

    // Charger les rôles titulaires
    $reqRoles = $pdo->prepare('
        SELECT r.code 
        FROM role r 
        JOIN personnel_role pr ON pr.role_id = r.id 
        WHERE pr.personnel_id = :id
    ');
    $reqRoles->execute(['id' => $id]);
    $personnel['roles_titulaires'] = $reqRoles->fetchAll(PDO::FETCH_COLUMN);

    // Charger toutes les permissions associées à ses rôles
    $reqPerms = $pdo->prepare('
        SELECT DISTINCT p.code 
        FROM permission p 
        JOIN role_permission rp ON rp.permission_id = p.id 
        JOIN personnel_role pr ON pr.role_id = rp.role_id 
        WHERE pr.personnel_id = :id
    ');
    $reqPerms->execute(['id' => $id]);
    $personnel['permissions'] = $reqPerms->fetchAll(PDO::FETCH_COLUMN);

    return $personnel;
}

/**
 * Cherche les homonymes exacts : même prénom, et même nom ou nom d'usage (sans tenir compte de la casse).
 * Les profils désactivés comptent aussi, car leur historique reste consultable.
 */
function chercherHomonymes(PDO $pdo, string $nom, string $nomUsage, string $prenom, int $sauf = 0): array
{
    $autreNom = $nomUsage !== '' ? $nomUsage : $nom;
    $requete = $pdo->prepare(
        'SELECT id, nom, nom_usage, prenom, identifiant_scientifique
         FROM personnel
         WHERE id <> :sauf
           AND LOWER(prenom) = LOWER(:prenom)
           AND (LOWER(nom) IN (LOWER(:nom1), LOWER(:autre1))
                OR LOWER(nom_usage) IN (LOWER(:nom2), LOWER(:autre2)))'
    );
    $requete->execute([
        'sauf' => $sauf,
        'prenom' => $prenom,
        'nom1' => $nom,
        'autre1' => $autreNom,
        'nom2' => $nom,
        'autre2' => $autreNom,
    ]);
    return $requete->fetchAll();
}

function identifiantDejaPris(PDO $pdo, string $identifiant, int $sauf = 0): bool
{
    $requete = $pdo->prepare(
        'SELECT 1 FROM personnel WHERE identifiant_scientifique = :identifiant AND id <> :sauf'
    );
    $requete->execute(['identifiant' => trim($identifiant), 'sauf' => $sauf]);
    return (bool) $requete->fetchColumn();
}

function colonnesPersonnel(array $d): array
{
    $identifiant = isset($d['identifiant_scientifique']) ? trim($d['identifiant_scientifique']) : '';
    return [
        'nom' => trim($d['nom']),
        'nom_usage' => trim($d['nom_usage']) !== '' ? trim($d['nom_usage']) : null,
        'prenom' => trim($d['prenom']),
        'statut' => trim($d['statut']),
        'identifiant' => $identifiant !== '' ? $identifiant : null,
        'structure' => (int) ($d['structure_id'] ?? 0) !== 0 ? (int) $d['structure_id'] : null,
    ];
}

function creerPersonnel(PDO $pdo, array $d, array $roleIds): int
{
    $pdo->beginTransaction();

    $requete = $pdo->prepare(
        'INSERT INTO personnel (nom, nom_usage, prenom, statut, identifiant_scientifique, structure_id, password_hash, est_actif)
         VALUES (:nom, :nom_usage, :prenom, :statut, :identifiant, :structure, :password_hash, TRUE)
         RETURNING id'
    );

    $colonnes = colonnesPersonnel($d);
    $colonnes['password_hash'] = '1234';

    $requete->execute($colonnes);
    $id = (int) $requete->fetchColumn();

    // Si aucun rôle n'a été coché, attribuer le rôle PERSONNEL par défaut
    if (empty($roleIds)) {
        $reqRoleDefaut = $pdo->query("SELECT id FROM role WHERE code = 'PERSONNEL' LIMIT 1");
        $idRolePersonnel = $reqRoleDefaut->fetchColumn();
        if ($idRolePersonnel) {
            $roleIds = [(int) $idRolePersonnel];
        }
    }

    remplacerRoles($pdo, $id, $roleIds);
    $pdo->commit();

    return $id;
}

/** $roleIds à null laisse les rôles inchangés. */
function modifierPersonnel(PDO $pdo, int $id, array $d, ?array $roleIds): void
{
    $pdo->beginTransaction();
    $requete = $pdo->prepare(
        'UPDATE personnel
         SET nom = :nom, nom_usage = :nom_usage, prenom = :prenom, statut = :statut,
             identifiant_scientifique = :identifiant, structure_id = :structure, mis_a_jour_le = NOW()
         WHERE id = :id'
    );
    $requete->execute(colonnesPersonnel($d) + ['id' => $id]);
    if ($roleIds !== null) {
        remplacerRoles($pdo, $id, $roleIds);
    }
    $pdo->commit();
}

function definirActivation(PDO $pdo, int $id, bool $actif): void
{
    $requete = $pdo->prepare(
        'UPDATE personnel SET est_actif = :actif, mis_a_jour_le = NOW() WHERE id = :id'
    );
    $requete->execute(['actif' => $actif ? 'true' : 'false', 'id' => $id]);
}

function rolesDuPersonnel(PDO $pdo, int $id): array
{
    $requete = $pdo->prepare('SELECT role_id FROM personnel_role WHERE personnel_id = :id');
    $requete->execute(['id' => $id]);
    return array_map('intval', $requete->fetchAll(PDO::FETCH_COLUMN));
}

function remplacerRoles(PDO $pdo, int $id, array $roleIds): void
{
    $requete = $pdo->prepare('DELETE FROM personnel_role WHERE personnel_id = :id');
    $requete->execute(['id' => $id]);
    $insertion = $pdo->prepare('INSERT INTO personnel_role (personnel_id, role_id) VALUES (:id, :role)');
    foreach ($roleIds as $roleId) {
        $insertion->execute(['id' => $id, 'role' => $roleId]);
    }
}

function trouverPersonnelParIdentifiant(PDO $pdo, string $identifiant): ?array
{
    $requete = $pdo->prepare('SELECT * FROM personnel WHERE LOWER(TRIM(identifiant_scientifique)) = LOWER(TRIM(:id)) LIMIT 1');
    $requete->execute(['id' => $identifiant]);
    $personnel = $requete->fetch();

    if (!$personnel) {
        return null;
    }

    // Récupérer les codes des rôles propres (titulaires)
    $reqRoles = $pdo->prepare('
        SELECT r.code 
        FROM role r 
        JOIN personnel_role pr ON pr.role_id = r.id 
        WHERE pr.personnel_id = :id
    ');
    $reqRoles->execute(['id' => $personnel['id']]);
    $personnel['roles_titulaires'] = $reqRoles->fetchAll(PDO::FETCH_COLUMN);

    // Récupérer toutes les permissions associées à ses rôles
    $reqPerms = $pdo->prepare('
        SELECT DISTINCT p.code 
        FROM permission p 
        JOIN role_permission rp ON rp.permission_id = p.id 
        JOIN personnel_role pr ON pr.role_id = rp.role_id 
        WHERE pr.personnel_id = :id
    ');
    $reqPerms->execute(['id' => $personnel['id']]);
    $personnel['permissions'] = $reqPerms->fetchAll(PDO::FETCH_COLUMN);

    return $personnel;
}