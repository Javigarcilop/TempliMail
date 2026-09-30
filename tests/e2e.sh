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

# El worker real usaria tu SMTP: se para durante la prueba y se reactiva al terminar
docker compose stop worker >/dev/null 2>&1
trap 'docker compose up -d worker >/dev/null 2>&1' EXIT

echo "== Limpieza previa =="
# Seguridad: solo se ejecuta si no hay datos de negocio que perder
BUSINESS=$(SQL "SELECT (SELECT COUNT(*) FROM contactos WHERE correo NOT LIKE '%@test.local')+(SELECT COUNT(*) FROM plantillas WHERE nombre<>'Bienvenida')+(SELECT COUNT(*) FROM campanas_correo WHERE 1=0)")
[ "$BUSINESS" = "0" ] || { echo "ABORTADO: hay datos reales en la BD"; exit 2; }
SQL "DELETE FROM grupos_contacto; DELETE FROM intentos_login; DELETE FROM campanas_correo; DELETE FROM contactos; DELETE FROM plantillas; DELETE FROM usuarios WHERE nombre_usuario='otheruser';"
curl -s -X DELETE http://localhost:8025/api/v1/messages > /dev/null

echo "== 1. Login =="
R=$(req POST /login '{"nombre_usuario":"admin","contrasena":"mala"}'); check "contraseña incorrecta -> 401" "$(code "$R")" 401
has "mensaje unificado" "$(body "$R")" "Credenciales incorrectas"
R=$(req POST /login '{"nombre_usuario":"admin"}'); check "faltan datos -> 400" "$(code "$R")" 400
for i in 1 2 3 4 5; do req POST /login '{"nombre_usuario":"ghost","contrasena":"x"}' > /dev/null; done
R=$(req POST /login '{"nombre_usuario":"ghost","contrasena":"x"}'); check "6º intento fallido -> 429 (bloqueo)" "$(code "$R")" 429
R=$(req POST /login '{"nombre_usuario":"admin","contrasena":"123456"}'); check "login correcto -> 200" "$(code "$R")" 200
TOKEN=$(json "$(body "$R")" token)
[ -n "$TOKEN" ] && ok "token recibido" || bad "sin token"

echo "== 2. Sesion =="
R=$(req GET /me "" "$TOKEN"); check "/me -> 200" "$(code "$R")" 200; has "/me devuelve usuario" "$(body "$R")" '"nombre_usuario":"admin"'
R=$(req GET /contactos ""); check "sin token -> 401" "$(code "$R")" 401
R=$(req GET /contactos "" "token.falso.aqui"); check "token falso -> 401" "$(code "$R")" 401
R=$(req GET /nada "" "$TOKEN"); check "ruta inexistente -> 404" "$(code "$R")" 404
R=$(req DELETE /login); check "método incorrecto -> 405" "$(code "$R")" 405

echo "== 3. Registro =="
R=$(req POST /register '{"nombre_usuario":"otheruser","correo":"other@test.local","contrasena":"corta"}'); check "contraseña corta -> 400" "$(code "$R")" 400
R=$(req POST /register '{"nombre_usuario":"otheruser","correo":"other@test.local","contrasena":"passw0rd123"}'); check "registro -> 201" "$(code "$R")" 201
R=$(req POST /register '{"nombre_usuario":"otheruser","correo":"otro@test.local","contrasena":"passw0rd123"}'); check "usuario duplicado -> 409" "$(code "$R")" 409
R=$(req POST /register '{"nombre_usuario":"otro2","correo":"other@test.local","contrasena":"passw0rd123"}'); check "correo duplicado -> 409" "$(code "$R")" 409
OTOKEN=$(json "$(body "$(req POST /login '{"nombre_usuario":"otheruser","contrasena":"passw0rd123"}')")" token)

