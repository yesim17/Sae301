<?php
// model/reference.php
// Données de paramétrage utilisées dans les listes déroulantes.

function typesActivite(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, code, libelle, description FROM type_activite WHERE est_actif ORDER BY libelle'
    )->fetchAll();
}

function structures(PDO $pdo): array
{
    return $pdo->query('SELECT id, code, nom FROM structure WHERE est_actif ORDER BY nom')->fetchAll();
}

function roles(PDO $pdo): array
{
    return $pdo->query('SELECT id, code, libelle FROM role ORDER BY libelle')->fetchAll();
}
