#!/bin/bash
# Prueba e2e del backend (Docker + Mailpit). NO envia correo real: el worker se lanza con SMTP -> Mailpit.
# Requisitos: docker compose --profile dev up -d  (con mailpit) y BD SIN datos reales: borra contactos/plantillas/campanas.
# Uso: bash tests/e2e.sh
cd "$(dirname "$0")/.."
API=http://localhost:8080/backend/api/index.php
PASS=0; FAIL=0
ok()   { echo "  ✔ $1"; PASS=$((PASS+1)); }
bad()  { echo "  ✘ $1"; FAIL=$((FAIL+1)); }
check(){ if [ "$2" = "$3" ]; then ok "$1"; else bad "$1 (esperado '$3', obtenido '$2')"; fi; }
has()  { if echo "$2" | grep -q -- "$3"; then ok "$1"; else bad "$1 (no contiene '$3'): $2"; fi; }
json() { echo "$1" | grep -o "\"$2\":[^,}]*" | head -1 | sed "s/\"$2\"://; s/\"//g"; }
req()  { # metodo ruta [cuerpo] [token]
  local m=$1 p=$2 b=$3 t=$4
  curl -s -X "$m" "$API$p" -H "Content-Type: application/json" ${t:+-H "Authorization: Bearer $t"} ${b:+-d "$b"} -w "|%{http_code}"
}
body() { echo "${1%|*}"; }
code() { echo "${1##*|}"; }
SQL()  { docker compose exec -T db mysql -uroot -N templimail_db -e "$1" 2>/dev/null; }
MP="-e SMTP_HOST=mailpit -e SMTP_PORT=1025 -e SMTP_SECURE=none -e SMTP_USER= -e SMTP_PASSWORD= -e MAIL_THROTTLE_MS=0"
worker_once() { docker compose run --rm -T $MP "$@" worker php backend/bin/worker.php --once 2>&1 | grep -v "^$"; }

echo "== Limpieza previa =="
# Seguridad: solo se ejecuta si no hay datos de negocio que perder
BUSINESS=$(SQL "SELECT (SELECT COUNT(*) FROM contacts WHERE email NOT LIKE '%@test.local')+(SELECT COUNT(*) FROM templates WHERE name<>'Bienvenida')+(SELECT COUNT(*) FROM email_campaigns WHERE 1=0)")
[ "$BUSINESS" = "0" ] || { echo "ABORTADO: hay datos reales en la BD"; exit 2; }
SQL "DELETE FROM login_attempts; DELETE FROM email_campaigns; DELETE FROM contacts; DELETE FROM templates; DELETE FROM users WHERE username='otheruser';"
curl -s -X DELETE http://localhost:8025/api/v1/messages > /dev/null

echo "== 1. Login =="
R=$(req POST /login '{"username":"admin","password":"mala"}'); check "contraseña incorrecta -> 401" "$(code "$R")" 401
has "mensaje unificado" "$(body "$R")" "Credenciales incorrectas"
R=$(req POST /login '{"username":"admin"}'); check "faltan datos -> 400" "$(code "$R")" 400
for i in 1 2 3 4 5; do req POST /login '{"username":"ghost","password":"x"}' > /dev/null; done
R=$(req POST /login '{"username":"ghost","password":"x"}'); check "6º intento fallido -> 429 (bloqueo)" "$(code "$R")" 429
R=$(req POST /login '{"username":"admin","password":"123456"}'); check "login correcto -> 200" "$(code "$R")" 200
TOKEN=$(json "$(body "$R")" token)
[ -n "$TOKEN" ] && ok "token recibido" || bad "sin token"

echo "== 2. Sesion =="
R=$(req GET /me "" "$TOKEN"); check "/me -> 200" "$(code "$R")" 200; has "/me devuelve usuario" "$(body "$R")" '"username":"admin"'
R=$(req GET /contacts ""); check "sin token -> 401" "$(code "$R")" 401
R=$(req GET /contacts "" "token.falso.aqui"); check "token falso -> 401" "$(code "$R")" 401
R=$(req GET /nada "" "$TOKEN"); check "ruta inexistente -> 404" "$(code "$R")" 404
R=$(req DELETE /login); check "método incorrecto -> 405" "$(code "$R")" 405

