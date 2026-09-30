-- Datos iniciales SOLO para desarrollo.
-- Usuario: admin / contrasena: 123456  (cambiala en cualquier entorno real)
INSERT INTO usuarios (id, nombre_usuario, correo, hash_contrasena, version_token)
VALUES (1, 'admin', 'admin@templimail.com', '$2y$10$5fxEq6yjWQuQ/g/ClJwbb.6B/qFWj92m9.XRNcFRUQ1Atde/IZbFa', 1)
ON DUPLICATE KEY UPDATE nombre_usuario = nombre_usuario;
