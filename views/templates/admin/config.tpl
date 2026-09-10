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

<div class="container">
    <div class="row">
        <img src="{$img_header|escape:'htmlall':'UTF-8'}"/>

        <h2>{l s='Solución de pagos khipu' mod='khipupayment'}</h2>
    </div>

    <div class="panel panel-info">
        <div class="panel-heading" style="margin: -20px -20px 0px -20px;">
            <i class="fa fa-info-circle"></i> Información del Módulo
        </div>
        <div class="panel-body">
            <div class="row">
                <label class="col-3 col-form-label"><strong>Module version</strong>: {$version|escape:'htmlall':'UTF-8'}
                </label>
            </div>
            <div class="row">
                <label class="col-3 col-form-label"><strong>API
                        version</strong>: {$api_version|escape:'htmlall':'UTF-8'}</label>
            </div>
        </div>
    </div>
    <div class="panel panel-info ">
        <div class="panel-heading" style="margin: -20px -20px 0px -20px;">
            <i class="fa fa-cogs fa-2x" aria-hidden="true"> </i> {l s='Configuración Básica' mod='khipupayment'}
        </div>
        <div class="panel-body">
            <form action="{$post_url|escape:'htmlall':'UTF-8'}" method="post" class="form-horizontal">
                <fieldset class="form-group">
                    <div class="form-group row">
                        <label for="apiKey" class="col-sm-3 col-form-label">{l s='API Key' mod='khipupayment'}</label>
                        <div class="col-sm-9">
                            <input type="text" name="apiKey" class="form-control" id="apiKey"
                                   value="{$data_apiKey|escape:'htmlall':'UTF-8'}"/>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="merchantID" class="col-sm-3 col-form-label">{l s='ID Cobrador' mod='khipupayment'}</label>
                        <div class="col-sm-9">
                            <input type="text" id="merchantID" class="form-control" name="merchantID"
                                   value="{$data_merchantid|escape:'htmlall':'UTF-8'}"/>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="secretCode"
                               class="col-sm-3 col-form-label">{l s='Llave secreta' mod='khipupayment'}</label>
                        <div class="col-sm-9">
                            <input type="text" name="secretCode" class="form-control" id="secretCode"
                                   value="{$data_secretcode|escape:'htmlall':'UTF-8'}"/>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="minutesTimeout"
                               class="col-sm-3 col-form-label">{l s='Minutos para realizar el pago (pasado este tiempo la orden se cancela y se recupera el stock)' mod='khipupayment'}</label>
                        <div class="col-sm-9">
                            <input type="number" id="minutesTimeout" class="form-control" name="minutesTimeout"
                                   value="{$data_minutesTimeout|escape:'htmlall':'UTF-8'}"/>
                        </div>
                    </div>

                    <input type="submit" name="khipu_updateSettings" class="btn btn-primary"
                           value="{l s='Guardar' mod='khipupayment'}"/>
                </fieldset>
            </form>
        </div>
    </div>

    <div class="panel panel-info">
        <div class="panel-heading" style="margin: -20px -20px 0px -20px;">
            <i class="fa fa-undo" aria-hidden="true"></i> {l s='Billetera de reversas' mod='khipupayment'}
        </div>
        <div class="panel-body">
            {if !$wallet.configured}
                <div class="alert alert-info">
                    {l s='Complete los datos para poder visualizar la billetera.' mod='khipupayment'}
                </div>
            {elseif !$wallet.enabled}
                <div class="alert alert-info">
                    {l s='La billetera de reversas no está habilitada en tu cuenta Khipu. Solicítala a soporte@khipu.com.' mod='khipupayment'}
                </div>
            {elseif !$wallet.reachable}
                <div class="alert alert-warning">
                    {l s='No se pudo consultar el saldo de la billetera de reversas.' mod='khipupayment'}
                </div>
            {else}
                <p>
                    <strong>{l s='Saldo' mod='khipupayment'}:</strong>
                    {if $wallet_balance_display}{$wallet_balance_display|escape:'html':'UTF-8'}{else}—{/if}
                    {if $wallet.add_funds_url}
                        &nbsp;&mdash;&nbsp;<a href="{$wallet.add_funds_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
                            {l s='Recargar' mod='khipupayment'}
                        </a>
                    {/if}
                </p>
            {/if}

            {if $wallet.configured && $wallet.enabled}
                <form action="{$post_url|escape:'htmlall':'UTF-8'}" method="post" class="form-horizontal">
                    <div class="form-group row">
                        <label for="refundOrderState" class="col-sm-3 col-form-label">
                            {l s='Estado del pedido tras reversar' mod='khipupayment'}
                        </label>
                        <div class="col-sm-9">
                            <select name="refundOrderState" id="refundOrderState" class="form-control">
                                <option value="refund" {if $data_refundOrderState == 'refund'}selected="selected"{/if}>
                                    {l s='Registrar vale y marcar Reembolsado al agotar el saldo' mod='khipupayment'}
                                </option>
                                <option value="" {if $data_refundOrderState != 'refund'}selected="selected"{/if}>
                                    {l s='— No cambiar —' mod='khipupayment'}
                                </option>
                            </select>
                            <p class="help-block">
                                {l s='Toda reversa se concreta en el ciclo diario de Khipu. Si eliges registrar el vale, la contabilidad reflejará la reversa al solicitarla, no al concretarse.' mod='khipupayment'}
                                {l s='Además, al agotarse el saldo reversable el pedido pasa a «Reembolsado», y ese estado envía un correo al cliente si lo tienes configurado así en Estados de pedido.' mod='khipupayment'}
                            </p>
                        </div>
                    </div>
                    <input type="submit" name="khipu_updateSettings" class="btn btn-primary"
                           value="{l s='Guardar' mod='khipupayment'}" />
                </form>
            {/if}
        </div>
    </div>
</div>