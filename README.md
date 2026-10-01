# Khipu para PrestaShop

Módulo oficial de [Khipu](https://khipu.com) para recibir pagos por transferencia en
PrestaShop, con soporte de reversas (devolución de dinero) desde la ficha del pedido.

- **Módulo:** `khipupayment` · **Versión:** 4.4.1 · **API de Khipu:** v3
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
4. Ajustar los **minutos** de espera antes de cancelar un pedido impago (defecto: 360) y
   guardar.

Requiere que la tienda sea alcanzable por HTTPS desde Internet: Khipu confirma cada pago
mediante una notificación a `https://<tu-tienda>/modules/khipupayment/validate.php`.

## Medios de pago

- **Transferencia simplificada** — en todas las monedas habilitadas en tu cuenta.
- **Transferencia normal** — solo cuando el carro está en **CLP**.

Se muestran únicamente los que tu cuenta tenga habilitados.

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
