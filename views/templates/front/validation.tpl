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

{capture name=path}{l s='Error' mod='khipupayment'}{/capture}

<h2>{l s='Pago' mod='khipupayment'}</h2>

{assign var='current_step' value='khipupayment'}


<h3>{l s='Payment Service Provider message' mod='khipupayment'}</h3>

<p class="warning">{$error|escape:'htmlall':'UTF-8'}</p>
