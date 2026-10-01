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

class KhipuPaymentValidateModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::initContent();

        $this->handleGET();
    }

    private function handleGET()
    {
        $cart_id = (int)Tools::getValue('cartId');
        $reference = Tools::getValue('reference');
        // Solo un texto: Tools::getValue() devuelve arreglos tal cual
        // (reference[]=A&reference[]=B) y Order::getByReference() los convierte
        // en un IN (...), así que se podían traer pedidos de otros clientes.
        $orders = (is_string($reference) && '' !== $reference) ? Order::getByReference($reference) : array();

        // Sin pedido no hay a dónde volver: la referencia viene de la URL, así
        // que cualquiera puede llegar acá con una inexistente. Antes esta rama
        // leía $orders[0] de una colección vacía y reventaba con un 500 en cara
        // del pagador, justo al volver de pagar.
        if (count($orders) == 0) {
            $this->logUnknownReference();

            Tools::redirect(Context::getContext()->link->getPageLink('index', true));

            return;
        }

        $customer = $orders[0]->getCustomer();

        if (Tools::getValue('return') == 'cancel') {
            // Cualquiera que conociera la referencia (un correo, una factura)
            // podía cancelar un pedido ajeno, incluso mientras el comprador
            // pagaba en Khipu. Solo cancela quien tiene la sesión del cliente; si
            // vuelve desde otro navegador, cancelExpiredOrders lo cancela al vencer.
            // Se comprueba cada pedido y no solo el primero de la colección.
            foreach ($orders as $order) {
                if (!$this->belongsToVisitor($order)) {
                    Tools::redirect(Context::getContext()->link->getPageLink('index', true));

                    return;
                }
            }

            $canceledState = (int)Configuration::get('PS_OS_CANCELED');
            foreach ($orders as $order) {
                if ($order->current_state == (int)Configuration::get('PS_OS_KHIPU_OPEN')) {
                    $this->module->setCurrentOrderState($order, $canceledState);
                }
            }

            // El carro se rehace acá y no con el submitReorder del core, que no
            // atiende a invitados: a ellos les dejaba el checkout vacío.
            //
            // Si el pedido quedó cancelado, sea ahora o antes (cancelExpiredOrders
            // lo cancela si el comprador se demoró en Khipu), pero no si ya está
            // pagado: rehacerlo invitaría a comprarlo dos veces. Y solo si la
            // sesión no tiene ya un carro propio con productos, que puede ser el
            // que se rehízo en un clic anterior o uno nuevo: no se le pisa.
            //
            // El carro que ya tiene pedido no cuenta como propio. En 8.1,
            // FrontController::init() lo saca de la sesión antes de llegar acá
            // y queda uno vacío; no se depende de que todas las versiones lo
            // hagan, porque si no, el comprador volvía al checkout con el carro
            // ya consumido en vez de uno que pueda pagar.
            $sessionCart = $this->context->cart;
            $sessionCartIsUsable = $sessionCart->nbProducts() && !$sessionCart->orderExists();

            if ((int)$orders[0]->current_state === $canceledState && !$sessionCartIsUsable) {
                if (!$this->module->restoreCartOf($orders[0])) {
                    $this->showMessage('cart_lost');

                    return;
                }
            }

            Tools::redirect(Context::getContext()->link->getPageLink('order', true));

        } else {
            if (Tools::getValue('return') == 'ok') {
                // La redirección lleva la secure_key del pedido en la URL, y se
                // le entregaba a cualquiera que supiera la referencia. En
                // PrestaShop 8 esa clave basta para ver la confirmación del
                // pedido y, si es de invitado, ponerle contraseña a su cuenta.
                //
                // Sin la sesión del cliente se muestra una página que no dice
                // nada del pedido. Antes se mandaba al historial, que pide
                // iniciar sesión, y un invitado no tiene con qué: el que pagó
                // en la app del banco y volvió en otro navegador se quedaba sin
                // ninguna confirmación.
                if (!$this->belongsToVisitor($orders[0])) {
                    $this->showMessage('received', Context::getContext()->link->getPageLink('history', true));

                    return;
                }

                Tools::redirect(
                    Context::getContext()->link->getPageLink(
                        'order-confirmation', true, null,
                        array(
                            "id_cart" => $cart_id,
                            "id_module" => Module::getInstanceByName($orders[0]->module)->id,
                            "id_order" => $orders[0]->id,
                            "key" => $customer->secure_key
                        )
                    )
                );
            }
        }
    }

    /**
     * Página con un aviso al comprador, dentro del tema de la tienda.
     *
     * @param string $kind      'received' (volvió de pagar sin la sesión) o
     *                          'cart_lost' (canceló y el carro no se pudo rehacer)
     * @param string $buttonUrl a dónde lleva el botón, si hay
     */
    private function showMessage($kind, $buttonUrl = '')
    {
        $this->context->smarty->assign(array(
            'khipu_kind' => $kind,
            'khipu_button_url' => $buttonUrl,
        ));
        $this->setTemplate('module:khipupayment/views/templates/front/khipu_message.tpl');
    }

    /**
     * Deja constancia de un retorno con referencia desconocida, a lo más una
     * vez por hora.
     *
     * Antes el mensaje llevaba la referencia y admitía duplicados: un escáner
     * que probara referencias al azar escribía una fila por intento, con texto
     * suyo, y tapaba los errores reales del log. El límite es propio porque el
     * de PrestaShopLogger (allow_duplicate = false) no funciona en contexto de
     * tienda: compara id_shop_group = 0 contra el NULL que él mismo guarda, y
     * nunca encuentra el duplicado (visto en 8.1).
     *
     * La hora del último aviso se guarda en Configuration y no se busca en el
     * log: PrestaShop carga Configuration entera en memoria en cada petición,
     * así que mirarla no cuesta una consulta, y ps_log no tiene índice sobre
     * message ni date_add. Buscar ahí recorría la tabla entera en cada petición
     * de un escáner, justo en las tiendas con el log más grande.
     */
    private function logUnknownReference()
    {
        if (time() - (int)Configuration::get('KHIPU_UNKNOWN_REF_LOGGED_AT') < 3600) {
            return;
        }

        Configuration::updateValue('KHIPU_UNKNOWN_REF_LOGGED_AT', time());
        PrestaShopLogger::addLog(
            'Khipu: retorno de pago con una referencia que no corresponde a ningún pedido.',
            2, null, 'Order', null, true
        );
    }

    /**
     * Si el pedido es del cliente con sesión abierta (también cuenta el de
     * invitado, que PrestaShop deja en la sesión al terminar la compra).
     *
     * @return bool
     */
    private function belongsToVisitor(Order $order)
    {
        $customer = $this->context->customer;

        return Validate::isLoadedObject($customer) && (int)$customer->id === (int)$order->id_customer;
    }
}