-- Datos iniciales SOLO para desarrollo.
-- Usuario: admin / contrasena: 123456  (cambiala en cualquier entorno real)
INSERT INTO users (id, username, email, password_hash, token_version)
VALUES (1, 'admin', 'admin@templimail.com', '$2y$10$5fxEq6yjWQuQ/g/ClJwbb.6B/qFWj92m9.XRNcFRUQ1Atde/IZbFa', 1)
ON DUPLICATE KEY UPDATE username = username;
