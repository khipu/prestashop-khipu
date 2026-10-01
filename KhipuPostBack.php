<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    khipu <support@khipu.com>
 * @copyright 2007-2020 khipu SpA
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once dirname(__FILE__) . '/classes/KhipuApi.php';
require_once dirname(__FILE__) . '/classes/KhipuRefund.php';

class KhipuPostback
{
    public function init()
    {
        define('_PS_ADMIN_DIR_', getcwd());

        // Load Presta Configuration
        Configuration::loadConfiguration();
        Context::getContext()->link = new Link();

        // Handle the postback
        $this->handlePOST();
    }

    /** Campos sin los cuales la notificación no se puede procesar. */
    private static $required_fields = array('transaction_id', 'receiver_id', 'amount', 'payment_id');

    /** Segundos que una notificación espera a que termine otra del mismo pedido. */
    const LOCK_TIMEOUT = 10;

    private function handlePOST()
    {
        http_response_code(400);

        $secret = Configuration::get('KHIPU_SECRETCODE');
        $raw_post = file_get_contents('php://input');
        // Esta URL es pública: le llegan escaneos, sondas y peticiones sin
        // cabecera. Leer el índice a secas terminaba en un 500, que para quien
        // sondea es la señal de que hay algo interesante detrás.
        $signature = isset($_SERVER['HTTP_X_KHIPU_SIGNATURE']) ? (string)$_SERVER['HTTP_X_KHIPU_SIGNATURE'] : '';

        if (!$this->verifySignature($raw_post, $signature, $secret)) {
            exit('Invalid signature');
        }

        $paymentResponse = json_decode($raw_post, true);

        if (!is_array($paymentResponse)) {
            exit('Invalid payment response');
        }

        // Escalares: un arreglo en transaction_id llegaba tal cual a
        // Order::getByReference(), que lo convierte en un IN (...).
        foreach (self::$required_fields as $field) {
            if (!isset($paymentResponse[$field]) || !is_scalar($paymentResponse[$field])) {
                exit('Missing field ' . $field);
            }
        }

        $reference = (string)$paymentResponse['transaction_id'];
        $orders = Order::getByReference($reference);

        if (count($orders) == 0) {
            exit('No order for reference ' . $reference);
        }

        if (count($orders) > 1) {
            exit('More than one order with the same reference ' . $reference);
        }

        // Khipu reintenta mientras no recibe respuesta, así que dos
        // notificaciones del mismo pago pueden llegar a la vez. Cada paso de
        // abajo es «mirar y después escribir»: sin el candado, las dos veían
        // que faltaba el pago y lo registraban dos veces (dos filas, el doble
        // en total_paid_real, dos «Pago aceptado» con su correo).
        $idOrder = (int)$orders[0]->id;
        $lock = 'khipu_notify_' . md5(_DB_NAME_ . _DB_PREFIX_) . '_' . $idOrder;
        $db = Db::getInstance();

        if (1 !== (int)$db->getValue('SELECT GET_LOCK(\'' . pSQL($lock) . '\', ' . self::LOCK_TIMEOUT . ')', false)) {
            http_response_code(500);
            exit('Notification already being processed');
        }

        try {
            list($code, $body) = $this->processNotification($idOrder, $paymentResponse);
        } finally {
            $db->getValue('SELECT RELEASE_LOCK(\'' . pSQL($lock) . '\')', false);
        }

        http_response_code($code);
        exit($body);
    }

