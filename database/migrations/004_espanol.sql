-- Migracion 004: renombra tablas y columnas al espanol. No borra ni
-- transforma ningun dato, solo cambia los nombres.
--   docker compose exec -T db mysql -uroot templimail_db < database/migrations/004_espanol.sql

SET time_zone = '+00:00';

-- usuarios
ALTER TABLE users
  CHANGE COLUMN username       nombre_usuario  VARCHAR(100) NOT NULL,
  CHANGE COLUMN email          correo          VARCHAR(255) NOT NULL,
  CHANGE COLUMN password_hash  hash_contrasena VARCHAR(255) NOT NULL,
  CHANGE COLUMN token_version  version_token   INT NOT NULL DEFAULT 1,
  CHANGE COLUMN created_at     creado_en       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CHANGE COLUMN updated_at     actualizado_en  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHANGE COLUMN deleted_at     eliminado_en    TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE users RENAME INDEX username TO nombre_usuario;
ALTER TABLE users RENAME INDEX email TO correo;
RENAME TABLE users TO usuarios;

-- intentos_login
ALTER TABLE login_attempts
  CHANGE COLUMN username      nombre_usuario VARCHAR(100) NOT NULL,
  CHANGE COLUMN attempted_at  intentado_en   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE login_attempts RENAME INDEX idx_attempts_username TO idx_intentos_usuario;
ALTER TABLE login_attempts RENAME INDEX idx_attempts_ip TO idx_intentos_ip;
RENAME TABLE login_attempts TO intentos_login;

-- contactos
ALTER TABLE contacts DROP FOREIGN KEY fk_contacts_user;
ALTER TABLE contacts
  CHANGE COLUMN user_id          usuario_id   INT NOT NULL,
  CHANGE COLUMN first_name       nombre       VARCHAR(100) DEFAULT NULL,
  CHANGE COLUMN last_name        apellidos    VARCHAR(100) DEFAULT NULL,
  CHANGE COLUMN email            correo       VARCHAR(255) NOT NULL,
  CHANGE COLUMN phone            telefono     VARCHAR(50)  DEFAULT NULL,
  CHANGE COLUMN company          empresa      VARCHAR(150) DEFAULT NULL,
  CHANGE COLUMN position         cargo        VARCHAR(150) DEFAULT NULL,
  CHANGE COLUMN unsubscribed_at  baja_en      TIMESTAMP NULL DEFAULT NULL,
  CHANGE COLUMN created_at       creado_en    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CHANGE COLUMN updated_at       actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHANGE COLUMN deleted_at       eliminado_en TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE contacts RENAME INDEX idx_contacts_user TO idx_contactos_usuario;
RENAME TABLE contacts TO contactos;
ALTER TABLE contactos
  ADD CONSTRAINT fk_contactos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE;

-- plantillas
ALTER TABLE templates DROP FOREIGN KEY fk_templates_user;
ALTER TABLE templates
  CHANGE COLUMN user_id       usuario_id     INT NOT NULL,
  CHANGE COLUMN name          nombre         VARCHAR(255) NOT NULL,
  CHANGE COLUMN subject       asunto         VARCHAR(255) NOT NULL,
  CHANGE COLUMN content_html  contenido_html LONGTEXT NOT NULL,
  CHANGE COLUMN attachment    adjunto        VARCHAR(255) DEFAULT NULL,
  CHANGE COLUMN created_at    creado_en      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CHANGE COLUMN updated_at    actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHANGE COLUMN deleted_at    eliminado_en   TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE templates RENAME INDEX idx_templates_user TO idx_plantillas_usuario;
RENAME TABLE templates TO plantillas;
ALTER TABLE plantillas
  ADD CONSTRAINT fk_plantillas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE;

-- campanas_correo
ALTER TABLE email_campaigns
  DROP FOREIGN KEY fk_campaigns_user,
  DROP FOREIGN KEY fk_campaigns_template;
