{capture name=path}{l s='Seleccione el banco' mod='khipupayment'}{/capture}
<h2>{l s='Order summary' mod='cheque'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}
{include file="$tpl_dir./errors.tpl"}


<h2>{l s='Escoge el banco para pagar' mod='khipupayment'}</h2>

{$bankselector}