# Manual técnico — módulo Khipu para PrestaShop

Documentación de la implementación: cómo se integra con PrestaShop, qué llama a la API de
Khipu, qué persiste y cómo se desarrolla y empaqueta.

Para instalar y operar el módulo, ver [manual-comercio.md](manual-comercio.md).

- **Módulo:** `khipupayment`
- **Versión:** 4.5.0 (fuente única: `classes/KhipuVersion.php`)
- **API de Khipu:** v3 (`https://payment-api.khipu.com/v3`)
- **Compatibilidad:** PrestaShop 1.7 – 8.x, PHP 7.x/8.x, extensión cURL obligatoria

---

## 1. Cómo se engancha con PrestaShop

Un módulo de pago de PrestaShop es una clase que extiende `PaymentModule`, se registra en
hooks y aporta controladores propios. Este módulo usa cuatro piezas de la plataforma:

| Pieza de PrestaShop | Qué hace aquí |
|---|---|
| **Hooks** | `paymentOptions` (ofrecer medios de pago), `paymentReturn` (página de confirmación), `displayAdminOrderMainBottom` + `displayAdminOrder` (panel de reversa) |
| **Front controllers** | `controllers/front/{simplified,manual,ligopay,nave,embedded,validate}.php`, accesibles vía `getModuleLink()` |
| **Admin controller** | `controllers/admin/AdminKhipuRefundController.php`, ejecuta la reversa |
| **`Configuration`** | Almacén clave-valor donde viven credenciales, ajustes (título, descripción, modo, medios) y las cachés |

Fuera de ese marco hay **un punto de entrada directo**: `validate.php` en la raíz del
módulo, que recibe la notificación de pago de Khipu. No es un controlador de PrestaShop
—hace `require` de `config.inc.php` e `init.php` a mano— porque debe responder a un POST
de servidor a servidor sin pasar por el front office.

### Mapa de archivos

```
khipupayment.php                      Clase del módulo: install/uninstall, hooks, configuración
validate.php                          Punto de entrada del webhook de Khipu (URL pública)
KhipuPostBack.php                     Lógica del webhook: firma, validación, cambio de estado
config.xml / config_es.xml            Metadatos del módulo (versión declarada a PrestaShop)

classes/
  KhipuVersion.php                    Versión del plugin y de la API; arma el User-Agent
  KhipuHttp.php                       Transporte cURL compartido (aquí viven los timeouts)
  KhipuApi.php                        Cliente de los endpoints de pago (checkout)
  KhipuMethods.php                    Lógica pura de medios: etiquetas, título compuesto, selección, embebido
  KhipuEmbeddedRules.php              Lógica pura del embebido: payment_id, URL https, cuándo se puede cancelar
  KhipuCheckoutController.php         Tronco común de los controladores de checkout
  KhipuRefund.php                     ObjectModel de la tabla khipu_refund + consultas
  KhipuRefundRules.php                Lógica pura de reversa (sin PrestaShop, sin red)
  KhipuRefundService.php              Cliente HTTP de los endpoints de reversa

controllers/front/
  simplified.php                      Medio SIMPLIFIED_TRANSFER (`simplified_transfer_url`); decide si va embebido
  manual.php                          Medio REGULAR_TRANSFER (`transfer_url`)
  ligopay.php                         Medio LIGOPAY (`ligopay_url`)
  nave.php                            Medio NAVE, QR bancario de Argentina (`nave_url`)
  embedded.php                        Cancelación del pago embebido (POST JSON)
  validate.php                        Retorno del cliente: confirmación o cancelación

controllers/admin/
  AdminKhipuRefundController.php      Recibe el POST del panel y orquesta la reversa

views/templates/
  admin/config.tpl                    Pantalla de configuración del módulo
  admin/order_refund_panel.tpl        Panel de reversa en la ficha del pedido
  hook/info.tpl                       Descripción compartida bajo cada fila del checkout
  hook/payment_return.tpl             Bloque de la página de confirmación
  front/khipu_error.tpl               Error de comunicación con Khipu
  front/khipu_message.tpl             Avisos al volver de Khipu (pago recibido, carro perdido)
  front/embedded.tpl                  Página con el ancla #khipu-web-root del pago embebido
  front/validation.tpl                Plantilla heredada, sin uso en el flujo actual

upgrade/upgrade-4.4.0.php             Migración para tiendas ya instaladas
upgrade/upgrade-4.4.1.php             Sin cambios de esquema (existe porque se exige uno por versión)
upgrade/upgrade-4.5.0.php             Sin cambios de esquema ni de hooks; los ajustes nuevos no se migran
views/js/khipu-embedded.js            Cliente de Khipu Web (KWS) del pago embebido
views/img/                            khipu-transferencia-normal.png, khipu-transferencia-simplificada.png
tests/                                PHPUnit sobre la lógica pura (no se empaqueta)
package.sh                            Genera dist/khipupayment.zip
```

`composer.json` solo declara dependencias de **desarrollo** (PHPUnit). El módulo no usa
autoloader ni librerías en tiempo de ejecución: todas las llamadas son cURL a través de
`KhipuHttp`.

---

## 2. Flujo de pago

