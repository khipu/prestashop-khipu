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

    const PLUGIN_VERSION = '3.0.5';

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
        $configuration = new Khipu\Configuration();
        $configuration->setSecret(Configuration::get('KHIPU_SECRETCODE'));
        $configuration->setReceiverId(Configuration::get('KHIPU_MERCHANTID'));
        $configuration->setPlatform('prestashop-khipu', KhipuPostback::PLUGIN_VERSION);

        $client = new Khipu\ApiClient($configuration);
        $payments = new Khipu\Client\PaymentsApi($client);

        try {
            $paymentResponse = $payments->paymentsGet(Tools::getValue('notification_token'));
        } catch(\Khipu\ApiException $exception) {
            error_log(print_r($exception->getResponseObject(), TRUE));
            return;
        }

        $order = new Order(Order::getOrderByCartId($paymentResponse->getTransactionId()));

        $cart = Cart::getCartByOrderId($order->id);

        //$currency = Currency::getCurrency($cart->id_currency);
        $currency = Currency::getCurrencyInstance($cart->id_currency);

        $precision = 0;
        if($currency->iso_code =='CLP'){
            $precision = 0;
        } else if ($currency->iso_code == 'BOB'){
            $precision = 2;
        }

        //$precision = $currency['decimals'] * _PS_PRICE_COMPUTE_PRECISION_;

        if (Configuration::get('KHIPU_MERCHANTID') == $paymentResponse->getReceiverId()
            && $paymentResponse->getStatus() == 'done'
            && Tools::ps_round((float)($cart->getOrderTotal(true, Cart::BOTH)), $precision) == $paymentResponse->getAmount()
        ) {
            $orders = Order::getByReference($order->reference);
            foreach ($orders as $referenced_order) {
                if($referenced_order->current_state == (int)Configuration::get('PS_OS_KHIPU_OPEN')) {
                    $referenced_order->setCurrentState((int)Configuration::get('PS_OS_PAYMENT'));
                }
            }
            exit('Notification received correctly');
        } else {
            exit('Notification rejected [response: ' . print_r($paymentResponse, true)
                . '] [ReceiverID: ' . Configuration::get('KHIPU_MERCHANTID') . ' - '
                . $paymentResponse->getReceiverId() . '] [Amount: ' . Tools::ps_round(
                    (float)($cart->getOrderTotal(
                        true,
                        Cart::BOTH
                    )),
                    $precision
                ) . ' - ' . $paymentResponse->getAmount()
                . ']');
        }

    }


}
