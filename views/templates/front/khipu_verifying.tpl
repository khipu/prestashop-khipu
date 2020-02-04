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
*  @copyright 2007-2020 khipu SpA
*  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{extends file='page.tpl'}

{assign var='current_step' value='payment'}

{block name='content'}
    <h2>{l s='Verificando tu pago' mod='khipupayment'}</h2>
    <div id="wait-msg" class="alert alert-info">
        <strong>{l s='Estamos verificando tu pago' mod='khipupayment'}</strong><br>
        <div>{l s='Recibirás un correo electrónico cuando tu pedido sea procesado.' mod='khipupayment'}</div>
    </div>
{/block}
