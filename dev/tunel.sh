#!/usr/bin/env bash
#
# Expone la tienda local en una URL pública https y deja a PrestaShop
# configurado con ese dominio.
#
# Hace falta para probar de verdad: Khipu RECHAZA crear el pago si el
# notify_url apunta a localhost («La URL ... no es válida»), así que sin túnel
# el checkout muere en «No se pudo procesar el pago con Khipu». De paso, las
# notificaciones de pago llegan solas y no hay que simularlas.
#
#   ./dev/tunel.sh           levanta el túnel y configura la tienda
#   ./dev/tunel.sh --status  muestra la URL vigente
#   ./dev/tunel.sh --stop    lo baja y vuelve a localhost:8080
#
# Mientras el túnel está arriba, http://localhost:8080 DEJA DE FUNCIONAR: la
# tienda queda en modo SSL y redirige a https, pero el Apache del contenedor
# solo habla http en ese puerto. El navegador lo reporta como «SSL received a
# record that exceeded the maximum permissible length». Usa la URL del túnel.
#
# OJO: mientras el túnel esté arriba, la tienda —incluido el administrador—
# queda accesible para cualquiera que tenga la URL. Por eso, mientras dure:
#
#   - la clave del administrador se cambia por una al azar (la de siempre está
#     publicada en el README, y con ella se leen la API Key y la llave secreta);
#   - el modo desarrollo se apaga, para no mostrar trazas de error al público.
#
# --stop deja las dos cosas como estaban. Bájalo al terminar.

set -Eeuo pipefail

cd "$(dirname "$0")"

DB_CONTAINER=dev-db-1
PS_CONTAINER=dev-prestashop-1

# Los mismos valores de docker-compose.yml.
ADMIN_MAIL=admin@khipu.local
CLAVE_LOCAL=khipu1234

DEFINES=/var/www/html/config/defines_custom.inc.php
MARCA_TUNEL=/var/www/html/config/tunel.lock

# En PrestaShop 8 el modo desarrollo lo enciende defines_custom.inc.php leyendo
# PS_DEV_MODE. Se le agrega (una sola vez) la condición de que no exista la
# marca del túnel, y así se apaga sin recrear el contenedor.
modo_desarrollo() {
    local encender="$1"

    docker exec "$PS_CONTAINER" sed -i \
        "s|if ((bool) getenv('PS_DEV_MODE')) {|if ((bool) getenv('PS_DEV_MODE') \&\& !file_exists(__DIR__ . '/tunel.lock')) {|" \
        "$DEFINES" 2>/dev/null || true

    if [ "$encender" = "1" ]; then
        docker exec "$PS_CONTAINER" rm -f "$MARCA_TUNEL"
    else
        docker exec "$PS_CONTAINER" touch "$MARCA_TUNEL"
    fi
}

modo_desarrollo_activo() {
    docker exec "$PS_CONTAINER" php -r \
        "include '$DEFINES'; echo (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) ? 'si' : 'no';" 2>/dev/null || echo "?"
}

