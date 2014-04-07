{capture name=path}{l s='Seleccione el banco' mod='khipupayment'}{/capture}
<h2>{l s='Order summary' mod='cheque'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}
{include file="$tpl_dir./errors.tpl"}

<div id="wait-msg" class="alert alert-info">Estamos iniciando el terminal de pagos khipu, por favor espera unos segundos.<br>No
    cierres esta página, una vez que completes el pago serás redirigido automáticamente.
</div>
<div id="khipu-chrome-extension-div" style="display: none"></div>
<script>
    window.onload = function () {ldelim}
        KhipuLib.onLoad({ldelim}
            data: {$data}
            {rdelim})
    {rdelim}
</script>
