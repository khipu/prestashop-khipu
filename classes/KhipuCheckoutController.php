<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @author    khipu <support@khipu.com>
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once dirname(__FILE__) . '/KhipuApi.php';
require_once dirname(__FILE__) . '/KhipuEmbeddedRules.php';

/**
 * Tronco común del checkout de Khipu.
 *
 * Los dos medios de pago —transferencia simplificada y transferencia normal—
 * hacen exactamente lo mismo: crean el pedido en espera de pago, le piden el
 * pago a Khipu y redirigen al pagador. Lo único que cambia entre ellos es qué
 * URL de la respuesta se usa.
 *
 * Estaban copiados enteros en dos archivos, así que cada arreglo había que
 * hacerlo dos veces.
 */
abstract class KhipuCheckoutController extends ModuleFrontController
{
    /**
     * Clave de la respuesta de Khipu con la URL a la que se manda al pagador.
     *
     * @return string
     */
    abstract protected function getPaymentUrlKey();

    /**
     * Id del medio en la API de Khipu (REGULAR_TRANSFER, SIMPLIFIED_TRANSFER,
     * LIGOPAY o NAVE). La URL del controlador es pública: con esto se comprueba que la
     * cuenta y el panel ofrezcan este medio antes de crear nada.
     *
     * @return string
     */
    abstract protected function getMethodId();

    /**
     * Si el pago se abre embebido en la tienda (Khipu Web) en vez de redirigir
     * al pagador. Solo la transferencia simplificada lo tiene.
     *
     * @return bool
     */
    protected function usesEmbedded(): bool
    {
        return false;
    }

    /**
     * Si el pedido es del cliente con sesión abierta (también cuenta el de
     * invitado, que PrestaShop deja en la sesión al terminar la compra).
     *
     * Lo usan el retorno desde Khipu (validate) y la cancelación del embebido.
     *
     * @return bool
     */
    public static function belongsToVisitor(Order $order, Context $context)
    {
        $customer = $context->customer;

        return Validate::isLoadedObject($customer) && (int)$customer->id === (int)$order->id_customer;
    }

    /**
     * Solo el controlador `resume` retoma un pedido ya creado en vez de crear
     * uno nuevo con el carro.
     */
    protected function resumesOrder(): bool
    {
        return false;
    }

    /**
     * Vuelve a abrir el modal de un pedido embebido que sigue esperando pago.
     *
     * Recargar (F5) o volver con «Atrás» llevaba a un checkout con el carro
     * vacío —el carro ya tiene pedido y el core lo descarta—, con el pedido
     * abierto y el pago vivo sin forma de retomarlo. La página del embebido
     * reemplaza su dirección por esta (history.replaceState), que se identifica
     * por el pedido y no por el carro.
     */
    private function resumeEmbedded()
    {
        $link = $this->context->link;
        $checkout = $link->getPageLink('order', true, null, 'step=1');
        $reference = Tools::getValue('reference');
        // Solo un texto: un arreglo se convertiría en un IN (...).
        $orders = (is_string($reference) && '' !== $reference) ? Order::getByReference($reference) : array();

        if (1 !== count($orders) || !self::belongsToVisitor($orders[0], $this->context)) {
            Tools::redirect($checkout);

            return;
        }

        $order = $orders[0];
        $canceled = (int)Configuration::get('PS_OS_CANCELED');

        if ((int)$order->current_state !== (int)Configuration::get('PS_OS_KHIPU_OPEN')) {
            // Ya pagado (o en otro estado): el retorno normal decide qué mostrar.
            // Cancelado: no hay nada que retomar.
            Tools::redirect($canceled === (int)$order->current_state
                ? $checkout
                : $link->getModuleLink($this->module->name, 'validate', array(
                    'return' => 'ok',
                    'reference' => $order->reference,
                    'cartId' => (int)$order->id_cart,
                ), true));

            return;
        }

        $paymentId = $this->module->rememberedPaymentId($order);
        $api = new KhipuApi(Configuration::get('KHIPU_API_KEY'));
        $payment = (null !== $paymentId && $this->module->isEmbeddedActive()) ? $api->getPayment($paymentId) : null;

        // Mismo criterio que la cancelación: el pago es de este pedido y sigue
        // pendiente.
        if (!KhipuEmbeddedRules::canCancel($order->reference, true, $payment)) {
            $this->displayKhipuError(
                'No se pudo retomar el pago de este pedido. Si ya pagaste, la confirmación llegará a tu correo; si no, vuelve a intentarlo desde el carro.',
                array(),
                $checkout
            );

            return;
        }

        $fallback = isset($payment['simplified_transfer_url']) ? $payment['simplified_transfer_url'] : '';
        $this->displayEmbedded(
            $order,
            $paymentId,
            KhipuEmbeddedRules::isHttpsUrl($fallback)
                ? $fallback
                : $link->getModuleLink($this->module->name, 'validate', array(
                    'return' => 'ok',
                    'reference' => $order->reference,
                    'cartId' => (int)$order->id_cart,
                ), true)
        );
    }

    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::initContent();