    /**
     * Registra el pago de una notificación ya verificada. Corre con el candado
     * del pedido tomado.
     *
     * @return array array(código HTTP, cuerpo de la respuesta)
     */
    private function processNotification($idOrder, array $paymentResponse)
    {
        // Se relee el pedido con el candado tomado: si esta notificación
        // esperó a otra, la copia de antes ya no vale, y escribirla de vuelta
        // pisaría lo que la otra dejó.
        Cache::clean('objectmodel_Order_' . (int)$idOrder . '_*');
        $order = new Order((int)$idOrder);

        // La moneda se lee del pedido y no del carro: Cart::getCartByOrderId()
        // devuelve false si el carro ya no está, y de ahí salía un 500. Es la
        // misma moneda, el pedido la copia del carro al crearse.
        $currency = Currency::getCurrencyInstance((int)$order->id_currency);
        $precision = KhipuApi::amountPrecision($currency->iso_code);
        $orderTotal = Tools::ps_round((float)$order->total_paid_tax_incl, $precision);

        if ($paymentResponse['receiver_id'] != Configuration::get('KHIPU_MERCHANTID')
            || $orderTotal != (float)$paymentResponse['amount']
        ) {
            return array(400, 'Notification rejected [ReceiverId: '
                . Configuration::get('KHIPU_MERCHANTID')
                . '] [Payment ReceiverId: ' . $paymentResponse['receiver_id']
                . '] [Order Total: ' . $orderTotal
                . '] [Payment Amount: ' . $paymentResponse['amount'] . ']');
        }

        // Si algo falla al registrar el pago se responde 500 para que Khipu
        // reintente: con un 200 dejaría de notificar y el pedido se quedaría
        // sin pagar o sin payment_id, es decir, sin reversa. La base de datos
        // puede fallar de las dos formas: PrestaShop 8 lanza la excepción de
        // PDO, y antes de este try esa excepción terminaba en el 400 que se
        // fija al principio.
        $error = '';
        try {
            // Solo se marca pagado un pedido que nunca lo estuvo. Comparar con
            // el estado actual no bastaba: un pedido enviado, entregado o
            // reembolsado tampoco está en PS_OS_PAYMENT, y un reintento de
            // Khipu lo devolvía a «Pago aceptado» y le reenviaba el correo.
            if (!$this->hasPaidState($order)) {
                $order->setCurrentState((int)Configuration::get('PS_OS_PAYMENT'));

                // setCurrentState() no avisa si no hizo nada (PS_OS_PAYMENT
                // vacío, o un módulo que lo impidió). Sin comprobarlo se
                // respondía 200, Khipu dejaba de notificar, y el pedido pagado
                // seguía en espera hasta que cancelExpiredOrders() lo cancelaba.
                if (!$this->hasPaidState($order)) {
                    throw new Exception('el pedido no quedó en un estado pagado; revisa el estado configurado en PS_OS_PAYMENT');
                }
            }
            // Va fuera del if: si el pedido ya estaba pagado (a mano desde el
            // back office, o un intento anterior falló después de cambiar el
            // estado), el payment_id igual tiene que quedar guardado. El
            // reintento ya no cambia el estado, solo vuelve a guardarlo.
            $stored = $this->storePaymentId($order, $paymentResponse, $precision);
        } catch (Exception $e) {
            $stored = false;
            $error = $e->getMessage();
        }

        if (!$stored) {
            // Antes del log: si la base está caída, el log también lanza, y
            // PHP conserva este código en vez de volver al 400.
            http_response_code(500);
            PrestaShopLogger::addLog(
                'Khipu: no se pudo registrar el pago del pedido ' . $order->reference
                    . ($error ? ' (' . $error . ')' : '')
                    . '; se respondió 500 para que Khipu reintente la notificación.',
                3, null, 'Order', (int)$order->id, true
            );

            return array(500, 'Could not record payment');
        }

        return array(200, 'Notification received correctly');
    }

