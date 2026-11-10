-- liquibase formatted sql

--changeset yessim17:insert-donnees-reference-dur
-- Insertion en "dur" : Les données fixes de paramétrage
INSERT INTO structure (code, nom) VALUES 
('IUT', 'IUT de Maubeuge - UPHF'),
('DSI', 'Direction des Systèmes d''Information'),
('RH', 'Ressources Humaines');

--liquibase formatted sql

--changeset yessim17:ajout-nouvelles-activites
INSERT INTO type_activite (code, libelle, description) VALUES 
('PROJ_RECH', 'Projet de recherche', 'Conception et contribution à un projet de recherche individuel ou collaboratif'),
('CONF_INVITE', 'Conférence invitée', 'Participation à une conférence en tant qu''invité'),
('PRES_SESSION', 'Présidence de session', 'Gestion et animation de session lors d''un congrès'),
('RELECTURE_ART', 'Relecture d''article', 'Évaluation et relecture d''articles scientifiques'),
('ORG_EVENT', 'Organisation d''événement scientifique', 'Organisation de colloques, séminaires ou congrès'),
('GEST_PLAT','Gestion de plateforme', 'Gestion et maintenance de plateforme technique ou scientifique'),
('COMITE_SELECT', 'Comité de sélection', 'Participation à un comité de sélection (recrutement)'),
('EXP_DOSSIER', 'Expertise de dossier', 'Évaluation et analyse de dossiers administratifs ou académiques');

--rollback DELETE FROM type_activite WHERE code IN ('PROJ_RECH', 'CONF_INVITE', 'PRES_SESSION', 'RELECTURE_ART', 'ORG_EVENT', 'GEST_PLAT', 'COMITE_SELECT', 'EXP_DOSSIER');

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