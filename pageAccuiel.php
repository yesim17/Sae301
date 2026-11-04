<?php

require_once __DIR__ . '/config/connexion.php';
$message_connexion = isset($pdo) ? "Connexion à la base de données PostgreSQL réussie ! 🐘" : "Erreur inconnue.";