echo "== 4. Contactos =="
R=$(req POST /contactos '{"nombre":"Ana","apellidos":"Lopez","correo":"ana@test.local","empresa":"ACME"}' "$TOKEN"); check "crear Ana -> 201" "$(code "$R")" 201
req POST /contactos '{"nombre":"","apellidos":"","correo":"beto@test.local"}' "$TOKEN" > /dev/null
req POST /contactos '{"nombre":"Carla","correo":"carla@test.local"}' "$TOKEN" > /dev/null
R=$(req POST /contactos '{"correo":"ana@test.local"}' "$TOKEN"); check "correo duplicado -> 409" "$(code "$R")" 409
R=$(req POST /contactos '{"correo":"no-es-email"}' "$TOKEN"); check "correo inválido -> 400" "$(code "$R")" 400
req POST /contactos '{"nombre":"Victima","correo":"victima@test.local"}' "$OTOKEN" > /dev/null
ANA=$(SQL "SELECT id FROM contactos WHERE correo='ana@test.local'"); BETO=$(SQL "SELECT id FROM contactos WHERE correo='beto@test.local'")
CARLA=$(SQL "SELECT id FROM contactos WHERE correo='carla@test.local'"); VICTIMA=$(SQL "SELECT id FROM contactos WHERE correo='victima@test.local'")
R=$(req PUT /contactos/$CARLA/subscription '{"subscribed":false}' "$TOKEN"); check "dar de baja a Carla -> 200" "$(code "$R")" 200
R=$(req PUT /contactos/$VICTIMA/subscription '{"subscribed":false}' "$TOKEN"); check "baja de contacto AJENO -> 404" "$(code "$R")" 404
R=$(req PUT /contactos/$VICTIMA '{"correo":"hack@test.local"}' "$TOKEN"); check "editar contacto AJENO -> 404" "$(code "$R")" 404
R=$(req DELETE /contactos/$VICTIMA "" "$TOKEN"); check "borrar contacto AJENO -> 404" "$(code "$R")" 404

echo "== 5. Plantilla =="
R=$(req POST /plantillas '{"nombre":"","asunto":"x","contenido_html":"<p>x</p>"}' "$TOKEN"); check "plantilla sin nombre -> 400" "$(code "$R")" 400
R=$(req POST /plantillas '{"nombre":"Bienvenida","asunto":"Hola {{nombre|amigo}}","contenido_html":"<p>Hola {{nombre_completo|cliente}} de {{empresa|tu empresa}}. <b>{{nombre}}</b></p>"}' "$TOKEN"); check "crear plantilla -> 201" "$(code "$R")" 201
TPL=$(SQL "SELECT id FROM plantillas WHERE nombre='Bienvenida'")
R=$(req POST /mail/preview "{\"asunto\":\"Hola {{nombre|amigo}}\",\"contenido_html\":\"<p>{{nombre_completo}} - {{empresa}}</p>\",\"contacto_id\":$ANA}" "$TOKEN")
has "preview con contacto real" "$(body "$R")" "Ana Lopez - ACME"
R=$(req POST /mail/preview '{"asunto":"Hola {{nombre}}","contenido_html":"<p>{{nope}}</p>"}' "$TOKEN")
has "preview con datos de ejemplo" "$(body "$R")" "Hola Ana"
has "variable desconocida se deja visible" "$(body "$R")" "{{nope}}"
has "preview incluye pie de baja" "$(body "$R")" "date de baja"

echo "== 6. Campaña masiva (IDOR + bajas) =="
R=$(req POST /send-massive "{\"plantilla_id\":$TPL,\"ids_contacto\":[$VICTIMA]}" "$TOKEN"); check "SOLO contactos ajenos -> 400" "$(code "$R")" 400
R=$(req POST /send-massive "{\"plantilla_id\":$TPL,\"ids_contacto\":[$ANA,$BETO,$CARLA,$VICTIMA,999999],\"nombre\":\"Prueba e2e\"}" "$TOKEN"); check "campaña mixta -> 201" "$(code "$R")" 201
check "solo 2 destinatarios válidos (Ana y Beto)" "$(json "$(body "$R")" destinatarios)" 2
check "3 excluidos (baja, ajeno, inexistente)" "$(json "$(body "$R")" excluidos)" 3
CAMP=$(json "$(body "$R")" campana_id)
check "ajeno NO está en las entregas" "$(SQL "SELECT COUNT(*) FROM entregas_correo WHERE campana_id=$CAMP AND contacto_id=$VICTIMA")" 0
check "queda en cola (scheduled)" "$(SQL "SELECT estado FROM campanas_correo WHERE id=$CAMP")" scheduled

