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
- `api/index.php`: entrada única. **Tabla de rutas** `[método, regex, controlador, acción, pública]`; las rutas no públicas pasan por `MiddlewareAutenticacion`.
- `bootstrap.php`: autoload, dotenv, zona UTC, `display_errors` desactivado. Lo usan la API y los scripts de `bin/`.
- Capas: `Controllers/` (extienden `ControladorBase::respond()`) → `Services/` (lógica) → `Models/` (PDO, métodos estáticos). Namespace `TempliMail\` → `backend/src/`. Los nombres de clase, columnas y claves JSON están en español (p. ej. `ModeloContacto`, `ServicioAutenticacion`); las carpetas (`Controllers/Models/Services/Utils`) se mantienen en inglés.
- **Errores**: lanzar `ExcepcionApi` (con código HTTP) para errores que el cliente puede ver. Cualquier otra excepción se registra con `error_log` y responde 500 genérico. No devolver `$e->getMessage()` de excepciones internas.
- **Seguridad multiusuario**: todas las consultas filtran por `usuario_id` (`$_SERVER['AUTH_USER_ID']`). Al recibir ids de contactos, validarlos con `ModeloContacto::getSendableByIds`.
- Configuración: `Utils\Entorno::get()`; conexión: `Utils\BD` (variables `DB_*`, sesión en UTC).
- **Fechas**: se guardan en UTC. Los modelos devuelven ISO 8601 con sufijo `Z` (`DATE_FORMAT(..., '%Y-%m-%dT%H:%i:%sZ')`) para que el navegador las convierta a hora local.
- Autenticación: JWT HS256 (8 h) con `version_token`; `POST /logout` incrementa la versión e invalida todos los tokens. Login limitado a 5 fallos/15 min por usuario (`intentos_login`).

### Cola de envío
- `POST /send-massive` **solo encola** (campaña `scheduled`; `programado_en` NULL = enviar ya). `backend/bin/worker.php` (servicio `worker`) la procesa.
- `ModeloCampanaCorreo::claim()` reclama la campaña de forma atómica (evita duplicados); `heartbeat()` marca actividad y una campaña `processing` sin latido 5 min se retoma.
- `ServicioCorreo::deliver()`: personaliza por contacto (`Utils\RenderizadorPlantilla`), añade pie y cabeceras de baja (`Utils\Baja`), reintenta hasta `MAIL_MAX_ATTEMPTS`.
- Estados de campaña: `scheduled → processing → completed | cancelled` (valores guardados en inglés). Entregas: `pending | sent | failed | skipped`.
- Envíos individuales también quedan en el historial (`tipo = 'single'`, `contacto_id` NULL).
- Variables de plantilla en español (`{{nombre}}`, `{{correo}}`...); `Utils\RenderizadorPlantilla::ALIAS` reconoce además los nombres antiguos en inglés por compatibilidad con plantillas guardadas antes de traducir el proyecto.

### Grupos, importación y cuenta
- Grupos: `grupos_contacto` + `miembros_grupo_contacto` (N:M, cascada). `ModeloGrupo::setContactGroups` valida que contacto y grupos sean del usuario. `GET /contactos` devuelve `ids_grupo` de cada contacto.
- `POST /contactos/import` (máx. 2000 filas, transacción): omite correos existentes o repetidos (sin distinguir mayúsculas) e informa de los inválidos. El CSV se parsea en el navegador (`utils/csv.ts`, con `csv.spec.ts`).
- `PUT /me/contrasena` exige la contraseña actual y devuelve un token nuevo (el cambio incrementa `version_token` y cierra las demás sesiones).
- Dashboard: `GET /dashboard/stats` incluye `total_enviados`/`total_fallidos`; `GET /dashboard/activity` devuelve 14 días (UTC) rellenando con 0.

### Frontend (`frontend/src/app/`)
- Componentes standalone con lazy loading (`app.routes.ts`); nuevo control flow (`@if`/`@for`) en el código nuevo.
- `services/api.service.ts`: único punto de acceso HTTP, tipado con `models/api.models.ts`. La URL viene de `environments/` (`environment.development.ts` con `ng serve`).
- `interceptors/auth.interceptor.ts`: añade el JWT y, ante un 401 con sesión, cierra sesión y redirige a `/login`.
- `utils/token.ts`: acceso al token y comprobación de caducidad (usado por los guards).
- `services/toast.service.ts` + `shared/toast`: avisos globales. `shared/mail-preview`: vista previa en `iframe` con `sandbox`.
- Interfaz en español.

### Endpoints
Públicos: `POST /login`, `POST /register`, `GET|POST /unsubscribe/{id}/{firma}`.
Con JWT: `GET|PUT /me`, `PUT /me/contrasena`, `POST /logout`, contactos (`/contactos`, `/contactos/{id}`, `PUT /contactos/{id}/subscription`, `PUT /contactos/{id}/grupos`, `POST /contactos/import`), grupos (`/grupos`, `/grupos/{id}`), plantillas (`/plantillas`, `/plantillas/{id}`, `POST /upload-template-file`), correo (`POST /send-mail`, `/send-massive`, `/mail/preview`, `/mail/test`, `GET /process-scheduled`), historial (`GET /history`, `/history/{id}/deliveries`, `POST /history/{id}/cancel`, `/history/{id}/retry-failed`), `POST /ai/suggest-asunto`, `GET /dashboard/stats|summary|activity`.

## Convenciones y trampas
- En Apache solo `backend/api/` es accesible (`backend/docker/templimail.conf`); no mover secretos ni código a esa carpeta.
- En plantillas Angular, `{{` es interpolación: los textos con llaves literales se exponen desde el componente.
- La **demo pública** (`ng build --configuration demo`, GitHub Pages) usa `demo/demo.interceptor.ts`, un backend simulado en memoria: al añadir o cambiar un endpoint hay que reflejarlo también ahí.
- Con `curl.exe` en Windows, `\"` dentro del JSON se rompe: enviar el cuerpo desde un fichero (`--data-binary @f.json`).
- El `.env` real contiene credenciales SMTP: no imprimirlo ni commitearlo.
