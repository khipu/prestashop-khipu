<?php

class KhipuPaymentValidateModuleFrontController extends ModuleFrontController
{

    public $ssl = true;
    private $byKhipuStatus;
    private $byPrestaStatus;

    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::initContent();


        $this->handleGET();
    }

    private function handleGET()
    {
        $cartId = $_GET['cartId'];
        $order = new Order(Order::getOrderByCartId($cartId));
        $customer = $order->getCustomer();
        $modID = Module::getInstanceByName($order->module);

        if ($_GET['return'] == 'cancel') {
            Tools::redirect(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' . $cartId . '&id_module=' . (int)$modID->id . '&id_order=' . $order->id . '&key=' . $customer->secure_key . '&status=ERR');

        } else if ($_GET['return'] == 'ok') {
            Tools::redirect(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' . $cartId . '&id_module=' . (int)$modID->id . '&id_order=' . $order->id . '&key=' . $customer->secure_key . '&status=OPEN');
        }
    }


}
