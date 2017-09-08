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

{assign var='current_step' value='payment'}


<h2>{l s='Error de conexión con khipu' mod='khipupayment'}</h2>

<ul>
    <li><strong>{l s='Estado' mod='khipupayment'}</strong>: {$error->getStatus()}</li>
    <li><strong>{l s='Mensaje' mod='khipupayment'}</strong>: {$error->getMessage()}</li>

    {if method_exists($error, 'getErrors')}
        <li>{l s='Errores' mod='khipupayment'}
            <ul>
            {foreach from=$error->getErrors() item=errorItem}
                <li><strong>{$errorItem->getField()}</strong>: {$errorItem->getMessage()}</li>
            {/foreach}
            </ul>
        </li>
    {/if}
</ul>