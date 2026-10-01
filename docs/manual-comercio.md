# Khipu en PrestaShop

Guía para instalar, configurar, operar y actualizar Khipu como medio de pago en PrestaShop.

Este contenido está escrito para publicarse en la sección **Prestashop** de
[docs.khipu.com → Ecommerce](https://docs.khipu.com/payment-solutions/instant-payments/khipu-ecommerce#prestashop).

---

## Antes de empezar

Necesitas:

- Una cuenta habilitada para cobrar en [khipu.com](https://khipu.com/login/auth).
- PrestaShop **1.7 a 8.x**.
- PHP con la extensión **cURL** habilitada en el servidor.
- La tienda publicada en **HTTPS** y accesible desde Internet: Khipu confirma el pago
  llamando a una URL de tu tienda. Si esa URL no es alcanzable, los pedidos quedan
  esperando pago aunque el cliente haya pagado.
- El archivo `khipupayment.zip` de la
  [última versión publicada](https://github.com/khipu/prestashop-khipu/releases/latest).

De tu cuenta Khipu vas a ocupar tres datos, todos en **Opciones de la cuenta → Para
integrar Khipu a tu sitio web**:

| Dato | Para qué lo usa el módulo |
|---|---|
| **Id de cobrador** | Identifica tu cuenta al pedir los medios de pago y al validar cada notificación de pago. |
| **Llave secreta** | Verifica la firma de las notificaciones que envía Khipu. Sin ella, ningún pago se marca como pagado. |
| **Api Key** | Autentica las llamadas del módulo a la API de Khipu (crear pagos y reversar). Se crea en esa misma sección. |

Los tres son obligatorios. Trátalos como credenciales: no los compartas ni los pegues
en tickets, correos ni capturas de pantalla.

---

## Instalación y configuración

1. Descarga el archivo `khipupayment.zip` desde la
   [última versión publicada](https://github.com/khipu/prestashop-khipu/releases/latest).
2. Abre el administrador de tu tienda PrestaShop e ingresa con tu usuario y contraseña.
3. En el menú, ve a **Módulos → Módulos** (en PrestaShop 8: **Módulos → Administrador de módulos**).
4. Arriba a la derecha, clic en **Añadir nuevo módulo** (o **Subir un módulo**).
5. Selecciona el `khipupayment.zip` que descargaste y súbelo. Si aparece una advertencia
   de origen del módulo, presiona **Seguir con la instalación**.
6. Cuando termine, busca **khipu** en la lista de módulos y presiona **Configurar**.
7. En otra pestaña entra a [tu cuenta de Khipu](https://khipu.com/login/auth), clic en
   **Opciones de la cuenta** y baja hasta **Para integrar Khipu a tu sitio web**. Ahí
   están tu **Id de cobrador** y tu **Llave secreta**, y ahí mismo puedes crear tu **Api Key**.
8. Vuelve a PrestaShop y completa, en **Configuración Básica**:
   - **API Key**
   - **ID Cobrador**
   - **Llave secreta**
   - **Minutos para realizar el pago**: cuánto tiempo esperas el pago antes de cancelar
     el pedido y devolver el stock. Por defecto **360** (6 horas).
9. Presiona **Guardar**. Listo: Khipu ya aparece como medio de pago en tu tienda.

> **Verifica la instalación con una compra de prueba de monto bajo.** Es la forma más
> rápida de confirmar que el servidor recibe la notificación de Khipu y que el pedido
> pasa a *Pago aceptado*.

### Medios de pago que verás en el checkout

El módulo consulta a Khipu qué medios tiene habilitada tu cuenta y muestra solo esos:

- **Paga usando Khipu** (transferencia simplificada): disponible en todas las monedas que
  tenga habilitada tu cuenta. Es la opción recomendada para el comprador.
- **Transferencia Normal**: se muestra **solo cuando el carro está en pesos chilenos (CLP)**.

Si no aparece ninguno de los dos, revisa las credenciales: con una API Key o un Id de
cobrador incorrectos, la consulta a Khipu falla y el módulo no ofrece ningún medio de pago.

---

## Cómo avanza un pedido pagado con Khipu

1. El cliente elige Khipu en el checkout. PrestaShop crea el pedido en el estado
   **Esperando pago Khipu** y descuenta el stock.
2. El cliente es redirigido a Khipu para pagar.
3. Khipu notifica el pago a tu tienda. El módulo verifica la firma, el Id de cobrador y
   el monto; si todo calza, el pedido pasa a **Pago aceptado** y queda registrado el
   identificador del pago de Khipu.
4. El cliente vuelve a la tienda y ve la confirmación del pedido.

Casos que no terminan en pago:

- **El cliente cancela en Khipu**: el pedido pasa a **Cancelado** y se le ofrece volver a comprar.
- **El cliente nunca paga**: el pedido queda en *Esperando pago Khipu* hasta superar los
  minutos configurados; entonces pasa a **Cancelado** y se recupera el stock.

> La cancelación automática de pedidos vencidos se ejecuta cuando algún visitante llega
> al checkout, no con un proceso programado. En tiendas con poco tráfico, un pedido puede
> quedar vencido y aún visible como *Esperando pago Khipu* hasta la siguiente visita al
> checkout. No afecta a los pagos: un pago que llega después del vencimiento igual marca
> el pedido como pagado.

**El estado *Pago aceptado* depende de la notificación de Khipu, no del regreso del
cliente a la tienda.** Si un cliente paga y el pedido sigue en *Esperando pago Khipu*,
el problema está en que tu servidor no recibió esa notificación (ver
[Solución de problemas](#solución-de-problemas)).

---

## Reversas (devolución de dinero al cliente)

Desde la versión **4.4.0** puedes devolverle el dinero a un cliente desde la ficha del
pedido, sin salir de PrestaShop.

### Requisitos

- La **billetera de reversas** debe estar habilitada en tu cuenta Khipu. Si no la tienes,
  solicítala a **soporte@khipu.com**.
- La billetera debe tener **saldo**: el dinero que se devuelve sale de ahí, no del pago original.
- El pedido debe haber sido pagado con Khipu por este módulo.
- El empleado que reversa necesita permiso de **edición sobre Pedidos**.

### Cómo reversar

1. Entra al pedido en **Pedidos → Pedidos** y ábrelo.
2. Baja hasta el panel **Reversa Khipu** (queda bajo el bloque de pago).
3. Revisa los dos saldos que muestra el panel —son distintos y conviene no confundirlos:
   - **Saldo reversable de este pedido**: cuánto queda por devolver de ese pago.
   - **Saldo de la billetera de reversas**: cuánto dinero tienes disponible para devolver.
     Desde ahí puedes **Recargar**.
4. Elige el tipo de reversa:
   - **Total**: devuelve todo el saldo reversable del pedido.
   - **Parcial**: devuelve el monto que indiques (usa el punto como separador decimal).
5. Presiona **Reversar** y confirma en el cuadro de diálogo.

Cada reversa queda en el historial del panel, con fecha, tipo, monto devuelto y saldo
restante, y además como nota privada en el pedido.

### Qué esperar después

- **Las reversas se concretan en el ciclo diario de Khipu**, no al instante. El panel te
  lo indica cuando la reversa queda en proceso.
- Puedes hacer **varias reversas parciales** sobre un mismo pedido hasta agotar el saldo.
- **Una reversa no se puede deshacer.**
- Khipu solo permite reversar dentro de los **180 días** siguientes a la conciliación del pago.
- Si la billetera no tiene saldo suficiente, la reversa se solicita igual, pero conviene
  recargarla para que alcance a concretarse: el panel te lo advierte y te ofrece el enlace.

### Cómo se refleja en la contabilidad de PrestaShop

En la configuración del módulo, panel **Billetera de reversas**, eliges qué hace
PrestaShop cuando reversas:

| Opción | Qué hace |
|---|---|
| **Registrar vale y marcar Reembolsado al agotar el saldo** (por defecto) | Genera un vale por el monto devuelto y, cuando el saldo reversable del pedido llega a cero, cambia el pedido a **Reembolsado**. La contabilidad refleja la reversa al solicitarla. |
| **— No cambiar —** | No toca el estado ni la contabilidad. Solo queda el registro en el panel y la nota en el pedido. |

Dos advertencias sobre la opción por defecto:

- La contabilidad refleja la reversa **al solicitarla**, no cuando Khipu la concreta en su
  ciclo diario.
- El estado **Reembolsado** envía correo al cliente si así lo tienes configurado en
  **Estados de pedido**.

El circuito de reembolsos propio de PrestaShop (los botones nativos de la ficha de pedido)
**no** mueve dinero en Khipu: la plata solo vuelve por el panel **Reversa Khipu**.

---

## Actualización del plugin

El módulo no se distribuye por el marketplace de PrestaShop, así que la actualización es
manual: se sube el `.zip` nuevo sobre el módulo instalado.

1. **Respalda** la base de datos y la carpeta `modules/khipupayment/` de tu tienda.
2. Descarga el `khipupayment.zip` de la
   [última versión publicada](https://github.com/khipu/prestashop-khipu/releases/latest).
3. En el administrador, ve a **Módulos → Módulos** y clic en **Añadir nuevo módulo**.
4. Sube el `.zip` nuevo. PrestaShop reemplaza los archivos y ejecuta la actualización.

**No desinstales el módulo para actualizarlo.** Subir el `.zip` encima conserva tus
credenciales, tus ajustes y el historial de reversas; desinstalar borra la configuración.

Después de actualizar:

- Entra a **Configurar** y verifica que tus credenciales sigan ahí.
- Confirma la versión en el panel **Información del Módulo**, arriba en la misma pantalla.
- Si la pantalla queda con estilos rotos o algo no aparece, borra la caché en
  **Parámetros avanzados → Rendimiento → Borrar caché**.

---

## Desinstalación

Al desinstalar el módulo se borran las credenciales y los ajustes.

**El historial de reversas no se borra.** Es el único registro que existe del dinero
devuelto —la API de Khipu no permite reconstruirlo— así que sobrevive a la desinstalación
a propósito. Los pedidos ya pagados tampoco se ven afectados.

---

## Solución de problemas

| Síntoma | Causa más probable | Qué hacer |
|---|---|---|
| Khipu no aparece como medio de pago en el checkout | Credenciales incorrectas, o la moneda del carro no está habilitada en tu cuenta | Revisa **API Key** e **ID Cobrador** en la configuración del módulo y que la moneda del carro esté habilitada en Khipu |
| **Ningún** medio de pago aparece en el checkout, ni Khipu ni los demás | El país de la dirección del cliente no está habilitado para los módulos de pago. PrestaShop los filtra por país antes de consultarlos | **Módulos → Pagos → Restricciones por país**: marca los países desde los que vendes. Es configuración de la tienda, no del módulo |
| Solo aparece «Paga usando Khipu» y no «Transferencia Normal» | Es el comportamiento esperado: la transferencia normal se muestra únicamente en **CLP** | Nada que hacer |
| El cliente pagó pero el pedido sigue en *Esperando pago Khipu* | Tu servidor no recibió o no validó la notificación de Khipu | Verifica que la tienda sea alcanzable por HTTPS desde Internet y que la **Llave secreta** sea exactamente la de tu cuenta (sin espacios) |
| «Error de conexión con khipu» al ir a pagar | cURL deshabilitado, salida a Internet bloqueada, o API Key inválida | Habilita cURL, permite la salida hacia `payment-api.khipu.com` y revisa la API Key |
| Pedidos vencidos que no se cancelan solos | La cancelación corre cuando alguien llega al checkout | Espera a la siguiente visita al checkout, o cancela el pedido a mano |
| El panel **Reversa Khipu** no aparece en el pedido | El pedido no se pagó con Khipu, o no tiene identificador de pago registrado | Solo se puede reversar un pago hecho con Khipu y confirmado por notificación |
| «La billetera de reversas no está habilitada en tu cuenta Khipu» | Tu cuenta no tiene el permiso de reversas | Solicítalo a **soporte@khipu.com** |
| «…no es reembolsable» al reversar | El pago ya fue reversado por completo, se reversó por otro medio, o pasaron más de 180 días desde su conciliación | El mensaje del panel indica cuál de los tres casos es |
| «Reversa de RESULTADO DESCONOCIDO» | Khipu no respondió, o respondió algo que el módulo no pudo interpretar | **No reintentes de inmediato.** El panel te dice si Khipu ya registró reversas en ese pago; si no lo puede confirmar, escribe a soporte@khipu.com con el ID de pago que quedó en la nota del pedido |

Si el problema persiste, revisa **Parámetros avanzados → Registros** en PrestaShop: el
módulo deja ahí el detalle de lo ocurrido con cada reversa.

---

## Soporte

- Correo: **soporte@khipu.com**
- Código fuente e historial de versiones: <https://github.com/khipu/prestashop-khipu>
- Para PrestaShop 1.5 y 1.6 (sin soporte): <https://github.com/khipu/prestashop1.6-khipu>

Al escribir a soporte, incluye la versión del módulo y de PrestaShop (ambas visibles en la
pantalla de configuración del módulo), el número de pedido y, si es una reversa, el ID de
pago que aparece en la nota del pedido. **No envíes tu Llave secreta ni tu API Key.**
