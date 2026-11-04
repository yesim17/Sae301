<?php
// config/connexion.php

// Inclusion des paramètres
require_once __DIR__ . '/param.php';

try {
    // Création du DSN (Data Source Name) pour PostgreSQL
    $dsn = "pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME;
    
    // Initialisation de la connexion PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    
    // Configuration des options PDO :
    // - Déclenche une exception en cas d'erreur SQL
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // - Récupère les données sous forme de tableau associatif par défaut
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Arrêt du script et affichage de l'erreur si la connexion échoue
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}