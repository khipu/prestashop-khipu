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
 * @copyright 2007-2020 khipu SpA
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
        $reference = Tools::getValue('reference');
        $orders = Order::getByReference($reference);
        if (count($orders) == 0) {
            Tools::redirect(
                Context::getContext()->link->getPageLink(
                    'order-detail', true, null,
                    array("id_order" => $orders[0]->id)
                )
            );
        }

        $customer = $orders[0]->getCustomer();

        if (Tools::getValue('return') == 'cancel') {
            foreach ($orders as $order) {
                if ($order->current_state == (int)Configuration::get('PS_OS_KHIPU_OPEN')) {
                    $this->module->setCurrentOrderState($order, (int)Configuration::get('PS_OS_CANCELED'));
                }
            }

            Tools::redirect(
                Context::getContext()->link->getPageLink(
                    'order', true, null, 'submitReorder&id_order=' . $orders[0]->id
                )
            );

        } else {
            if (Tools::getValue('return') == 'ok') {
                if ($this->context->customer->isLogged()) {
                    Tools::redirect(
                        Context::getContext()->link->getPageLink(
                            'order-detail', true, null,
                            array("id_order" => $orders[0]->id)
                        )
                    );
                } else {
                    Tools::redirect(
                        Context::getContext()->link->getPageLink(
                            'guest-tracking', true, null,
                            array("order_reference" => $orders[0]->reference, "email" => $customer->email)
                        )
                    );
                }
            }
        }
    }
}
