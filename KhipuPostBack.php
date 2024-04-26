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

class KhipuPostback
{

    const PLUGIN_VERSION = '4.1';

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
        http_response_code(400);
        $configuration = new Khipu\Configuration();
        $configuration->setSecret(Configuration::get('KHIPU_SECRETCODE'));
        $configuration->setReceiverId(Configuration::get('KHIPU_MERCHANTID'));
        $configuration->setPlatform('prestashop-khipu', KhipuPostback::PLUGIN_VERSION);

        $client = new Khipu\ApiClient($configuration);
        $payments = new Khipu\Client\PaymentsApi($client);

        if (!Tools::getValue('notification_token')){
            exit('No notification_token');
        }

        if (!Tools::getValue('api_version')){
            exit('No apiVersion');
        }

        if (Tools::getValue('api_version') != '1.3'){
            exit('Wrong apiVersion, only 1.3 allowed');
        }

        try {
            $paymentResponse = $payments->paymentsGet(Tools::getValue('notification_token'));
        } catch(\Khipu\ApiException $exception) {
            exit(print_r($exception->getResponseObject(), TRUE));
            error_log(print_r($exception->getResponseObject(), TRUE));
            return;
        }


        $orders = Order::getByReference($paymentResponse->getTransactionId());

        if (count($orders) == 0) {
            exit('No order for reference '. $paymentResponse->getTransactionId());
        }

        if (count($orders) > 1) {
            exit('More than one order with the same reference '. $paymentResponse->getTransactionId());
        }

        $order = $orders[0];


        $cart = Cart::getCartByOrderId($order->id);


        $currency = Currency::getCurrencyInstance($cart->id_currency);

        $precision = 0;
        if($currency->iso_code =='CLP'){
            $precision = 0;
        } else if ($currency->iso_code == 'ARS' OR $currency->iso_code == 'EUR'){
            $precision = 2;
        }

        if (Configuration::get('KHIPU_MERCHANTID') == $paymentResponse->getReceiverId()
            && $paymentResponse->getStatus() == 'done'
            && Tools::ps_round((float)($order->total_paid_tax_incl), $precision) == $paymentResponse->getAmount()
        ) {
            if($order->current_state != (int)Configuration::get('PS_OS_PAYMENT')) {
                $order->setCurrentState((int)Configuration::get('PS_OS_PAYMENT'));
                $order_payment_collection = $order->getOrderPaymentCollection();
                $order_payment = $order_payment_collection[0];
                $order_payment->transaction_id = $paymentResponse->getPaymentId();
                $order_payment->update();
            }
            http_response_code(200);
            exit('Notification received correctly');
        } else {
            exit('Notification rejected [ReceiverId: '
                . Configuration::get('KHIPU_MERCHANTID')
                . '] [Payment ReceiverId: '
                . $paymentResponse->getReceiverId()
                .'] [Payment Status: '
                . $paymentResponse->getStatus()
                . '] [Order Total: '
                . Tools::ps_round((float)($order->total_paid_tax_incl), $precision)
                . '] [Payment Amount: '. $paymentResponse->getAmount()
                .']');
        }

    }
}
