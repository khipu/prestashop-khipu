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

class KhipuPaymentWebpaypspModuleFrontController extends ModuleFrontController
{

    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::initContent();

        $cart = $this->context->cart;

        $this->module->validateOrder(
            (int)self::$cart->id,
            (int)Configuration::get('PS_OS_KHIPU_OPEN'),
            (float)self::$cart->getOrderTotal(),
            $this->module->displayName,
            null,
            array(),
            null,
            false,
            self::$cart->secure_key
        );

        $order = new Order(Order::getOrderByCartId($cart->id));



        $customer = $this->context->customer;


        $configuration = new Khipu\Configuration();
        $configuration->setSecret(Configuration::get('KHIPU_SECRETCODE'));
        $configuration->setReceiverId(Configuration::get('KHIPU_MERCHANTID'));
        $configuration->setPlatform('prestashop-khipu', $this->module->version);


        $client = new Khipu\ApiClient($configuration);
        $payments = new Khipu\Client\PaymentsApi($client);

        $shopDomainSsl = Tools::getShopDomainSsl(
            true,
            true
        );

        $currency = Currency::getCurrencyInstance($cart->id_currency);

        $precision = 0; //BOB $currency['decimals'] * _PS_PRICE_COMPUTE_PRECISION_; //LM: CHECK

        $interval = new DateInterval('PT' . Configuration::get('KHIPU_MINUTES_TIMEOUT') . 'M');
        $timeout = new DateTime('now');
        $timeout->add($interval);

        $opts = array(
            'transaction_id' => $order->reference
        ,
            'return_url' => Context::getContext()->link->getModuleLink($this->module->name, 'validate', array("return"=>"ok", "reference"=>$order->reference))
        ,
            'cancel_url' => Context::getContext()->link->getModuleLink($this->module->name, 'validate', array("return"=>"cancel", "reference"=>$order->reference))
        ,
            'notify_url' => $shopDomainSsl . __PS_BASE_URI__ . "modules/{$this->module->name}/validate.php"
        ,
            'notify_api_version' => '1.3'
        ,
            'payer_email' => $customer->email
        ,
            'expires_date' => $timeout
        ,
            'mandatory_payment_method' => 'WEBPAY_PSP'
        );

        try {
            $createPaymentResponse = $payments->paymentsPost(
                Configuration::get('PS_SHOP_NAME') . ' Carro #' . $cart->id
                , $currency->iso_code
                , Tools::ps_round((float)$cart->getOrderTotal(true, Cart::BOTH), $precision)
                , $opts);
        } catch (\Khipu\ApiException $exception) {
            $this->context->smarty->assign(
                                    array(
                                            'error' => $exception->getResponseObject()
                                        )
                                    );
            $this->setTemplate('module:khipupayment/views/templates/front/khipu_error.tpl');
            return;
        }

        Tools::redirect($createPaymentResponse->getWebpayUrl());
    }
}