echo "== 7. Worker =="
worker_once | sed 's/^/    /'
check "campaña completada" "$(SQL "SELECT estado FROM campanas_correo WHERE id=$CAMP")" completed
check "2 entregas enviadas" "$(SQL "SELECT COUNT(*) FROM entregas_correo WHERE campana_id=$CAMP AND estado='sent'")" 2
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
R=$(req POST /send-massive "{\"plantilla_id\":$TPL,\"ids_contacto\":[$ANA],\"programado_en\":\"$FUTURE\"}" "$TOKEN"); check "programar -> 201" "$(code "$R")" 201
check "programado=true" "$(json "$(body "$R")" programado)" true
CAMP2=$(json "$(body "$R")" campana_id)
check "guardada en UTC (+2h)" "$(SQL "SELECT TIMESTAMPDIFF(HOUR, UTC_TIMESTAMP(), programado_en) FROM campanas_correo WHERE id=$CAMP2")" 1
worker_once > /dev/null
check "el worker NO envía una programada futura" "$(SQL "SELECT estado FROM campanas_correo WHERE id=$CAMP2")" scheduled
R=$(req POST /history/$CAMP2/cancel "" "$OTOKEN"); check "cancelar campaña AJENA -> 409" "$(code "$R")" 409
R=$(req POST /history/$CAMP2/cancel "" "$TOKEN"); check "cancelar la propia -> 200" "$(code "$R")" 200
check "estado cancelled" "$(SQL "SELECT estado FROM campanas_correo WHERE id=$CAMP2")" cancelled
check "entregas marcadas skipped" "$(SQL "SELECT estado FROM entregas_correo WHERE campana_id=$CAMP2")" skipped
R=$(req POST /history/$CAMP/cancel "" "$TOKEN"); check "cancelar una ya completada -> 409" "$(code "$R")" 409

echo "== 9. Reintentos =="
R=$(req POST /send-massive "{\"plantilla_id\":$TPL,\"ids_contacto\":[$ANA,$BETO]}" "$TOKEN"); CAMP3=$(json "$(body "$R")" campana_id)
# SMTP roto (puerto cerrado) -> falla tras 2 intentos
docker compose run --rm -T -e SMTP_HOST=mailpit -e SMTP_PORT=9 -e SMTP_SECURE=none -e SMTP_USER= -e MAIL_THROTTLE_MS=0 -e MAIL_MAX_ATTEMPTS=2 worker php backend/bin/worker.php --once > /dev/null 2>&1
check "campaña terminó" "$(SQL "SELECT estado FROM campanas_correo WHERE id=$CAMP3")" completed
check "2 entregas fallidas" "$(SQL "SELECT COUNT(*) FROM entregas_correo WHERE campana_id=$CAMP3 AND estado='failed'")" 2
check "reintentos = 2 intentos" "$(SQL "SELECT MAX(reintentos) FROM entregas_correo WHERE campana_id=$CAMP3")" 2
R=$(req POST /history/$CAMP3/retry-failed "" "$OTOKEN"); check "reintentar campaña AJENA -> 404" "$(code "$R")" 404
R=$(req POST /history/$CAMP3/retry-failed "" "$TOKEN"); check "reintentar fallidas -> 200" "$(code "$R")" 200
check "vuelve a la cola" "$(SQL "SELECT estado FROM campanas_correo WHERE id=$CAMP3")" scheduled
worker_once > /dev/null
check "ahora las 2 se envían" "$(SQL "SELECT COUNT(*) FROM entregas_correo WHERE campana_id=$CAMP3 AND estado='sent'")" 2

echo "== 10. Envío individual y prueba (vía CLI, SMTP->Mailpit) =="
docker compose run --rm -T $MP worker php -r '
require "backend/bootstrap.php";
TempliMail\Services\ServicioCorreo::sendSingle(1, ["destinatario"=>"destino@test.local","asunto"=>"Suelto\r\nBcc: x@y.z","cuerpo"=>"<p>Hola</p>"]);
echo "single ok\n";
echo "test enviado a ".TempliMail\Services\ServicioCorreo::sendTest(1, ["asunto"=>"Hola {{nombre}}","contenido_html"=>"<p>x</p>"])."\n";' 2>&1 | sed 's/^/    /'
check "envío individual en el historial (tipo=single)" "$(SQL "SELECT COUNT(*) FROM campanas_correo WHERE tipo='single' AND estado='completed'")" 1
check "asunto sin saltos de línea (anti-inyección)" "$(SQL "SELECT asunto FROM campanas_correo WHERE tipo='single'" | grep -c Bcc)" 1
check "destinatario individual registrado" "$(SQL "SELECT correo_destinatario FROM entregas_correo ed JOIN campanas_correo ec ON ec.id=ed.campana_id WHERE ec.tipo='single'")" destino@test.local
R=$(req GET /history "" "$TOKEN"); has "historial incluye contadores" "$(body "$R")" '"enviados":2'
R=$(req GET /dashboard/stats "" "$TOKEN"); has "dashboard cuenta solo campañas masivas" "$(body "$R")" '"total_campanas":3'

