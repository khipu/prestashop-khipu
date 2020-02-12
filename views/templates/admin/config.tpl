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
                        <label for="merchantID"
                               class="col-sm-3 col-form-label">{l s='ID Cobrador' mod='khipupayment'}</label>
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
                        <label for="hoursTimeout"
                               class="col-sm-3 col-form-label">{l s='Horas para realizar el pago (pasado este tiempo la orden se cancela y se recupera el stock)' mod='khipupayment'}</label>
                        <div class="col-sm-9">
                            <input type="number" id="hoursTimeout" class="form-control" name="hoursTimeout"
                                   value="{$data_hoursTimeout|escape:'htmlall':'UTF-8'}"/>
                        </div>
                    </div>


                    <input type="submit" name="khipu_updateSettings" class="btn btn-primary"
                           value="{l s='Guardar' mod='khipupayment'}"/>
                </fieldset>
            </form>
        </div>
    </div>
</div>
