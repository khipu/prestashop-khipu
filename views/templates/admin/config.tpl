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
            <style>
                /* Interruptor de dos extremos: a la izquierda Embebido (marcado), a la derecha Redireccion. */
                .khipu-config input.khipu-toggle { -webkit-appearance: none; appearance: none; position: relative; box-sizing: border-box; width: 240px; height: 34px; margin: 0; padding: 0; border: 0; border-radius: 17px; background: #e2e2e2; box-shadow: none; vertical-align: middle; cursor: pointer; font: 600 13px/34px sans-serif; }
                .khipu-config input.khipu-toggle::before { content: "Redirección"; position: absolute; top: 3px; left: 3px; width: 116px; height: 28px; margin: 0; border-radius: 14px; background: #8347AD; color: #fff; text-align: center; font: 600 13px/28px sans-serif; transform: translateX(118px); transition: transform .2s; }
                .khipu-config input.khipu-toggle::after { content: "Embebido"; position: absolute; top: 0; left: 0; width: 120px; height: 34px; color: #666; text-align: center; font: 600 13px/34px sans-serif; }
                .khipu-config input.khipu-toggle:checked::before { content: "Embebido"; transform: translateX(0); }
                .khipu-config input.khipu-toggle:checked::after { content: "Redirección"; left: auto; right: 0; }
                .khipu-config input.khipu-toggle:disabled { opacity: .55; cursor: not-allowed; }
                .khipu-config input.khipu-toggle:focus { outline: 2px solid #3CB4E5; outline-offset: 2px; box-shadow: none; }
                .khipu-config .khipu-help { display: inline-block; box-sizing: border-box; width: 16px; height: 16px; margin-left: 4px; border-radius: 50%; background: #6c868e; color: #fff; text-align: center; font: 700 11px/16px sans-serif; vertical-align: middle; cursor: help; }
            </style>
            <form action="{$post_url|escape:'htmlall':'UTF-8'}" method="post" class="form-horizontal khipu-config">
                <fieldset class="form-group">
                    <div class="form-group row">
                        <label for="apiKey" class="col-sm-3 col-form-label">{l s='API Key' mod='khipupayment'}
                            {capture assign='khipu_tip'}{l s='Ingrese su API Key. Se obtiene en https://khipu.com/merchant/profile' mod='khipupayment'}{/capture}
                            <span class="khipu-help label-tooltip" data-toggle="tooltip" title="{$khipu_tip|escape:'html':'UTF-8'}">?</span>
                        </label>
                        <div class="col-sm-9">
                            <input type="text" name="apiKey" class="form-control" id="apiKey"
                                   value="{$data_apiKey|escape:'htmlall':'UTF-8'}"/>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="merchantID" class="col-sm-3 col-form-label">{l s='ID Cobrador' mod='khipupayment'}
                            {capture assign='khipu_tip'}{l s='Ingrese su ID de cobrador. Se obtiene en https://khipu.com/merchant/profile' mod='khipupayment'}{/capture}
                            <span class="khipu-help label-tooltip" data-toggle="tooltip" title="{$khipu_tip|escape:'html':'UTF-8'}">?</span>
                        </label>
                        <div class="col-sm-9">
                            <input type="text" id="merchantID" class="form-control" name="merchantID"
                                   value="{$data_merchantid|escape:'htmlall':'UTF-8'}"/>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="secretCode"
                               class="col-sm-3 col-form-label">{l s='Llave secreta' mod='khipupayment'}
                            {capture assign='khipu_tip'}{l s='Ingrese su llave secreta. Se obtiene en https://khipu.com/merchant/profile' mod='khipupayment'}{/capture}
                            <span class="khipu-help label-tooltip" data-toggle="tooltip" title="{$khipu_tip|escape:'html':'UTF-8'}">?</span>
                        </label>
                        <div class="col-sm-9">
                            <input type="text" name="secretCode" class="form-control" id="secretCode"
                                   value="{$data_secretcode|escape:'htmlall':'UTF-8'}"/>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="minutesTimeout"
                               class="col-sm-3 col-form-label">{l s='Minutos para realizar el pago (pasado este tiempo la orden se cancela y se recupera el stock)' mod='khipupayment'}
                            {capture assign='khipu_tip'}{l s='Si el comprador no paga en este plazo, la orden se cancela y se recupera el stock.' mod='khipupayment'}{/capture}
                            <span class="khipu-help label-tooltip" data-toggle="tooltip" title="{$khipu_tip|escape:'html':'UTF-8'}">?</span>
                        </label>
                        <div class="col-sm-9">
                            <input type="number" id="minutesTimeout" class="form-control" name="minutesTimeout"
                                   value="{$data_minutesTimeout|escape:'htmlall':'UTF-8'}"/>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="khipuTitle" class="col-sm-3 col-form-label">{l s='Título' mod='khipupayment'}
                            {capture assign='khipu_tip'}{l s='Título que ve el comprador. Cada medio de pago se muestra como «Título - Medio».' mod='khipupayment'}{/capture}
                            <span class="khipu-help label-tooltip" data-toggle="tooltip" title="{$khipu_tip|escape:'html':'UTF-8'}">?</span>
                        </label>
                        <div class="col-sm-9">
                            <input type="text" name="title" class="form-control" id="khipuTitle"
                                   value="{$data_title|escape:'html':'UTF-8'}"/>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="khipuDescription" class="col-sm-3 col-form-label">{l s='Descripción' mod='khipupayment'}
                            {capture assign='khipu_tip'}{l s='Descripción que ve el comprador al elegir un medio de Khipu.' mod='khipupayment'}{/capture}
                            <span class="khipu-help label-tooltip" data-toggle="tooltip" title="{$khipu_tip|escape:'html':'UTF-8'}">?</span>
                        </label>
                        <div class="col-sm-9">
                            <input type="text" name="description" class="form-control" id="khipuDescription"
                                   value="{$data_description|escape:'html':'UTF-8'}"/>
                        </div>
                    </div>

                    <div class="form-group row">
                        {* El "?" va fuera del <label for>: dentro, un clic en él alternaba el interruptor. *}
                        <div class="col-sm-3 col-form-label">
                            <label for="khipuEmbedded">{l s='Modo de pago' mod='khipupayment'}</label>
                            {capture assign='khipu_tip'}{l s='Embebido abre Khipu dentro de la tienda. Redirección lleva al comprador al sitio de Khipu.' mod='khipupayment'}{/capture}
                            <span class="khipu-help label-tooltip" data-toggle="tooltip" title="{$khipu_tip|escape:'html':'UTF-8'}">?</span>
                        </div>
                        <div class="col-sm-9">
                            {* Con el interruptor deshabilitado no se envia embedded_present: getContent()
                               guardaria "0" (casilla ausente = Redireccion) y pisaria el ajuste del comercio. *}
                            {if $embedded_available}
                                <input type="hidden" name="embedded_present" value="1"/>
                            {/if}
                            {capture assign='khipu_aria'}{l s='Modo de pago: a la izquierda Embebido, a la derecha Redirección' mod='khipupayment'}{/capture}
                            <input type="checkbox" name="embedded" value="1" id="khipuEmbedded" class="khipu-toggle"
                                   aria-label="{$khipu_aria|escape:'html':'UTF-8'}"
                                   {if $embedded_available && $data_embedded}checked="checked"{/if}
                                   {if !$embedded_available}disabled="disabled"{/if}/>
                            <p class="help-block">{l s='Embebido solo es efectivo para el medio de pago transferencia simplificada.' mod='khipupayment'}</p>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">{l s='Medios de pago a mostrar' mod='khipupayment'}
                            {capture assign='khipu_tip'}{l s='Marca los medios de pago de tu cuenta que quieres ofrecer. Cada medio marcado aparece como una opción en el checkout.' mod='khipupayment'}{/capture}
                            <span class="khipu-help label-tooltip" data-toggle="tooltip" title="{$khipu_tip|escape:'html':'UTF-8'}">?</span>
                        </label>
                        <div class="col-sm-9">
                            {if $account_methods|@count > 0}
                                <input type="hidden" name="enabledMethods_present" value="1"/>
                                {foreach from=$account_methods item=method}
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="enabledMethods[]"
                                                   value="{$method.id|escape:'html':'UTF-8'}"
                                                   {if $method.checked}checked="checked"{/if}/>
                                            {$method.label|escape:'html':'UTF-8'}
                                        </label>
                                    </div>
                                {/foreach}
                            {elseif !$data_apiKey || !$data_merchantid}
                                <p class="help-block">{l s='Guarda tus credenciales para ver los medios de pago disponibles.' mod='khipupayment'}</p>
                            {elseif $account_methods_error}
                                <p class="help-block">{l s='No se pudieron consultar los medios de pago de tu cuenta. Se conserva la selección guardada.' mod='khipupayment'}</p>
                            {else}
                                <p class="help-block">{l s='Tu cuenta no tiene medios de pago habilitados en Khipu.' mod='khipupayment'}</p>
                            {/if}
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