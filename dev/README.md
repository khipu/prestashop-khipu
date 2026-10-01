# Tienda local para probar el módulo

Levanta una PrestaShop con el módulo montado desde este repositorio, para
probar el checkout, el webhook y el panel de reversa sin tocar una tienda real.

Solo necesitas **Docker**.

## 1. Levantar la tienda

```bash
cd dev
docker compose up -d
```

La primera vez tarda **unos 5 minutos**: descarga las imágenes e instala la
tienda. Durante la instalación el puerto no responde (`000`) y en el log se
queda en «Installing PrestaShop, this may take a while». Para esperar a que
esté lista:

```bash
until curl -s -o /dev/null -w '%{http_code}' http://localhost:8080/ | grep -qE '^(200|302)$'; do sleep 10; done; echo lista
```

Durante la instalación el log muestra un `PHP Warning: Attempt to read property
"theme_name" on null`. Es de la imagen, no del módulo, y no impide nada.

| | |
|---|---|
| Tienda | <http://localhost:8080> |
| Administrador | <http://localhost:8080/admin-khipu> |
| Usuario | `admin@khipu.local` |
| Clave | `khipu1234` (con el túnel arriba es otra, ver §4) |

El módulo se monta desde el repositorio: editar un `.php` se refleja al
recargar. Si tocas una plantilla `.tpl`, borra la caché:

```bash
docker compose exec -u www-data prestashop php bin/console cache:clear --env=dev
```

## 2. Instalar el módulo

```bash
docker compose exec -u www-data prestashop php bin/console prestashop:module install khipupayment
```

El `-u www-data` importa: por omisión `exec` entra como root y los archivos de
caché que genere quedan de root, y después Apache no puede escribir ahí. Si ya
pasó, se arregla con
`docker compose exec prestashop chown -R www-data:www-data /var/www/html/var`.

También se puede desde el administrador, en **Módulos → Administrador de
módulos**, buscando «khipu».

Para probar el camino de actualización (que es el que se rompe en las tiendas
reales), instala primero la versión publicada desde el `.zip` y recién después
monta el repositorio encima.

## 3. Configurar credenciales

En **Módulos → khipu → Configurar**, completa **API Key**, **ID Cobrador** y
**Llave secreta** de una cuenta de Khipu.

> Usa una cuenta de pruebas. Un pago hecho desde esta tienda es un pago real.

## 4. Probar un pago

**Hace falta un túnel.** Khipu valida el `notify_url` al crear el pago y
rechaza cualquiera que apunte a `localhost`:

```
campo notify_url: La URL http://localhost:8080/modules/khipupayment/validate.php no es válida
```

Sin túnel, el checkout siempre termina en «No se pudo procesar el pago con
Khipu». Con túnel, además, las notificaciones llegan solas.

```bash
./tunel.sh          # levanta el túnel y le configura el dominio a PrestaShop
./tunel.sh --stop   # lo baja y vuelve a localhost:8080
```

Usa la URL pública que imprime para hacer la compra. Después: agregar al carro
→ checkout → Khipu → volver a la tienda, todo real.

> Mientras el túnel esté arriba, la tienda y su administrador quedan accesibles
> para cualquiera que tenga la URL. Por eso `./tunel.sh` cambia la clave del
> administrador por una al azar (la imprime al levantarlo; si la pierdes, vuelve
> a correrlo) y apaga el modo desarrollo, para no mostrar trazas de error al
> público. Los errores de PHP quedan en `docker compose logs prestashop`.
> `--stop` devuelve la clave `khipu1234` y el modo desarrollo. Bájalo al terminar.
>
> Lo del modo desarrollo está probado en PrestaShop 8.1. En 1.7.8 la imagen lo
> enciende de otra forma y puede que no se apague; el script avisa si pasa.

### a) Simular la notificación (sin exponer nada)

Solo sirve para un pedido que ya exista. Como crear el pedido requiere que
Khipu acepte el pago, en la práctica esto es para repetir notificaciones o
probar rechazos, no para reemplazar el túnel.

```bash
KHIPU_SECRET=<tu llave secreta> KHIPU_RECEIVER_ID=<tu id de cobrador> \
  ./notificar.sh <referencia del pedido> <monto>
```

El script firma el cuerpo igual que Khipu (HMAC-SHA256 de `<t>.<cuerpo>` en
base64), así que el módulo no puede distinguirlo de una notificación real.

