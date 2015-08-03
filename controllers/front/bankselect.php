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

class KhipuPaymentBankselectModuleFrontController extends ModuleFrontController
{

    public function __construct()
    {
        parent::__construct();
        $this->display_column_left = false;
    }

    public function initContent()
    {
        parent::initContent();

        //$cart = $this->context->cart;

        $khipu = new Khipu();

        $khipu->authenticate(Configuration::get('KHIPU_MERCHANTID'), Configuration::get('KHIPU_SECRETCODE'));

        $khipu->setAgent(
            'prestashop-khipu-2.3.0;;' . Tools::getShopDomainSsl(
                true,
                true
            ) . __PS_BASE_URI__ . ';;' . Configuration::get('PS_SHOP_NAME')
        );
        $khipu_service = $khipu->loadService('ReceiverBanks');


        $banks = Tools::jsonDecode($khipu_service->consult());

        $this->context->smarty->assign(
            array(
                'action' => Context::getContext()->link->getModuleLink('khipupayment', 'payment'),
                'request' => $_REQUEST,
                'banks' => $banks->banks
            )
        );

        $this->setTemplate('bankselect.tpl');
    }
}
