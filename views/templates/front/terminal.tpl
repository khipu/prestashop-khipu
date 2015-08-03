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

{capture name=path}{l s='Seleccione el banco' mod='khipupayment'}{/capture}
<h2>{l s='Resumen del pedido' mod='khipupayment'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}
{include file="$tpl_dir./errors.tpl"}

<div id="wait-msg" class="alert alert-info">
    Estamos iniciando el terminal de pagos khipu, por favor espera unos segundos.<br>No
    cierres esta página, una vez que completes el pago serás redirigido automáticamente.
</div>
<div id="khipu-chrome-extension-div" style="display: none"></div>
<script>
    window.onload = function () {ldelim}
        KhipuLib.onLoad({ldelim}
                    data:{ldelim}
                    {foreach from=$data item=value key=key}
                        "{$key|escape:'htmlall':'UTF-8'}": "{$value|escape:'htmlall':'UTF-8'}",
                    {/foreach}
                        {rdelim}
                    {rdelim}
        )
        {rdelim}
</script>