- La **referencia** es la del listado de pedidos del administrador.
- El **monto** tiene que ser exactamente el total del pedido, redondeado como
  lo hace el módulo: sin decimales en CLP.
- Respuesta esperada: `200 Notification received correctly`, y el pedido pasa a
  **Pago aceptado**.

Sirve también para probar los rechazos: cambia un dígito del monto, usa otra
llave secreta o quita la cabecera y comprueba que responde **400** y que el
pedido no se mueve.

### b) Notificación real de Khipu

Con `./tunel.sh` arriba no hay nada que hacer: Khipu notifica al
`notify_url` público y el pedido pasa solo a **Pago aceptado**.

El script hace las tres cosas que hay que recordar: levanta el túnel, escribe
el dominio en `ps_shop_url` y en `PS_SHOP_DOMAIN(_SSL)`, y activa SSL en toda
la tienda para que el regreso desde Khipu —que siempre es https— no pierda la
sesión.

## 5. Probar reversas

El panel **Reversa Khipu** aparece en la ficha de un pedido pagado con el
módulo. Con una notificación simulada puedes verificar que se dibuja, los
saldos, la validación del monto y el camino de error.

La reversa en sí **no se puede simular**: el módulo llama a la API real de
Khipu, que rechaza un `payment_id` inventado. Para el camino feliz necesitas un
pago real de una cuenta de pruebas con la billetera de reversas habilitada.

## 6. Otras versiones de PrestaShop

```bash
docker compose down -v                        # borra tienda y base de datos
PS_TAG=1.7.8-apache docker compose up -d
```

Etiquetas útiles: `8.1-apache` (por defecto), `8.2-apache`, `1.7.8-apache`. La
ficha de pedido cambió de Bootstrap 3 a 4 en 1.7.7, así que el panel de reversa
conviene mirarlo en las dos puntas del rango.

## 7. Apagar

```bash
docker compose stop            # conserva la tienda
docker compose down -v         # borra tienda, base de datos y configuración
```

## Problemas frecuentes

| Síntoma | Qué pasa |
|---|---|
| `mounts denied` al levantar | Docker Desktop solo comparte rutas autorizadas. Agrega la carpeta del repositorio en Settings → Resources → File Sharing |
| El puerto 8080 está ocupado | Cambia `8080:80` por otro puerto en `docker-compose.yml` y ajusta `PS_DOMAIN` |
| `Call to undefined method PhpParser\ParserFactory::create()` | Se está cargando el `vendor/` del repositorio dentro de la tienda. El `docker-compose.yml` lo tapa con un volumen vacío; si editaste ese archivo, revisa que la línea siga ahí |
| `SSL received a record that exceeded the maximum permissible length` en `localhost:8080` | Hay un túnel arriba: la tienda quedó en modo SSL y redirige a https, pero ese puerto solo habla http. Entra por la URL del túnel (`./tunel.sh --status`) o bájalo con `./tunel.sh --stop` |
| `Cannot rename ... var/cache/dev/...` | Caché generada por root. `docker compose exec prestashop chown -R www-data:www-data /var/www/html/var` |
| `Failed opening required ... appParameters.php` | Falta la caché del entorno dev, normalmente tras recrear el contenedor. `docker compose exec -u www-data prestashop php bin/console cache:warmup --env=dev` |
| Cambios en `.tpl` que no se ven | Borra la caché (paso 1) |
| Cambios en `.php` que no se ven (Docker Desktop) | El montaje del repositorio puede quedarse con la versión anterior de un archivo que el editor guardó reemplazándolo. Compara `md5sum` del archivo en el host y en `/var/www/html/modules/khipupayment/` del contenedor; si difieren, `docker compose restart prestashop` |
| Página en blanco | `docker compose logs prestashop`; con `PS_DEV_MODE: 1` el error sale en pantalla (salvo con el túnel arriba, que apaga el modo desarrollo) |
| Khipu no aparece en el checkout | Credenciales mal puestas: sin medios de pago válidos el módulo no ofrece nada. Si Khipu falló hace menos de un minuto, el fallo está en caché: espera o vuelve a guardar la configuración del módulo |

Esta carpeta es solo para desarrollo: `package.sh` la excluye del `.zip` que se
instala en las tiendas.
