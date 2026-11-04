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
    return $requete->fetch() ?: null;
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
    $requete->execute(['identifiant' => $identifiant, 'sauf' => $sauf]);
    return (bool) $requete->fetchColumn();
}

function colonnesPersonnel(array $d): array
{
    return [
        'nom' => $d['nom'],
        'nom_usage' => $d['nom_usage'] !== '' ? $d['nom_usage'] : null,
        'prenom' => $d['prenom'],
        'statut' => $d['statut'],
        'identifiant' => $d['identifiant_scientifique'] !== '' ? $d['identifiant_scientifique'] : null,
        'structure' => $d['structure_id'] !== 0 ? $d['structure_id'] : null,
    ];
}

function creerPersonnel(PDO $pdo, array $d, array $roleIds): int
{
    $pdo->beginTransaction();
    $requete = $pdo->prepare(
        'INSERT INTO personnel (nom, nom_usage, prenom, statut, identifiant_scientifique, structure_id)
         VALUES (:nom, :nom_usage, :prenom, :statut, :identifiant, :structure)
         RETURNING id'
    );
    $requete->execute(colonnesPersonnel($d));
    $id = (int) $requete->fetchColumn();
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
