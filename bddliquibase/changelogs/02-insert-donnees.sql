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


--changeset yessim17:ajouter-profils-tests-etendus
-- Ajout de profils de test : 2 admins fonctionnels et plusieurs personnels standards.
-- Une seule personne RH est conservée (Sophie Martin, déjà créée plus haut).
-- Les structures (IUT, DSI) et les rôles utilisés existent déjà.
 
-- 1. Création des personnels
INSERT INTO personnel (nom, prenom, statut, identifiant_scientifique, structure_id)
SELECT 'Lefebvre', 'Claire', 'Ingénieure système', 'CLEFEBVRE2026', id
FROM structure WHERE code = 'DSI';
 
INSERT INTO personnel (nom, prenom, statut, identifiant_scientifique, structure_id)
SELECT 'Benali', 'Karim', 'Administrateur applicatif', 'KBENALI2026', id
FROM structure WHERE code = 'DSI';
 
INSERT INTO personnel (nom, prenom, statut, identifiant_scientifique, structure_id)
SELECT 'Leroy', 'Marie', 'Maître de conférences', 'MLEROY2026', id
FROM structure WHERE code = 'IUT';
 
INSERT INTO personnel (nom, prenom, statut, identifiant_scientifique, structure_id)
SELECT 'Petit', 'Thomas', 'Enseignant-chercheur', 'TPETIT2026', id
FROM structure WHERE code = 'IUT';
 
INSERT INTO personnel (nom, prenom, statut, identifiant_scientifique, structure_id)
SELECT 'Dubois', 'Camille', 'Doctorante', 'CDUBOIS2026', id
FROM structure WHERE code = 'IUT';
 
INSERT INTO personnel (nom, prenom, statut, identifiant_scientifique, structure_id)
SELECT 'Moreau', 'Lucas', 'Technicien', 'LMOREAU2026', id
FROM structure WHERE code = 'DSI';
 
INSERT INTO personnel (nom, prenom, statut, identifiant_scientifique, structure_id)
SELECT 'Haddad', 'Nadia', 'Maîtresse de conférences', 'NHADDAD2026', id
FROM structure WHERE code = 'IUT';
 
-- 2. Rôle ADMIN_FONCTIONNEL pour Claire et Karim
INSERT INTO personnel_role (personnel_id, role_id)
SELECT p.id, r.id
FROM personnel p, role r
WHERE p.identifiant_scientifique IN ('CLEFEBVRE2026', 'KBENALI2026')
  AND r.code = 'ADMIN_FONCTIONNEL';
 
-- 3. Rôle PERSONNEL pour les profils standards
INSERT INTO personnel_role (personnel_id, role_id)
SELECT p.id, r.id
FROM personnel p, role r
WHERE p.identifiant_scientifique IN ('MLEROY2026', 'TPETIT2026', 'CDUBOIS2026', 'LMOREAU2026', 'NHADDAD2026')
  AND r.code = 'PERSONNEL';
 
-- 4. Mot de passe par défaut (comme pour les comptes précédents)
UPDATE personnel
SET password_hash = '1234'
WHERE identifiant_scientifique IN ('CLEFEBVRE2026', 'KBENALI2026', 'MLEROY2026', 'TPETIT2026', 'CDUBOIS2026', 'LMOREAU2026', 'NHADDAD2026');
 
--rollback DELETE FROM personnel_role WHERE personnel_id IN (SELECT id FROM personnel WHERE identifiant_scientifique IN ('CLEFEBVRE2026', 'KBENALI2026', 'MLEROY2026', 'TPETIT2026', 'CDUBOIS2026', 'LMOREAU2026', 'NHADDAD2026'));
--rollback DELETE FROM personnel WHERE identifiant_scientifique IN ('CLEFEBVRE2026', 'KBENALI2026', 'MLEROY2026', 'TPETIT2026', 'CDUBOIS2026', 'LMOREAU2026', 'NHADDAD2026');


--changeset yessim17:insert-donnees-gabarit
-- Gabarits de déclaration d'activité. Les statuts requis correspondent aux statuts des profils de test.
 
INSERT INTO gabarit (code, libelle, statut_requis, description) VALUES
('ENS_COURS_MCF', 'Cours magistraux et TD', 'Maître de conférences', 'Déclaration des heures de cours magistraux et de travaux dirigés'),
('ENS_COURS_MCF_F', 'Cours magistraux et TD', 'Maîtresse de conférences', 'Déclaration des heures de cours magistraux et de travaux dirigés'),
('ENS_RECH_PUBLI', 'Enseignement et publications', 'Enseignant-chercheur', 'Déclaration conjointe des heures d''enseignement et des travaux de recherche publiés'),
('RECH_DOCTORAT', 'Travaux de thèse', 'Doctorante', 'Suivi des travaux de recherche menés dans le cadre du doctorat'),
('ADM_RH_GESTION', 'Gestion administrative RH', 'Gestionnaire RH', 'Suivi des dossiers du personnel, des campagnes et des validations'),
('ADM_SYSTEME', 'Administration système', 'Ingénieure système', 'Maintenance, supervision et évolution de l''infrastructure'),
('ADM_APPLICATIF', 'Administration applicative', 'Administrateur applicatif', 'Paramétrage, support et suivi des applications métier'),
('SUPPORT_TECH', 'Support technique', 'Technicien', 'Interventions et assistance technique auprès des utilisateurs'),
('DEV_PROJET', 'Développement de projet', 'Etudiant/Dev', 'Développement logiciel dans le cadre d''un projet pédagogique');
 
--rollback DELETE FROM gabarit WHERE code IN ('ENS_COURS_MCF', 'ENS_COURS_MCF_F', 'ENS_RECH_PUBLI', 'RECH_DOCTORAT', 'ADM_RH_GESTION', 'ADM_SYSTEME', 'ADM_APPLICATIF', 'SUPPORT_TECH', 'DEV_PROJET');