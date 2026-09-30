-- =============================================================
-- TempliMail - Esquema completo (MySQL 8)
-- Se ejecuta automaticamente en el primer arranque del contenedor
-- (docker-entrypoint-initdb.d). Todas las fechas se guardan en UTC.
-- =============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
  id             INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(100) NOT NULL,
  email          VARCHAR(255) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  token_version  INT          NOT NULL DEFAULT 1,
  created_at     TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at     TIMESTAMP    NULL DEFAULT NULL,
  UNIQUE KEY username (username),
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
  id            INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(100) NOT NULL,
  ip            VARCHAR(45)  NOT NULL,
  attempted_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attempts_username (username, attempted_at),
  KEY idx_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contacts (
  id               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id          INT          NOT NULL,
  first_name       VARCHAR(100) DEFAULT NULL,
  last_name        VARCHAR(100) DEFAULT NULL,
  email            VARCHAR(255) NOT NULL,
  phone            VARCHAR(50)  DEFAULT NULL,
  company          VARCHAR(150) DEFAULT NULL,
  position         VARCHAR(150) DEFAULT NULL,
  unsubscribed_at  TIMESTAMP    NULL DEFAULT NULL,
  created_at       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at       TIMESTAMP    NULL DEFAULT NULL,
  KEY idx_contacts_user (user_id),
  CONSTRAINT fk_contacts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS templates (
  id            INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id       INT          NOT NULL,
  name          VARCHAR(255) NOT NULL,
  subject       VARCHAR(255) NOT NULL,
  content_html  LONGTEXT     NOT NULL,
  attachment    VARCHAR(255) DEFAULT NULL,
  created_at    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at    TIMESTAMP    NULL DEFAULT NULL,
  KEY idx_templates_user (user_id),
  CONSTRAINT fk_templates_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Una campana = un envio (masivo o individual) con el contenido "congelado".
-- status: scheduled (en cola / programada) -> processing -> completed | cancelled
CREATE TABLE IF NOT EXISTS email_campaigns (
  id                     INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id                INT          NOT NULL,
  template_id            INT          NULL,
  name                   VARCHAR(255) NULL,
  type                   ENUM('mass','single') NOT NULL DEFAULT 'mass',
  subject                VARCHAR(255) NOT NULL,
  content_html           LONGTEXT     NOT NULL,
  status                 ENUM('scheduled','processing','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  scheduled_at           DATETIME     NULL,
  processing_started_at  DATETIME     NULL,
  created_at             TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_campaigns_user (user_id),
  KEY idx_campaigns_template (template_id),
  KEY idx_campaigns_due (status, scheduled_at),
  CONSTRAINT fk_campaigns_user     FOREIGN KEY (user_id)     REFERENCES users (id)     ON DELETE CASCADE,
  CONSTRAINT fk_campaigns_template FOREIGN KEY (template_id) REFERENCES templates (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Una entrega = un destinatario de una campana.
-- contact_id es NULL en envios individuales o si el contacto se elimina.
CREATE TABLE IF NOT EXISTS email_deliveries (
  id               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campaign_id      INT          NOT NULL,
  contact_id       INT          NULL,
  recipient_email  VARCHAR(255) NOT NULL,
  status           ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  sent_at          DATETIME     NULL,
  opened_at        DATETIME     NULL,
  open_count       INT          NOT NULL DEFAULT 0,
  clicked_at       DATETIME     NULL,
  click_count      INT          NOT NULL DEFAULT 0,
  error_message    TEXT         NULL,
  retry_count      INT          NOT NULL DEFAULT 0,
  created_at       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_deliveries_campaign (campaign_id, status),
  KEY idx_deliveries_contact (contact_id),
  CONSTRAINT fk_deliveries_campaign FOREIGN KEY (campaign_id) REFERENCES email_campaigns (id) ON DELETE CASCADE,
  CONSTRAINT fk_deliveries_contact  FOREIGN KEY (contact_id)  REFERENCES contacts (id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Grupos (listas) de contactos: un contacto puede estar en varios grupos.
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