ALTER TABLE email_campaigns
  CHANGE COLUMN user_id                usuario_id       INT NOT NULL,
  CHANGE COLUMN template_id            plantilla_id     INT NULL,
  CHANGE COLUMN name                   nombre           VARCHAR(255) NULL,
  CHANGE COLUMN type                   tipo             ENUM('mass','single') NOT NULL DEFAULT 'mass',
  CHANGE COLUMN subject                asunto           VARCHAR(255) NOT NULL,
  CHANGE COLUMN content_html           contenido_html   LONGTEXT NOT NULL,
  CHANGE COLUMN status                 estado           ENUM('scheduled','processing','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  CHANGE COLUMN scheduled_at           programado_en    DATETIME NULL,
  CHANGE COLUMN processing_started_at  procesamiento_iniciado_en DATETIME NULL,
  CHANGE COLUMN created_at             creado_en        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CHANGE COLUMN updated_at             actualizado_en   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE email_campaigns RENAME INDEX idx_campaigns_user TO idx_campanas_usuario;
ALTER TABLE email_campaigns RENAME INDEX idx_campaigns_template TO idx_campanas_plantilla;
ALTER TABLE email_campaigns RENAME INDEX idx_campaigns_due TO idx_campanas_pendientes;
RENAME TABLE email_campaigns TO campanas_correo;
ALTER TABLE campanas_correo
  ADD CONSTRAINT fk_campanas_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios (id)   ON DELETE CASCADE,
  ADD CONSTRAINT fk_campanas_plantilla FOREIGN KEY (plantilla_id) REFERENCES plantillas (id) ON DELETE SET NULL;

-- entregas_correo
ALTER TABLE email_deliveries
  DROP FOREIGN KEY fk_deliveries_campaign,
  DROP FOREIGN KEY fk_deliveries_contact;
ALTER TABLE email_deliveries
  CHANGE COLUMN campaign_id      campana_id          INT NOT NULL,
  CHANGE COLUMN contact_id       contacto_id         INT NULL,
  CHANGE COLUMN recipient_email  correo_destinatario VARCHAR(255) NOT NULL,
  CHANGE COLUMN status           estado              ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  CHANGE COLUMN sent_at          enviado_en          DATETIME NULL,
  CHANGE COLUMN opened_at        abierto_en          DATETIME NULL,
  CHANGE COLUMN open_count       aperturas           INT NOT NULL DEFAULT 0,
  CHANGE COLUMN clicked_at       clic_en             DATETIME NULL,
  CHANGE COLUMN click_count      clics               INT NOT NULL DEFAULT 0,
  CHANGE COLUMN error_message    mensaje_error       TEXT NULL,
  CHANGE COLUMN retry_count      reintentos          INT NOT NULL DEFAULT 0,
  CHANGE COLUMN created_at       creado_en           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE email_deliveries RENAME INDEX idx_deliveries_campaign TO idx_entregas_campana;
ALTER TABLE email_deliveries RENAME INDEX idx_deliveries_contact TO idx_entregas_contacto;
RENAME TABLE email_deliveries TO entregas_correo;
ALTER TABLE entregas_correo
  ADD CONSTRAINT fk_entregas_campana  FOREIGN KEY (campana_id)  REFERENCES campanas_correo (id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_entregas_contacto FOREIGN KEY (contacto_id) REFERENCES contactos (id)       ON DELETE SET NULL;

-- grupos_contacto
ALTER TABLE contact_groups DROP FOREIGN KEY fk_groups_user;
ALTER TABLE contact_groups
  CHANGE COLUMN user_id     usuario_id INT NOT NULL,
  CHANGE COLUMN name        nombre     VARCHAR(100) NOT NULL,
  CHANGE COLUMN created_at  creado_en  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE contact_groups RENAME INDEX uniq_group_user_name TO uniq_grupo_usuario_nombre;
RENAME TABLE contact_groups TO grupos_contacto;
ALTER TABLE grupos_contacto
  ADD CONSTRAINT fk_grupos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE;

-- miembros_grupo_contacto
ALTER TABLE contact_group_members
  DROP FOREIGN KEY fk_members_group,
  DROP FOREIGN KEY fk_members_contact;
ALTER TABLE contact_group_members
  CHANGE COLUMN group_id    grupo_id    INT NOT NULL,
  CHANGE COLUMN contact_id  contacto_id INT NOT NULL;
ALTER TABLE contact_group_members RENAME INDEX idx_members_contact TO idx_miembros_contacto;
RENAME TABLE contact_group_members TO miembros_grupo_contacto;
ALTER TABLE miembros_grupo_contacto
  ADD CONSTRAINT fk_miembros_grupo    FOREIGN KEY (grupo_id)    REFERENCES grupos_contacto (id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_miembros_contacto FOREIGN KEY (contacto_id) REFERENCES contactos (id)       ON DELETE CASCADE;
