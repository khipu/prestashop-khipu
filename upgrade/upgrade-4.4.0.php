<?php
/**
 * Las tiendas ya instaladas no vuelven a pasar por install(), así que la
 * feature de reversa se les entrega por acá. Todo idempotente.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @param KhipuPayment $module
 *
 * @return bool
 */
function upgrade_module_4_4_0($module)
{
    require_once dirname(__FILE__) . '/../classes/KhipuRefundRules.php';
    require_once dirname(__FILE__) . '/../classes/KhipuRefund.php';

    if (!KhipuRefund::installTable()) {
        return false;
    }

    // Los dos: el específico ubica el panel donde corresponde en 1.7.7+, el
    // genérico es el respaldo de 1.7.0–1.7.6. Ver hookDisplayAdminOrder().
    foreach (array('displayAdminOrder', 'displayAdminOrderMainBottom') as $hook) {
        if (!$module->isRegisteredInHook($hook) && !$module->registerHook($hook)) {
            return false;
        }
    }

    if ((int) Tab::getIdFromClassName('AdminKhipuRefund') === 0) {
        $tab = new Tab();
        $tab->class_name = 'AdminKhipuRefund';
        $tab->module = $module->name;
        $tab->id_parent = -1;
        $tab->active = 1;
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Reversa Khipu';
        }
        if (!$tab->add()) {
            return false;
        }
    }

    if (false === Configuration::get('KHIPU_REFUND_ORDER_STATE')) {
        Configuration::updateValue('KHIPU_REFUND_ORDER_STATE', 'refund');
    }

    return true;
}
