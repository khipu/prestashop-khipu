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

<div id="wait-msg" class="alert alert-info">
    {if $status == 'ERR'}
        {l s='Pago declinado' mod='khipupayment'}
        <br/>
        <br/>
        <span class="bold">{l s='El pago de su orden ha sido declinado.' mod='khipupayment'}</span>
    {elseif $status == 'OPEN'}
        {l s='Pago en verificación' mod='khipupayment'}
        <br/>
        <br/>
        <span class="bold">{l s='El pago de su orden está siendo verificado.' mod='khipupayment'}
            {l s='Recibirá un correo electrónico cuando este pedido sea procesado.' mod='khipupayment'}
	</span>
    {/if}
</div>