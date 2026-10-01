<?php
/**
 * 4.4.1 no trae cambios de esquema, hooks ni ajustes: la caché nueva de medios
 * de pago (KHIPU_PAYMENT_METHODS_CACHE) se crea sola la primera vez que se usa.
 *
 * El archivo existe igual porque KhipuVersionTest exige uno por versión.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @param KhipuPayment $module
 *
 * @return bool
 */
function upgrade_module_4_4_1($module)
{
    return true;
}
