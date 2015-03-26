<?php


class KhipuPaymentTerminalModuleFrontController extends ModuleFrontController
{

    function base64url_decode_uncompress($data) {
        return gzuncompress(base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT)));
    }

    public function __construct()
    {
        parent::__construct();
        $this->display_column_left = false;
    }

    public function initContent()
    {
        parent::initContent();
        $data = $this->base64url_decode_uncompress($_REQUEST['data']);

        $this->context->smarty->assign(array(
            'data' => $data
        ));

        $this->addJquery();
        $this->addJS('https://cdnjs.cloudflare.com/ajax/libs/atmosphere/2.1.2/atmosphere.min.js');
        $this->addJS('https://storage.googleapis.com/installer/khipu-1.1.jquery.js');
        $this->setTemplate('terminal.tpl');
    }
}