```
Checkout                         Módulo                            Khipu
   │                                │                                │
   │ elige Khipu ──────────────────►│                                │
   │                                │ validateOrder()                │
   │                                │ → pedido en "Esperando pago"   │
   │                                │ POST /v3/payments ────────────►│
   │                                │◄──── simplified_transfer_url / transfer_url / ligopay_url / nave_url
   │◄─ redirect a Khipu (o página embebida, ver 2.5)                 │
   │                                                                 │
   │ paga en su banco ──────────────────────────────────────────────►│
   │                                │◄── POST notify_url (webhook) ──│
   │                                │ verifica firma + receiver + monto
   │                                │ → pedido en "Pago aceptado"    │
   │◄─ redirect a return_url ───────│                                │
   │  → order-confirmation          │                                │
```

### 2.1 Selección del medio de pago — `hookPaymentOptions()`

1. Llama a `cancelExpiredOrders()` (ver §6).
2. Si `cartSplitsIntoSeveralOrders()` dice que el carro se va a partir en varios pedidos
   (productos que no caben en un solo paquete: transportistas, direcciones o almacenes
   distintos), no ofrece Khipu. Un pago lleva una sola referencia y un solo monto, y el
   webhook rechaza una referencia con más de un pedido.
3. `getVisibleMethodIds()` cruza dos cosas y nada más:
   - los medios de la cuenta: `KhipuApi::getPaymentMethods()` →
     `GET /v3/merchants/{KHIPU_MERCHANTID}/paymentMethods`. `KhipuMethods::accountMethodIds()`
     devuelve todos sus ids, sin repetir y en el orden de Khipu, **menos los excluidos**
     (lista fija en `KhipuMethods`, hoy solo `DIRECT_DEBIT`, que no sirve para pagar una
     compra). **Orden de visualización** (`KhipuMethods::sortForDisplay()`): `SIMPLIFIED_TRANSFER` primero, `REGULAR_TRANSFER` al final y el resto en medio, en el orden de Khipu; son dos reglas independientes. Esa lista es la del panel: un medio que el módulo no conoce aparece como
     casilla. Para el checkout, `getVisibleMethodIds()` se queda además solo con los que
     tienen controlador (`REGULAR_TRANSFER`, `SIMPLIFIED_TRANSFER`, `LIGOPAY`, `NAVE`): un medio sin
     controlador puede estar marcado y no genera ninguna fila ni rompe nada;
   - las casillas del panel (`KHIPU_ENABLED_METHODS`, ver §4).
4. Por cada id visible, `getPaymentOptionFor()` arma una fila `PaymentOption`:

   | Medio | Controlador | Clave de la URL | Imagen |
   |---|---|---|---|
   | `REGULAR_TRANSFER` | `manual` | `transfer_url` | `khipu-transferencia-normal.png` |
   | `SIMPLIFIED_TRANSFER` | `simplified` | `simplified_transfer_url` | `khipu-transferencia-simplificada.png` |
   | `LIGOPAY` | `ligopay` | `ligopay_url` | ninguna |
   | `NAVE` | `nave` | `nave_url` | ninguna |

   El nombre de la fila es `KhipuMethods::composeTitle()`: `{Título} - {Medio}` (títulos
   `Transferencia normal`, `Transferencia simplificada`, `LigoPay`, `Pago por QR`); un título vacío cae a
   `Khipu`. Bajo la fila va `hook/info.tpl` con la descripción.

**El módulo no decide por moneda ni por país.** No hay ningún `if` por moneda o país: una
fila existe si la cuenta tiene el medio y está marcado. Sigue valiendo la restricción de
monedas, países y grupos de *Preferencias de pago*, que es de PrestaShop (y que los
controladores vuelven a comprobar, ver §2.2). Perú ya no tiene botón único: cada medio de
la cuenta tiene su fila. Lo mismo en Argentina: una cuenta AR trae `NAVE` (el QR de
Transferencias 3.0; Nave es el proveedor) y `SIMPLIFIED_TRANSFER`, y cada uno es una fila.

**Caché y tolerancia a fallos.** `getAccountMethods()` guarda la lista en
`KHIPU_PAYMENT_METHODS_CACHE`: 5 minutos si la respuesta fue buena, 1 minuto si falló (para
no colgar a cada comprador mientras Khipu no responde), con una huella MD5 de API Key + id
de cobrador. Junto a la entrada se guarda el **último resultado bueno** (`last_good`) de esa
misma cuenta: si Khipu falla después de un éxito, el checkout sigue ofreciendo los medios
que ya conocía. Sin éxito previo (tienda nueva, credenciales nuevas: el cambio de
credenciales descarta la caché y el último bueno) un fallo devuelve `null` y no hay filas.
Sin credenciales configuradas no se llama a Khipu. Cuando sí se llama, el techo es de 15 s.

### 2.2 Creación del pago — `KhipuCheckoutController`

El flujo vive entero en `classes/KhipuCheckoutController.php`. Los controladores
`simplified.php`, `manual.php`, `ligopay.php` y `nave.php` solo declaran su medio (`getMethodId()`) y
qué clave de la respuesta de Khipu lleva la URL de pago (`getPaymentUrlKey()`); además,
`simplified.php` decide si va embebido (`usesEmbedded()`).

Secuencia:

0. Resguardos, los mismos de los controladores de pago del core: módulo activo, carro con
   cliente, direcciones y productos, carro sin pedido previo, módulo autorizado en
   *Preferencias de pago* (país y grupo con `Module::getPaymentModules()`; la moneda
   aparte, con `getCurrency()` del módulo, porque ese método no la mira y el hook sí) y
   medio visible: el `getMethodId()` del controlador tiene que estar en
   `getVisibleMethodIds()` (cuenta + casilla), porque la URL del controlador es pública.
   No hay comprobación de moneda propia del módulo. Si alguno falla, redirige al paso 1 del checkout sin
   crear nada. Si el carro se divide en varios pedidos, muestra `khipu_error.tpl` con la
   explicación, también sin crear nada.
