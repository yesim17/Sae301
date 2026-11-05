-- liquibase formatted sql

--changeset yessim17:insert-donnees-reference-dur
-- Insertion en "dur" : Les données fixes de paramétrage
INSERT INTO structure (code, nom) VALUES 
('IUT', 'IUT de Maubeuge - UPHF'),
('DSI', 'Direction des Systèmes d''Information'),
('RH', 'Ressources Humaines');

INSERT INTO type_activite (code, libelle, description) VALUES 
('ENS', 'Enseignement', 'Heures de cours, TD, TP'),
('RECH', 'Recherche', 'Travaux de recherche et publications'),
('ADM', 'Administration', 'Tâches administratives et gestion');

INSERT INTO role (code, libelle) VALUES 
('ADMIN_FONCTIONNEL', 'Administrateur Fonctionnel'),
('RESPONSABLE_RH', 'Responsable des Ressources Humaines'),
('PERSONNEL', 'Personnel standard');

INSERT INTO permission (code, libelle) VALUES 
('CAMPAGNE_CREATE', 'Créer une campagne de déclaration'),
('PROFIL_DISABLE', 'Désactiver un profil utilisateur'),
('ACTIVITE_VALIDATE', 'Valider les activités déclarées'),
('ACTIVITE_CREATE', 'Créer une déclaration d''activité');

--rollback DELETE FROM permission;
--rollback DELETE FROM role;
--rollback DELETE FROM type_activite;
--rollback DELETE FROM structure;


--changeset yessim17:insert-donnees-dynamiques
-- Insertion "dynamique" : On utilise des SELECT pour récupérer automatiquement les ID générés.

-- 1. Lier le rôle PERSONNEL à la permission ACTIVITE_CREATE
INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id 
FROM role r, permission p 
WHERE r.code = 'PERSONNEL' AND p.code = 'ACTIVITE_CREATE';

-- 2. Lier le rôle ADMIN à toutes les permissions
INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id 
FROM role r, permission p 
WHERE r.code = 'ADMIN_FONCTIONNEL';

-- 3. Lier le rôle RESPONSABLE_RH aux permissions de gestion (PROFIL_DISABLE, ACTIVITE_VALIDATE)
INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id 
FROM role r, permission p 
WHERE r.code = 'RESPONSABLE_RH' AND p.code IN ('PROFIL_DISABLE', 'ACTIVITE_VALIDATE');

-- 4. Créer un personnel de test (toi) affecté automatiquement à l'IUT
INSERT INTO personnel (nom, prenom, statut, identifiant_scientifique, structure_id)
SELECT 'Sehil', 'Yesim', 'Etudiant/Dev', 'YSEHIL2026', id
FROM structure 
WHERE code = 'IUT';

-- 5. T'attribuer le rôle d'Admin Fonctionnel
INSERT INTO personnel_role (personnel_id, role_id)
SELECT p.id, r.id 
FROM personnel p, role r 
WHERE p.identifiant_scientifique = 'YSEHIL2026' AND r.code = 'ADMIN_FONCTIONNEL';

--rollback DELETE FROM personnel_role;
--rollback DELETE FROM personnel;
--rollback DELETE FROM role_permission;

--changeset yessim17:ajouter-profils-tests-supplementaires
-- Ajout de profils supplémentaires (un Personnel standard et un Gestionnaire RH) pour tester les différents accès.

-- 1. Créer un personnel standard (Jean Dupont) rattaché à l'IUT
INSERT INTO personnel (nom, prenom, statut, identifiant_scientifique, structure_id)
SELECT 'Dupont', 'Jean', 'Maître de conférences', 'JDUPONT2026', id
FROM structure 
WHERE code = 'IUT';

-- 2. Attribuer le rôle PERSONNEL à Jean
INSERT INTO personnel_role (personnel_id, role_id)
SELECT p.id, r.id 
FROM personnel p, role r 
WHERE p.identifiant_scientifique = 'JDUPONT2026' AND r.code = 'PERSONNEL';

-- 3. Créer un gestionnaire RH (Sophie Martin) rattachée au département RH
INSERT INTO personnel (nom, prenom, statut, identifiant_scientifique, structure_id)
SELECT 'Martin', 'Sophie', 'Gestionnaire RH', 'SMARTIN2026', id
FROM structure 
WHERE code = 'RH';

-- 4. Attribuer le rôle RESPONSABLE_RH à Sophie
INSERT INTO personnel_role (personnel_id, role_id)
SELECT p.id, r.id 
FROM personnel p, role r 
WHERE p.identifiant_scientifique = 'SMARTIN2026' AND r.code = 'RESPONSABLE_RH';

--rollback DELETE FROM personnel_role WHERE personnel_id IN (SELECT id FROM personnel WHERE identifiant_scientifique IN ('JDUPONT2026', 'SMARTIN2026'));
--rollback DELETE FROM personnel WHERE identifiant_scientifique IN ('JDUPONT2026', 'SMARTIN2026');


--changeset yessim17:initialiser-mots-de-passe
-- Attribuer le mot de passe "1234" par défaut à l'ensemble des comptes créés
UPDATE personnel 
SET password_hash = '1234' 
WHERE password_hash IS NULL;

--rollback UPDATE personnel SET password_hash = NULL;