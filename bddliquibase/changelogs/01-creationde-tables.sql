-- liquibase formatted sql

--changeset yessim17:create-table-activite
CREATE TABLE activite (
    id SERIAL PRIMARY KEY,
    personnel_id INT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    date_activite DATE NOT NULL,
    est_collective BOOLEAN DEFAULT FALSE NOT NULL,
    statut VARCHAR(50) DEFAULT 'brouillon' NOT NULL,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    mis_a_jour_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_activite_personnel_id ON activite(personnel_id);
CREATE INDEX idx_activite_date ON activite(date_activite);
CREATE INDEX idx_activite_est_collective ON activite(est_collective);
CREATE INDEX idx_activite_titre_recherche ON activite USING gin(to_tsvector('french', titre));
--rollback DROP INDEX idx_activite_titre_recherche;
--rollback DROP INDEX idx_activite_est_collective;
--rollback DROP INDEX idx_activite_date;
--rollback DROP INDEX idx_activite_personnel_id;
--rollback DROP TABLE activite;

--changeset yessim17:create-table-structure-et-type-activite
CREATE TABLE structure (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    nom VARCHAR(255) NOT NULL,
    est_actif BOOLEAN DEFAULT TRUE NOT NULL,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE type_activite (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    libelle VARCHAR(255) NOT NULL,
    description TEXT,
    est_actif BOOLEAN DEFAULT TRUE NOT NULL
);
--rollback DROP TABLE type_activite;
--rollback DROP TABLE structure;

--changeset yessim17:create-table-personnel
CREATE TABLE personnel (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    nom_usage VARCHAR(100),
    prenom VARCHAR(100) NOT NULL,
    statut VARCHAR(50) NOT NULL,
    identifiant_scientifique VARCHAR(100) UNIQUE,
    est_actif BOOLEAN DEFAULT TRUE NOT NULL,
    structure_id INT REFERENCES structure(id) ON DELETE SET NULL,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    mis_a_jour_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_personnel_nom_prenom ON personnel(nom, prenom);
CREATE INDEX idx_personnel_est_actif ON personnel(est_actif);
--rollback DROP INDEX idx_personnel_est_actif;
--rollback DROP INDEX idx_personnel_nom_prenom;
--rollback DROP TABLE personnel;

--changeset yessim17:link-activite-type-et-structure
ALTER TABLE activite ADD COLUMN type_activite_id INT REFERENCES type_activite(id);
ALTER TABLE activite ADD COLUMN structure_id INT REFERENCES structure(id) ON DELETE SET NULL;
CREATE INDEX idx_activite_type_activite ON activite(type_activite_id);
--rollback DROP INDEX idx_activite_type_activite;
--rollback ALTER TABLE activite DROP COLUMN structure_id;
--rollback ALTER TABLE activite DROP COLUMN type_activite_id;

--changeset yessim17:create-table-activite-participant
CREATE TABLE activite_participant (
    id SERIAL PRIMARY KEY,
    activite_id INT NOT NULL REFERENCES activite(id) ON DELETE CASCADE,
    personnel_id INT NOT NULL REFERENCES personnel(id) ON DELETE CASCADE,
    role VARCHAR(100) NOT NULL,
    est_confirme BOOLEAN DEFAULT FALSE NOT NULL,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_activite_personnel UNIQUE (activite_id, personnel_id)
);
CREATE INDEX idx_participant_activite ON activite_participant(activite_id);
CREATE INDEX idx_participant_personnel ON activite_participant(personnel_id);
--rollback DROP INDEX idx_participant_personnel;
--rollback DROP INDEX idx_participant_activite;
--rollback DROP TABLE activite_participant;

--changeset yessim17:update-activite-participant-and-create-notification
ALTER TABLE activite_participant
    ADD COLUMN statut VARCHAR(20) DEFAULT 'EN_ATTENTE' NOT NULL 
    CONSTRAINT chk_participant_statut CHECK (statut IN ('EN_ATTENTE', 'CONFIRME', 'REFUSE'));
CREATE TABLE notification (
    id SERIAL PRIMARY KEY,
    personnel_id INT NOT NULL REFERENCES personnel(id) ON DELETE CASCADE,
    titre VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    est_lue BOOLEAN DEFAULT FALSE NOT NULL,
    type_notification VARCHAR(50) NOT NULL,
    activite_participant_id INT REFERENCES activite_participant(id) ON DELETE CASCADE,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_notification_personnel_lue ON notification(personnel_id, est_lue);
CREATE INDEX idx_participant_statut ON activite_participant(statut);
--rollback DROP INDEX idx_participant_statut;
--rollback DROP INDEX idx_notification_personnel_lue;
--rollback DROP TABLE notification;
--rollback ALTER TABLE activite_participant DROP COLUMN statut;

--changeset yessim17:create-table-rbac-and-delegation
CREATE TABLE role (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL
);
CREATE TABLE permission (
    id SERIAL PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    libelle VARCHAR(255) NOT NULL
);
CREATE TABLE role_permission (
    role_id INT NOT NULL REFERENCES role(id) ON DELETE CASCADE,
    permission_id INT NOT NULL REFERENCES permission(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);
CREATE TABLE personnel_role (
    personnel_id INT NOT NULL REFERENCES personnel(id) ON DELETE CASCADE,
    role_id INT NOT NULL REFERENCES role(id) ON DELETE CASCADE,
    PRIMARY KEY (personnel_id, role_id)
);
CREATE TABLE delegation_droit (
    id SERIAL PRIMARY KEY,
    donneur_ordre_id INT NOT NULL REFERENCES personnel(id) ON DELETE CASCADE,
    delegataire_id INT NOT NULL REFERENCES personnel(id) ON DELETE CASCADE,
    role_id INT NOT NULL REFERENCES role(id) ON DELETE CASCADE,
    date_debut TIMESTAMP NOT NULL,
    date_fin TIMESTAMP NOT NULL,
    est_actif BOOLEAN DEFAULT TRUE NOT NULL,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_delegation_dates CHECK (date_fin > date_debut),
    CONSTRAINT chk_delegation_auto_ref CHECK (donneur_ordre_id <> delegataire_id)
);
CREATE INDEX idx_delegation_periode ON delegation_droit(date_debut, date_fin, est_actif);
CREATE INDEX idx_delegation_delegataire ON delegation_droit(delegataire_id);
--rollback DROP INDEX idx_delegation_delegataire;
--rollback DROP INDEX idx_delegation_periode;
--rollback DROP TABLE delegation_droit;
--rollback DROP TABLE personnel_role;
--rollback DROP TABLE role_permission;
--rollback DROP TABLE permission;
--rollback DROP TABLE role;

--changeset yessim17:create-table-campagne
CREATE TABLE campagne (
    id SERIAL PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    est_actif BOOLEAN DEFAULT TRUE NOT NULL,
    cree_par INT REFERENCES personnel(id) ON DELETE SET NULL,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    mis_a_jour_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_campagne_dates CHECK (date_fin >= date_debut)
);
CREATE INDEX idx_campagne_dates ON campagne(date_debut, date_fin);
--rollback DROP INDEX idx_campagne_dates;
--rollback DROP TABLE campagne;

--changeset yessim17:alter-table-campagne-ajout-colonnes
ALTER TABLE campagne 
ADD COLUMN periode_reference VARCHAR(50) NOT NULL,
ADD COLUMN nature VARCHAR(100) NOT NULL;

--changeset yessim17:ajouter-colonne-password-simple
-- Ajout de la colonne avec un mot de passe par défaut à '1234' pour tous
ALTER TABLE personnel ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) DEFAULT '1234';
--rollback ALTER TABLE personnel DROP COLUMN IF EXISTS password_hash;