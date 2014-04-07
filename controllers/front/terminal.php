<?php


class KhipuPaymentTerminalModuleFrontController extends ModuleFrontController
{

    public function __construct()
    {
        parent::__construct();
        $this->display_column_left = false;
    }

    public function initContent()
    {

        parent::initContent();

        $this->context->smarty->assign(array(
            'data' => $_GET['data']
        ));

        $this->addJquery();
        $this->addJS('https://cdnjs.cloudflare.com/ajax/libs/atmosphere/2.1.2/atmosphere.min.js');
        $this->addJS('https://storage.googleapis.com/installer/khipu-1.1.js');
        $this->setTemplate('terminal.tpl');
    }
}
