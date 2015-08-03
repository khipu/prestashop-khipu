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

class KhipuPayment extends PaymentModule
{

    protected $errors = array();

    public function __construct()
    {
        include_once(_PS_MODULE_DIR_ . 'khipupayment/lib/lib-khipu/src/Khipu.php');

        $this->name = 'khipupayment';
        $this->module_key = '44ce18c9f730a38ff054c6a2a535c296';

        // Calling the parent's constructor. This must be done before any use of the $this->l() method,
        // and after the creation of $this->name.
        parent::__construct();

        $this->displayName = $this->l('khipu');
        $this->description = $this->l('Transferencia bancaria usando khipu');

        $this->author = 'khipu';
        $this->version = '2.3.0';
        $this->tab = 'payments_gateways';

        // Module settings
        $this->setModuleSettings();

        // Check module requirements
        $this->checkModuleRequirements();
    }

    private function setModuleSettings()
    {
        $this->merchantID = Configuration::get('KHIPU_MERCHANTID');
        $this->secretCode = Configuration::get('KHIPU_SECRETCODE');
        $this->paymentType = Configuration::get('KHIPU_PAYMENTYPE');
        $this->recommended = Configuration::get('KHIPU_RECOMMENDED');
        $this->hoursTimeout = (Configuration::get('KHIPU_HOURS_TIMEOUT') ? Configuration::get(
            'KHIPU_HOURS_TIMEOUT'
        ) : 3);
    }

    private function checkModuleRequirements()
    {
        if (!Configuration::get('KHIPU_HOURS_TIMEOUT')) {
            Configuration::updateValue('KHIPU_HOURS_TIMEOUT', 6);
        }
        $this->errors = array();
    }

    public function install()
    {
        if (!parent::install()
            or !$this->registerHook('payment')
            or !$this->registerHook('paymentReturn')
            or !$this->registerHook('displayHeader')
            or !$this->registerHook('displayBackOfficeHeader')
        ) {
            return false;
        }

        $this->addOrderStates();

        return true;
    }

    private function addOrderStates()
    {
        if (!(Configuration::get('PS_OS_KHIPU_OPEN') > 0)) {
            // Open
            $OrderState = new OrderState(null, Configuration::get('PS_LANG_DEFAULT'));
            $OrderState->name = "Esperando pago khipu";
            $OrderState->invoice = false;
            $OrderState->send_email = false;
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

            if (file_exists(dirname(dirname(dirname(__file__))) . '/img/os/10.gif')) {
                copy(
                    dirname(dirname(dirname(__file__))) . '/img/os/10.gif',
                    dirname(dirname(dirname(__file__))) . '/img/os/' . $OrderState->id . '.gif'
                );
            }
        }
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        // Drop the paymentmethod table
        Db::getInstance()->execute("DROP TABLE if exists {$this->dbPmInfo}");

        // Drop the paymentmethod raw data table
        Db::getInstance()->execute("DROP TABLE if exists {$this->dbRawData}");

        return true;
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        $this->checkExpireOrders();
    }

    public function checkExpireOrders()
    {
        if (!Configuration::get('KHIPU_LAST_orDER_EXPIRE_RUN')) {
            Configuration::updateValue('KHIPU_LAST_orDER_EXPIRE_RUN', 1);
        }

        if ((time() - Configuration::get('KHIPU_LAST_orDER_EXPIRE_RUN')) > 10 * 60) {
            Configuration::updateValue('KHIPU_LAST_orDER_EXPIRE_RUN', time());
            $orders = Order::getOrderIdsByStatus((int)Configuration::get('PS_OS_KHIPU_OPEN'));
            foreach ($orders as $order_id) {
                $order = new Order($order_id);
                if ((time() - strtotime($order->date_upd)) > ((int)Configuration::get('KHIPU_HOURS_TIMEOUT')) * 60 * 60
                ) {
                    echo "expire $order_id";
                    foreach (Order::getByReference($order->reference) as $referencedOrder) {
                        $referencedOrder->setCurrentState((int)Configuration::get('PS_OS_CANCELED'));
                    }
                }
            }
        }
    }

    public function hookDisplayHeader($params)
    {
        $this->checkExpireOrders();
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
        $this->context->smarty->assign(
            array(
                'status' => Tools::getValue('status', 'OPEN')
            )
        );
        return $this->display(__FILE__, 'confirmation.tpl');
    }

    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }

        // Get active Shop ID for multistore shops
        //$activeShopID = (int)Context::getContext()->shop->id;

        $this->context->smarty->assign(
            array(
                'logo' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . "modules/{$this->name}/logo.png",
                'paymentType' => Configuration::get('KHIPU_PAYMENTYPE'),
                'recommended' => Configuration::get('KHIPU_RECOMMENDED')
            )
        );

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    public function getContent()
    {
        // Get active Shop ID for multistore shops
        //$activeShopID = (int)Context::getContext()->shop->id;

        if (Tools::getIsset('khipu_updateSettings')) {
            Configuration::updateValue('KHIPU_MERCHANTID', trim(Tools::getValue('merchantID')));
            Configuration::updateValue('KHIPU_SECRETCODE', trim(Tools::getValue('secretCode')));
            Configuration::updateValue('KHIPU_PAYMENTYPE', Tools::getValue('paymentType'));
            Configuration::updateValue('KHIPU_RECOMMENDED', Tools::getValue('recommended'));
            if ((int)Tools::getValue('hoursTimeout') > 0) {
                Configuration::updateValue('KHIPU_HOURS_TIMEOUT', (int)Tools::getValue('hoursTimeout'));
            }

            $this->setModuleSettings();
            $this->checkModuleRequirements();

        }

        $shopDomainSsl = Tools::getShopDomainSsl(true, true);
        $this->context->smarty->assign(
            array(
                'errors' => $this->errors,
                'post_url' => $_SERVER['REQUEST_URI'],
                'data_merchantid' => $this->merchantID,
                'data_secretcode' => $this->secretCode,
                'data_paymentType' => $this->paymentType,
                'data_recommended' => $this->recommended,
                'data_hoursTimeout' => $this->hoursTimeout,
                'version' => $this->version,
                'api_version' => Khipu::VERSION,
                'img_header' => $shopDomainSsl . __PS_BASE_URI__ . "modules/{$this->name}/logo.png",
                'khipu_notify_url' => $shopDomainSsl . __PS_BASE_URI__
                    . "index.php?fc=module&module={$this->name}&controller=validate",
                'khipu_postback_url' => $shopDomainSsl . __PS_BASE_URI__ . "modules/{$this->name}/validate.php"
            )
        );

        return $this->display($this->name, 'views/templates/admin/config.tpl');
    }
}