1. `validateOrder(id_cart, PS_OS_KHIPU_OPEN, total, …)` — crea el pedido **antes** de
   redirigir, en el estado *Esperando pago Khipu*. El stock se descuenta aquí. El pedido se
   toma de `$module->currentOrder`. Si no queda cargado, el flujo se corta ahí: un pago con
   `transaction_id` vacío no lo podría reconciliar nunca la notificación.
2. `POST /v3/payments` con:

   | Campo | Valor |
   |---|---|
   | `subject` | `{nombre de la tienda} Carro #{id_cart}` |
   | `currency` | ISO de la moneda del carro |
   | `amount` | `total_paid_tax_incl` del pedido, redondeado con `KhipuApi::amountPrecision()` (0 para CLP, 2 para el resto): el mismo número que compara el webhook |
   | `transaction_id` | **Referencia del pedido** (`$order->reference`) — la clave con la que el webhook lo encuentra |
   | `custom` | `id_order` |
   | `return_url` / `cancel_url` | Front controller `validate` con `return=ok` / `return=cancel` |
   | `notify_url` | `https://{dominio SSL}{base}/modules/khipupayment/validate.php` |
   | `body` | Detalle de productos (`cantidad x nombre` por línea) |
   | `payer_email` | Correo del cliente |
   | `notify_api_version` | `3.0` |

3. Si la respuesta no es 200, o no trae la URL de su propio medio
   (`KhipuApi::selectRedirectUrl()`: ninguna otra URL la reemplaza), **el pedido se cancela** (libera
   el stock), se registra el motivo en el log —incluido el error de transporte si hubo
   timeout, DNS o TLS—, el carro se rehace en la sesión (`KhipuPayment::restoreCartOf()`)
   y se renderiza `front/khipu_error.tpl` con un botón *Volver a intentar* que lleva al
   checkout. No se usa el `submitReorder` del core porque solo atiende a clientes con
   cuenta: a un invitado lo dejaba con el carro vacío. Si el carro no se puede rehacer
   (un producto que se agotó o se desactivó), la página lo dice y no muestra el botón,
   que llevaría a un checkout vacío.
4. Con la URL, `Tools::redirect()` al portal de Khipu; salvo en el modo embebido (§2.5).

> `notify_url` se construye con `Tools::getShopDomainSsl()`. Si el dominio SSL de la tienda
> está mal configurado en PrestaShop, la notificación se dirige a una URL equivocada y
> ningún pago se confirma.

### 2.3 Regreso del cliente — `controllers/front/validate.php`

La referencia viene en la URL, así que las dos ramas exigen que el pedido sea del cliente
con sesión abierta (el invitado también cuenta: PrestaShop lo deja en la sesión al
terminar la compra). Solo se acepta como texto: `Tools::getValue()` devuelve arreglos tal
cual y `Order::getByReference()` convierte `reference[]=A&reference[]=B` en un `IN (...)`,
así que un cliente con un pedido propio podía traerse pedidos ajenos. Por lo mismo,
`return=cancel` comprueba el dueño de **cada** pedido de la colección.

- `return=cancel`: los pedidos de esa referencia que sigan en *Esperando pago Khipu* pasan
  a `PS_OS_CANCELED` y se redirige al checkout. El carro se rehace con `restoreCartOf()`
  (también para invitados) si el pedido quedó cancelado —ahora o antes, por
  `cancelExpiredOrders()` si el comprador se demoró en Khipu— y la sesión no tiene ya un
  carro con productos (un segundo clic, o uno nuevo, no se pisa). Un pedido ya pagado no
  se rehace: sería invitar a comprarlo dos veces. Si el carro no se puede rehacer, se
  muestra `front/khipu_message.tpl` con la explicación. Sin la sesión del cliente, redirige
  al inicio sin cancelar; el pedido lo cancela `cancelExpiredOrders()` al vencer.
- `return=ok`: redirige a `order-confirmation` con `id_cart`, `id_module`, `id_order` y
  `secure_key`. Sin la sesión del cliente, muestra `front/khipu_message.tpl` («Gracias por
  tu pago», sin datos del pedido) y no entrega la clave: en PrestaShop 8 la `secure_key`
  basta para ver la confirmación y, en pedidos de invitado, ponerle contraseña a la
  cuenta. No se manda al historial: pide iniciar sesión, y un invitado que pagó en la app
  del banco y volvió en otro navegador no tiene con qué.
- Referencia inexistente: una entrada fija en el log, a lo más una por hora y sin la
  referencia (para que un escáner no llene la tabla con texto suyo), y redirige al
  inicio. El límite es propio: el `allow_duplicate = false` de `PrestaShopLogger` no
  detecta duplicados en contexto de tienda (compara `id_shop_group = 0` contra `NULL`).
  La hora del último aviso vive en `KHIPU_UNKNOWN_REF_LOGGED_AT` y no se busca en
  `ps_log`: esa tabla no tiene índice sobre `message` ni `date_add`, y cada petición de un
  escáner la recorría entera.

Este controlador **no** marca el pedido como pagado: eso lo hace solo el webhook.

### 2.4 Confirmación del pago — webhook

`validate.php` → `KhipuPostBack::init()` → `handlePOST()`:

