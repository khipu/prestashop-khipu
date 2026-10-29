# Khipu para PrestaShop

Módulo oficial de [Khipu](https://khipu.com) para recibir pagos por transferencia en
PrestaShop, con soporte de reversas (devolución de dinero) desde la ficha del pedido.

- **Módulo:** `khipupayment` · **Versión:** 4.5.0 · **API de Khipu:** v3
- **Compatibilidad:** PrestaShop 1.7 – 8.x, PHP con extensión cURL habilitada

## Documentación

| Para | Documento |
|---|---|
| Instalar, configurar, operar, reversar y actualizar | [docs/manual-comercio.md](docs/manual-comercio.md) |
| Arquitectura, flujos, API, desarrollo y empaquetado | [docs/manual-tecnico.md](docs/manual-tecnico.md) |
| Manual publicado | [docs.khipu.com → Ecommerce → Prestashop](https://docs.khipu.com/payment-solutions/instant-payments/khipu-ecommerce#prestashop) |

## Instalación rápida

1. Descarga `khipupayment.zip` desde la
   [última versión publicada](https://github.com/khipu/prestashop-khipu/releases/latest).
2. En el back-office: **Módulos → Añadir nuevo módulo**, sube el zip e instala.
3. **Configurar** el módulo y completar **API Key**, **ID Cobrador** y **Llave secreta**
   (los tres están en tu cuenta Khipu, en *Opciones de la cuenta → Para integrar Khipu a
   tu sitio web*).
4. Ajustar el **título** (defecto: `Khipu`), la **descripción** (defecto: `Paga con tu
   banco`), el **modo de pago**, los **medios** que quieres ofrecer y los **minutos** de
   espera antes de cancelar un pedido impago (defecto: 360), y guardar.

Requiere que la tienda sea alcanzable por HTTPS desde Internet: Khipu confirma cada pago
mediante una notificación a `https://<tu-tienda>/modules/khipupayment/validate.php`.

## Medios de pago

El checkout muestra **una fila por medio de pago**, con el nombre `{Título} - {Medio}`
(por defecto, `Khipu - Transferencia normal`):

| Medio | Imagen |
|---|---|
| **Transferencia normal** | Sí |
| **Transferencia simplificada** | Sí |
| **LigoPay** | No |
| **Pago por QR** (Argentina) | No |

Una fila aparece cuando **tu cuenta Khipu tiene ese medio habilitado** (el módulo lo
consulta en `paymentMethods`) **y** lo dejaste marcado en el panel de configuración. El
módulo no aplica reglas por moneda ni por país: lo que decide es tu cuenta. La restricción
de monedas y países de **Preferencias de pago** de PrestaShop sigue valiendo, porque es de
la plataforma. En Perú no hay botón único: cada medio que tenga tu cuenta (por ejemplo,
transferencia normal y LigoPay) tiene su propia fila; en Argentina, el pago por QR y la
transferencia simplificada también.

Si Khipu no responde un momento, el checkout sigue mostrando los medios del último
resultado bueno.

## Modo de pago: Embebido o Redirección

Un interruptor en el panel (Embebido a la izquierda, por defecto; Redirección a la derecha):

- **Embebido** abre Khipu Web dentro de la tienda. Solo aplica a la **transferencia
  simplificada**; el resto de los medios siempre redirige al sitio de Khipu.
- **Redirección** lleva al comprador al sitio de Khipu.

El interruptor solo se puede mover si tu cuenta tiene transferencia simplificada; si no,
queda fijo en Redirección. Si Khipu Web no carga en 10 segundos, se redirige al sitio de
Khipu. Si el pago embebido termina en error (incluido el abandono, cuando Khipu Web lo informa así), se muestra el motivo, el pedido se cancela y el carro se rehace en segundo plano, y el comprador vuelve con un enlace cuando termina de leerlo (la página no cambia sola).

> **Seguridad y servicios externos:** el módulo llama a la API de Khipu
> (`https://payment-api.khipu.com`). En modo embebido, además, el navegador del comprador
> carga `https://js.khipu.com/v1/kws.js`.

## Actualizar a 4.5.0

Los ajustes nuevos no se migran. Tras actualizar, el título y la descripción vuelven a sus
valores por defecto: vuelve a ingresarlos si quieres otros, y revisa los medios marcados
(por defecto se ofrecen todos los de tu cuenta). El modo por defecto es Embebido.

## Desarrollo

```bash
composer install       # PHPUnit (dependencia solo de desarrollo)
./vendor/bin/phpunit   # tests de la lógica pura (sin PrestaShop ni red)
./package.sh           # genera dist/khipupayment.zip
```

Tienda de pruebas con Docker (PrestaShop + MySQL, módulo montado desde el
repositorio):

```bash
cd dev && docker compose up -d      # http://localhost:8080/admin-khipu
```

Instrucciones completas —incluido cómo simular la notificación de pago de Khipu
sin exponer la tienda a Internet— en [dev/README.md](dev/README.md).

Detalles de arquitectura, versionado y checklist de release en
[docs/manual-tecnico.md](docs/manual-tecnico.md).

## Soporte

**soporte@khipu.com** — indica versión del módulo y de PrestaShop, número de pedido y, en
reversas, el ID de pago que queda en la nota del pedido. Nunca envíes tu Llave secreta ni
tu API Key.

Para PrestaShop 1.5 y 1.6 (sin soporte): <https://github.com/khipu/prestashop1.6-khipu>
