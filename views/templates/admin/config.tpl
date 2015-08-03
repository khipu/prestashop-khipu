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

<img src="{$img_header|escape:'htmlall':'UTF-8'}"/>

<h2>{l s='Transferencia bancaria usando khipu' mod='khipupayment'}</h2>

<fieldset>
    <legend><img src="../img/admin/warning.gif"/>{l s='Information' mod='khipupayment'}</legend>
    <div class="margin-form">Module version: {$version|escape:'htmlall':'UTF-8'}</div>
    <div class="margin-form">API version: {$api_version|escape:'htmlall':'UTF-8'}</div>
    <label>{l s='Thank you page, error page' mod='khipupayment'}</label>

    <div class="margin-form"><input type="text" size="233" name="url"
                                    value="{$khipu_notify_url|escape:'htmlall':'UTF-8'}" readonly/></div>
    <label>{l s='Postback URL' mod='khipupayment'}</label>

    <div class="margin-form"><input type="text" size="233" name="url"
                                    value="{$khipu_postback_url|escape:'htmlall':'UTF-8'}" readonly/></div>
</fieldset>

<form action="{$post_url|escape:'htmlall':'UTF-8'}" method="post" style="clear: both; margin-top: 10px;">
    <fieldset>
        <legend><img src="../img/admin/contact.gif"/>{l s='Settings' mod='khipupayment'}</legend>
        {if isset($errors.merchantERR)}
            <div class="error">
                <p>{$errors.merchantERR|escape:'htmlall':'UTF-8'}</p>
            </div>
        {/if}

        <label for="merchantID">{l s='ID Cobrador' mod='khipupayment'}</label>

        <div class="margin-form"><input type="text" size="33" id="merchantID" name="merchantID"
                                        value="{$data_merchantid|escape:'htmlall':'UTF-8'}"/></div>
        <label for="secretCode">{l s='Llave secreta' mod='khipupayment'}</label>

        <div class="margin-form"><input type="text" size="33" name="secretCode"
                                        id="secretCode" value="{$data_secretcode|escape:'htmlall':'UTF-8'}"/></div>

        <label for="secretCode">{l s='Tipos de pago habilitados' mod='khipupayment'}</label>

        <div class="margin-form">
            <select name="paymentType">
                <option value="all" {if $data_paymentType eq "all"}selected{/if}>Todos</option>
                <option value="simple" {if $data_paymentType eq "simple"}selected{/if}>Transferencia simplificada (con
                    terminal de pagos khipu)
                </option>
                <option value="manual" {if $data_paymentType eq "manual"}selected{/if}>Transferencia normal</option>
            </select>
        </div>

        <div class="margin-form"><input type="checkbox" name="recommended" {if $data_recommended}checked{/if} value="1">Marcar
            como opción recomendada
        </div>

        <label for="merchantID">{l s='Horas para realizar el pago (pasado este tiempo la orden se cancela y se recupera el stock)' mod='khipupayment'}</label>

        <div class="margin-form"><input type="number" size="33" id="hoursTimeout" name="hoursTimeout"
                                        value="{$data_hoursTimeout|escape:'htmlall':'UTF-8'}"/></div>

        <center><input type="submit" name="khipu_updateSettings" value="{l s='Save Settings' mod='khipupayment'}"
                       class="button" style="cursor: pointer; display:"/></center>
    </fieldset>
</form>