echo "== 11. Baja pública =="
SIG=$(docker compose exec -T backend php -r 'require "/var/www/html/backend/bootstrap.php"; echo TempliMail\Utils\Baja::signature('$ANA');' 2>/dev/null)
R=$(curl -s -o /dev/null -w "%{http_code}" $API/unsubscribe/$ANA/$SIG); check "GET muestra confirmación (200)" "$R" 200
check "GET NO da de baja" "$(SQL "SELECT baja_en IS NULL FROM contactos WHERE id=$ANA")" 1
R=$(curl -s -o /dev/null -w "%{http_code}" $API/unsubscribe/$ANA/$(printf 'a%.0s' $(seq 64))); check "firma falsa -> 404" "$R" 404
R=$(curl -s -o /dev/null -w "%{http_code}" -X POST $API/unsubscribe/$ANA/$SIG); check "POST (one-click) -> 200" "$R" 200
check "POST da de baja" "$(SQL "SELECT baja_en IS NOT NULL FROM contactos WHERE id=$ANA")" 1
R=$(req POST /send-massive "{\"plantilla_id\":$TPL,\"ids_contacto\":[$ANA]}" "$TOKEN"); check "dado de baja no puede recibir campañas -> 400" "$(code "$R")" 400

echo "== 12. Grupos de contactos =="
R=$(req POST /grupos '{"nombre":"Clientes"}' "$TOKEN"); check "crear grupo -> 201" "$(code "$R")" 201
GRP=$(json "$(body "$R")" id)
R=$(req POST /grupos '{"nombre":"Clientes"}' "$TOKEN"); check "grupo duplicado -> 409" "$(code "$R")" 409
R=$(req POST /grupos '{"nombre":"  "}' "$TOKEN"); check "grupo sin nombre -> 400" "$(code "$R")" 400
R=$(req POST /grupos '{"nombre":"Clientes"}' "$OTOKEN"); check "otro usuario puede usar el mismo nombre -> 201" "$(code "$R")" 201
OGRP=$(json "$(body "$R")" id)
R=$(req PUT /contactos/$BETO/grupos "{\"ids_grupo\":[$GRP]}" "$TOKEN"); check "asignar contacto a grupo -> 200" "$(code "$R")" 200
R=$(req GET /contactos "" "$TOKEN"); has "el contacto devuelve sus ids_grupo" "$(body "$R")" "\"ids_grupo\":\[$GRP\]"
R=$(req GET /grupos "" "$TOKEN"); has "el grupo cuenta 1 miembro" "$(body "$R")" '"total_miembros":1'
R=$(req PUT /contactos/$BETO/grupos "{\"ids_grupo\":[$OGRP]}" "$TOKEN"); check "asignar a grupo AJENO -> 404" "$(code "$R")" 404
R=$(req PUT /contactos/$VICTIMA/grupos "{\"ids_grupo\":[$GRP]}" "$TOKEN"); check "asignar contacto AJENO -> 404" "$(code "$R")" 404
R=$(req PUT /grupos/$OGRP '{"nombre":"Hack"}' "$TOKEN"); check "renombrar grupo AJENO -> 404" "$(code "$R")" 404
R=$(req DELETE /grupos/$OGRP "" "$TOKEN"); check "borrar grupo AJENO -> 404" "$(code "$R")" 404
R=$(req PUT /grupos/$GRP '{"nombre":"VIP"}' "$TOKEN"); check "renombrar grupo -> 200" "$(code "$R")" 200

