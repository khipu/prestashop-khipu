<?php
/**
 * 4.5.0 no trae cambios de esquema ni de hooks. Los ajustes nuevos
 * (KHIPU_TITLE, KHIPU_DESCRIPTION, KHIPU_EMBEDDED, KHIPU_ENABLED_METHODS) no se
 * migran: mientras no existan, el módulo usa sus valores por defecto (título
 * "Khipu", descripción "Paga con tu banco", modo embebido y todos los medios de
 * la cuenta marcados).
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
function upgrade_module_4_5_0($module)
{
    return true;
}
