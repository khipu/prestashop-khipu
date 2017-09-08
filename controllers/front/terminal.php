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

class KhipuPaymentTerminalModuleFrontController extends ModuleFrontController
{

    public function postProcess()
    {
        $this->context->smarty->assign(
            array(
                'data' => array(
                    'id' => Tools::getValue('payment_id'),
                    'url' => Tools::getValue('url'),
                    'ready-for-terminal' => 'true'
                )
            )
        );

        $this->setTemplate('module:khipupayment/views/templates/front/terminal.tpl');
    }


    public function setMedia()
    {
        parent::setMedia();
        $this->registerJavascript('module-khipupayment-khipulib', 'https://storage.googleapis.com/installer/khipu.js', ['server' => 'remote', 'position' => 'bottom', 'priority' => 20]);

    }
}
