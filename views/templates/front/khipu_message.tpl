{*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
*
*  @author    khipu<support@khipu.com>
*  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*
* Avisos al comprador que vuelve de Khipu. No muestra datos del pedido: la
* página se abre con solo conocer la referencia.
*}
{extends file='page.tpl'}

{block name='page_title'}
    {if $khipu_kind == 'received'}
        {l s='Gracias por tu pago' mod='khipupayment'}
    {else}
        {l s='Pago cancelado' mod='khipupayment'}
    {/if}
{/block}

{block name='page_content'}
    {if $khipu_kind == 'received'}
        <p>{l s='Si completaste el pago en Khipu, lo estamos confirmando. Te llegará un correo con la confirmación del pedido.' mod='khipupayment'}</p>
        <p>{l s='Si tienes cuenta en la tienda, también puedes ver el pedido en tu historial.' mod='khipupayment'}</p>
    {else}
        <p>{l s='Cancelaste el pago, pero no pudimos recuperar tu carro, quizás porque algún producto ya no está disponible. Vuelve a agregar los productos para comprar.' mod='khipupayment'}</p>
    {/if}

    {if $khipu_button_url}
        <p><a class="btn btn-primary" href="{$khipu_button_url}">{l s='Ver mis pedidos' mod='khipupayment'}</a></p>
    {/if}
{/block}