        if ($this->resumesOrder()) {
            $this->resumeEmbedded();

            return;
        }

        $cart = $this->context->cart;

        // Un medio que la cuenta no tiene o el comercio desmarcó no se paga por
        // la URL directa: se corta antes de crear el pedido, igual que si el
        // carro no se pudiera pagar.
        if (!in_array($this->getMethodId(), $this->module->getVisibleMethodIds(), true)
            || !$this->canPayCart($cart)) {
            Tools::redirect($this->context->link->getPageLink('order', true, null, 'step=1'));

            return;
        }

        // hookPaymentOptions() ya no ofrece Khipu para estos carros; esto cubre
        // a quien llegue a la URL igual. Se corta antes de crear nada: con
        // varios pedidos, el monto de uno solo le cobraría al comprador una
        // parte del carro, y la notificación no reconciliaría ninguno.
        if ($this->module->cartSplitsIntoSeveralOrders($cart)) {
            $this->displayKhipuError(
                'Khipu no puede cobrar este carro porque se divide en varios pedidos. Elige otro medio de pago o separa la compra.',
                array(),
                $this->context->link->getPageLink('order', true)
            );

            return;
        }

        $this->module->validateOrder(
            (int)$cart->id,
            (int)Configuration::get('PS_OS_KHIPU_OPEN'),
            (float)$cart->getOrderTotal(),
            $this->module->displayName,
            null,
            array(),
            null,
            false,
            $cart->secure_key
        );

        $order = new Order((int)$this->module->currentOrder);

        // Sin pedido no hay `transaction_id`, y un pago creado con una
        // referencia vacía no lo puede reconciliar nunca la notificación: el
        // pagador pagaría y el pedido no existiría para recibirlo.
        if (!Validate::isLoadedObject($order)) {
            PrestaShopLogger::addLog(
                'Khipu: no se pudo obtener el pedido del carro ' . (int)$cart->id . ' para crear el pago.',
                3, null, 'Cart', (int)$cart->id, true
            );

            $this->displayKhipuError('No se pudo crear el pedido para este carro.');

            return;
        }

        $api = new KhipuApi(Configuration::get('KHIPU_API_KEY'));
        $response = $api->createPayment($this->buildPayload($cart, $order));
        $urlKey = $this->getPaymentUrlKey();

        $redirectUrl = $response['ok'] && is_array($response['data'])
            ? KhipuApi::selectRedirectUrl(
                $response['data'],
                $urlKey
            )
            : null;

        // En el embebido la URL del medio va a un href y a window.location
        // desde el navegador: si no es https no se usa, y se sigue el camino
        // de error de siempre.
        if ($redirectUrl && $this->usesEmbedded() && !KhipuEmbeddedRules::isHttpsUrl($redirectUrl)) {
            $redirectUrl = null;
        }

        // Se guarda antes de cualquier otra cosa: es lo que permite borrar el
        // pago si el pedido se cancela. Una respuesta sin éxito no trae pago.
        if ($response['ok']) {
            $this->module->rememberPaymentId($order, KhipuEmbeddedRules::paymentIdFrom($response['data']));
        }

        if ($redirectUrl) {
            $paymentId = KhipuEmbeddedRules::paymentIdFrom($response['data']);

            // Sin payment_id KWS no puede abrir el pago, pero la URL del medio
            // sí sirve: se redirige como siempre.
            if ($this->usesEmbedded() && null !== $paymentId) {
                $this->displayEmbedded($order, $paymentId, $redirectUrl);

                return;
            }

            Tools::redirect($redirectUrl);

            return;
        }

        // El pedido ya existe y el carro ya se consumió: si se deja abierto, el
        // stock queda reservado hasta que cancelExpiredOrders lo libere y el
        // comprador vuelve a un carro vacío, sin forma de reintentar. Se cancela
        // y el carro se rehace acá mismo, igual que cuando cancela en Khipu: el
        // botón solo lleva de vuelta al checkout. (El submitReorder del core no
        // sirve: no atiende a invitados.)
        //
        // Si Khipu llegó a crear el pago pero no devolvió la URL del medio, se
        // borra: pagable y sin pedido abierto, pasaba a «Pago aceptado» un
        // pedido cancelado.
        $this->module->cancelOpenOrder($order);
        $this->logApiFailure($order, $response, $urlKey);

