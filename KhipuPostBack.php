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

        $secret = Configuration::get('KHIPU_SECRETCODE');
        $raw_post = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_KHIPU_SIGNATURE'];

        if (!$this->verifySignature($raw_post, $signature, $secret)) {
            exit('Invalid signature');
        }

        $paymentResponse = json_decode($raw_post, true);

        if (!$paymentResponse) {
            exit('Invalid payment response');
        }

        $orders = Order::getByReference($paymentResponse['transaction_id']);

        if (count($orders) == 0) {
            exit('No order for reference '. $paymentResponse['transaction_id']);
        }

        if (count($orders) > 1) {
            exit('More than one order with the same reference '. $paymentResponse['transaction_id']);
        }

        $order = $orders[0];
        $cart = Cart::getCartByOrderId($order->id);
        $currency = Currency::getCurrencyInstance($cart->id_currency);
        $precision = ($currency->iso_code == 'CLP') ? 0 : 2;

        if ($paymentResponse['receiver_id'] == Configuration::get('KHIPU_MERCHANTID') &&
            Tools::ps_round((float)($order->total_paid_tax_incl), $precision) == $paymentResponse['amount']) {

            if ($order->current_state != (int)Configuration::get('PS_OS_PAYMENT')) {
                $order->setCurrentState((int)Configuration::get('PS_OS_PAYMENT'));
                $order_payment_collection = $order->getOrderPaymentCollection();
                $order_payment = $order_payment_collection[0];
                $order_payment->transaction_id = $paymentResponse['payment_id'];
                $order_payment->update();
            }
            http_response_code(200);
            exit('Notification received correctly');
        } else {
            exit('Notification rejected [ReceiverId: '
                . Configuration::get('KHIPU_MERCHANTID')
                . '] [Payment ReceiverId: '
                . $paymentResponse['receiver_id']
                . '] [Order Total: '
                . Tools::ps_round((float)($order->total_paid_tax_incl), $precision)
                . '] [Payment Amount: '. $paymentResponse['amount']
                .']');
        }
    }

    private function verifySignature($raw_post, $signature, $secret)
    {
        $signature_parts = explode(',', $signature,2);
        foreach ($signature_parts as $part) {
            [$key, $value] = explode('=', $part,2);
            if ($key === 't') {
                $t_value = $value;
            } elseif ($key === 's') {
                $s_value = $value;
            }
        }
        $to_hash = $t_value . '.' . $raw_post;
        $hash_bytes = hash_hmac('sha256', $to_hash, $secret, true);
        $hmac_base64 = base64_encode($hash_bytes);

        return hash_equals($hmac_base64, $s_value);
    }
}