1. Responde `400` por defecto; solo el camino feliz lo cambia a `200`. Los campos
   obligatorios tienen que ser escalares (un arreglo en `transaction_id` llegaría como
   `IN (...)` a `getByReference()`).
2. Verifica la cabecera **`X-Khipu-Signature`**, de la forma `t=<timestamp>,s=<firma>`:

   ```
   firma_esperada = base64( HMAC-SHA256( "<t>.<cuerpo crudo>", KHIPU_SECRETCODE ) )
   ```

   Se compara con `hash_equals()`. Si no calza: `400 Invalid signature`. Una cabecera
   ausente, a medias o sin llave secreta configurada cae en esa misma rama: esta URL es
   pública y un 500 le diría a quien sondea que llegó más lejos que el resto.
3. Exige que el cuerpo traiga `transaction_id`, `receiver_id`, `amount` y `payment_id`.
4. Busca el pedido por `Order::getByReference($payload['transaction_id'])`. Cero o más de
   una coincidencia aborta. Desde acá hasta la respuesta corre con un candado MySQL por
   pedido (`GET_LOCK`, hasta 10 s de espera; si no se consigue, `500` para que Khipu
   reintente), y el pedido se relee ya con el candado tomado. Khipu reintenta mientras no
   recibe respuesta, así que dos notificaciones del mismo pago pueden llegar juntas; sin
   candado, las dos veían que faltaba el pago y lo registraban dos veces.
5. Valida que `receiver_id` sea igual a `KHIPU_MERCHANTID` **y** que el monto coincida con
   `total_paid_tax_incl` redondeado con `KhipuApi::amountPrecision()` (precisión **0 para
   CLP**, 2 para el resto; la misma que usa el checkout).
6. Si el pedido **nunca estuvo pagado** (ni historial con un estado `paid` ni estado actual
   `paid`, leído de la base; el estado actual cuenta porque `setCurrentState()` lo cambia
   antes de escribir el historial, y si eso último falla el reintento quedaba trabado),
   lo cambia a `PS_OS_PAYMENT` y comprueba que haya quedado pagado: `setCurrentState()`
   no avisa si no hizo nada (por ejemplo con `PS_OS_PAYMENT` vacío), y un 200 en ese caso
   dejaba un pedido pagado en espera hasta que `cancelExpiredOrders()` lo cancelara. Si no
   quedó pagado, es un fallo del paso 8 (`500`).
7. En toda notificación válida, registra el `payment_id` en `order_payment.transaction_id`
   si no está ya en alguna fila. Solo reutiliza una fila **sin identificador, de Khipu y con
   el mismo monto** que cobró Khipu (la que crea PrestaShop al pasar el pedido a pagado).
   «De Khipu» es que su medio de pago sea el del pedido o el nombre actual del módulo, sin
   distinguir mayúsculas, como compara MySQL: una fila de otro medio no se reutiliza aunque
   calce el monto. Al reutilizarla le deja el medio del pedido, que es el que busca
   `getPaymentRowForOrder()`, y el monto exacto de Khipu (con precisión de cálculo 2, PrestaShop
   anota 11900.50 y Khipu cobra 11901, y la reversa calcula el saldo con el monto de esa
   fila). Después cuadra `total_paid_real` con la suma de los pagos del pedido, que es la
   regla con que lo lleva PrestaShop; con pagos en otra moneda no lo toca. Nunca pisa el identificador de
   otro pago. Si no hay fila reutilizable, crea una con
   `addOrderPayment()` y el monto de Khipu, y si el pedido ya tenía otros pagos deja un
   aviso en el log: puede ser el mismo dinero anotado dos veces. Sin ese identificador el
   panel de reversa nunca aparece; `KhipuRefund::getPaymentRowForOrder()` solo considera
   filas de Khipu (`khipuPaymentMethodNames()`: el medio del pedido o «khipu», sin
   distinguir mayúsculas), así que un pago manual de otro medio nunca llega a la API de
   reversas. Sin fila de Khipu no hay panel.
8. `200 Notification received correctly`. Si los pasos 6 o 7 fallan —devuelven `false` o
   lanzan, como hace PrestaShop 8 ante un error de base de datos— responde **`500 Could
   not record payment`** y deja el motivo en el log, para que Khipu reintente. El reintento
   no vuelve a cambiar el estado y solo intenta de nuevo guardar el `payment_id`. Si lo
   encuentra ya guardado —la fila se escribió pero el total del pedido no—, antes de
   responder `200` cuadra `total_paid_real`: guardar la fila y el total no es atómico, y
   sin esto el reintento daba el pago por registrado con el total desactualizado.

La idempotencia viene de los pasos 6 y 7: una notificación repetida sobre un pedido que ya
pasó por un estado pagado —aunque ahora esté enviado, entregado o reembolsado— responde
`200` sin volver a cambiar el estado ni reenviar el correo, y el `payment_id` solo se
escribe si falta.

### 2.5 Pago embebido (Khipu Web)

Solo lo tiene `SIMPLIFIED_TRANSFER`. `simplified.php::usesEmbedded()` devuelve
`isEmbeddedActive()`: el comercio lo quiere (`KHIPU_EMBEDDED` = `1`, el valor por defecto)
**y** la cuenta lo permite (`SIMPLIFIED_TRANSFER` en `paymentMethods`,
`KhipuMethods::embeddedAvailable()`). Con el modo en Embebido y la simplificada sin marcar
no pasa nada: la fila no existe.

Con el modo activo, tras crear el pedido y el pago, en vez de redirigir el controlador
muestra `front/embedded.tpl`:

