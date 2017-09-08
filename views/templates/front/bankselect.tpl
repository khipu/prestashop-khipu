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



<h2>{l s='Escoge el banco para pagar' mod='khipupayment'}</h2>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
<form method='POST' action='{$action|escape:'htmlall':'UTF-8'}' class='form form-horizontal'>
{foreach from=$request item=value key=key}
    {if $key neq "fc" && $key neq "module" && $key neq "controller"}
        <input type="hidden" value ="{$value|escape:'htmlall':'UTF-8'}" name="{$key|escape:'htmlall':'UTF-8'}">
    {/if}
{/foreach}

<input type="hidden" value ="payment" name="controller">
<input type="hidden" value ="module" name="fc">
<input type="hidden" value ="khipupayment" name="module">

    <div class="row row-margin-bottom">
        <div class="col-sm-6">
            <select id="root-bank" name="root-bank" style="width: auto;" class="input-lg"></select>
            <select id="bank-id" name="bank-id" style="display: none; width: auto;" class="input-lg"></select>
        </div>
        <div class="col-sm-6">
            <button type="submit" class="button btn btn-default standard-checkout button-medium pull-right">
                <span>{l s='Continuar' mod='khipupayment'} <i class="icon-chevron-right right"></i></span>
            </button>
        </div>
    </div>
</form>
    <script>
        (function ($) {
            var messages = [];
            var bankRootSelect = $('#root-bank');
            var bankOptions = [];
            var selectedRootBankId = 0;
            var selectedBankId = 0;
            bankRootSelect.attr("disabled", "disabled");
            {foreach from=$banks item=bank}
            {if $bank->getParent() eq ''}
            bankRootSelect.append('<option value="{$bank->getBankId()|escape:'htmlall':'UTF-8'}">{$bank->getName()|escape:'htmlall':'UTF-8'}</option>');
            bankOptions['{$bank->getBankId()|escape:'htmlall':'UTF-8'}'] = [];
            bankOptions['{$bank->getBankId()|escape:'htmlall':'UTF-8'}'].push('<option value="{$bank->getBankId()|escape:'htmlall':'UTF-8'}">{$bank->getType()|escape:'htmlall':'UTF-8'}</option>');
            {else}
            bankOptions['{$bank->getParent()|escape:'htmlall':'UTF-8'}'].push('<option value="{$bank->getBankId()|escape:'htmlall':'UTF-8'}">{$bank->getType()|escape:'htmlall':'UTF-8'}</option>');
            {/if}
            {/foreach}
            function updateBankOptions(rootId, bankId) {
                if (rootId) {
                    $('#root-bank').val(rootId);
                }
                var idx = $('#root-bank :selected').val();
                $('#bank-id').empty();
                var options = bankOptions[idx];
                for (var i = 0; i < options.length; i++) {
                    $('#bank-id').append(options[i]);
                }
                if (options.length > 1) {
                    $('#bank-id').show();
                } else {
                    $('#bank-id').hide();
                }
                if (bankId) {
                    $('#bank-id').val(bankId);
                }
                $('#bank-id').change();
            }
            $('#root-bank').change(function () {
                updateBankOptions();
            });
            $(document).ready(function () {
                updateBankOptions(selectedRootBankId, selectedBankId);
                bankRootSelect.removeAttr("disabled");
            });
        })(jQuery);
    </script>
{/block}
