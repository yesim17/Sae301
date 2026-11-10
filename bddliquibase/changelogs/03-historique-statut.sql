-- liquibase formatted sql

--changeset yessim17:creer-table-historique-statut-personnel
-- Historique des statuts d'un profil : le statut courant ne suffit pas pour choisir un gabarit
-- rétroactif sur une période antérieure au dernier changement de statut.
CREATE TABLE IF NOT EXISTS personnel_statut_historique (
    id SERIAL PRIMARY KEY,
    personnel_id INT NOT NULL REFERENCES personnel(id) ON DELETE CASCADE,
    statut VARCHAR(50) NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_historique_statut_dates CHECK (date_fin IS NULL OR date_fin >= date_debut)
);
CREATE INDEX idx_historique_statut_personnel
    ON personnel_statut_historique(personnel_id, date_debut, date_fin);
--rollback DROP INDEX idx_historique_statut_personnel;
--rollback DROP TABLE personnel_statut_historique;

--changeset yessim17:initialiser-historique-statut
-- Les profils existants n'ont pas d'historique : on ouvre une période avec leur statut courant.
INSERT INTO personnel_statut_historique (personnel_id, statut, date_debut)
SELECT p.id, p.statut, COALESCE(p.cree_le::date, CURRENT_DATE)
FROM personnel p
WHERE NOT EXISTS (
    SELECT 1 FROM personnel_statut_historique h WHERE h.personnel_id = p.id
);
--rollback DELETE FROM personnel_statut_historique;
