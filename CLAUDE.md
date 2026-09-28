# CLAUDE.md

Guía para Claude Code (claude.ai/code) en este repositorio.

## Resumen

TempliMail: aplicación de email marketing (TFG/DAW). Frontend Angular 19 + backend PHP 8.2 puro, MySQL 8, todo orquestado con Docker Compose. Ver `README.md` para el arranque.

## Comandos

```bash
docker compose up -d                       # API (:8080), worker, MySQL, phpMyAdmin (:8081)
docker compose --profile dev up -d mailpit # buzón de pruebas (:8025)
cd frontend && npm install && npm start    # frontend en :4200
cd frontend && npm run build               # build de producción
bash tests/e2e.sh                          # pruebas e2e de la API (BORRA datos de negocio)
docker compose logs -f worker              # actividad del worker de envío
```

- API base: `http://localhost:8080/backend/api/index.php`
- Usuario de desarrollo: `admin` / `123456` (`database/seed.sql`)
- Configuración en `backend/.env` (plantilla: `backend/.env.example`). **Nunca se versiona.**
- Cambios de esquema: editar `database/schema.sql` **y** añadir `database/migrations/NNN_*.sql`.
- Tras cambiar `angular.json` hay que reiniciar `ng serve`. En el contenedor `frontend` los cambios de archivo se detectan con `--poll`.

## Arquitectura

### Backend (`backend/`)
- `api/index.php`: entrada única. **Tabla de rutas** `[método, regex, controlador, acción, pública]`; las rutas no públicas pasan por `AuthMiddleware`.
- `bootstrap.php`: autoload, dotenv, zona UTC, `display_errors` desactivado. Lo usan la API y los scripts de `bin/`.
- Capas: `Controllers/` (extienden `BaseController::respond()`) → `Services/` (lógica) → `Models/` (PDO, métodos estáticos). Namespace `TempliMail\` → `backend/src/`.
- **Errores**: lanzar `ApiException` (con código HTTP) para errores que el cliente puede ver. Cualquier otra excepción se registra con `error_log` y responde 500 genérico. No devolver `$e->getMessage()` de excepciones internas.
- **Seguridad multiusuario**: todas las consultas filtran por `user_id` (`$_SERVER['AUTH_USER_ID']`). Al recibir ids de contactos, validarlos con `ContactModel::getSendableByIds`.
- Configuración: `Utils\Env::get()`; conexión: `Utils\DB` (variables `DB_*`, sesión en UTC).
- **Fechas**: se guardan en UTC. Los modelos devuelven ISO 8601 con sufijo `Z` (`DATE_FORMAT(..., '%Y-%m-%dT%H:%i:%sZ')`) para que el navegador las convierta a hora local.
- Autenticación: JWT HS256 (8 h) con `token_version`; `POST /logout` incrementa la versión e invalida todos los tokens. Login limitado a 5 fallos/15 min por usuario (`login_attempts`).

### Cola de envío
- `POST /send-massive` **solo encola** (campaña `scheduled`; `scheduled_at` NULL = enviar ya). `backend/bin/worker.php` (servicio `worker`) la procesa.
- `EmailCampaignModel::claim()` reclama la campaña de forma atómica (evita duplicados); `heartbeat()` marca actividad y una campaña `processing` sin latido 5 min se retoma.
- `MailService::deliver()`: personaliza por contacto (`Utils\TemplateRenderer`), añade pie y cabeceras de baja (`Utils\Unsubscribe`), reintenta hasta `MAIL_MAX_ATTEMPTS`.
- Estados de campaña: `scheduled → processing → completed | cancelled`. Entregas: `pending | sent | failed | skipped`.
- Envíos individuales también quedan en el historial (`type = 'single'`, `contact_id` NULL).

### Grupos, importación y cuenta
- Grupos: `contact_groups` + `contact_group_members` (N:M, cascada). `GroupModel::setContactGroups` valida que contacto y grupos sean del usuario. `GET /contacts` devuelve `group_ids` de cada contacto.
- `POST /contacts/import` (máx. 2000 filas, transacción): omite emails existentes o repetidos (sin distinguir mayúsculas) e informa de los inválidos. El CSV se parsea en el navegador (`utils/csv.ts`, con `csv.spec.ts`).
- `PUT /me/password` exige la contraseña actual y devuelve un token nuevo (el cambio incrementa `token_version` y cierra las demás sesiones).
- Dashboard: `GET /dashboard/stats` incluye `total_sent`/`total_failed`; `GET /dashboard/activity` devuelve 14 días (UTC) rellenando con 0.

### Frontend (`frontend/src/app/`)
- Componentes standalone con lazy loading (`app.routes.ts`); nuevo control flow (`@if`/`@for`) en el código nuevo.
- `services/api.service.ts`: único punto de acceso HTTP, tipado con `models/api.models.ts`. La URL viene de `environments/` (`environment.development.ts` con `ng serve`).
- `interceptors/auth.interceptor.ts`: añade el JWT y, ante un 401 con sesión, cierra sesión y redirige a `/login`.
- `utils/token.ts`: acceso al token y comprobación de caducidad (usado por los guards).
- `services/toast.service.ts` + `shared/toast`: avisos globales. `shared/mail-preview`: vista previa en `iframe` con `sandbox`.
- Interfaz en español.

### Endpoints
Públicos: `POST /login`, `POST /register`, `GET|POST /unsubscribe/{id}/{firma}`.
Con JWT: `GET|PUT /me`, `PUT /me/password`, `POST /logout`, contactos (`/contacts`, `/contacts/{id}`, `PUT /contacts/{id}/subscription`, `PUT /contacts/{id}/groups`, `POST /contacts/import`), grupos (`/groups`, `/groups/{id}`), plantillas (`/templates`, `/templates/{id}`, `POST /upload-template-file`), correo (`POST /send-mail`, `/send-massive`, `/mail/preview`, `/mail/test`, `GET /process-scheduled`), historial (`GET /history`, `/history/{id}/deliveries`, `POST /history/{id}/cancel`, `/history/{id}/retry-failed`), `POST /ai/suggest-subject`, `GET /dashboard/stats|summary|activity`.

## Convenciones y trampas
- En Apache solo `backend/api/` es accesible (`backend/docker/templimail.conf`); no mover secretos ni código a esa carpeta.
- En plantillas Angular, `{{` es interpolación: los textos con llaves literales se exponen desde el componente.
- La **demo pública** (`ng build --configuration demo`, GitHub Pages) usa `demo/demo.interceptor.ts`, un backend simulado en memoria: al añadir o cambiar un endpoint hay que reflejarlo también ahí.
- Con `curl.exe` en Windows, `\"` dentro del JSON se rompe: enviar el cuerpo desde un fichero (`--data-binary @f.json`).
- El `.env` real contiene credenciales SMTP: no imprimirlo ni commitearlo.