echo "== 3. Registro =="
R=$(req POST /register '{"username":"otheruser","email":"other@test.local","password":"corta"}'); check "contraseña corta -> 400" "$(code "$R")" 400
R=$(req POST /register '{"username":"otheruser","email":"other@test.local","password":"passw0rd123"}'); check "registro -> 201" "$(code "$R")" 201
R=$(req POST /register '{"username":"otheruser","email":"otro@test.local","password":"passw0rd123"}'); check "usuario duplicado -> 409" "$(code "$R")" 409
R=$(req POST /register '{"username":"otro2","email":"other@test.local","password":"passw0rd123"}'); check "email duplicado -> 409" "$(code "$R")" 409
OTOKEN=$(json "$(body "$(req POST /login '{"username":"otheruser","password":"passw0rd123"}')")" token)

echo "== 4. Contactos =="
R=$(req POST /contacts '{"first_name":"Ana","last_name":"Lopez","email":"ana@test.local","company":"ACME"}' "$TOKEN"); check "crear Ana -> 201" "$(code "$R")" 201
req POST /contacts '{"first_name":"","last_name":"","email":"beto@test.local"}' "$TOKEN" > /dev/null
req POST /contacts '{"first_name":"Carla","email":"carla@test.local"}' "$TOKEN" > /dev/null
R=$(req POST /contacts '{"email":"ana@test.local"}' "$TOKEN"); check "email duplicado -> 409" "$(code "$R")" 409
R=$(req POST /contacts '{"email":"no-es-email"}' "$TOKEN"); check "email inválido -> 400" "$(code "$R")" 400
req POST /contacts '{"first_name":"Victima","email":"victima@test.local"}' "$OTOKEN" > /dev/null
ANA=$(SQL "SELECT id FROM contacts WHERE email='ana@test.local'"); BETO=$(SQL "SELECT id FROM contacts WHERE email='beto@test.local'")
CARLA=$(SQL "SELECT id FROM contacts WHERE email='carla@test.local'"); VICTIMA=$(SQL "SELECT id FROM contacts WHERE email='victima@test.local'")
R=$(req PUT /contacts/$CARLA/subscription '{"subscribed":false}' "$TOKEN"); check "dar de baja a Carla -> 200" "$(code "$R")" 200
R=$(req PUT /contacts/$VICTIMA/subscription '{"subscribed":false}' "$TOKEN"); check "baja de contacto AJENO -> 404" "$(code "$R")" 404
R=$(req PUT /contacts/$VICTIMA '{"email":"hack@test.local"}' "$TOKEN"); check "editar contacto AJENO -> 404" "$(code "$R")" 404
R=$(req DELETE /contacts/$VICTIMA "" "$TOKEN"); check "borrar contacto AJENO -> 404" "$(code "$R")" 404

echo "== 5. Plantilla =="
R=$(req POST /templates '{"name":"","subject":"x","content_html":"<p>x</p>"}' "$TOKEN"); check "plantilla sin nombre -> 400" "$(code "$R")" 400
R=$(req POST /templates '{"name":"Bienvenida","subject":"Hola {{first_name|amigo}}","content_html":"<p>Hola {{full_name|cliente}} de {{company|tu empresa}}. <b>{{first_name}}</b></p>"}' "$TOKEN"); check "crear plantilla -> 201" "$(code "$R")" 201
TPL=$(SQL "SELECT id FROM templates WHERE name='Bienvenida'")
R=$(req POST /mail/preview "{\"subject\":\"Hola {{first_name|amigo}}\",\"content_html\":\"<p>{{full_name}} - {{company}}</p>\",\"contact_id\":$ANA}" "$TOKEN")
has "preview con contacto real" "$(body "$R")" "Ana Lopez - ACME"
R=$(req POST /mail/preview '{"subject":"Hola {{first_name}}","content_html":"<p>{{nope}}</p>"}' "$TOKEN")
has "preview con datos de ejemplo" "$(body "$R")" "Hola Ana"
has "variable desconocida se deja visible" "$(body "$R")" "{{nope}}"
has "preview incluye pie de baja" "$(body "$R")" "date de baja"

