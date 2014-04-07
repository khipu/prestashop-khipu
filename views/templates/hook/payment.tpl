{if $paymentType eq "all" or $paymentType eq "simple"}
<p class="payment_module">
    <a href="{$link->getModuleLink('khipupayment', 'bankselect')|escape:'html'}"
       title="{l s='Transferencia simplificada' mod='khipupayment'}">
        <img src="{$logo}" alt="{l s='Transferencia simplificada' mod='khipupayment'}"/>
        {l s='Transferencia simplificada' mod='khipupayment'}
    </a>
</p>
{/if}
{if $paymentType eq "all" or $paymentType eq "manual"}
<p class="payment_module">
    <a href="{$link->getModuleLink('khipupayment', 'manual')|escape:'html'}"
       title="{l s='Transferencia bancaria usando khipu' mod='khipupayment'}">
        <img src="{$logo}" alt="{l s='Transferencia bancaria usando khipu' mod='khipupayment'}"/>
        {l s='Transferencia bancaria (normal)' mod='khipupayment'}
    </a>
</p>
{/if}