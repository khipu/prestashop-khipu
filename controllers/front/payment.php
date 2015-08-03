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

        $khipu = new Khipu();

        $khipu->authenticate(Configuration::get('KHIPU_MERCHANTID'), Configuration::get('KHIPU_SECRETCODE'));
        $shopDomainSsl = Tools::getShopDomainSsl(
            true,
            true
        );
        $khipu->setAgent(
            'prestashop-khipu-2.3.0;;' . $shopDomainSsl . __PS_BASE_URI__ . ';;' . Configuration::get('PS_SHOP_NAME')
        );
        $khipu_service = $khipu->loadService('CreatePaymentURL');

        $data = array(
            'subject' => Configuration::get('PS_SHOP_NAME') . ' Carro #' . $cart->id,
            'body' => '',
            'amount' => Tools::ps_round((float)$cart->getOrderTotal(true, Cart::BOTH), 0),
            'return_url' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__
                . "index.php?fc=module&module={$khipu_payment->name}&controller=validate&return=ok&cartId=" . $cart->id,
            'cancel_url' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__
                . "index.php?fc=module&module={$khipu_payment->name}&controller=validate&return=cancel&cartId="
                . $cart->id,
            'transaction_id' => $cart->id,
            'payer_email' => $customer->email,
            'picture_url' => '',
            'bank_id' => Tools::getValue('bank-id'),
            'custom' => '',
            'expires_date' => time() + ((int)Configuration::get('KHIPU_HOURS_TIMEOUT')) * 3600,
            'notify_url' => $shopDomainSsl . __PS_BASE_URI__ . "modules/{$khipu_payment->name}/validate.php"
        );
        foreach ($data as $name => $value) {
            $khipu_service->setParameter($name, $value);
        }

        $json = $khipu_service->createUrl();

        $data = Tools::jsonDecode($json, true);

        if (!$data['ready-for-terminal']) {
            Tools::redirect($data['url']);
            return;
        }

        $query_string = "";
        foreach ($data as $key => $value) {
            $query_string .= "&$key=" . urlencode($value);
        }

        Tools::redirect(
            $shopDomainSsl
            . __PS_BASE_URI__ . "index.php?fc=module&module={$khipu_payment->name}&controller=terminal"
            . $query_string
        );
    }
}
