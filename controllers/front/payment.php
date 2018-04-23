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

class KhipuPaymentPaymentModuleFrontController extends ModuleFrontController
{

    public function initContent()
    {
        $cart = $this->context->cart;

        $khipu_payment = new KhipuPayment();
        $khipu_payment->validateOrder(
            (int)self::$cart->id,
            (int)Configuration::get('PS_OS_KHIPU_OPEN'),
            (float)self::$cart->getOrderTotal(),
            $khipu_payment->displayName,
            null,
            array(),
            null,
            false,
            self::$cart->secure_key
        );

        parent::initContent();

        $customer = $this->context->customer;

        $configuration = new Khipu\Configuration();
        $configuration->setSecret(Configuration::get('KHIPU_SECRETCODE'));
        $configuration->setReceiverId(Configuration::get('KHIPU_MERCHANTID'));
        $configuration->setPlatform('prestashop-khipu', $khipu_payment->version);


        $client = new Khipu\ApiClient($configuration);
        $payments = new Khipu\Client\PaymentsApi($client);

        $shopDomainSsl = Tools::getShopDomainSsl(
            true,
            true
        );


        $currency = Currency::getCurrencyInstance($cart->id_currency);

        $precision = 0; //CLP $currency['decimals'] * _PS_PRICE_COMPUTE_PRECISION_;


        $interval = new DateInterval('PT' . Configuration::get('KHIPU_HOURS_TIMEOUT') . 'H');
        $timeout = new DateTime('now');
        $timeout->add($interval);


        $opts = array(
            'transaction_id' => $cart->id
        ,
            'return_url' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__
                . "index.php?fc=module&module={$khipu_payment->name}&controller=validate&return=ok&cartId=" . $cart->id
        ,
            'cancel_url' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__
                . "index.php?fc=module&module={$khipu_payment->name}&controller=validate&return=cancel&cartId=" . $cart->id
        ,
            'notify_url' => $shopDomainSsl . __PS_BASE_URI__ . "modules/{$khipu_payment->name}/validate.php"
        ,
            'notify_api_version' => '1.3'
        ,
            'payer_email' => $customer->email
        ,
            'expires_date' => $timeout
        ,
            'bank_id' => Tools::getValue('bank-id')
        );

        try {
            $createPaymentResponse = $payments->paymentsPost(
                Configuration::get('PS_SHOP_NAME') . ' Carro #' . $cart->id
                , $currency->iso_code
                , Tools::ps_round((float)$cart->getOrderTotal(true, Cart::BOTH), $precision)
                , $opts
            );
        } catch (\Khipu\ApiException $exception) {
            $this->context->smarty->assign(
                array(
                    'error' => $exception->getResponseObject()
                )
            );
            $this->setTemplate('module:khipupayment/views/templates/front/khipu_error.tpl');
            return;
        }

        if (!$createPaymentResponse->getReadyForTerminal()) {
            Tools::redirect($createPaymentResponse->getTransferUrl());
            return;
        }


        $query_string = "&payment_id=" . urlencode($createPaymentResponse->getPaymentId())
            . "&url=" . urlencode($createPaymentResponse->getPaymentUrl());

        Tools::redirect(
            $shopDomainSsl
            . __PS_BASE_URI__ . "index.php?fc=module&module={$khipu_payment->name}&controller=terminal"
            . $query_string
        );
    }
}
