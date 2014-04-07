{if $status == 'OK'}
<p>{l s='Payment received' mod='khipupayment'}
	<br /><br /><span class="bold">{l s='The payment for your order has been received.' mod='khipupayment'}</span>
</p>
{elseif $status == 'OPEN'}
<p>{l s='Your payment is being processed' mod='khipupayment'}
	<br /><br /><span class="bold">{l s='The order has been placed and awaiting payment verification.' mod='khipupayment'}
	{l s='You will receive an e-mail when payment has been completed or you can track the status of your order on our site.' mod='khipupayment'}
	</span>
</p>
{elseif $status == 'AUTHORIZED'}
<p>{l s='Your payment is being processed' mod='khipupayment'}
	<br /><br /><span class="bold">{l s='The order has been placed and awaiting payment verification.' mod='khipupayment'}
	{l s='You will receive an e-mail when payment has been completed or you can track the status of your order on our site.' mod='khipupayment'}
	</span>
</p>
{/if}