#Aceptar pagos con khipu en Prestashop#

En este manual se explica como configurar khipu para ser usado como medio de pago en Prestashop (http://www.prestashop.com/).

Antes de comenzar, se debe contar con lo siquiente:
- Una cuenta habilitada para cobrar en khipu.com.
- Prestashop 1.7 a 8.1
- El archivo khipupayment.zip, el cual contiene el plugin khipu para Prestashop.
- requiere tener habilido cURL en el servidor.

1. Instala el módulo khipupayment.zip
2. Ingresa a la configuración del módulo
3. Ingresa a tu cuenta de cobro Khipu, allí en "Opciones de la cuenta=>Para integrar Khipu en tu sitio WEB, encontrarás el Id de tu cuenta de cobro y la llave secreta. Mas abajo, podrás crear tu nueva Api Key."
4. Completa el Id de la Cuenta, la Llave Secreta y la Api Key.
5. Configura el tiempo, en minutos, que deseas esperar el pago antes de cancelarlo
6. Guarda la configuración.

Nota: Si buscas el repositorio de khipu para Prestashop 1.5 y 1.6, fue movido acá: https://github.com/khipu/prestashop1.6-khipu
