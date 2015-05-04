<?php
include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');
include_once (_PS_MODULE_DIR_ . 'khipupayment/lib/lib-khipu/src/Khipu.php');

class Khipu_Postback {

    private $byKhipuStatus;
    private $byPrestaStatus;
    private $version = '2.0.6';

    public function init() {
        define('_PS_ADMIN_DIR_', getcwd());

        // Load Presta Configuration
        Configuration::loadConfiguration();
        Context::getContext()->link = new Link();

        // Handle the postback
        $this->handlePOST();
    }

    private function handlePOST() {
        $khipu = new Khipu_Postback();


        $api_version = $_POST['api_version'];

        if($api_version == '1.2') {
            $this->validate_1_2_notification();
            return;
        } else if($api_version == '1.3') {
            $this->validate_1_3_notification();
            return;
        }

        //not supported
        return;

        
    }

    private function validate_1_2_notification() {
        $Khipu = new Khipu();
        // No necesitamos identificar al cobrador para usar este servicio.
        $khipu_service = $Khipu->loadService('VerifyPaymentNotification');
        // Adjuntamos los valores del $_POST en el servicio.
        $khipu_service->setDataFromPost();
        // Hacemos una solicitud a Khipu para verificar.
        $response = $khipu_service->verify();
        $order = new Order(Order::getOrderByCartId($_POST['transaction_id']));
        $cart = Cart::getCartByOrderId($order->id);
        print_r($cart);
        if ($response['response'] == 'VERIFIED' && Configuration::get('KHIPU_MERCHANTID') == $_POST['receiver_id'] && Tools::ps_round(floatval($cart->getOrderTotal(true, Cart::BOTH)), 0) == $_POST['amount']){
            $order->setCurrentState((int)Configuration::get('PS_OS_PAYMENT'));
            exit('Notification received correctly');
        } else {
            exit('Notification rejected [response: '.$response['response'].'] [ReceiverID: '.Configuration::get('KHIPU_MERCHANTID').' - '.$_POST['receiver_id'].'] [Amount: '.Tools::ps_round(floatval($cart->getOrderTotal(true, Cart::BOTH)), 0).' - '.$_POST['amount'].']');
        }
    }

    private function validate_1_3_notification() {
        $Khipu = new Khipu();
        $Khipu->authenticate(Configuration::get('KHIPU_MERCHANTID'), Configuration::get('KHIPU_SECRETCODE'));
        $Khipu->setAgent('prestashop-khipu-2.0.6;;'.Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ .';;'.Configuration::get('PS_SHOP_NAME'));
        $khipu_service = $Khipu->loadService('GetPaymentNotification');

        $khipu_service->setDataFromPost();
        $response = json_decode($khipu_service->consult());

        $order = new Order(Order::getOrderByCartId($response->transaction_id));
        $cart = Cart::getCartByOrderId($order->id);
        if (Configuration::get('KHIPU_MERCHANTID') == $response->receiver_id && Tools::ps_round(floatval($cart->getOrderTotal(true, Cart::BOTH)), 0) == $response->amount){
            $order->setCurrentState((int)Configuration::get('PS_OS_PAYMENT'));
            exit('Notification received correctly');
        } else {
            exit('Notification rejected [response: '.print_r($response,true).'] [ReceiverID: '.Configuration::get('KHIPU_MERCHANTID').' - '.$response->receiver_id.'] [Amount: '.Tools::ps_round(floatval($cart->getOrderTotal(true, Cart::BOTH)), 0).' - '.$response->amount.']');
        }
    }


}

$notify = new Khipu_Postback();
$notify->init();