- Solo se usa si la URL del medio es `https` con host (`KhipuEmbeddedRules::isHttpsUrl()`):
  termina en un `href` y en `window.location`. Si no, se sigue el camino de error de
  siempre. Si la respuesta no trae un `payment_id` utilizable, se redirige a la URL del
  medio como si no hubiera embebido.
- La plantilla pone el ancla `#khipu-web-root` con `data-*` (`payment-id`, `return-url`,
  `fallback-url`, `cancel-url`, `reference`, `token`, `kws-url`) y registra
  `views/js/khipu-embedded.js`. El token es `Tools::getToken(false)`; no se llama `token`
  en Smarty para no pisar el global de PrestaShop.
- El JS carga `https://js.khipu.com/v1/kws.js` **en el navegador del comprador** y abre el
  modal (`startOperation`, color `#8347AD`). **Seguridad y servicios externos:** ese
  dominio es el único origen extra que carga el navegador en el modo embebido; con
  Redirección no se carga.
- `OK`, `WARNING`, `CONTINUE` y cualquier resultado que no sea `ERROR` llevan a
  `validate` con `return=ok`; el estado del pedido lo decide la notificación, no esta
  página.
- Si KWS no carga en 10 s o lanza una excepción, redirige a la URL del medio
  (`simplified_transfer_url`).
- `ERROR` muestra bajo el modal «El pago no se completó: <motivo>» (`exitMessage` o
  `failureReason` del resultado, escritos como texto) y llama al endpoint de cancelación **en
  segundo plano**: la página no navega sola, para que el comprador alcance a leer el motivo;
  cuando el endpoint responde se agrega el enlace «Volver a mi carrito» (o «Ver mi pedido» si
  no se canceló nada). El abandono del comprador se trata así cuando Khipu Web lo informa como
  `ERROR`; cualquier otro resultado va al retorno normal.
- `startOperation` se llama con `skipExitPage: false`, así Khipu Web muestra su propia página de
  salida dentro del modal (con `true` el modal se cerraba apenas llegaba el error).

**Endpoint de cancelación — `controllers/front/embedded.php`.** POST con `reference`,
`payment_id` y `token`. Responde siempre JSON `{"ok": bool, "redirect": url}` (`Cache-Control:
no-store`); los rechazos van con HTTP 403 y el mismo cuerpo, que el JS lee igual. Comprobaciones,
en orden:

1. Método POST, `token` igual a `Tools::getToken(false)` (`hash_equals`), `payment_id` no
   vacío, y los tres valores deben ser texto (no arreglos).
2. La referencia resuelve **un solo** pedido (`Order::getByReference()`) y es del visitante
   con sesión (`KhipuCheckoutController::belongsToVisitor()`). Si algo de esto falla no se
   devuelve nada del pedido: solo el inicio de la tienda.
3. El pedido sigue en *Esperando pago Khipu*. Si no, no se pregunta a Khipu y se manda al
   retorno normal.
4. `KhipuEmbeddedRules::canCancel()`: `GET /v3/payments/{id}` debe traer un
   `transaction_id` igual a la referencia del pedido (el `payment_id` viene del navegador;
   sin esto, con la referencia propia se podría borrar el pago de otro comprador) y estar
   `pending` (o sin estado).
5. `DELETE /v3/payments/{id}`; solo 2xx o 404 cuentan (`deleteSucceeded()`). Cualquier otra
   cosa deja el pedido abierto y lo registra en el log: cancelarlo con el pago pagable
   permitiría pagar un pedido cancelado; si nadie paga, lo cancela `cancelExpiredOrders()`.
6. Se relee el estado del pedido de la base (sin caché). Si ya no está abierto, no se
   cancela otra vez.
7. Se cancela (`PS_OS_CANCELED`) y se rehace el carro con `restoreCartOf()` (sin pisar un
   carro propio con productos), y se redirige al paso 1 del checkout. Si el carro no se
   puede rehacer, al retorno con `return=cancel`, que muestra el aviso.

Un pedido por clic: PrestaShop crea un pedido nuevo en cada intento, así que no hay lógica
de reutilizar pagos.

---

## 3. Reversas (API v3)

Añadidas en 4.4.0. La feature está partida en cuatro piezas con responsabilidades
separadas, para que la lógica delicada se pueda probar sin levantar PrestaShop:

| Clase | Responsabilidad | Depende de |
|---|---|---|
| `KhipuRefundRules` | Validar montos, parsear errores de Khipu, clasificar desenlaces | Nada (PHP puro) |
| `KhipuRefundService` | Hablar HTTP con la API v3 | `KhipuRefundRules`, `KhipuVersion` |
| `KhipuRefund` | Persistir y consultar el historial | PrestaShop (`ObjectModel`) |
| `AdminKhipuRefundController` | Orquestar: validar → llamar → interpretar → persistir → reflejar → avisar | Todas |

El render del panel vive en `KhipuPayment::hookDisplayAdminOrderMainBottom()` y **solo
lee**: ninguna mutación ocurre en un hook de display.

### 3.1 Endpoints usados

| Endpoint | Uso | Campos obligatorios en el 200 |
|---|---|---|
| `GET /v3/refund-wallet/balance` | Saldo de la billetera y si la cuenta tiene el flag | `balance` |
| `POST /v3/refunds` | Ejecutar la reversa (`type`: `full` \| `partial`; `amount` **solo** en parcial, con los decimales de `KhipuApi::amountPrecision()`: en CLP, ninguno) | `id`, `payment_id`, `refunded_amount`, `total_refunded`, `remaining` |
| `GET /v3/payments/{id}` | Resolver un desenlace ambiguo vía `status_detail` | `transaction_id` |