poner_clave_admin() {
    local clave="$1" hash

    hash=$(docker exec "$PS_CONTAINER" php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' -- "$clave")
    docker exec "$DB_CONTAINER" mysql -uroot -pprestashop prestashop -e \
        "UPDATE ps_employee SET passwd='${hash}' WHERE email='${ADMIN_MAIL}';" 2>/dev/null
}

configurar_dominio() {
    local dominio="$1" ssl="$2"

    # PS_SSL_ENABLED_EVERYWHERE también: con solo PS_SSL_ENABLED, PrestaShop
    # sirve el catálogo por http y salta a https únicamente en el checkout. Ese
    # ida y vuelta entre protocolos puede costar la sesión justo cuando el
    # pagador vuelve de Khipu, que siempre vuelve por https.
    docker exec "$DB_CONTAINER" mysql -uroot -pprestashop prestashop -e "
        UPDATE ps_shop_url SET domain='${dominio}', domain_ssl='${dominio}';
        UPDATE ps_configuration SET value='${dominio}' WHERE name IN ('PS_SHOP_DOMAIN','PS_SHOP_DOMAIN_SSL');
        UPDATE ps_configuration SET value='${ssl}' WHERE name IN ('PS_SSL_ENABLED','PS_SSL_ENABLED_EVERYWHERE');
    " 2>/dev/null

    # Las dos: con el modo desarrollo apagado la tienda usa la caché de prod.
    docker exec -u www-data "$PS_CONTAINER" php bin/console cache:clear --env=dev >/dev/null 2>&1 || true
    docker exec -u www-data "$PS_CONTAINER" php bin/console cache:clear --env=prod >/dev/null 2>&1 || true
}

url_vigente() {
    docker compose logs tunnel 2>&1 | grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' | tail -1 || true
}

if [ "${1:-}" = "--status" ]; then
    url=$(url_vigente)
    dominio=$(docker exec "$DB_CONTAINER" mysql -uroot -pprestashop prestashop -N -e "SELECT domain FROM ps_shop_url" 2>/dev/null | head -1)

    if [ -z "$url" ]; then
        echo "No hay túnel arriba. La tienda debería estar en http://localhost:8080"
    else
        echo "Túnel     : $url"
        echo "Admin     : $url/admin-khipu (clave: la que mostró ./tunel.sh; si la perdiste, vuelve a correrlo)"
        echo "Modo desarrollo encendido: $(modo_desarrollo_activo)"
        echo "PrestaShop cree que su dominio es: $dominio"
        echo
        echo "http://localhost:8080 no funciona mientras el túnel esté arriba."
    fi
    exit 0
fi

clave_admin_es() {
    local hash

    hash=$(docker exec "$DB_CONTAINER" mysql -uroot -pprestashop prestashop -N -e \
        "SELECT passwd FROM ps_employee WHERE email='${ADMIN_MAIL}'" 2>/dev/null) || return 1
    [ -n "$hash" ] || return 1
    [ "$(docker exec "$PS_CONTAINER" php -r 'echo password_verify($argv[1], $argv[2]) ? "1" : "0";' -- "$1" "$hash")" = "1" ]
}

# Baja el túnel y deja la tienda como se usa en local. Cada paso sigue aunque
# el anterior falle: esto también corre cuando algo salió mal a medio camino.
restaurar_local() {
    docker compose --profile tunnel stop tunnel >/dev/null 2>&1 || true
    docker compose --profile tunnel rm -f tunnel >/dev/null 2>&1 || true
    modo_desarrollo 1 || true
    poner_clave_admin "$CLAVE_LOCAL" || true
    configurar_dominio "localhost:8080" "0" || true
}

if [ "${1:-}" = "--stop" ]; then
    restaurar_local
    echo "Túnel abajo. La tienda vuelve a http://localhost:8080 (clave admin: $CLAVE_LOCAL)"
    exit 0
fi

# Primero se protege la tienda y recién después se publica: si cambiar la
# clave fallaba con el túnel ya arriba, set -e cortaba el script con la tienda
# en Internet y la clave publicada en el README todavía vigente.
modo_desarrollo 0
clave=$(docker exec "$PS_CONTAINER" php -r 'echo bin2hex(random_bytes(10));') || clave=""
[ -n "$clave" ] && poner_clave_admin "$clave" && clave_admin_es "$clave" || {
    echo "No se pudo cambiar la clave del administrador; el túnel no se levanta." >&2
    restaurar_local
    exit 1
}

# De acá en adelante la tienda ya puede estar en Internet: si algo falla, se
# baja el túnel y se vuelve a local.
fallo_con_tunel() {
    echo "Falló un paso con el túnel arriba: se baja y la tienda vuelve a local." >&2
    restaurar_local
    exit 1
}
trap fallo_con_tunel ERR

echo "Levantando el túnel..."
docker compose --profile tunnel up -d tunnel >/dev/null

url=""
for _ in $(seq 1 60); do
    url=$(docker compose logs tunnel 2>&1 | grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' | tail -1 || true)
    [ -n "$url" ] && break
    sleep 2
done

if [ -z "$url" ]; then
    echo "No se pudo obtener la URL del túnel. Revisa: docker compose logs tunnel" >&2
    fallo_con_tunel
fi

dominio="${url#https://}"
configurar_dominio "$dominio" "1"

trap - ERR

echo
echo "Tienda pública : $url"
echo "Administrador  : $url/admin-khipu"
echo "  usuario      : $ADMIN_MAIL"
echo "  clave        : $clave   (solo mientras dure el túnel)"
echo
if [ "$(modo_desarrollo_activo)" != "no" ]; then
    echo "AVISO: no se pudo apagar el modo desarrollo (¿PrestaShop 1.7?). Los errores"
    echo "de PHP se van a ver en la URL pública."
    echo
fi
echo "Khipu ya puede crear pagos y te va a notificar a:"
echo "  $url/modules/khipupayment/validate.php"
echo
echo "Usa SIEMPRE esa URL: http://localhost:8080 deja de funcionar mientras el"
echo "túnel esté arriba (la tienda redirige a https y ese puerto solo habla http)."
echo
echo "Al terminar: ./dev/tunel.sh --stop"
