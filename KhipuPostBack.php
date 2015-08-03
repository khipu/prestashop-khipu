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
 * @copyright 2007-2015 khipu SpA
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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

    private function handlePOST()
    {
        $api_version = Tools::getValue('api_version');

        if ($api_version == '1.2') {
            $this->validate12Notification();
            return;
        } else {
            if ($api_version == '1.3') {
                $this->validate13Notification();
                return;
            }
        }

    }

    private function validate12Notification()
    {
        $khipu_lib = new Khipu();
        // No necesitamos identificar al cobrador para usar este servicio.
        $khipu_service = $khipu_lib->loadService('VerifyPaymentNotification');
        // Adjuntamos los valores del $_POST en el servicio.
        $khipu_service->setDataFromPost();
        // Hacemos una solicitud a Khipu para verificar.
        $response = $khipu_service->verify();
        $order = new Order(Order::getOrderByCartId(Tools::getValue('transaction_id')));
        $cart = Cart::getCartByOrderId($order->id);
        $receiver_id = Tools::getValue('receiver_id');
        $merchant_id = Configuration::get('KHIPU_MERCHANTID');
        if ($response['response'] == 'VERIFIED' && $merchant_id == $receiver_id
            && Tools::ps_round((float)$cart->getOrderTotal(true, Cart::BOTH), 0) == Tools::getValue('amount')
        ) {
            $orders = Order::getByReference($order->reference);
            foreach ($orders as $referenced_order) {
                $referenced_order->setCurrentState((int)Configuration::get('PS_OS_PAYMENT'));
            }
            exit('Notification received correctly');
        } else {

            exit('Notification rejected [response: ' . $response['response'] . '] [ReceiverID: ' . $merchant_id . ' - '
                . $receiver_id . '] [Amount: ' . Tools::ps_round(
                    (float)$cart->getOrderTotal(
                        true,
                        Cart::BOTH
                    ),
                    0
                ) . ' - '
                . Tools::getValue('amount')
                . ']');
        }
    }

    private function validate13Notification()
    {
        $khipu_lib = new Khipu();
        $khipu_lib->authenticate(Configuration::get('KHIPU_MERCHANTID'), Configuration::get('KHIPU_SECRETCODE'));
        $shopDomainSsl = Tools::getShopDomainSsl(
            true,
            true
        );
        $khipu_lib->setAgent(
            'prestashop-khipu-2.3.0;;' . $shopDomainSsl . __PS_BASE_URI__ . ';;' . Configuration::get('PS_SHOP_NAME')
        );
        $khipu_service = $khipu_lib->loadService('GetPaymentNotification');

        $khipu_service->setDataFromPost();
        $response = Tools::jsonDecode($khipu_service->consult());

        $order = new Order(Order::getOrderByCartId($response->transaction_id));
        $cart = Cart::getCartByOrderId($order->id);
        if (Configuration::get('KHIPU_MERCHANTID') == $response->receiver_id
            && Tools::ps_round((float)($cart->getOrderTotal(true, Cart::BOTH)), 0) == $response->amount
        ) {
            $orders = Order::getByReference($order->reference);
            foreach ($orders as $referenced_order) {
                $referenced_order->setCurrentState((int)Configuration::get('PS_OS_PAYMENT'));
            }
            exit('Notification received correctly');
        } else {
            exit('Notification rejected [response: ' . print_r($response, true)
                . '] [ReceiverID: ' . Configuration::get('KHIPU_MERCHANTID') . ' - '
                . $response->receiver_id . '] [Amount: ' . Tools::ps_round(
                    (float)($cart->getOrderTotal(
                        true,
                        Cart::BOTH
                    )),
                    0
                ) . ' - ' . $response->amount
                . ']');
        }
    }
}
