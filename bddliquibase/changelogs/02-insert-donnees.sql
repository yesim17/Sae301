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
-- Insertion "dynamique" : On utilise des SELECT pour récupérer automatiquement les ID (SERIAL) générés à l'étape précédente.

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

-- 3. Créer un personnel de test (toi) affecté automatiquement à l'IUT
INSERT INTO personnel (nom, prenom, statut, identifiant_scientifique, structure_id)
SELECT 'Sehil', 'Yesim', 'Etudiant/Dev', 'YSEHIL2026', id
FROM structure 
WHERE code = 'IUT';

-- 4. T'attribuer le rôle d'Admin Fonctionnel
INSERT INTO personnel_role (personnel_id, role_id)
SELECT p.id, r.id 
FROM personnel p, role r 
WHERE p.identifiant_scientifique = 'YSEHIL2026' AND r.code = 'ADMIN_FONCTIONNEL';

--rollback DELETE FROM personnel_role;
--rollback DELETE FROM personnel;
--rollback DELETE FROM role_permission;