echo "== 6. Campaña masiva (IDOR + bajas) =="
R=$(req POST /send-massive "{\"template_id\":$TPL,\"contact_ids\":[$VICTIMA]}" "$TOKEN"); check "SOLO contactos ajenos -> 400" "$(code "$R")" 400
R=$(req POST /send-massive "{\"template_id\":$TPL,\"contact_ids\":[$ANA,$BETO,$CARLA,$VICTIMA,999999],\"name\":\"Prueba e2e\"}" "$TOKEN"); check "campaña mixta -> 201" "$(code "$R")" 201
check "solo 2 destinatarios válidos (Ana y Beto)" "$(json "$(body "$R")" recipients)" 2
check "3 excluidos (baja, ajeno, inexistente)" "$(json "$(body "$R")" excluded)" 3
CAMP=$(json "$(body "$R")" campaign_id)
check "ajeno NO está en las entregas" "$(SQL "SELECT COUNT(*) FROM email_deliveries WHERE campaign_id=$CAMP AND contact_id=$VICTIMA")" 0
check "queda en cola (scheduled)" "$(SQL "SELECT status FROM email_campaigns WHERE id=$CAMP")" scheduled

echo "== 7. Worker =="
worker_once | sed 's/^/    /'
check "campaña completada" "$(SQL "SELECT status FROM email_campaigns WHERE id=$CAMP")" completed
check "2 entregas enviadas" "$(SQL "SELECT COUNT(*) FROM email_deliveries WHERE campaign_id=$CAMP AND status='sent'")" 2
MSGS=$(curl -s http://localhost:8025/api/v1/messages)
check "Mailpit recibió 2 correos" "$(echo "$MSGS" | grep -o '"total":[0-9]*' | head -1 | sed 's/"total"://')" 2
has "asunto personalizado (Ana)" "$MSGS" "Hola Ana"
has "asunto con valor por defecto (Beto)" "$MSGS" "Hola amigo"
MID=$(echo "$MSGS" | grep -o '"ID":"[^"]*"' | head -1 | sed 's/"ID":"//; s/"//')
RAW=$(curl -s http://localhost:8025/api/v1/message/$MID/raw)
has "cabecera List-Unsubscribe" "$RAW" "List-Unsubscribe:"
has "cabecera one-click" "$RAW" "List-Unsubscribe-Post"
BODYMSG=$(curl -s http://localhost:8025/api/v1/message/$MID)
has "enlace de baja en el cuerpo" "$BODYMSG" "/unsubscribe/"

echo "== 8. Programada y cancelación =="
FUTURE=$(date -u -d "+2 hours" +%Y-%m-%dT%H:%M:%S.000Z)
R=$(req POST /send-massive "{\"template_id\":$TPL,\"contact_ids\":[$ANA],\"scheduled_at\":\"$FUTURE\"}" "$TOKEN"); check "programar -> 201" "$(code "$R")" 201
check "scheduled=true" "$(json "$(body "$R")" scheduled)" true
CAMP2=$(json "$(body "$R")" campaign_id)
check "guardada en UTC (+2h)" "$(SQL "SELECT TIMESTAMPDIFF(HOUR, UTC_TIMESTAMP(), scheduled_at) FROM email_campaigns WHERE id=$CAMP2")" 1
worker_once > /dev/null
check "el worker NO envía una programada futura" "$(SQL "SELECT status FROM email_campaigns WHERE id=$CAMP2")" scheduled
R=$(req POST /history/$CAMP2/cancel "" "$OTOKEN"); check "cancelar campaña AJENA -> 409" "$(code "$R")" 409
R=$(req POST /history/$CAMP2/cancel "" "$TOKEN"); check "cancelar la propia -> 200" "$(code "$R")" 200
check "estado cancelled" "$(SQL "SELECT status FROM email_campaigns WHERE id=$CAMP2")" cancelled
check "entregas marcadas skipped" "$(SQL "SELECT status FROM email_deliveries WHERE campaign_id=$CAMP2")" skipped
R=$(req POST /history/$CAMP/cancel "" "$TOKEN"); check "cancelar una ya completada -> 409" "$(code "$R")" 409

echo "== 9. Reintentos =="
R=$(req POST /send-massive "{\"template_id\":$TPL,\"contact_ids\":[$ANA,$BETO]}" "$TOKEN"); CAMP3=$(json "$(body "$R")" campaign_id)
# SMTP roto (puerto cerrado) -> falla tras 2 intentos
docker compose run --rm -T -e SMTP_HOST=mailpit -e SMTP_PORT=9 -e SMTP_SECURE=none -e SMTP_USER= -e MAIL_THROTTLE_MS=0 -e MAIL_MAX_ATTEMPTS=2 worker php backend/bin/worker.php --once > /dev/null 2>&1
check "campaña terminó" "$(SQL "SELECT status FROM email_campaigns WHERE id=$CAMP3")" completed
check "2 entregas fallidas" "$(SQL "SELECT COUNT(*) FROM email_deliveries WHERE campaign_id=$CAMP3 AND status='failed'")" 2
check "retry_count = 2 intentos" "$(SQL "SELECT MAX(retry_count) FROM email_deliveries WHERE campaign_id=$CAMP3")" 2
R=$(req POST /history/$CAMP3/retry-failed "" "$OTOKEN"); check "reintentar campaña AJENA -> 404" "$(code "$R")" 404
R=$(req POST /history/$CAMP3/retry-failed "" "$TOKEN"); check "reintentar fallidas -> 200" "$(code "$R")" 200
check "vuelve a la cola" "$(SQL "SELECT status FROM email_campaigns WHERE id=$CAMP3")" scheduled
worker_once > /dev/null
check "ahora las 2 se envían" "$(SQL "SELECT COUNT(*) FROM email_deliveries WHERE campaign_id=$CAMP3 AND status='sent'")" 2

echo "== 10. Envío individual y prueba (vía CLI, SMTP->Mailpit) =="
docker compose run --rm -T $MP worker php -r '
require "backend/bootstrap.php";
TempliMail\Services\MailService::sendSingle(1, ["to"=>"destino@test.local","subject"=>"Suelto\r\nBcc: x@y.z","body"=>"<p>Hola</p>"]);
echo "single ok\n";
echo "test enviado a ".TempliMail\Services\MailService::sendTest(1, ["subject"=>"Hola {{first_name}}","content_html"=>"<p>x</p>"])."\n";' 2>&1 | sed 's/^/    /'
check "envío individual en el historial (type=single)" "$(SQL "SELECT COUNT(*) FROM email_campaigns WHERE type='single' AND status='completed'")" 1
check "asunto sin saltos de línea (anti-inyección)" "$(SQL "SELECT subject FROM email_campaigns WHERE type='single'" | grep -c Bcc)" 1
check "destinatario individual registrado" "$(SQL "SELECT recipient_email FROM email_deliveries ed JOIN email_campaigns ec ON ec.id=ed.campaign_id WHERE ec.type='single'")" destino@test.local
R=$(req GET /history "" "$TOKEN"); has "historial incluye contadores" "$(body "$R")" '"sent":2'
R=$(req GET /dashboard/stats "" "$TOKEN"); has "dashboard cuenta solo campañas masivas" "$(body "$R")" '"total_campaigns":3'

echo "== 11. Baja pública =="
SIG=$(docker compose exec -T backend php -r 'require "/var/www/html/backend/bootstrap.php"; echo TempliMail\Utils\Unsubscribe::signature('$ANA');' 2>/dev/null)
R=$(curl -s -o /dev/null -w "%{http_code}" $API/unsubscribe/$ANA/$SIG); check "GET muestra confirmación (200)" "$R" 200
check "GET NO da de baja" "$(SQL "SELECT unsubscribed_at IS NULL FROM contacts WHERE id=$ANA")" 1
R=$(curl -s -o /dev/null -w "%{http_code}" $API/unsubscribe/$ANA/$(printf 'a%.0s' $(seq 64))); check "firma falsa -> 404" "$R" 404
R=$(curl -s -o /dev/null -w "%{http_code}" -X POST $API/unsubscribe/$ANA/$SIG); check "POST (one-click) -> 200" "$R" 200
check "POST da de baja" "$(SQL "SELECT unsubscribed_at IS NOT NULL FROM contacts WHERE id=$ANA")" 1
R=$(req POST /send-massive "{\"template_id\":$TPL,\"contact_ids\":[$ANA]}" "$TOKEN"); check "dado de baja no puede recibir campañas -> 400" "$(code "$R")" 400

echo "== 12. Logout =="
R=$(req POST /logout "" "$TOKEN"); check "logout -> 200" "$(code "$R")" 200
R=$(req GET /me "" "$TOKEN"); check "token invalidado tras logout -> 401" "$(code "$R")" 401

echo
echo "RESULTADO: $PASS correctas, $FAIL fallidas"
[ $FAIL -eq 0 ]
