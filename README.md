# 📬 TempliMail

> **Demo en vivo:** https://javigarcilop.github.io/TempliMail/ (datos simulados en el navegador, sin backend; no envía correos reales).

**TempliMail es una aplicación web de email marketing.** Permite gestionar contactos, crear plantillas visuales con variables de personalización y enviar campañas masivas (inmediatas o programadas) con seguimiento del progreso, reintentos y baja de suscriptores.

- **Frontend**: Angular 19 (standalone components, TinyMCE)
- **Backend**: PHP 8.2 puro (sin frameworks), PHPMailer, PHPWord, PDFParser
- **Base de datos**: MySQL 8
- **Infraestructura**: Docker Compose (API, worker de envío, MySQL, phpMyAdmin, Mailpit opcional)

---

## ✅ Funcionalidades

- Registro / login con JWT, cierre de sesión que invalida el token y límite de intentos fallidos
- Contactos: CRUD, búsqueda, baja y reactivación
- Plantillas: editor visual (TinyMCE), importación desde `.docx` / `.pdf`, vista previa y correo de prueba
- **Variables de personalización**: `{{first_name}}`, `{{last_name}}`, `{{full_name}}`, `{{company}}`, `{{position}}`, `{{email}}`, con valor por defecto (`{{first_name|amigo}}`)
- **Campañas masivas en cola**: las envía un worker en segundo plano (no dependen del navegador), con conexión SMTP reutilizada, pausa entre envíos y hasta 3 intentos por destinatario
- Envío programado (las horas se guardan en UTC) y cancelación de campañas programadas
- Historial con progreso en tiempo real, detalle por destinatario y **reintento de fallidos**
- **Baja de suscriptores**: enlace firmado en cada correo, cabeceras `List-Unsubscribe` (baja *one-click*) y exclusión automática de los dados de baja
- Envío individual (queda registrado en el historial) y sugerencia de asuntos con IA (Groq)

---

## 🚀 Puesta en marcha (Docker)

Requisitos: Docker Desktop y Node.js 20+.

```bash
# 1. Configuración (rellena SMTP, JWT_SECRET y, opcionalmente, GROQ_API_KEY)
cp backend/.env.example backend/.env

# 2. Dependencias PHP (una vez)
composer install

# 3. Backend + base de datos + worker + phpMyAdmin
docker compose up -d

# 4. Frontend
cd frontend
npm install
npm start
```

| Servicio | URL |
|---|---|
| Aplicación | http://localhost:4200 |
| API | http://localhost:8080/backend/api/index.php |
| phpMyAdmin | http://localhost:8081 (usuario `root`, sin contraseña) |
| Mailpit (opcional) | http://localhost:8025 |

**Usuario de desarrollo:** `admin` / `123456` (definido en `database/seed.sql`; cámbialo fuera de desarrollo).

La base de datos se crea sola la primera vez (`database/schema.sql` + `database/seed.sql`). Si ya tenías una base de datos anterior, aplica la migración:

```bash
docker compose exec -T db mysql -uroot templimail_db < database/migrations/001_email_queue.sql
```

### El worker
`docker compose up -d` arranca el servicio `worker` (`backend/bin/worker.php`), que envía las campañas en cola y las programadas cuando les toca. **Sin él, las campañas se quedan en "En cola".** Ver su actividad: `docker compose logs -f worker`.

### Probar sin enviar correos reales
Mailpit captura todo lo que se envía:

```bash
docker compose --profile dev up -d mailpit
```

y en `backend/.env`: `SMTP_HOST=mailpit`, `SMTP_PORT=1025`, `SMTP_SECURE=none`, `SMTP_USER=` y `SMTP_PASSWORD=` vacíos. Bandeja en http://localhost:8025.

### Frontend en Docker (opcional)
`docker compose --profile frontend up -d frontend` (en lugar de `npm start`).

### Con XAMPP
Copia el proyecto en `htdocs/TempliMail`, usa `DB_HOST=localhost` en `backend/.env` y cambia `apiUrl` en `frontend/src/environments/environment.development.ts`. Como XAMPP no tiene worker, ejecútalo a mano: `php backend/bin/worker.php`.

---

## 🧪 Pruebas

```bash
bash tests/e2e.sh          # 70+ comprobaciones de la API (requiere Docker y Mailpit)
cd frontend && npm run build
```

⚠️ `tests/e2e.sh` borra contactos, plantillas y campañas de la base de datos: se niega a ejecutarse si detecta datos reales.

---

## 📁 Estructura

```
TempliMail/
├── backend/
│   ├── api/index.php       # Router (tabla de rutas)
│   ├── bin/                # worker.php, test_mail.php
│   ├── src/                # Controllers → Services → Models (PSR-4: TempliMail\)
│   └── .env.example
├── database/               # schema.sql, seed.sql, migrations/
├── frontend/               # Angular 19
├── tests/e2e.sh
└── docker-compose.yml
```

---

## 🔒 Seguridad

- Solo `backend/api/` es accesible por HTTP (Apache bloquea `.env`, código y SQL).
- Todas las consultas están acotadas al usuario autenticado; los contactos de una campaña se validan contra su propietario.
- Los errores internos se registran en el log y nunca se muestran al cliente.
- CORS limitado al origen configurado en `CORS_ORIGIN`.
- La vista previa de correos se renderiza en un `iframe` con `sandbox`.

---

## 📝 Información del proyecto

- **Autor**: Francisco Javier García López
- **Inicio**: Abril 2025

---

## 🌐 Demo pública

La demo es el mismo frontend Angular compilado con `ng build --configuration demo`. Sustituye las llamadas a la API PHP por un backend simulado en memoria (`frontend/src/app/demo/demo.interceptor.ts`), así que se puede alojar como web estática en GitHub Pages. Se despliega sola con cada push a `main` (`.github/workflows/demo.yml`).

Para probarla en local:

```bash
cd frontend
npx ng serve --configuration demo
```