        $message = $response['ok'] ? 'Respuesta no válida de la API de Khipu.' : 'No se pudo procesar el pago con Khipu.';
        $errors = $response['ok'] ? $this->errorItems($response) : array();

        // Si el carro no se pudo rehacer (un producto que se agotó o se
        // desactivó mientras tanto), «Volver a intentar» llevaba a un checkout
        // vacío. Se dice qué pasó y no se ofrece el botón.
        if (!$this->module->restoreCartOf($order)) {
            $this->displayKhipuError(
                $message . ' Tampoco se pudo recuperar el carro, quizás porque algún producto ya no está disponible: vuelve a agregar los productos.',
                $errors
            );

            return;
        }

        $this->displayKhipuError($message, $errors, $this->context->link->getPageLink('order', true));
    }

    /**
     * Página del módulo que abre Khipu Web (KWS) en un modal dentro de la
     * tienda. El pedido y el pago ya están creados; khipu-embedded.js lee los
     * data-* del ancla y decide a dónde va el comprador según el resultado.
     *
     * @param string $paymentId
     * @param string $fallbackUrl URL del medio, por si KWS no carga
     */
    private function displayEmbedded(Order $order, $paymentId, $fallbackUrl)
    {
        $link = $this->context->link;

        $this->registerJavascript(
            'modules-khipupayment-embedded',
            'modules/' . $this->module->name . '/views/js/khipu-embedded.js',
            array('position' => 'bottom', 'priority' => 200, 'version' => KhipuVersion::PLUGIN)
        );

        $this->context->smarty->assign(array(
            'payment_id' => $paymentId,
            // La misma URL de retorno que se le dio a Khipu al crear el pago.
            'return_url' => $link->getModuleLink($this->module->name, 'validate', array(
                'return' => 'ok',
                'reference' => $order->reference,
                'cartId' => (int)$order->id_cart,
            )),
            'resume_url' => $link->getModuleLink($this->module->name, 'resume', array('reference' => $order->reference), true),
            'fallback_url' => $fallbackUrl,
            'cancel_url' => $link->getModuleLink($this->module->name, 'embedded', array(), true),
            'reference' => $order->reference,
            // No se llama `token`: pisaría el `token` global de PrestaShop.
            'khipu_token' => Tools::getToken(false),
            'kws_url' => 'https://js.khipu.com/v1/kws.js',
        ));
        $this->setTemplate('module:khipupayment/views/templates/front/embedded.tpl');
    }

    /**
     * Los mismos resguardos que los controladores de pago del core.
     *
     * Sin ellos, cualquiera que llegara a la URL creaba un pedido, y volver a
     * abrirla con el pedido ya hecho terminaba en el die() de validateOrder: una
     * página en blanco con un mensaje en inglés.
     *
     * @return bool
     */
    private function canPayCart(Cart $cart)
    {
        if (!$this->module->active || !Validate::isLoadedObject($cart) || $cart->orderExists()) {
            return false;
        }

        if (!$cart->id_customer || !$cart->id_address_delivery || !$cart->id_address_invoice || !$cart->nbProducts()) {
            return false;
        }

        if (!Validate::isLoadedObject(new Customer((int)$cart->id_customer))) {
            return false;
        }

        // Respeta las restricciones de «Preferencias de pago» del back office.
        // Module::getPaymentModules() cubre país y grupo de clientes...
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == $this->module->name) {
                $authorized = true;
                break;
            }
        }

        // ...pero no la moneda: esa la filtra el hook, y por la URL directa se
        // podía pagar en una moneda que el comercio desmarcó para Khipu. Es la
        // misma comprobación que checkCurrency() de ps_wirepayment.
        return $authorized && $this->moduleAcceptsCurrency((int)$cart->id_currency);
    }

    /**
     * Si la moneda está marcada para el módulo en «Preferencias de pago».
     *
     * @param int $idCurrency
     *
     * @return bool
     */
    private function moduleAcceptsCurrency($idCurrency)
    {
        $currencies = $this->module->getCurrency($idCurrency);

        if (!is_array($currencies)) {
            return false;
        }

        foreach ($currencies as $currency) {
            if ((int)$currency['id_currency'] === (int)$idCurrency) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deja en el log el motivo que dio Khipu al rechazar el pago.
     *
     * Al pagador se le sigue mostrando un texto genérico: los errores de
     * validación hablan de la configuración de la tienda —el notify_url, la
     * cuenta de cobro— y no son asunto suyo. Pero el comercio necesita el
     * detalle para poder arreglarlo. Sin esto, de un rechazo perfectamente
     * explicado por Khipu no queda más rastro que «No se pudo procesar el pago».
     *
     * @param string $urlKey
     */
    private function logApiFailure(Order $order, array $response, $urlKey)
    {
        $detail = array();

        // Timeout, DNS, TLS: sin esto el log decía solo «HTTP 0».
        if ('' !== $response['error']) {
            $detail[] = $response['error'];
        }

        foreach ($this->errorItems($response) as $error) {
            $detail[] = ('' !== $error['field'] ? $error['field'] . ': ' : '') . $error['message'];
        }

        if (!$detail && isset($response['data']['message']) && is_scalar($response['data']['message'])) {
            $detail[] = (string)$response['data']['message'];
        }

        $what = $response['ok']
            ? 'la respuesta al crear el pago del pedido ' . $order->reference . ' no trae ' . $urlKey
            : 'no se pudo crear el pago del pedido ' . $order->reference;

        PrestaShopLogger::addLog(
            'Khipu: ' . $what
                . ' (HTTP ' . (int)$response['status'] . ')'
                . ($detail ? ': ' . implode(' | ', $detail) : '')
                . '. El pedido quedó cancelado.',
            3, null, 'Order', (int)$order->id, true
        );
    }

    /**
     * Errores de validación de la respuesta, como lista de
     * array('field', 'message').
     *
     * `errors` viene de afuera: si un intermediario lo manda como texto u
     * objeto, pasarlo tal cual al parámetro tipado de displayKhipuError() era un
     * TypeError, un 500 en vez de la página de error.
     *
     * @return array
     */
    private function errorItems(array $response)
    {
        $items = array();
        $errors = isset($response['data']['errors']) ? $response['data']['errors'] : array();

        foreach ((array)$errors as $error) {
            if (!is_array($error) || !isset($error['message']) || !is_scalar($error['message'])) {
                continue;
            }
            $items[] = array(
                'field' => (isset($error['field']) && is_scalar($error['field'])) ? (string)$error['field'] : '',
                'message' => (string)$error['message'],
            );
        }

        return $items;
    }

    /**
     * Cuerpo del POST /v3/payments.
     *
     * @return array
     */
    private function buildPayload(Cart $cart, Order $order)
    {
        $link = $this->context->link;
        $isoCode = Currency::getCurrencyInstance((int)$order->id_currency)->iso_code;

        return array(
            'subject' => Configuration::get('PS_SHOP_NAME') . ' Carro #' . $cart->id,
            'currency' => $isoCode,
            // Exactamente el número con que KhipuPostBack compara al recibir la
            // notificación: el total del pedido, con la misma precisión. Es el
            // total del carro entero porque initContent() no deja pasar carros
            // que se dividen en varios pedidos.
            'amount' => Tools::ps_round((float)$order->total_paid_tax_incl, KhipuApi::amountPrecision($isoCode)),
            // La referencia del pedido es la llave con la que la notificación
            // de Khipu vuelve a encontrarlo.
            'transaction_id' => $order->reference,
            'custom' => (string)$order->id,
            'return_url' => $link->getModuleLink($this->module->name, 'validate', array(
                'return' => 'ok',
                'reference' => $order->reference,
                'cartId' => $cart->id,
            )),
            'cancel_url' => $link->getModuleLink($this->module->name, 'validate', array(
                'return' => 'cancel',
                'reference' => $order->reference,
            )),
            'notify_url' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__
                . 'modules/' . $this->module->name . '/validate.php',
            'body' => $this->getCartProductDetails($cart),
            'payer_email' => $this->context->customer->email,
            'notify_api_version' => '3.0',
        );
    }

    /**
     * Detalle de productos que Khipu muestra al pagador.
     *
     * @return string
     */
    private function getCartProductDetails(Cart $cart)
    {
        $detail = '';

        foreach ($cart->getProducts() as $product) {
            $detail .= $product['cart_quantity'] . ' x ' . $product['name'] . "\n";
        }

        return $detail;
    }

    /**
     * @param string $message
     * @param array  $errors   lista de array('field', 'message'), ver errorItems()
     * @param string $retryUrl a dónde mandar al pagador para rehacer el carro
     */
    private function displayKhipuError($message, array $errors = array(), $retryUrl = '')
    {
        $error = array(
            'status' => 'Error',
            'message' => $message,
        );

        if ($errors) {
            $error['errors'] = $errors;
        }

        if ('' !== $retryUrl) {
            $error['retry_url'] = $retryUrl;
        }

        $this->context->smarty->assign('error', $error);
        $this->setTemplate('module:khipupayment/views/templates/front/khipu_error.tpl');
    }
}
