{if $paymentType eq "all" or $paymentType eq "simple"}
<p class="payment_module">
    <a href="{$link->getModuleLink('khipupayment', 'bankselect')|escape:'html'}"
       title="{l s='Transferencia simplificada' mod='khipupayment'}">
        <img src="//s3.amazonaws.com/static.khipu.com/buttons/2015/150x50-transparent.png" alt="{l s='Transferencia simplificada' mod='khipupayment'}"/>
        {l s='Transferencia simplificada' mod='khipupayment'} {if $recommended}{l s='(Recomendada)' mod='khipupayment'}{/if}
    </a>
</p>
{/if}
{if $paymentType eq "all" or $paymentType eq "manual"}
<p class="payment_module">
    <a href="{$link->getModuleLink('khipupayment', 'manual')|escape:'html'}"
       title="{l s='Transferencia bancaria usando khipu' mod='khipupayment'}">
        <img src="//s3.amazonaws.com/static.khipu.com/buttons/2015/150x50-normal-transparent.png" alt="{l s='Transferencia normal' mod='khipupayment'}"/>
        {l s='Transferencia normal' mod='khipupayment'} {if $paymentType eq "manual" && $recommended}{l s='(Recomendada)' mod='khipupayment'}{/if}
    </a>
</p>
{/if}
