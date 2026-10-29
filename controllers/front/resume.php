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

require_once _PS_MODULE_DIR_ . 'khipupayment/classes/KhipuCheckoutController.php';

/**
 * Retoma el modal embebido de un pedido que sigue esperando pago (recarga o «Atrás»).
 * El flujo vive en KhipuCheckoutController::resumeEmbedded().
 */
class KhipuPaymentResumeModuleFrontController extends KhipuCheckoutController
{
    protected function getPaymentUrlKey()
    {
        return 'simplified_transfer_url';
    }

    protected function getMethodId()
    {
        return 'SIMPLIFIED_TRANSFER';
    }

    protected function resumesOrder(): bool
    {
        return true;
    }
}
