<?php

if (!defined('_PS_VERSION_'))
    exit;

include_once (_PS_MODULE_DIR_ . 'khipupayment/lib/lib-khipu/src/Khipu.php');

class KhipuPayment extends PaymentModule {

    protected $_errors = array();

    public function __construct() {
        $this->name = 'khipupayment';

        // Calling the parent's constructor. This must be done before any use of the $this->l() method, and after the creation of $this->name.
        parent::__construct();

        $this->displayName = $this->l('khipu');
        $this->description = $this->l('Transferencia bancaria usando khipu');

        $this->author = 'khipu';
        $this->version = '2.0.1';
        $this->tab = 'payments_gateways';


        // Module settings
        $this->setModuleSettings();

        // Check module requirements
        $this->checkModuleRequirements();
    }

    public function install() {
        if (!parent::install() OR !$this->registerHook('payment') OR !$this->registerHook('paymentReturn'))
            return false;

        $this->addOrderStates();

        return true;
    }

    public function uninstall() {
        if (!parent::uninstall())
            return false;

        // Drop the paymentmethod table
        Db::getInstance()->execute("DROP TABLE if exists {$this->dbPmInfo}");

        // Drop the paymentmethod raw data table
        Db::getInstance()->execute("DROP TABLE if exists {$this->dbRawData}");

        return true;
    }

    private function addOrderStates() {
        if (!(Configuration::get('PS_OS_KHIPU_OPEN') > 0)) {
            // Open
            $OrderState = new OrderState(null, Configuration::get('PS_LANG_DEFAULT'));
            $OrderState->name = "Awaiting payment";
            $OrderState->invoice = false;
            $OrderState->send_email = true;
            $OrderState->module_name = $this->name;
            $OrderState->color = "RoyalBlue";
            $OrderState->unremovable = true;
            $OrderState->hidden = false;
            $OrderState->logable = false;
            $OrderState->delivery = false;
            $OrderState->shipped = false;
            $OrderState->paid = false;
            $OrderState->deleted = false;
            $OrderState->template = "order_changed";
            $OrderState->add();
            
            Configuration::updateValue("PS_OS_KHIPU_OPEN", $OrderState->id);
            
            if (file_exists(dirname(dirname(dirname(__file__))) . '/img/os/10.gif'))
                copy(dirname(dirname(dirname(__file__))) . '/img/os/10.gif', dirname(dirname(dirname(__file__))) . '/img/os/'.$OrderState->id.'.gif');
        }

        if (!(Configuration::get('PS_OS_KHIPU_AUTH') > 0)) {
            // AUTHORIZED
            $OrderState = new OrderState(null, Configuration::get('PS_LANG_DEFAULT'));
            $OrderState->name = "Payment Authorized";
            $OrderState->invoice = false;
            $OrderState->send_email = true;
            $OrderState->module_name = $this->name;
            $OrderState->color = "RoyalBlue";
            $OrderState->unremovable = true;
            $OrderState->hidden = false;
            $OrderState->logable = false;
            $OrderState->delivery = false;
            $OrderState->shipped = false;
            $OrderState->paid = false;
            $OrderState->deleted = false;
            $OrderState->template = "order_changed";
            $OrderState->add();
            
            Configuration::updateValue("PS_OS_KHIPU_AUTH", $OrderState->id);
            
            if (file_exists(dirname(dirname(dirname(__file__))) . '/img/os/10.gif'))
                copy(dirname(dirname(dirname(__file__))) . '/img/os/10.gif', dirname(dirname(dirname(__file__))) . '/img/os/'.$OrderState->id.'.gif');
        }
    }

    public function hookPaymentReturn($params) {
        if (!$this->active)
            return;
        global $smarty;
        $smarty->assign(array(
            'status' => Tools::getValue('status', 'OPEN')
        ));
        return $this->display(__FILE__, 'confirmation.tpl');
    }

    public function hookPayment($params) {
        if (!$this->active)
            return;

        global $smarty;

        // Get active Shop ID for multistore shops
        $activeShopID = (int) Context::getContext()->shop->id;
        
        $smarty->assign(array(
        	'logo' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . "modules/{$this->name}/logo.png",
            'paymentType' => Configuration::get('KHIPU_PAYMENTYPE')
        ));

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }


    public function getContent() { 
        // Get active Shop ID for multistore shops
        $activeShopID = (int) Context::getContext()->shop->id;

        if (isset($_POST['khipu_updateSettings'])) {
            Configuration::updateValue('KHIPU_MERCHANTID', Tools::getValue('merchantID'));
            Configuration::updateValue('KHIPU_SECRETCODE', Tools::getValue('secretCode'));
            Configuration::updateValue('KHIPU_PAYMENTYPE', Tools::getValue('paymentType'));

            $this->setModuleSettings();
            $this->checkModuleRequirements();

        }

        $this->context->smarty->assign(array(
            'errors' => $this->_errors,
            'post_url' => Tools::htmlentitiesUTF8($_SERVER['REQUEST_URI']),
            'data_merchantid' => $this->merchantID,
            'data_secretcode' => $this->secretCode,
            'data_paymentType' => $this->paymentType,
            'version' => $this->version,
	        'api_version' => Khipu::VERSION,
            'img_header' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . "modules/{$this->name}/logo.png",
            'khipu_notify_url' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . "index.php?fc=module&module={$this->name}&controller=validate",
            'khipu_postback_url' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . "modules/{$this->name}/validate.php"
        ));

        return $this->display($this->name, 'views/templates/admin/config.tpl');
    }

    private function checkModuleRequirements() {
        $this->_errors = array();
    }

    private function setModuleSettings() {
        $this->merchantID = Configuration::get('KHIPU_MERCHANTID');
        $this->secretCode = Configuration::get('KHIPU_SECRETCODE');
        $this->paymentType = Configuration::get('KHIPU_PAYMENTYPE');
    }

}

