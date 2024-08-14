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

class KhipuPaymentSimplifiedModuleFrontController extends ModuleFrontController
{
    private $api_key;

    public function __construct()
    {
        parent::__construct();
        $this->api_key = Configuration::get('KHIPU_API_KEY');
    }

    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::initContent();

        $cart = $this->context->cart;

        $this->module->validateOrder(
            (int)$this->context->cart->id,
            (int)Configuration::get('PS_OS_KHIPU_OPEN'),
            (float)$cart->getOrderTotal(),
            $this->module->displayName,
            null,
            array(),
            null,
            false,
            $cart->secure_key
        );

        $order = new Order(Order::getOrderByCartId($cart->id));
        $customer = $this->context->customer;

        $data = [
            'subject' => Configuration::get('PS_SHOP_NAME') . ' Carro #' . $cart->id,
            'currency' => Currency::getCurrencyInstance($cart->id_currency)->iso_code,
            'amount' => Tools::ps_round((float)$cart->getOrderTotal(true, Cart::BOTH), 2),
            'transaction_id' => $order->reference,
            'custom' => (string)$order->id,
            'return_url' => $this->context->link->getModuleLink($this->module->name, 'validate', array("return"=>"ok", "reference"=>$order->reference, "cartId"=>$cart->id)),
            'cancel_url' => $this->context->link->getModuleLink($this->module->name, 'validate', array("return"=>"cancel", "reference"=>$order->reference)),
            'notify_url' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . "modules/{$this->module->name}/validate.php",
            'body' => $this->getCartProductDetails($cart),
            'payer_email' => $customer->email,
            'notify_api_version' => '3.0'
        ];

        $paymentResponse = $this->createKhipuPayment($data);

        if ($paymentResponse === false) {
            $this->context->smarty->assign('error', [
                'status' => 'Error',
                'message' => 'No se pudo procesar el pago con Khipu.'
            ]);
            $this->setTemplate('module:khipupayment/views/templates/front/khipu_error.tpl');
            return;
        }

        if (isset($paymentResponse['simplified_transfer_url'])) {
            Tools::redirect($paymentResponse['simplified_transfer_url']);
        } else {
            $this->context->smarty->assign('error', [
                'status' => 'Error',
                'message' => 'Respuesta no válida de la API de Khipu.',
                'errors' => isset($paymentResponse['errors']) ? $paymentResponse['errors'] : []
            ]);
            $this->setTemplate('module:khipupayment/views/templates/front/khipu_error.tpl');
        }
    }

    private function createKhipuPayment($data)
    {
        $url = 'https://payment-api.khipu.com/v3/payments';

        $body = json_encode([
            "subject" => $data['subject'],
            "currency" => $data['currency'],
            "amount" => $data['amount'],
            "transaction_id" => $data['transaction_id'],
            "custom" => $data['custom'],
            "return_url" => $data['return_url'],
            "cancel_url" => $data['cancel_url'],
            "notify_url" => $data['notify_url'],
            "body" => $data['body'],
            "payer_email" => $data['payer_email'],
            "notify_api_version" => "3.0"
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . Configuration::get('KHIPU_API_KEY')
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, "khipu-api-php-client/" . KhipuPayment::API_VERSION . "|prestashop-khipu/" . KhipuPayment::PLUGIN_VERSION);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code !== 200) {
            $error_message = curl_error($ch);
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        $response_data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return $response_data;
    }


    private function getCartProductDetails($cart)
    {
        $products = $cart->getProducts();
        $cartProductsKhipu = '';
        foreach ($products as $product) {
            $cartProductsKhipu .= $product['cart_quantity'] . " x " . $product['name'] . "\n";
        }
        return $cartProductsKhipu;
    }
}