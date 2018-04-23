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

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

require __DIR__ . '/vendor/autoload.php';
if (!defined('_PS_VERSION_')) {
    exit;
}

class KhipuPayment extends PaymentModule
{
    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;
    protected $_html = '';
    protected $_postErrors = array();

    public function __construct()
    {
        $this->name = 'khipupayment';
        $this->tab = 'payments_gateways';
        $this->version = '3.0.5';
        $this->apiVersion = '2.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Khipu SpA';
        $this->controllers = array('validate');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Khipu');
        $this->description = $this->l('Paga usando Khipu');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
        $this->merchantID = Configuration::get('KHIPU_MERCHANTID');
        $this->secretCode = Configuration::get('KHIPU_SECRETCODE');
        $this->simpleTransfer = Configuration::get('KHIPU_SIMPLE_TRANSFER_ENABLED');
        $this->regularTransfer = Configuration::get('KHIPU_REGULAR_TRANSFER_ENABLED');
        $this->payme = Configuration::get('KHIPU_PAYME_ENABLED');
        $this->notify_url = Configuration::get('KHIPU_NOTIFY_URL');
        $this->postback_url = Configuration::get('KHIPU_POSTBACK_URL');

        $this->hoursTimeout = (Configuration::get('KHIPU_HOURS_TIMEOUT') ? Configuration::get(
            'KHIPU_HOURS_TIMEOUT'
        ) : 6);
    }


    public function install()
    {

        if (parent::install() && $this->registerHook('paymentOptions') && $this->registerHook('paymentReturn')) {
            $this->addOrderStates();
            return true;
        }
    }

