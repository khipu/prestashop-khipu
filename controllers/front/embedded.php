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

require_once _PS_MODULE_DIR_ . 'khipupayment/classes/KhipuCheckoutController.php';

/**
 * Cancelación del pago embebido (Khipu Web).
 *
 * khipu-embedded.js llama acá por POST cuando KWS termina con ERROR o el
 * comprador cierra el modal: se borra el pago en Khipu, se cancela el pedido y
 * se rehace el carro, para que no quede ni un pedido abierto con el stock
 * reservado ni un pago que se pueda pagar después.
 *
 * Responde siempre JSON {"ok": bool, "redirect": url}. Si algo no cuadra no se
 * toca nada y se manda al comprador al retorno normal (validate), que decide
 * con el estado real del pedido.
 */
class KhipuPaymentEmbeddedModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $ajax = true;

    public function postProcess()
    {
        $reference = Tools::getValue('reference');
        $paymentId = Tools::getValue('payment_id');
        $token = Tools::getValue('token');

        // Solo textos: Tools::getValue() devuelve arreglos tal cual, y
        // Order::getByReference() con un arreglo arma un IN (...).
        $reference = is_string($reference) ? $reference : '';
        $paymentId = is_string($paymentId) ? $paymentId : '';
        $token = is_string($token) ? $token : '';

        $order = null;
        if ('' !== $reference) {
            $orders = Order::getByReference($reference);
            // Los carros que se dividen en varios pedidos no llegan a Khipu:
            // más de uno con la misma referencia no es un pago embebido.
            if (1 === count($orders)) {
                $order = $orders[0];
            }
        }

        if ('POST' !== (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '')
            || '' === $token || !hash_equals((string)Tools::getToken(false), $token)
            || '' === $paymentId
            || !($order instanceof Order) || !Validate::isLoadedObject($order)
            || !KhipuCheckoutController::belongsToVisitor($order, $this->context)) {
            // Antes de saber que el pedido es del visitante no se devuelve nada
            // suyo (ni el carro): solo el inicio de la tienda.
            $this->respond(false, $this->context->link->getPageLink('index', true));
        }

        // Ya es del visitante: el retorno normal, con su carro, decide con el
        // estado real del pedido.
        $fallback = $this->validateUrl('ok', $reference, $order);

        $isOpen = (int)$order->current_state === (int)Configuration::get('PS_OS_KHIPU_OPEN');

        // Sin pedido abierto no se pregunta a Khipu: ya lo pagaron, lo canceló
        // cancelExpiredOrders o un clic anterior.
        if (!$isOpen) {
            $this->respond(false, $fallback);
        }

        $api = new KhipuApi(Configuration::get('KHIPU_API_KEY'));

        // El payment_id viene del navegador: se comprueba en Khipu que el pago
        // sea de este pedido ANTES de borrarlo.
        if (!KhipuEmbeddedRules::canCancel($order->reference, $isOpen, $api->getPayment($paymentId))) {
            $this->respond(false, $fallback);
        }

        $status = $api->deletePayment($paymentId);

        if (!KhipuEmbeddedRules::deleteSucceeded($status)) {
            // Si Khipu no aceptó borrarlo, cancelar el pedido dejaría un pago
            // que se puede pagar contra un pedido cancelado. Queda abierto: la
            // notificación o cancelExpiredOrders deciden.
            PrestaShopLogger::addLog(
                'Khipu: no se pudo borrar el pago embebido del pedido ' . $order->reference
                    . ' (HTTP ' . (int)$status . '). El pedido sigue en espera de pago.',
                2, null, 'Order', (int)$order->id, true
            );
            $this->respond(false, $fallback);
        }

        // Mientras se hablaba con Khipu otra petición (un segundo clic,
        // cancelExpiredOrders) pudo cambiar el pedido: cancelOpenOrder lo relee
        // con el candado y, si ya no está abierto, no lo cancela otra vez ni se
        // rehace el carro de nuevo. El pago ya se borró arriba.
        if (!$this->module->cancelOpenOrder($order, false)) {
            $this->respond(false, $fallback);
        }

        // Igual que el retorno con return=cancel: no se le pisa a la sesión un
        // carro propio con productos, y el que ya tiene pedido no cuenta.
        $sessionCart = $this->context->cart;
        $sessionCartIsUsable = Validate::isLoadedObject($sessionCart)
            && $sessionCart->nbProducts() && !$sessionCart->orderExists();

        if (!$sessionCartIsUsable && !$this->module->restoreCartOf($order)) {
            // El retorno con return=cancel muestra el aviso de carro perdido.
            $this->respond(true, $this->validateUrl('cancel', $reference, null));
        }

        $this->respond(true, $this->context->link->getPageLink('order', true, null, 'step=1'));
    }

    /**
     * @param string     $return    'ok' o 'cancel'
     * @param string     $reference
     * @param Order|null $order     si está, se agrega su carro (lo pide el retorno ok);
     *                              solo con un pedido ya comprobado del visitante
     *
     * @return string
     */
    private function validateUrl($return, $reference, $order)
    {
        $params = array('return' => $return, 'reference' => $reference);

        if ($order instanceof Order) {
            $params['cartId'] = (int)$order->id_cart;
        }

        return $this->context->link->getModuleLink($this->module->name, 'validate', $params, true);
    }

    /**
     * Respuesta JSON limpia, sin plantilla ni columnas. No lleva nada del pago:
     * solo si se canceló y a dónde ir. Los rechazos van con 403; el cuerpo
     * JSON es el mismo, y khipu-embedded.js lo lee igual (fetch no falla por
     * el código HTTP).
     *
     * @param bool   $ok
     * @param string $redirect
     */
    private function respond($ok, $redirect)
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (!$ok) {
            http_response_code(403);
        }

        die(json_encode(array('ok' => (bool)$ok, 'redirect' => (string)$redirect)));
    }
}