Timeouts: 10 s de conexión, 20 s total —más largos que los del checkout (5/15) porque
aquí quien espera es un administrador, no un comprador frente a una página. El transporte
se inyecta por constructor (`KhipuRefundService::__construct($apiKey, $transport)`) para
poder testear sin red.

Un `200` que no traiga los campos obligatorios **no cuenta como éxito**: se trata como
resultado desconocido. Es la defensa contra un proxy o WAF que responda
`200 {"message": "..."}` sin haber hablado con Khipu.

### 3.2 Clasificación del desenlace

`KhipuRefundRules::classifyOutcome()` reduce cada respuesta a tres casos, y de ahí sale
todo el comportamiento:

| Desenlace | Cuándo | Qué hace el módulo |
|---|---|---|
| `ok` | 200 con cuerpo completo | Persiste, deja nota, refleja en contabilidad, avisa éxito |
| `rejected` | 4xx | **No muta nada.** Nota privada con el motivo y aviso al admin |
| `unknown` | 5xx, timeout, error de transporte, o 200 ilegible | **No muta nada.** Consulta `GET /v3/payments/{id}` para decirle al admin si puede reintentar |

La distinción importa: en QA se observó a Khipu responder 500 **habiendo procesado la
reversa**. Afirmar "falló" ahí llevaría al comerciante a reintentar y devolver el dinero
dos veces.

### 3.3 Estado de la billetera — `getWalletState()`

Devuelve cuatro señales que no deben colapsarse:

| Campo | Significado |
|---|---|
| `configured` | Hay API Key guardada |
| `enabled` | La cuenta tiene el flag de billetera (`false` **solo** ante un error identificado como flag apagado) |
| `reachable` | Se pudo consultar a Khipu |
| `balance` / `currency` / `add_funds_url` | Saldo y enlace de recarga (solo se acepta `https://`) |

Se cachea 30 s en `KHIPU_WALLET_CACHE`, con una huella MD5 de la API Key para que la caché
de una cuenta no se le sirva a otra. Usa los mismos `readConfigCache()` /
`writeConfigCache()` que la caché de medios de pago: JSON `{key, value, expires_at}` en
`Configuration`. Una entrada con el formato anterior cuenta como vencida. `getContent()` (pantalla de configuración) consulta
siempre con `$force = true`.

Un fallo de red **no** esconde el panel: solo lo hace `enabled === false`, que se
determina por `field === 'receiver_id'` en el error de Khipu, con el texto del mensaje
como refuerzo secundario (`KhipuRefundRules::WALLET_DISABLED_NEEDLE`).

### 3.4 Saldo reversable

La API de Khipu no expone ningún GET para consultar el saldo reversable de un pago: solo
llega en la respuesta del POST. Por eso se deriva localmente:

```
remaining = MIN(khipu_refund.remaining) del pedido   ← si hay reversas
          = order_payment.amount                     ← si no hay ninguna
```

Se toma el **mínimo**, no la fila más reciente: con dos reversas concurrentes la última
insertada puede traer un `remaining` mayor e inflar el saldo local.

### 3.5 Protección contra doble envío

Un nonce de un solo uso por empleado, guardado en `Configuration` con el nombre
`KHIPU_REFUND_NONCE_{id_employee}`:

- Se genera en el render del panel, y solo si no hay uno vigente (rotarlo en cada render
  invalidaría un formulario abierto en otra pestaña).
- Se consume con un `DELETE ... WHERE name = ? AND value = ?` crudo y se comprueba
  `Affected_Rows() === 1`. MySQL serializa ese DELETE, así que de dos peticiones
  simultáneas exactamente una gana.

Va en base de datos y no en la cookie justamente por eso: dos envíos simultáneos leen la
misma cookie y ambos la darían por válida. **Khipu no deduplica reversas**: dos envíos que
pasan son dos devoluciones de dinero.

Además: permiso `ROLE_MOD_TAB_ADMINORDERS_UPDATE`, `payment_id` leído del servidor (nunca
del formulario), confirmación en modal con respaldo a `confirm()`, y botón que se
deshabilita al enviar.

### 3.6 Reflejo contable

Controlado por `KHIPU_REFUND_ORDER_STATE` (`'refund'` por defecto, `''` para no tocar nada):

1. `OrderSlip` construido a mano por el monto reversado —no se usa
   `OrderSlip::createPartialOrderSlip()` porque en PS 8.1.7 no rellena los totales que su
   propio `$definition` declara requeridos y `add()` lanza. La parte sin impuesto se
   prorratea con la razón `total_paid_tax_excl / total_paid_tax_incl` del pedido.
2. Si el saldo reversable llega a 0, el pedido pasa a `PS_OS_REFUND`.

El circuito de reembolso nativo de PrestaShop nunca llama a la pasarela, así que registrar
el vale es puramente contable: no hay riesgo de doble devolución.

En la rama de éxito **ningún paso puede lanzar**: el dinero ya se movió en Khipu y un 500
en cara del admin lo llevaría a reintentar. Cada paso va en su propio `try/catch` y
degrada a nota en el pedido o a `PrestaShopLogger`.

### 3.7 Tabla `khipu_refund`

Una fila por reversa aceptada por Khipu.

