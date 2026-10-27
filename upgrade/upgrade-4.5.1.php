<?php
/**
 * 4.5.1 no trae cambios de esquema ni de hooks: suma el medio NAVE (pago por
 * QR en Argentina) con su controlador. No hay nada que migrar. Una tienda que
 * nunca guardó la selección de medios lo ofrece solo si su cuenta lo tiene; una
 * que ya la guardó lo ofrece si dejó marcada la casilla de NAVE, que el panel
 * de 4.5.0 ya mostraba.
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
function upgrade_module_4_5_1($module)
{
    return true;
}
