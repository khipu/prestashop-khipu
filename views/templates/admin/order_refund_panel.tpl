{*
* @author    khipu <support@khipu.com>
* @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*}

<div class="{$khipu_cls.card}" id="khipu-refund-panel">
    <div class="{$khipu_cls.header}">
        <h3 class="card-header-title">
            {l s='Reversa Khipu' mod='khipupayment'}
        </h3>
    </div>

    <div class="{$khipu_cls.body}">

        {if !$khipu_wallet.configured}
            <div class="alert alert-info">
                {l s='Complete los datos para poder visualizar la billetera.' mod='khipupayment'}
            </div>
        {elseif !$khipu_wallet.enabled}
            <div class="alert alert-info">
                {l s='La billetera de reversas no está habilitada en tu cuenta Khipu. Solicítala a soporte@khipu.com para poder reversar pagos.' mod='khipupayment'}
            </div>
        {else}
            {if isset($khipu_notice) && $khipu_notice}
                <div class="alert alert-{if $khipu_notice.status == 'success'}success{else}danger{/if}">
                    {$khipu_notice.text|escape:'html':'UTF-8'}
                    {if isset($khipu_notice.recharge_url) && $khipu_notice.recharge_url}
                        <a class="btn {$khipu_cls.btn_secondary} btn-xs" href="{$khipu_notice.recharge_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
                            {l s='Recargar billetera' mod='khipupayment'}
                        </a>
                    {/if}
                </div>
            {/if}

            {if !$khipu_wallet.reachable}
                <div class="alert alert-warning">
                    {l s='No se pudo consultar el saldo de la billetera de reversas. Puedes intentar la reversa igualmente.' mod='khipupayment'}
                </div>
            {/if}

            <div class="row" style="margin-bottom:15px;">
                <div class="col-md-6">
                    <strong>{l s='Saldo reversable de este pedido' mod='khipupayment'}:</strong>
                    {$khipu_remaining_display|escape:'html':'UTF-8'}
                </div>
                <div class="col-md-6">
                    <strong>{l s='Saldo de la billetera de reversas' mod='khipupayment'}:</strong>
                    {if $khipu_wallet_balance_display}{$khipu_wallet_balance_display|escape:'html':'UTF-8'}{else}—{/if}
                    {if $khipu_wallet.add_funds_url}
                        <a href="{$khipu_wallet.add_funds_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
                            {l s='Recargar' mod='khipupayment'}
                        </a>
                    {/if}
                </div>
            </div>

            {if !$khipu_can_refund}
                <div class="alert alert-info">
                    {l s='Este pedido no tiene saldo reversable.' mod='khipupayment'}
                </div>
            {else}
                <form method="post" action="{$khipu_action_url|escape:'html':'UTF-8'}" class="form-horizontal">
                    <input type="hidden" name="id_order" value="{$khipu_id_order|intval}" />
                    <input type="hidden" name="submitKhipuRefund" value="1" />
                    <input type="hidden" name="khipu_nonce" value="{$khipu_nonce|escape:'html':'UTF-8'}" />

                    <div class="form-group">
                        <label class="control-label col-lg-3">{l s='Tipo de reversa' mod='khipupayment'}</label>
                        <div class="col-lg-9">
                            <label class="radio-inline">
                                <input type="radio" name="refund_type" value="full" checked="checked" class="khipu-refund-type" />
                                {l s='Total (todo el saldo reversable)' mod='khipupayment'}
                            </label>
                            <label class="radio-inline">
                                <input type="radio" name="refund_type" value="partial" class="khipu-refund-type" />
                                {l s='Parcial' mod='khipupayment'}
                            </label>
                        </div>
                    </div>

                    <div class="form-group" id="khipu-refund-amount-row" style="display:none;">
                        <label class="control-label col-lg-3">{l s='Monto a reversar' mod='khipupayment'}</label>
                        <div class="col-lg-9">
                            <input type="text" name="amount" id="khipu-refund-amount" class="form-control"
                                   inputmode="decimal" autocomplete="off" />
                            <p class="{$khipu_cls.help}">
                                {l s='Usa el punto como separador decimal. No puede superar el saldo reversable.' mod='khipupayment'}
                            </p>
                        </div>
                    </div>

                    <div class="{$khipu_cls.footer}">
                        <button type="submit" class="btn btn-primary {$khipu_cls.right}">
                            <i class="process-icon-refresh"></i> {l s='Reversar' mod='khipupayment'}
                        </button>
                    </div>
                </form>

                <div class="modal fade" id="khipu-confirm-modal" tabindex="-1" role="dialog"
                     aria-labelledby="khipu-confirm-title" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title" id="khipu-confirm-title">
                                    {l s='Confirmar reversa' mod='khipupayment'}
                                </h4>
                                <button type="button" class="close" data-dismiss="modal"
                                        aria-label="{l s='Cerrar' mod='khipupayment'}">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <p id="khipu-confirm-text"></p>
                                <p class="mb-0">
                                    <strong>{l s='Esta acción no se puede deshacer.' mod='khipupayment'}</strong>
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn {$khipu_cls.btn_secondary}" data-dismiss="modal">
                                    {l s='Cancelar' mod='khipupayment'}
                                </button>
                                <button type="button" class="btn btn-primary" id="khipu-confirm-ok">
                                    {l s='Reversar' mod='khipupayment'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <script type="text/javascript">
                    (function () {
                        var row = document.getElementById('khipu-refund-amount-row');
                        var radios = document.querySelectorAll('.khipu-refund-type');
                        var amount = document.getElementById('khipu-refund-amount');

                        function toggle() {
                            var partial = document.querySelector('.khipu-refund-type[value="partial"]');
                            row.style.display = (partial && partial.checked) ? '' : 'none';
                        }

                        for (var i = 0; i < radios.length; i++) {
                            radios[i].addEventListener('change', toggle);
                        }

                        // Solo dígitos y un punto. Es comodidad, no seguridad: el
                        // monto se valida siempre en el servidor.
                        amount.addEventListener('input', function () {
                            this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
                        });

                        toggle();

                        var form = document.querySelector('#khipu-refund-panel form');
                        if (!form) {
                            return;
                        }

                        function lockButton() {
                            var btn = form.querySelector('button[type="submit"]');
                            if (btn) {
                                btn.disabled = true;
                                btn.textContent = '{l s='Enviando…' mod='khipupayment' js=1}';
                            }
                        }

                        // Monto tal como lo va a ver el operador en el diálogo. En
                        // total sale ya formateado del servidor; en parcial lo
                        // escribe él, así que se le pega el signo acá.
                        function amountLabel() {
                            var partial = document.querySelector('.khipu-refund-type[value="partial"]');
                            if (partial && partial.checked) {
                                return (amount.value || '0') + ' {$khipu_currency_sign|escape:'javascript'}';
                            }

                            return '{$khipu_remaining_display|escape:'javascript'}';
                        }

                        function confirmText() {
                            return '{l s='Vas a reversar' mod='khipupayment' js=1} ' + amountLabel()
                                + ' {l s='del pedido' mod='khipupayment' js=1} #{$khipu_id_order|intval}. '
                                + '{l s='El dinero sale de tu billetera de reversas de Khipu.' mod='khipupayment' js=1}';
                        }

                        var confirmed = false;

                        form.addEventListener('submit', function (e) {
                            if (confirmed) {
                                lockButton();

                                return;
                            }

                            // Reversar mueve dinero y Khipu no permite cancelarlo:
                            // nunca debe salir de un solo clic.
                            e.preventDefault();

                            var modal = document.getElementById('khipu-confirm-modal');
                            var jq = window.jQuery;

                            // Sin el modal de Bootstrap se cae a confirm(), que
                            // siempre existe. Quedarse sin confirmación no es
                            // opción, y quedarse sin botón tampoco.
                            if (!modal || !jq || !jq.fn || !jq.fn.modal) {
                                if (window.confirm(confirmText())) {
                                    confirmed = true;
                                    lockButton();
                                    form.submit();
                                }

                                return;
                            }

                            document.getElementById('khipu-confirm-text').textContent = confirmText();
                            jq(modal).modal('show');
                        });

                        var ok = document.getElementById('khipu-confirm-ok');
                        if (ok) {
                            ok.addEventListener('click', function () {
                                confirmed = true;
                                if (window.jQuery) {
                                    window.jQuery('#khipu-confirm-modal').modal('hide');
                                }
                                lockButton();
                                form.submit();
                            });
                        }
                    })();
                </script>
            {/if}

            {if $khipu_history}
                <hr />
                <h4>{l s='Reversas de este pedido' mod='khipupayment'}</h4>
                <table class="table">
                    <thead>
                        <tr>
                            <th>{l s='Fecha' mod='khipupayment'}</th>
                            <th>{l s='Tipo' mod='khipupayment'}</th>
                            <th>{l s='Reversado' mod='khipupayment'}</th>
                            <th>{l s='Saldo restante' mod='khipupayment'}</th>
                        </tr>
                    </thead>
                    <tbody>
                    {foreach from=$khipu_history item=row}
                        <tr>
                            <td>{$row.date_add|escape:'html':'UTF-8'}</td>
                            <td>{$row.type|escape:'html':'UTF-8'}</td>
                            <td>{$row.refunded_amount|escape:'html':'UTF-8'}</td>
                            <td>{$row.remaining|escape:'html':'UTF-8'}</td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
                <p class="{$khipu_cls.help}">
                    {l s='Las reversas se concretan en el ciclo diario de Khipu.' mod='khipupayment'}
                </p>
            {/if}
        {/if}
    </div>
</div>
