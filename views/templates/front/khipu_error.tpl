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
*  @copyright 2007-2020 khipu SpA
*  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{assign var='current_step' value='payment'}

<h2>{l s='Error de conexión con khipu' mod='khipupayment'}</h2>

<ul>
    {if isset($error.status)}
        <li><strong>{l s='Estado' mod='khipupayment'}</strong>: {$error.status}</li>
    {/if}
    {if isset($error.message)}
        <li><strong>{l s='Mensaje' mod='khipupayment'}</strong>: {$error.message}</li>
    {/if}
    {if isset($error.errors)}
        <li>{l s='Errores' mod='khipupayment'}
            <ul>
                {foreach from=$error.errors item=errorItem}
                    <li><strong>{$errorItem.field}</strong>: {$errorItem.message}</li>
                {/foreach}
            </ul>
        </li>
    {/if}
</ul>
