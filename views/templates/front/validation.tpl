{capture name=path}{l s='Error' mod='khipupayment'}{/capture}
{include file="$tpl_dir./breadcrumb.tpl"}

<h2>{l s='Payment' mod='khipu'}</h2>

{assign var='current_step' value='khipupayment'}
{include file="$tpl_dir./order-steps.tpl"}

<h3>{l s='Payment Service Provider message' mod='khipupayment'}</h3>

<p class="warning">{$error}</p>
