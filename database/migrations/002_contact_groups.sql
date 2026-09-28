-- Migracion 002: grupos (listas) de contactos.
--   docker compose exec -T db mysql -uroot templimail_db < database/migrations/002_contact_groups.sql

CREATE TABLE IF NOT EXISTS contact_groups (
  id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id     INT          NOT NULL,
  name        VARCHAR(100) NOT NULL,
  created_at  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_group_user_name (user_id, name),
  CONSTRAINT fk_groups_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_group_members (
  group_id    INT NOT NULL,
  contact_id  INT NOT NULL,
  PRIMARY KEY (group_id, contact_id),
  KEY idx_members_contact (contact_id),
  CONSTRAINT fk_members_group   FOREIGN KEY (group_id)   REFERENCES contact_groups (id) ON DELETE CASCADE,
  CONSTRAINT fk_members_contact FOREIGN KEY (contact_id) REFERENCES contacts (id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
