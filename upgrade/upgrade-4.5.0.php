<?php
/**
 * 4.5.0 no trae cambios de esquema ni de hooks. Los ajustes nuevos
 * (KHIPU_TITLE, KHIPU_DESCRIPTION, KHIPU_EMBEDDED, KHIPU_ENABLED_METHODS) no se
 * migran: mientras no existan, el módulo usa sus valores por defecto (título
 * "Khipu", descripción "Paga con tu banco", modo embebido y todos los medios de
 * la cuenta marcados).
 *
 * 4.5.0 incluye el medio NAVE (pago por QR en Argentina) con su controlador:
 * una tienda que nunca guardó la selección de medios lo ofrece solo si su
 * cuenta lo tiene; una que ya la guardó lo ofrece si dejó marcada su casilla.
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