| Columna | Contenido |
|---|---|
| `id_order`, `id_shop` | Pedido y tienda |
| `khipu_refund_id`, `payment_id` | Identificadores de Khipu |
| `type` | `full` \| `partial` |
| `refunded_amount`, `total_refunded`, `remaining` | Montos de la respuesta de Khipu |
| `currency`, `message` | Moneda y mensaje de Khipu (vacío = concretada de inmediato) |
| `id_employee`, `date_add` | Quién y cuándo |

**`uninstall()` no la borra**, a propósito: es el único registro que existe de la plata
devuelta y la API de Khipu no permite reconstruirlo.

---

## 4. Configuración persistida

| Clave (`Configuration`) | Contenido | Se borra al desinstalar |
|---|---|---|
| `KHIPU_API_KEY` | API Key v3 | Sí |
| `KHIPU_MERCHANTID` | Id de cobrador | Sí |
| `KHIPU_SECRETCODE` | Llave secreta (firma del webhook) | Sí |
| `KHIPU_MINUTES_TIMEOUT` | Minutos antes de cancelar un pedido impago (defecto 360) | Sí |
| `KHIPU_REFUND_ORDER_STATE` | `'refund'` o `''` | Sí |
| `KHIPU_WALLET_CACHE` | Caché JSON del estado de billetera (30 s) | Sí |
| `KHIPU_TITLE` | Título del checkout (defecto `Khipu`; en blanco cae a `Khipu`) | Sí |
| `KHIPU_DESCRIPTION` | Descripción bajo cada fila (defecto `Paga con tu banco`; vacía a propósito se respeta) | Sí |
| `KHIPU_EMBEDDED` | `1` = Embebido (defecto), `0` = Redirección | Sí |
| `KHIPU_ENABLED_METHODS` | JSON con los ids de medios marcados. Ausente = todos los de la cuenta; una lista, aun vacía, es una selección explícita | Sí |
| `KHIPU_PAYMENT_METHODS_CACHE` | Caché JSON de los medios de pago de la cuenta (5 min; 1 min si falló) más el último resultado bueno | Sí |
| `KHIPU_UNKNOWN_REF_LOGGED_AT` | Hora (Unix) del último aviso de retorno con referencia desconocida | Sí |
| `KHIPU_REFUND_NONCE_{id_employee}` | Nonce del formulario de reversa | Sí (por `LIKE`) |
| `PS_OS_KHIPU_OPEN` | Id del estado *Esperando pago Khipu* | **No** |

`getContent()` guarda solo los campos presentes en el POST: los ajustes están repartidos
en dos formularios (*Configuración Básica*, que lleva credenciales, título, descripción,
modo y medios, y *Billetera de reversas*) y `Tools::getValue()` devuelve `false` para un
campo ausente, así que escribir a ciegas borraría el otro panel. Una casilla desmarcada no
viaja en el POST, por eso el interruptor y la lista de medios envían un campo marcador
(`embedded_present`, `enabledMethods_present`) cuando se muestran; con el interruptor
bloqueado (la cuenta no tiene simplificada) no se envía, y el ajuste guardado no se pisa.
Sin lista de medios de la cuenta (Khipu no respondió y no hay último bueno) tampoco se
guarda la selección. **Los ajustes nuevos no se migran** (ver §5). Al guardar se invalida `KHIPU_WALLET_CACHE`;
`KHIPU_PAYMENT_METHODS_CACHE` no se borra a propósito, porque guarda el último resultado
bueno (`last_good`) y borrarla lo perdería justo si Khipu está caído. Su huella
`md5(apiKey|merchantID)` la descarta sola si cambian las credenciales. Además, si en el
mismo POST cambian `apiKey` o `merchantID`, la selección enviada es de la cuenta anterior:
se descarta `KHIPU_ENABLED_METHODS` (ausente = todos los medios de la cuenta nueva) y no se
guarda la selección del formulario.

---

## 5. Instalación, actualización y desinstalación

`install()`:

1. Registra `paymentOptions`, `paymentReturn`, `displayAdminOrder`, `displayAdminOrderMainBottom`.
2. Crea la tabla `khipu_refund`.
3. Crea el tab oculto `AdminKhipuRefund` (`id_parent = -1`): sin tab, PrestaShop no genera
   token ni resuelve permisos para el controlador de admin.
4. `KHIPU_REFUND_ORDER_STATE = 'refund'`.
5. Crea el estado de pedido **Esperando pago Khipu** (`PS_OS_KHIPU_OPEN`), color `#4169e1`,
   no `logable`, sin correo.
6. Fija `KHIPU_TITLE` = `Khipu`, `KHIPU_DESCRIPTION` = `Paga con tu banco` y
   `KHIPU_EMBEDDED` = `1`. `KHIPU_ENABLED_METHODS` no se fija (ausente = todos los de la
   cuenta).

`upgrade/upgrade-4.4.0.php` entrega lo mismo a las tiendas ya instaladas (tabla, hooks,
tab, ajuste por defecto), todo idempotente. `upgrade-4.4.1.php` y `upgrade-4.5.0.php` no
hacen nada: existen porque `KhipuVersionTest` exige un archivo por versión. En 4.5.0 no se
migran los ajustes nuevos: mientras no existan, el módulo usa sus valores por defecto
(título `Khipu`, descripción `Paga con tu banco`, Embebido y todos los medios de la cuenta).
Una tienda que actualiza conserva credenciales, pero debe volver a ingresar título y
descripción si quiere otros, y revisar los medios marcados.

