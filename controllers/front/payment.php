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
        $configuration->setPlatform('prestashop-khipu', KhipuPayment::PLUGIN_VERSION);


        $client = new Khipu\ApiClient($configuration);
        $payments = new Khipu\Client\PaymentsApi($client);

        $shopDomainSsl = Tools::getShopDomainSsl(
            true,
            true
        );


        $currency = Currency::getCurrency($cart->id_currency);

        $precision = $currency['decimals'] * _PS_PRICE_COMPUTE_PRECISION_;


        $interval = new DateInterval('PT' . Configuration::get('KHIPU_HOURS_TIMEOUT') . 'H');
        $timeout = new DateTime('now');
        $timeout->add($interval);


        try {
            $createPaymentResponse = $payments->paymentsPost(
                Configuration::get('PS_SHOP_NAME') . ' Carro #' . $cart->id
                , $currency['iso_code']
                , Tools::ps_round((float)$cart->getOrderTotal(true, Cart::BOTH), $precision)
                , $cart->id
                , null
                , null
                , Tools::getValue('bank-id')
                , Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__
                . "index.php?fc=module&module={$khipu_payment->name}&controller=validate&return=ok&cartId=" . $cart->id
                , Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__
                . "index.php?fc=module&module={$khipu_payment->name}&controller=validate&return=cancel&cartId="
                . $cart->id
                , null
                , $shopDomainSsl . __PS_BASE_URI__ . "modules/{$khipu_payment->name}/validate.php"
                , '1.3'
                , $timeout
                , null
                , null
                , $customer->email
                , null
                , null
                , null
                , null
            );
        } catch (\Khipu\ApiException $exception) {
            $this->context->smarty->assign(
                array(
                    'error' => $exception->getResponseObject()
                )
            );
            $this->setTemplate('khipu_error.tpl');
            return;
        }

        if (!$createPaymentResponse->getReadyForTerminal()) {
            Tools::redirect($createPaymentResponse->getTransferUrl());
            return;
        }


        $query_string = "&payment_id=" . urlencode($createPaymentResponse->getPaymentId())
               ."&url=".urlencode($createPaymentResponse->getPaymentUrl());

        Tools::redirect(
            $shopDomainSsl
            . __PS_BASE_URI__ . "index.php?fc=module&module={$khipu_payment->name}&controller=terminal"
            . $query_string
        );
    }
}