echo "== 13. Importación de contactos =="
R=$(req POST /contactos/import "{\"grupo_id\":$GRP,\"contactos\":[{\"correo\":\"nuevo1@test.local\",\"nombre\":\"Nuevo\"},{\"correo\":\"nuevo2@test.local\"},{\"correo\":\"beto@test.local\"},{\"correo\":\"no-valido\"},{\"correo\":\"NUEVO1@test.local\"}]}" "$TOKEN"); check "importar -> 201" "$(code "$R")" 201
check "2 creados" "$(json "$(body "$R")" creados)" 2
check "2 duplicados (existente + repetido en el fichero)" "$(json "$(body "$R")" duplicados)" 2
has "1 fila inválida informada" "$(body "$R")" '"motivo":"Correo no v'
check "los importados quedan en el grupo" "$(SQL "SELECT COUNT(*) FROM miembros_grupo_contacto WHERE grupo_id=$GRP")" 3
R=$(req POST /contactos/import "{\"grupo_id\":$OGRP,\"contactos\":[{\"correo\":\"otro@test.local\"}]}" "$TOKEN"); check "importar a grupo AJENO -> 404" "$(code "$R")" 404
check "…y no crea contactos" "$(SQL "SELECT COUNT(*) FROM contactos WHERE correo='otro@test.local'")" 0
R=$(req POST /contactos/import '{"contactos":[]}' "$TOKEN"); check "importación vacía -> 400" "$(code "$R")" 400
R=$(req DELETE /grupos/$GRP "" "$TOKEN"); check "borrar grupo -> 200" "$(code "$R")" 200
check "borrar el grupo NO borra los contactos" "$(SQL "SELECT COUNT(*) FROM contactos WHERE correo='nuevo1@test.local'")" 1

echo "== 14. Cuenta =="
R=$(req PUT /me '{"correo":"no-es-email"}' "$OTOKEN"); check "correo inválido -> 400" "$(code "$R")" 400
R=$(req PUT /me '{"correo":"admin@templimail.com"}' "$OTOKEN"); check "correo de otra cuenta -> 409" "$(code "$R")" 409
R=$(req PUT /me '{"correo":"nuevo-other@test.local"}' "$OTOKEN"); check "cambiar correo -> 200" "$(code "$R")" 200
has "devuelve el usuario actualizado" "$(body "$R")" "nuevo-other@test.local"
R=$(req PUT /me/contrasena '{"contrasena_actual":"mala","contrasena_nueva":"otraClave123"}' "$OTOKEN"); check "contraseña actual incorrecta -> 403" "$(code "$R")" 403
R=$(req PUT /me/contrasena '{"contrasena_actual":"passw0rd123","contrasena_nueva":"corta"}' "$OTOKEN"); check "contraseña nueva corta -> 400" "$(code "$R")" 400
R=$(req PUT /me/contrasena '{"contrasena_actual":"passw0rd123","contrasena_nueva":"passw0rd123"}' "$OTOKEN"); check "misma contraseña -> 400" "$(code "$R")" 400
R=$(req PUT /me/contrasena '{"contrasena_actual":"passw0rd123","contrasena_nueva":"otraClave123"}' "$OTOKEN"); check "cambiar contraseña -> 200" "$(code "$R")" 200
NEWTOKEN=$(json "$(body "$R")" token)
R=$(req GET /me "" "$OTOKEN"); check "el token anterior queda invalidado -> 401" "$(code "$R")" 401
R=$(req GET /me "" "$NEWTOKEN"); check "el token nuevo funciona -> 200" "$(code "$R")" 200
R=$(req POST /login '{"nombre_usuario":"otheruser","contrasena":"otraClave123"}'); check "login con la contraseña nueva -> 200" "$(code "$R")" 200

echo "== 15. Dashboard =="
R=$(req GET /dashboard/stats "" "$TOKEN"); has "stats incluye correos enviados" "$(body "$R")" '"total_enviados":'
has "stats incluye correos fallidos" "$(body "$R")" '"total_fallidos":'
R=$(req GET /dashboard/activity "" "$TOKEN"); check "actividad: 14 días" "$(echo "$(body "$R")" | grep -o '"fecha"' | wc -l | tr -d ' ')" 14

echo "== 16. Logout =="
R=$(req POST /logout "" "$TOKEN"); check "logout -> 200" "$(code "$R")" 200
R=$(req GET /me "" "$TOKEN"); check "token invalidado tras logout -> 401" "$(code "$R")" 401

echo
echo "RESULTADO: $PASS correctas, $FAIL fallidas"
[ $FAIL -eq 0 ]
