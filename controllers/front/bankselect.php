<?php


class KhipuPaymentBankselectModuleFrontController extends ModuleFrontController
{

    public function __construct()
    {
        parent::__construct();
        $this->display_column_left = false;
    }

    public function initContent()
    {

        parent::initContent();

        $cart = $this->context->cart;

        $khipu = new Khipu();

        $khipu->authenticate(Configuration::get('KHIPU_MERCHANTID'), Configuration::get('KHIPU_SECRETCODE'));

        $khipu->setAgent('prestashop-khipu-2.0.3');
        $khipu_service = $khipu->loadService('ReceiverBanks');


        $this->context->smarty->assign(array(
            'bankselector' => $this->generate_khipu_bankselect(json_decode($khipu_service->consult()))
        ));


        $this->setTemplate('bankselect.tpl');
    }


    private function generate_khipu_bankselect($banks)
    {

        if(!$banks) {
            return $this->comm_error();
        }

        $action = Context::getContext()->link->getModuleLink('khipupayment', 'payment');

        $bankSelector = "<form method='GET' action='$action' class='form form-horizontal'>\n";

        foreach ($_REQUEST as $key => $value) {
            if($key != 'fc' && $key != 'module' && $key != 'controller') {
                $bankSelector .= "<input type=\"hidden\" value =\"$value\" name=\"$key\">\n";
            }
        }
        $bankSelector .= "<input type=\"hidden\" value =\"payment\" name=\"controller\">\n";
        $bankSelector .= "<input type=\"hidden\" value =\"module\" name=\"fc\">\n";
        $bankSelector .= "<input type=\"hidden\" value =\"khipupayment\" name=\"module\">\n";

        $send_label = 'Continuar';
        $bank_selector_label = 'Selecciona tu banco:';
        $bankSelector .= <<<EOD
			<div class="row row-margin-bottom">
				<div class="col-sm-6">
                    <select id="root-bank" name="root-bank" style="width: auto;" class="input-lg"></select>
                    <select id="bank-id" name="bank-id" style="display: none; width: auto;" class="input-lg"></select>
				</div>
				<div class="col-sm-6">
				    <button type="submit" class="button btn btn-default standard-checkout button-medium pull-right">
                        <span>$send_label <i class="icon-chevron-right right"></i></span>
                    </button>
				</div>
			</div>
</form>
<script>
	(function ($) {
		var messages = [];
		var bankRootSelect = $('#root-bank')
		var bankOptions = []
		var selectedRootBankId = 0
		var selectedBankId = 0
		bankRootSelect.attr("disabled", "disabled");
EOD;

        foreach ($banks->banks as $bank) {
            if (!$bank->parent) {
                $bankSelector .= "bankRootSelect.append('<option value=\"$bank->id\">$bank->name</option>');\n";
                $bankSelector .= "bankOptions['$bank->id'] = [];\n";
                $bankSelector .= "bankOptions['$bank->id'].push('<option value=\"$bank->id\">$bank->type</option>')\n";
            } else {
                $bankSelector .= "bankOptions['$bank->parent'].push('<option value=\"$bank->id\">$bank->type</option>');\n";
            }
        }
        $bankSelector .= <<<EOD
	function updateBankOptions(rootId, bankId) {
		if (rootId) {
			$('#root-bank').val(rootId)
		}

		var idx = $('#root-bank :selected').val();
		$('#bank-id').empty();
		var options = bankOptions[idx];
		for (var i = 0; i < options.length; i++) {
			$('#bank-id').append(options[i]);
		}
		if (options.length > 1) {
			$('#bank-id').show();
		} else {
			$('#bank-id').hide();
		}
		if (bankId) {
			$('#bank-id').val(bankId);
		}
		$('#bank-id').change();
	}
	$('#root-bank').change(function () {
		updateBankOptions();
	});
	$(document).ready(function () {
		updateBankOptions(selectedRootBankId, selectedBankId);
		bankRootSelect.removeAttr("disabled");
	});
})(jQuery);
</script>
EOD;

        return $bankSelector;
    }

}