    private function addOrderStates()
    {
        if (!(Configuration::get('PS_OS_KHIPU_OPEN') > 0)) {
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
        return
            parent::uninstall()
            && Configuration::deleteByName('KHIPU_MERCHANTID')
            && Configuration::deleteByName('KHIPU_SECRETCODE')
            && Configuration::deleteByName('KHIPU_SIMPLE_TRANSFER_ENABLED')
            && Configuration::deleteByName('KHIPU_REGULAR_TRANSFER_ENABLED')
            && Configuration::deleteByName('KHIPU_PAYME_ENABLED')
            && Configuration::deleteByName('KHIPU_NOTIFY_URL')
            && Configuration::deleteByName('KHIPU_POSTBACK_URL')
            && $this->unregisterHook('paymentOptions')
            && $this->unregisterHook('paymentReturn');

    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        $payment_options = array();
        switch ($this->context->currency->iso_code) {
            case "CLP":
                $payment_options = [];
                if(Configuration::get('KHIPU_SIMPLE_TRANSFER_ENABLED')){
                    $payment_options[] = $this->getKhipuTerminalPayment();
                }
                if(Configuration::get('KHIPU_REGULAR_TRANSFER_ENABLED')) {
                    $payment_options[] = $this->getKhipuNormalTransferPayment();
                }
                if(!Configuration::get('KHIPU_SIMPLE_TRANSFER_ENABLED') && !Configuration::get('KHIPU_REGULAR_TRANSFER_ENABLED')){
                    $payment_options[] = $this->getKhipuTerminalPayment();
                    $payment_options[] = $this->getKhipuNormalTransferPayment();
                }
                break;

            case "BOB":
                $payment_options = [
                    $this->getKhipuPayMe()
                ];
                break;
            case "USD":
                $payment_options = [
                    $this->getKhipuPayMe()
                ];
                break;

        }
        return $payment_options;
    }


    public function getKhipuTerminalPayment()
    {
        $terminal = new PaymentOption();
        $terminal->setCallToActionText($this->l('Paga usando Khipu'))
            ->setAction($this->context->link->getModuleLink($this->name, 'bankselect', array(), true))
            ->setAdditionalInformation(
                $this->context->smarty->fetch('module:khipupayment/views/templates/hook/info_terminal.tpl')
            )
            ->setLogo('https://bi.khipu.com/150x50/capsule/khipu/transparent/' . $this->merchantID);

        return $terminal;
    }

    public function getKhipuNormalTransferPayment()
    {
        $normalTransfer = new PaymentOption();
        $normalTransfer->setCallToActionText($this->l('Transferencia Normal'))
            ->setAction($this->context->link->getModuleLink($this->name, 'manual', array(), true))
            ->setAdditionalInformation(
                $this->context->smarty->fetch('module:khipupayment/views/templates/hook/info_normal.tpl')
            )
            ->setLogo('https://bi.khipu.com/150x50/capsule/transfer/transparent/' . $this->merchantID);

        return $normalTransfer;
    }


    public function getKhipuPayMe()
    {
        $payme = new PaymentOption();
        $payme->setCallToActionText($this->l('Paga mediante PayMe'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payme', array(), true))
            ->setAdditionalInformation(
                $this->context->smarty->fetch('module:khipupayment/views/templates/hook/info_payme.tpl')
            )
            ->setLogo('https://bi.khipu.com/150x50/capsule/payme/transparent/' . $this->merchantID);

        return $payme;
    }


    public function getContent()
    {

        if (Tools::getIsset('khipu_updateSettings')) {
            Configuration::updateValue('KHIPU_MERCHANTID', trim(Tools::getValue('merchantID')));
            Configuration::updateValue('KHIPU_SECRETCODE', trim(Tools::getValue('secretCode')));
            Configuration::updateValue('KHIPU_SIMPLE_TRANSFER_ENABLED', Tools::getValue('simpleTransfer'));
            Configuration::updateValue('KHIPU_REGULAR_TRANSFER_ENABLED', Tools::getValue('regularTransfer'));
            Configuration::updateValue('KHIPU_PAYME_ENABLED', Tools::getValue('payme'));
            Configuration::updateValue('KHIPU_NOTIFY_URL', Tools::getValue('notify_url'));
            Configuration::updateValue('KHIPU_POSTBACK_URL', Tools::getValue('postback_url'));

            if ((int)Tools::getValue('hoursTimeout') > 0) {
                Configuration::updateValue('KHIPU_HOURS_TIMEOUT', (int)Tools::getValue('hoursTimeout'));
            }


            $this->merchantID = Configuration::get('KHIPU_MERCHANTID');
            $this->secretCode = Configuration::get('KHIPU_SECRETCODE');
            $this->simpleTransfer = Configuration::get('KHIPU_SIMPLE_TRANSFER_ENABLED');
            $this->regularTransfer = Configuration::get('KHIPU_REGULAR_TRANSFER_ENABLED');
            $this->payme = Configuration::get('KHIPU_PAYME_ENABLED');
            $this->notify_url = Configuration::get('KHIPU_NOTIFY_URL');
            $this->postback_url = Configuration::get('KHIPU_POSTBACK_URL');

            $this->hoursTimeout = (Configuration::get('KHIPU_HOURS_TIMEOUT') ? Configuration::get(
                'KHIPU_HOURS_TIMEOUT'
            ) : 6);
        }


        $shopDomainSsl = Tools::getShopDomainSsl(true, true);
        $params = array(
            'post_url' => $_SERVER['REQUEST_URI'],
            'data_merchantid' => $this->merchantID,
            'data_secretcode' => $this->secretCode,
            'data_hoursTimeout' => $this->hoursTimeout,
            'data_simpleTransfer' => $this->simpleTransfer,
            'data_regularTransfer' => $this->regularTransfer,
            'data_payme' => $this->payme,
            'version' => $this->version,
            'api_version' => $this->apiVersion,
            'img_header' => $shopDomainSsl . __PS_BASE_URI__ . "modules/{$this->name}/logo.png",
            'khipu_notify_url' => $shopDomainSsl . __PS_BASE_URI__ . "index.php?fc=module&module={$this->name}&controller=validate",
            'khipu_postback_url' => $shopDomainSsl . __PS_BASE_URI__ . "modules/{$this->name}/validate.php"
        );

        $this->context->smarty->assign($params);

        return $this->display($this->name, 'views/templates/admin/config.tpl');
    }

}
