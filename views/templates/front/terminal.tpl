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
{extends "$layout"}
{block name="content"}
{capture name=path}{l s='Seleccione el banco' mod='khipupayment'}{/capture}
<h2>{l s='Resumen del pedido' mod='khipupayment'}</h2>

{assign var='current_step' value='payment'}

<div id="wait-msg" class="alert alert-info">
    Estamos iniciando la aplicación khipu, por favor espera unos segundos.<br>
    No cierres esta página, una vez que completes el pago serás redirigido automáticamente.<br><br>
    Si pasado unos segundos no se ha abierto la aplicación<br><a href="javascript:openKhipu();" class="btn btn-default">Pincha este botón para abrirla</a>
</div>
<div id="khipu-chrome-extension-div" style="display: none"></div>
<script>
    function openKhipu() {ldelim}
        KhipuLib.onLoad({ldelim}
            data:{ldelim}
                {foreach from=$data item=value key=key}
                "{$key|escape:'htmlall':'UTF-8'}": "{$value|escape:'htmlall':'UTF-8'}",
                {/foreach}
                {rdelim}
            {rdelim}
        );
        {rdelim}
    window.onload = openKhipu;
</script>
{/block}