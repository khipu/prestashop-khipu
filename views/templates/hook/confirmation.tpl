{if $status == 'ERR'}
<p>{l s='Pago declinado' mod='khipupayment'}
	<br /><br /><span class="bold">{l s='El pago de su orden ha sido declinado.' mod='khipupayment'}</span>
</p>
{elseif $status == 'OPEN'}
<p>{l s='Pago en verificación' mod='khipupayment'}
	<br /><br /><span class="bold">{l s='El pago de su orden está siendo verificado.' mod='khipupayment'}
	{l s='Recibirá un correo electrónico cuando este pedido sea procesado.' mod='khipupayment'}
	</span>
</p>
{/if}