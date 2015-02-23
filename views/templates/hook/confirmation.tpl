<div id="wait-msg" class="alert alert-info">
{if $status == 'ERR'}
{l s='Pago declinado' mod='khipupayment'}
	<br /><br /><span class="bold">{l s='El pago de su orden ha sido declinado.' mod='khipupayment'}</span>
{elseif $status == 'OPEN'}
{l s='Pago en verificación' mod='khipupayment'}
	<br /><br /><span class="bold">{l s='El pago de su orden está siendo verificado.' mod='khipupayment'}
	{l s='Recibirá un correo electrónico cuando este pedido sea procesado.' mod='khipupayment'}
	</span>
{/if}
</div>