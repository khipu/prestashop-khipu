{*
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
*  @author    khipu<support@khipu.com>
*  @copyright 2007-2015 khipu SpA
*  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{if $simpleTransfer}
    <p class="payment_module">
        <a href="{$link->getModuleLink('khipupayment', 'bankselect')|escape:'htmlall':'UTF-8'}"
           title="{l s='Transferencia simplificada usando khipu' mod='khipupayment'}">
            <img src="//bi.khipu.com/150x50/capsule/khipu/transparent/{$merchantID}"
                 alt="{l s='Transferencia simplificada' mod='khipupayment'}"/>
            {l s='Transferencia simplificada (Recomendada)' mod='khipupayment'}
        </a>
    </p>
{/if}
{if $regularTransfer}
    <p class="payment_module">
        <a href="{$link->getModuleLink('khipupayment', 'manual')|escape:'htmlall':'UTF-8'}"
           title="{l s='Transferencia bancaria usando khipu' mod='khipupayment'}">
            <img src="//bi.khipu.com/150x50/capsule/transfer/transparent/{$merchantID}"
                 alt="{l s='Transferencia normal' mod='khipupayment'}"/>
            {l s='Transferencia normal' mod='khipupayment'}
        </a>
    </p>
{/if}
{if $payme}
    <p class="payment_module">
        <a href="{$link->getModuleLink('khipupayment', 'payme')|escape:'htmlall':'UTF-8'}"
           title="{l s='Pago con tarjeta bancaria usando khipu' mod='khipupayment'}">
            <img src="//bi.khipu.com/150x50/capsule/payme/transparent/{$merchantID}"
                 alt="{l s='Tarjeta bancaria' mod='khipupayment'}"/>
            {l s='Tarjeta bancaria' mod='khipupayment'}
        </a>
    </p>
{/if}
