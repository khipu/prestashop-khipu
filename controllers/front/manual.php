<?php

class KhipuPaymentManualModuleFrontController extends ModuleFrontController
{

    public function initContent()
    {
        global $smarty;

        $this->display_column_left = false;
        $this->display_column_right = false;

        $cart = $this->context->cart;

        $khipuPayment = new KhipuPayment();
        $khipuPayment->validateOrder((int)self::$cart->id, (int)Configuration::get('PS_OS_KHIPU_OPEN'), (float)self::$cart->getOrderTotal(), $khipuPayment->displayName, NULL, array(), NULL, false, self::$cart->secure_key);

        parent::initContent();


        $customer = $this->context->customer;
        $currency = Currency::getCurrency($cart->id_currency);

        $khipu = new Khipu();


        $khipu->authenticate(Configuration::get('KHIPU_MERCHANTID'), Configuration::get('KHIPU_SECRETCODE'));
        $khipu->setAgent('prestashop-khipu-2.0.3');
        $khipu_service = $khipu->loadService('CreatePaymentURL');

        $data = array(
            'subject' => Configuration::get('PS_SHOP_NAME') . ' Carro #' . $cart->id,
            'body' => '',
            'amount' => Tools::ps_round(floatval($cart->getOrderTotal(true, Cart::BOTH)), 0),
            'return_url' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . "index.php?fc=module&module={$khipuPayment->name}&controller=validate&return=ok&cartId=" . $cart->id,
            'cancel_url' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . "index.php?fc=module&module={$khipuPayment->name}&controller=validate&return=cancel&cartId=" . $cart->id,
            'transaction_id' => $cart->id,
            'payer_email' => $customer->email,
            'picture_url' => '',
            'custom' => '',
            'notify_url' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . "modules/{$khipuPayment->name}/validate.php"
        );
        foreach ($data as $name => $value) {
            $khipu_service->setParameter($name, $value);
        }

        $data = json_decode($khipu_service->createUrl(), true);


        Tools::redirect($data['manual-url']);
    }

}