    /**
     * Estampa el payment_id de Khipu en el pago del pedido.
     *
     * Ese identificador es lo único que permite reversar más tarde: sin él, el
     * panel de reversa no aparece. Antes se escribía sobre la primera fila de
     * la colección dando por hecho que existía; si PrestaShop no había creado
     * ninguna, el acceso reventaba con un 500 sobre un pedido que ya había
     * quedado pagado, y Khipu reintentaba la notificación.
     *
     * Se llama en cada notificación válida, así que no debe escribir de nuevo
     * si el identificador ya está guardado.
     *
     * Solo se reutiliza una fila sin identificador, de Khipu y con el mismo
     * monto que cobró Khipu: la que crea PrestaShop al pasar el pedido a
     * pagado. Antes se escribía sobre la primera fila sin mirar, y si el
     * comercio había registrado un pago a mano (otro medio, otro identificador,
     * otro monto) se le pisaba el identificador y la reversa se calculaba con
     * su monto. Una fila de otro medio no se reutiliza aunque calce el monto:
     * rotularla como Khipu borraría lo que anotó el comercio.
     *
     * Registrar el pago son dos escrituras —la fila y el total del pedido— y
     * no son atómicas: si la segunda falla, se responde 500 y el reintento
     * encuentra la fila ya con el payment_id. Por eso encontrarla no basta para
     * dar el pago por registrado: antes de responder se cuadra el total.
     *
     * @param int $precision decimales del monto, ver KhipuApi::amountPrecision()
     *
     * @return bool si el payment_id quedó guardado y el total del pedido cuadra
     */
    private function storePaymentId(Order $order, array $paymentResponse, $precision)
    {
        $payment_id = (string)$paymentResponse['payment_id'];
        $amount = Tools::ps_round((float)$paymentResponse['amount'], $precision);
        $payments = $order->getOrderPaymentCollection();
        $reusable = null;

        foreach ($payments as $order_payment) {
            $transaction_id = (string)$order_payment->transaction_id;

            if ($transaction_id === $payment_id) {
                return $this->reconcileTotalPaid($order, $payments);
            }

            if (null === $reusable && '' === $transaction_id
                && KhipuRefund::isKhipuPaymentMethod($order_payment->payment_method, $order)
                && Tools::ps_round((float)$order_payment->amount, $precision) == $amount
            ) {
                $reusable = $order_payment;
            }
        }

        if ($reusable) {
            // Con el monto que cobró Khipu, no con el que traía la fila: pueden
            // diferir por debajo de la precisión (un total CLP de 11900.50 se
            // cobra 11901), y la reversa calcula el saldo reversable con este.
            // Y con el medio del pedido tal cual, que es el que busca
            // KhipuRefund::getPaymentRowForOrder() para elegir la fila de Khipu.
            $reusable->transaction_id = $payment_id;
            $reusable->amount = (float)$paymentResponse['amount'];
            $reusable->payment_method = $order->payment;

            if (!$reusable->update()) {
                return false;
            }

            return $this->reconcileTotalPaid($order, $payments);
        }

        // Hay pagos, pero ninguno es este: el de Khipu se registra aparte y se
        // avisa, porque puede ser el mismo dinero anotado dos veces.
        if (count($payments)) {
            PrestaShopLogger::addLog(
                'Khipu: el pedido ' . $order->reference . ' ya tenía pagos que no calzan con el de Khipu'
                    . ' (otro identificador o monto); el pago de Khipu ' . $payment_id
                    . ' se registró aparte. Revisa si el pedido quedó cobrado dos veces.',
                2, null, 'Order', (int)$order->id, true
            );
        }

        // Se crea la fila con el monto que Khipu confirma haber recibido. El
        // medio de pago se copia del pedido y no se deja en null: la columna es
        // NOT NULL y con MySQL en modo estricto el INSERT fallaría.
        return (bool)$order->addOrderPayment((float)$paymentResponse['amount'], $order->payment, $payment_id);
    }

