-- liquibase formatted sql

--changeset yessim17:create-table-relance
CREATE TABLE relance (
    id SERIAL PRIMARY KEY,
    personnel_id INT NOT NULL REFERENCES personnel(id) ON DELETE CASCADE,
    campagne_id INT REFERENCES campagne(id) ON DELETE SET NULL,
    type_relance VARCHAR(30) NOT NULL
        CONSTRAINT chk_relance_type CHECK (type_relance IN ('INACTIVITE', 'RAPPEL_ECHEANCE')),
    envoyee_par INT REFERENCES personnel(id) ON DELETE SET NULL,
    envoyee_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
);
CREATE INDEX idx_relance_personnel_date ON relance(personnel_id, envoyee_le);
--rollback DROP INDEX idx_relance_personnel_date;
--rollback DROP TABLE relance;

--changeset yessim17:insert-permission-relance
INSERT INTO permission (code, libelle) VALUES ('RELANCE_SEND', 'Envoyer des relances et des rappels d''échéance');
INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id
FROM role r, permission p
WHERE r.code = 'ADMIN_FONCTIONNEL' AND p.code = 'RELANCE_SEND';
--rollback DELETE FROM role_permission WHERE permission_id IN (SELECT id FROM permission WHERE code = 'RELANCE_SEND');
--rollback DELETE FROM permission WHERE code = 'RELANCE_SEND';
