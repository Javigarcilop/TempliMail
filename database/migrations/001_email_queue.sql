-- Migracion 001: cola de envio, bajas de contactos, historial unificado y
-- proteccion de login. Para bases de datos creadas ANTES de esta version.
-- (Una base nueva ya trae todo esto desde schema.sql.)
--
--   docker compose exec -T db mysql -uroot templimail_db < database/migrations/001_email_queue.sql

SET time_zone = '+00:00';

-- Bajas de contactos
ALTER TABLE contacts
  ADD COLUMN unsubscribed_at TIMESTAMP NULL DEFAULT NULL AFTER position;

-- Campanas: nombre, tipo, bloqueo de procesamiento y nuevo estado "cancelled"
ALTER TABLE email_campaigns
  ADD COLUMN name VARCHAR(255) NULL AFTER template_id,
  ADD COLUMN type ENUM('mass','single') NOT NULL DEFAULT 'mass' AFTER name,
  ADD COLUMN processing_started_at DATETIME NULL AFTER scheduled_at,
  MODIFY COLUMN status ENUM('scheduled','processing','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  ADD KEY idx_campaigns_due (status, scheduled_at);

ALTER TABLE email_campaigns
  DROP FOREIGN KEY fk_campaigns_user,
  DROP FOREIGN KEY fk_campaigns_template;
ALTER TABLE email_campaigns
  ADD CONSTRAINT fk_campaigns_user     FOREIGN KEY (user_id)     REFERENCES users (id)     ON DELETE CASCADE,
  ADD CONSTRAINT fk_campaigns_template FOREIGN KEY (template_id) REFERENCES templates (id) ON DELETE SET NULL;

-- Entregas: email del destinatario congelado, contacto opcional, estado "skipped"
ALTER TABLE email_deliveries
  DROP FOREIGN KEY fk_deliveries_campaign,
  DROP FOREIGN KEY fk_deliveries_contact;
ALTER TABLE email_deliveries
  MODIFY COLUMN contact_id INT NULL,
  ADD COLUMN recipient_email VARCHAR(255) NULL AFTER contact_id,
  MODIFY COLUMN status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending';

UPDATE email_deliveries ed
JOIN contacts c ON c.id = ed.contact_id
SET ed.recipient_email = c.email
WHERE ed.recipient_email IS NULL;

UPDATE email_deliveries SET recipient_email = '' WHERE recipient_email IS NULL;

ALTER TABLE email_deliveries
  MODIFY COLUMN recipient_email VARCHAR(255) NOT NULL,
  ADD CONSTRAINT fk_deliveries_campaign FOREIGN KEY (campaign_id) REFERENCES email_campaigns (id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_deliveries_contact  FOREIGN KEY (contact_id)  REFERENCES contacts (id)        ON DELETE SET NULL;

-- Proteccion contra fuerza bruta en el login
CREATE TABLE IF NOT EXISTS login_attempts (
  id            INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(100) NOT NULL,
  ip            VARCHAR(45)  NOT NULL,
  attempted_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attempts_username (username, attempted_at),
  KEY idx_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
