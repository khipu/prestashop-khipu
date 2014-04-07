<img src="{$img_header}"/>

<h2>{l s='Transferencia bancaria usando khipu' mod='khipupayment'}</h2>

<fieldset>
    <legend><img src="../img/admin/warning.gif"/>{l s='Information' mod='khipupayment'}</legend>
    <div class="margin-form">Module version: {$version}</div>
    <div class="margin-form">API version: {$api_version}</div>
    <label>{l s='Thank you page, error page' mod='khipupayment'}</label>

    <div class="margin-form"><input type="text" size="233" name="url" value="{$khipu_notify_url}" readonly/></div>
    <label>{l s='Postback URL' mod='khipu'}</label>

    <div class="margin-form"><input type="text" size="233" name="url" value="{$khipu_postback_url}" readonly/></div>
</fieldset>

<form action="{$post_url}" method="post" style="clear: both; margin-top: 10px;">
    <fieldset>
        <legend><img src="../img/admin/contact.gif"/>{l s='Settings' mod='khipu'}</legend>
        {if isset($errors.merchantERR)}
            <div class="error">
                <p>{$errors.merchantERR}</p>
            </div>
        {/if}

        <label for="merchantID">{l s='ID Cobrador' mod='khipupayment'}</label>

        <div class="margin-form"><input type="text" size="33" id="merchantID" name="merchantID"
                                        value="{$data_merchantid}"/></div>
        <label for="secretCode">{l s='Llave secreta' mod='khipupayment'}</label>

        <div class="margin-form"><input type="text" size="33" name="secretCode"
                                        id="secretCode" value="{$data_secretcode}"/></div>

        <label for="secretCode">{l s='Tipos de pago habilitados' mod='khipupayment'}</label>

        <div class="margin-form">
            <select name="paymentType">
                <option value="all" {if $data_paymentType eq "all"}selected{/if}>Todos</option>
                <option value="simple" {if $data_paymentType eq "simple"}selected{/if}>Transferencia simplificada (con terminal de pagos khipu)</option>
                <option value="manual" {if $data_paymentType eq "manual"}selected{/if}>Transferencia manual (normal)</option>
            </select>
        </div>


        <center><input type="submit" name="khipu_updateSettings" value="{l s='Save Settings' mod='khipupayment'}"
                       class="button" style="cursor: pointer; display:"/></center>
    </fieldset>
</form>
