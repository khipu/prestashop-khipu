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

class KhipuPaymentValidateModuleFrontController extends ModuleFrontController
{

    public $ssl = true;

    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::initContent();

        $this->handleGET();
    }

    private function handleGET()
    {
        $cart_id = Tools::getValue('cartId');
        $order = new Order(Order::getOrderByCartId($cart_id));
        $customer = $order->getCustomer();
        $mod_id = Module::getInstanceByName($order->module);

        if (Tools::getValue('return') == 'cancel') {
            Tools::redirect(
                Tools::getShopDomainSsl(
                    true,
                    true
                ) . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' . $cart_id
                . '&id_module='
                . (int)$mod_id->id . '&id_order=' . $order->id . '&key=' . $customer->secure_key . '&status=ERR'
            );

        } else {
            if (Tools::getValue('return') == 'ok') {
                Tools::redirect(
                    Tools::getShopDomainSsl(
                        true,
                        true
                    ) . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' . $cart_id
                    . '&id_module=' . (int)$mod_id->id . '&id_order=' . $order->id . '&key=' . $customer->secure_key
                    . '&status=OPEN'
                );
            }
        }
    }
}