> PrestaShop decide si ejecuta un upgrade comparando la versión instalada con la de
> `config.xml`. Al subir de versión hay que tocar **cuatro** lugares:
> `classes/KhipuVersion.php::PLUGIN`, `config.xml`, `config_es.xml` y el nombre del archivo
> en `upgrade/`.

`uninstall()` borra las claves de `Configuration` (incluidas las de título, descripción, modo y medios), desregistra los hooks y elimina el tab.
Conserva la tabla `khipu_refund` y el estado de pedido.

---

## 6. Puntos a tener presentes

**`cancelExpiredOrders()` no es un cron.** Corre dentro de `hookPaymentOptions()`, es
decir cuando alguien llega al checkout. En tiendas con poco tráfico, los pedidos vencidos
pueden quedar en *Esperando pago Khipu* más allá del plazo. Cancela por `date_add` del
pedido, no por la fecha de creación del pago en Khipu.

**`validateOrder()` está sobrescrito.** `khipupayment.php` reimplementa el método de
`PaymentModule` (~700 líneas copiadas del core, con el ajuste de stock vía `StockManager`).
Es deuda asumida: cada subida de versión mayor de PrestaShop exige revisar que el método
siga alineado con el core.

**Dos vocabularios en los errores de Khipu.** El mensaje del flag de billetera cambió de
"reversas" a "devoluciones" entre agosto y septiembre de 2026. Por eso la detección se
apoya en `field === 'receiver_id'` y el texto es solo refuerzo, recortado antes del
sustantivo.

**Clases CSS según versión.** La ficha de pedido migró a Symfony/Bootstrap 4 en 1.7.7.
`KhipuPayment::adminOrderClasses()` devuelve el juego correcto (`panel`/`card`,
`pull-right`/`float-right`…). Cuando el mínimo soportado suba a 1.7.7, ese método se borra
y las clases vuelven a la plantilla.

**`logs/log.txt` no se usa.** Es un residuo; el módulo registra vía `PrestaShopLogger`
(**Parámetros avanzados → Registros**).

---

## 7. Desarrollo

### Tests

Cubren la lógica pura, sin PrestaShop ni red:

```bash
composer install       # instala PHPUnit (dependencia solo de desarrollo)
./vendor/bin/phpunit
```

Sin PHP en la máquina, sirve un contenedor:

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" composer:2 install
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" php:8.2-cli php vendor/bin/phpunit
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" php:7.2-cli php vendor/bin/phpunit
```

La suite usa **PHPUnit 8.5** a propósito: es el último que instala en PHP 7.2 y sigue
corriendo en 8.x, así que los tests se pueden correr en las dos puntas del rango
soportado. `composer.json` fija `config.platform.php` en `7.2.5` para que el
`composer.lock` resuelva siempre a versiones que instalan en el runtime más viejo, se
actualice desde donde se actualice. `phpunit.xml` no usa esquema ni opciones de una
versión en particular. PHP 7.1 (PrestaShop 1.7.0–1.7.6) queda fuera: ningún PHPUnit que
instale ahí corre también en PHP 8.

- `KhipuRefundRulesTest` — validación de montos, parseo de errores, clasificación de desenlaces
- `KhipuRefundServiceTest` — interpretación de respuestas con transporte inyectado
- `KhipuApiTest` — medios de pago y creación del pago, también con transporte inyectado
- `KhipuMethodsTest` — etiquetas, título compuesto, selección, disponibilidad y embebido según la cuenta
- `KhipuEmbeddedRulesTest` — `payment_id`, URL https y cuándo se puede cancelar el embebido
- `KhipuVersionTest` — coherencia de versiones

Toda lógica nueva debería poder probarse igual: si necesita red, va en una clase con
transporte inyectable (`KhipuApi`, `KhipuRefundService`); si es una decisión, va en
`KhipuRefundRules`. Lo que queda dentro de un controlador de PrestaShop no se puede
probar sin levantar la tienda.

Comprobación de sintaxis en las dos puntas del rango soportado:

```bash
docker run --rm -v "$PWD":/app -w /app php:7.4-cli \
  bash -c 'find . -name "*.php" -not -path "./vendor/*" | xargs -n1 php -l'
```

### Empaquetado

```bash
./package.sh     # genera dist/khipupayment.zip
```

Copia el repo a `../prestashop-khipu-release/khipupayment/`, elimina lo que es del
repositorio y no del módulo (`.git`, `tests/`, `phpunit.xml`, `package.sh`, `composer.*`,
`vendor/`, `docs/`, archivos de IDE) y comprime. **El directorio dentro del zip debe
llamarse `khipupayment`**: PrestaShop identifica el módulo por ese nombre.

`vendor/` se excluye explícitamente: después de un `composer install` ahí vive PHPUnit, y
no tiene nada que hacer dentro de una tienda en producción.

### Publicación

El zip se publica como asset de un release en
<https://github.com/khipu/prestashop-khipu/releases>, y la documentación de
docs.khipu.com enlaza a ese archivo. Al publicar una versión nueva hay que actualizar ese
enlace en la documentación.

### Checklist para subir de versión

1. `classes/KhipuVersion.php` → `PLUGIN`
2. `config.xml` y `config_es.xml` → `<version>`
3. `upgrade/upgrade-{x_y_z}.php` si hay cambios de esquema, hooks o ajustes nuevos
4. Tests en verde
5. `./package.sh` y probar el zip sobre una instalación previa (no sobre una limpia: lo que
   se rompe es la ruta de actualización)
6. Release en GitHub + actualizar el enlace en docs.khipu.com
