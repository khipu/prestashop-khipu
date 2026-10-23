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
*}

{extends file='page.tpl'}

{block name='page_title'}
    {l s='Paga con Khipu' mod='khipupayment'}
{/block}

{block name='page_content'}
    {* khipu-embedded.js lee estos data-* y monta acá el modal de Khipu Web. *}
    <div id="khipu-web-root"
         data-payment-id="{$payment_id|escape:'htmlall':'UTF-8'}"
         data-return-url="{$return_url|escape:'htmlall':'UTF-8'}"
         data-fallback-url="{$fallback_url|escape:'htmlall':'UTF-8'}"
         data-cancel-url="{$cancel_url|escape:'htmlall':'UTF-8'}"
         data-reference="{$reference|escape:'htmlall':'UTF-8'}"
         data-token="{$khipu_token|escape:'htmlall':'UTF-8'}"
         data-kws-url="{$kws_url|escape:'htmlall':'UTF-8'}"></div>

    <p class="khipu-embedded-msg">
        {l s='Abriendo Khipu para completar tu pago...' mod='khipupayment'}
        <a href="{$fallback_url|escape:'htmlall':'UTF-8'}">{l s='Si no se abre, haz clic aquí.' mod='khipupayment'}</a>
    </p>
{/block}
