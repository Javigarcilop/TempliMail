-- =============================================================
-- TempliMail - Esquema completo (MySQL 8)
-- Se ejecuta automaticamente en el primer arranque del contenedor
-- (docker-entrypoint-initdb.d). Todas las fechas se guardan en UTC.
-- =============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS usuarios (
  id               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre_usuario   VARCHAR(100) NOT NULL,
  correo           VARCHAR(255) NOT NULL,
  hash_contrasena  VARCHAR(255) NOT NULL,
  version_token    INT          NOT NULL DEFAULT 1,
  creado_en        TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  eliminado_en     TIMESTAMP    NULL DEFAULT NULL,
  UNIQUE KEY nombre_usuario (nombre_usuario),
  UNIQUE KEY correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intentos_login (
  id             INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre_usuario VARCHAR(100) NOT NULL,
  ip             VARCHAR(45)  NOT NULL,
  intentado_en   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_intentos_usuario (nombre_usuario, intentado_en),
  KEY idx_intentos_ip (ip, intentado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contactos (
  id           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id   INT          NOT NULL,
  nombre       VARCHAR(100) DEFAULT NULL,
  apellidos    VARCHAR(100) DEFAULT NULL,
  correo       VARCHAR(255) NOT NULL,
  telefono     VARCHAR(50)  DEFAULT NULL,
  empresa      VARCHAR(150) DEFAULT NULL,
  cargo        VARCHAR(150) DEFAULT NULL,
  baja_en      TIMESTAMP    NULL DEFAULT NULL,
  creado_en    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP  NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  eliminado_en TIMESTAMP    NULL DEFAULT NULL,
  KEY idx_contactos_usuario (usuario_id),
  CONSTRAINT fk_contactos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plantillas (
  id             INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id     INT          NOT NULL,
  nombre         VARCHAR(255) NOT NULL,
  asunto         VARCHAR(255) NOT NULL,
  contenido_html LONGTEXT     NOT NULL,
  adjunto        VARCHAR(255) DEFAULT NULL,
  creado_en      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  eliminado_en   TIMESTAMP    NULL DEFAULT NULL,
  KEY idx_plantillas_usuario (usuario_id),
  CONSTRAINT fk_plantillas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Una campana = un envio (masivo o individual) con el contenido "congelado".
-- estado: scheduled (en cola / programada) -> processing -> completed | cancelled
CREATE TABLE IF NOT EXISTS campanas_correo (
  id                      INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id              INT          NOT NULL,
  plantilla_id            INT          NULL,
  nombre                  VARCHAR(255) NULL,
  tipo                    ENUM('mass','single') NOT NULL DEFAULT 'mass',
  asunto                  VARCHAR(255) NOT NULL,
  contenido_html          LONGTEXT     NOT NULL,
  estado                  ENUM('scheduled','processing','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  programado_en           DATETIME     NULL,
  procesamiento_iniciado_en DATETIME   NULL,
  creado_en               TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en          TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_campanas_usuario (usuario_id),
  KEY idx_campanas_plantilla (plantilla_id),
  KEY idx_campanas_pendientes (estado, programado_en),
  CONSTRAINT fk_campanas_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios (id)   ON DELETE CASCADE,
  CONSTRAINT fk_campanas_plantilla FOREIGN KEY (plantilla_id) REFERENCES plantillas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Una entrega = un destinatario de una campana.
-- contacto_id es NULL en envios individuales o si el contacto se elimina.
CREATE TABLE IF NOT EXISTS entregas_correo (
  id                INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campana_id        INT          NOT NULL,
  contacto_id       INT          NULL,
  correo_destinatario VARCHAR(255) NOT NULL,
  estado            ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  enviado_en        DATETIME     NULL,
  abierto_en        DATETIME     NULL,
  aperturas         INT          NOT NULL DEFAULT 0,
  clic_en           DATETIME     NULL,
  clics             INT          NOT NULL DEFAULT 0,
  mensaje_error     TEXT         NULL,
  reintentos        INT          NOT NULL DEFAULT 0,
  creado_en         TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_entregas_campana (campana_id, estado),
  KEY idx_entregas_contacto (contacto_id),
  CONSTRAINT fk_entregas_campana  FOREIGN KEY (campana_id)  REFERENCES campanas_correo (id) ON DELETE CASCADE,
  CONSTRAINT fk_entregas_contacto FOREIGN KEY (contacto_id) REFERENCES contactos (id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Grupos (listas) de contactos: un contacto puede estar en varios grupos.
CREATE TABLE IF NOT EXISTS grupos_contacto (
  id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT          NOT NULL,
  nombre      VARCHAR(100) NOT NULL,
  creado_en   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_grupo_usuario_nombre (usuario_id, nombre),
  CONSTRAINT fk_grupos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS miembros_grupo_contacto (
  grupo_id    INT NOT NULL,
  contacto_id INT NOT NULL,
  PRIMARY KEY (grupo_id, contacto_id),
  KEY idx_miembros_contacto (contacto_id),
  CONSTRAINT fk_miembros_grupo    FOREIGN KEY (grupo_id)    REFERENCES grupos_contacto (id) ON DELETE CASCADE,
  CONSTRAINT fk_miembros_contacto FOREIGN KEY (contacto_id) REFERENCES contactos (id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
