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


    public function postProcess()
    {

        $configuration = new Khipu\Configuration();
        $configuration->setSecret(Configuration::get('KHIPU_SECRETCODE'));
        $configuration->setReceiverId(Configuration::get('KHIPU_MERCHANTID'));
        $khipu_payment = new KhipuPayment();
        $configuration->setPlatform('prestashop-khipu', $khipu_payment->version);


        $client = new Khipu\ApiClient($configuration);
        $banks = new Khipu\Client\BanksApi($client);


        try {
            $banksResponse = $banks->banksGet();
        } catch (\Khipu\ApiException $exception) {
            $this->context->smarty->assign(
                array(
                    'error' => $exception->getResponseObject()
                )
            );
            $this->setTemplate('module:khipupayment/views/templates/front/khipu_error.tpl');
            return;
        }

        $this->context->smarty->assign(
            array(
                'action' => Context::getContext()->link->getModuleLink('khipupayment', 'payment'),
                'request' => $_REQUEST,
                'banks' => $banksResponse->getBanks()
            )
        );

        $this->setTemplate('module:khipupayment/views/templates/front/bankselect.tpl');
    }
}