    /**
     * Si el pedido está o estuvo alguna vez en un estado pagado, leído de la
     * base en una sola consulta.
     *
     * Hace lo mismo que Order::hasBeenPaid(), pero sin su caché: getHistory()
     * guarda el resultado por pedido durante la petición, y la respuesta de
     * después de setCurrentState() depende de que PrestaShop la limpie al
     * agregar historial. 8.1 lo hace; en las 1.7 no está comprobado. Por lo
     * mismo va sin la caché de consultas de Db.
     *
     * También cuenta el estado actual del pedido, aunque no tenga su fila de
     * historial: setCurrentState() cambia el estado antes de escribir el
     * historial, y si eso último falla, el reintento volvía a llamarlo, no
     * hacía nada (el pedido ya está en ese estado) y el payment_id no se
     * guardaba nunca.
     *
     * @return bool
     */
    private function hasPaidState(Order $order)
    {
        $id = (int)$order->id;

        return (bool)Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'orders` o'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'order_state` os ON os.`id_order_state` = o.`current_state`'
            . ' WHERE o.`id_order` = ' . $id . ' AND os.`paid` = 1'
            . ' UNION ALL'
            . ' SELECT 1 FROM `' . _DB_PREFIX_ . 'order_history` oh'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'order_state` os ON os.`id_order_state` = oh.`id_order_state`'
            . ' WHERE oh.`id_order` = ' . $id . ' AND os.`paid` = 1',
            false
        );
    }

    /**
     * Deja total_paid_real igual a la suma de los pagos del pedido.
     *
     * Es la regla con que lo lleva PrestaShop (addOrderPayment() y el paso a
     * un estado pagado lo suben en lo mismo que la fila que agregan), así que
     * cuadrarlo es idempotente: si ya cuadra no escribe nada, y si una
     * escritura anterior quedó a medias la completa.
     *
     * Escribe solo esa columna: un Order::update() reescribe el pedido entero
     * con lo que haya en memoria.
     *
     * Con pagos en otra moneda no se toca: la suma dependería del tipo de
     * cambio del momento y no del que se usó al registrarlos.
     *
     * @param PrestaShopCollection $payments los pagos del pedido, ya leídos
     *
     * @return bool
     */
    private function reconcileTotalPaid(Order $order, $payments)
    {
        $sum = 0.0;

        foreach ($payments as $payment) {
            if ((int)$payment->id_currency !== (int)$order->id_currency) {
                return true;
            }
            $sum += (float)$payment->amount;
        }

        if (0.0 === round($sum - (float)$order->total_paid_real, 6)) {
            return true;
        }

        $order->total_paid_real = $sum;

        return (bool)Db::getInstance()->update(
            'orders',
            array('total_paid_real' => $sum),
            '`id_order` = ' . (int)$order->id
        );
    }

    /**
     * Verifica la cabecera `X-Khipu-Signature`, de la forma `t=<ts>,s=<firma>`.
     *
     * Cada parte se valida por separado: una cabecera ausente, a medias o con
     * basura tiene que terminar en el 400 de «firma inválida». Antes,
     * $t_value/$s_value podían quedar sin asignar y hash_equals(string, null)
     * lanzaba un TypeError en PHP 8 — un 500 que le decía a quien sondeaba que
     * había llegado más lejos que el resto.
     *
     * @return bool
     */
    private function verifySignature($raw_post, $signature, $secret)
    {
        // Sin llave secreta configurada no hay nada que verificar, y aceptar
        // sería dar por pagado cualquier POST que llegue a esta URL.
        if ('' === (string)$secret) {
            return false;
        }

        $t_value = '';
        $s_value = '';

        foreach (explode(',', (string)$signature, 2) as $part) {
            $pair = explode('=', $part, 2);

            if (count($pair) !== 2) {
                continue;
            }

            if ($pair[0] === 't') {
                $t_value = $pair[1];
            } elseif ($pair[0] === 's') {
                $s_value = $pair[1];
            }
        }

        if ('' === $t_value || '' === $s_value) {
            return false;
        }

        $to_hash = $t_value . '.' . $raw_post;
        $hash_bytes = hash_hmac('sha256', $to_hash, $secret, true);
        $hmac_base64 = base64_encode($hash_bytes);

        return hash_equals($hmac_base64, $s_value);
    }
}
