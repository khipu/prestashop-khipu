#!/usr/bin/env bash
#
# Simula la notificación de pago de Khipu contra una tienda local.
#
# Es la única forma de probar el webhook sin exponer la tienda a Internet: la
# firma se calcula igual que la de Khipu (HMAC-SHA256 de "<t>.<cuerpo>" con la
# llave secreta, en base64), así que KhipuPostBack no puede distinguirla de una
# notificación real.
#
# Uso:
#   KHIPU_SECRET=<llave secreta> KHIPU_RECEIVER_ID=<id cobrador> \
#     ./dev/notificar.sh <referencia del pedido> <monto>
#
# Ejemplo:
#   KHIPU_SECRET=abc123 KHIPU_RECEIVER_ID=1234 ./dev/notificar.sh XKBKNABJK 19990
#
# La referencia es la del pedido en PrestaShop (columna «Referencia» del
# listado de pedidos). El monto tiene que ser EXACTAMENTE el total del pedido,
# redondeado como lo hace el módulo: sin decimales en CLP, con dos en el resto.

set -euo pipefail

# El docker-compose.yml vive junto a este script. Sin -f, correrlo como
# ./dev/notificar.sh desde la raíz del repositorio no encontraba el archivo,
# no veía el túnel y mandaba la notificación a localhost en silencio.
COMPOSE_FILE="$(cd "$(dirname "$0")" && pwd)/docker-compose.yml"

# Si hay túnel arriba, la tienda está en modo SSL y localhost:8080 ya no
# responde: se usa la URL pública. Se puede forzar con KHIPU_NOTIFY_URL.
detectar_url() {
    local tunel
    tunel=$(docker compose -p dev -f "$COMPOSE_FILE" logs tunnel 2>/dev/null | grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' | tail -1 || true)

    if [ -n "$tunel" ]; then
        echo "${tunel}/modules/khipupayment/validate.php"
    else
        echo "http://localhost:8080/modules/khipupayment/validate.php"
    fi
}

URL="${KHIPU_NOTIFY_URL:-$(detectar_url)}"
SECRET="${KHIPU_SECRET:-}"
RECEIVER="${KHIPU_RECEIVER_ID:-}"
REFERENCE="${1:-}"
AMOUNT="${2:-}"
PAYMENT_ID="${3:-test-$(date +%s)}"
CURRENCY="${KHIPU_CURRENCY:-CLP}"

if [ -z "$SECRET" ] || [ -z "$RECEIVER" ] || [ -z "$REFERENCE" ] || [ -z "$AMOUNT" ]; then
    echo "Faltan datos. Uso:" >&2
    echo "  KHIPU_SECRET=<llave> KHIPU_RECEIVER_ID=<id> $0 <referencia> <monto> [payment_id]" >&2
    exit 2
fi

# La llave secreta y el id de cobrador tienen que ser los MISMOS que están
# guardados en la configuración del módulo: si la firma no calza el módulo
# responde 400, y si el receiver_id no calza, rechaza la notificación.
body=$(printf '{"transaction_id":"%s","receiver_id":"%s","amount":%s,"currency":"%s","payment_id":"%s","status":"done","status_detail":"normal"}' \
    "$REFERENCE" "$RECEIVER" "$AMOUNT" "$CURRENCY" "$PAYMENT_ID")

t=$(date +%s)
signature=$(printf '%s' "${t}.${body}" | openssl dgst -sha256 -hmac "$SECRET" -binary | base64)

echo "POST $URL"
echo "  cuerpo: $body"
echo

curl -s -i -X POST "$URL" \
    -H 'Content-Type: application/json' \
    -H "X-Khipu-Signature: t=${t},s=${signature}" \
    --data-binary "$body"

echo
echo
echo "Esperado: HTTP/1.1 200 y «Notification received correctly»."
echo "El pedido debería quedar en «Pago aceptado» con el payment_id $PAYMENT_ID."
