<?php

/**
 * Lógica pura de los medios de pago de Khipu: etiqueta de cada medio, título
 * compuesto de la fila del checkout y selección del panel (qué medios de la
 * cuenta se ofrecen).
 *
 * No depende de PrestaShop: recibe la lista de KhipuApi::getPaymentMethods()
 * y el JSON guardado en KHIPU_ENABLED_METHODS, y devuelve datos simples.
 */
class KhipuMethods
{
    const REGULAR = 'REGULAR_TRANSFER';
    const SIMPLIFIED = 'SIMPLIFIED_TRANSFER';
    const LIGOPAY = 'LIGOPAY';
    const NAVE = 'NAVE';

    /**
     * Etiquetas visibles. Sus claves son además los medios que tienen controlador
     * (y por lo tanto fila en el checkout).
     *
     * @var array
     */
    private static $labels = array(
        'REGULAR_TRANSFER' => 'Transferencia normal',
        'SIMPLIFIED_TRANSFER' => 'Transferencia simplificada',
        'LIGOPAY' => 'LigoPay',
        'NAVE' => 'Pago por QR',
    );

    /**
     * Medios que la cuenta puede tener habilitados pero que no sirven para pagar
     * una compra: nunca se muestran. Sumar uno acá es todo lo que hace falta.
     *
     * @var array
     */
    private static $excluded = array('DIRECT_DEBIT');

    /**
     * Etiqueta del medio. Un id que el módulo no conoce se muestra con el id
     * legible ("ALGUN_MEDIO" -> "Algun medio"), para que aparezca en el panel.
     *
     * @param string $methodId
     *
     * @return string
     */
    public static function label($methodId)
    {
        $methodId = (string) $methodId;
        if (isset(self::$labels[$methodId])) {
            return self::$labels[$methodId];
        }

        return ucfirst(strtolower(str_replace('_', ' ', $methodId)));
    }

    /**
     * Título de la fila del checkout: "{Título} - {Medio}". Un título vacío
     * o en blanco cae a "Khipu" para que la fila nunca quede sin nombre.
     *
     * @param string $title
     * @param string $methodId
     *
     * @return string
     */
    public static function composeTitle($title, $methodId)
    {
        $title = trim((string) $title);
        if ($title === '') {
            $title = 'Khipu';
        }

        return $title . ' - ' . self::label($methodId);
    }

    /**
     * Ids únicos de la cuenta, sin los excluidos, en el orden de visualización
     * (ver sortForDisplay(): simplificada primero, normal al final, el resto en el
     * orden en que Khipu los devuelve). Un medio que el módulo no conoce se incluye
     * igual (aparece en el panel); si no tiene controlador, no genera ninguna fila en el checkout
     * (ver visibleMethodIds()).
     *
     * @param array|null $methods lista de KhipuApi::getPaymentMethods()
     *
     * @return array
     */
    public static function accountMethodIds($methods)
    {
        $ids = array();
        if (!is_array($methods)) {
            return $ids;
        }
        foreach ($methods as $method) {
            if (!is_array($method) || !isset($method['id']) || !is_scalar($method['id'])) {
                continue;
            }
            $id = (string) $method['id'];
            if ($id === '' || in_array($id, self::$excluded, true) || in_array($id, $ids, true)) {
                continue;
            }
            $ids[] = $id;
        }

        return self::sortForDisplay($ids);
    }

    /**
     * Orden en que se muestran los medios, tanto en las casillas del panel como
     * en las filas del checkout: la transferencia simplificada primero y la
     * transferencia normal (manual) siempre al final. Son dos reglas
     * independientes: si la cuenta solo tiene esas dos, queda simplificada y
     * normal; si tiene otros, quedan en medio en el orden en que Khipu los
     * devuelve. Orden estable: no se reordena nada más.
     *
     * @param array $ids
     *
     * @return array
     */
    private static function sortForDisplay(array $ids)
    {
        $first = array();
        $middle = array();
        $last = array();
        foreach ($ids as $id) {
            if ($id === self::SIMPLIFIED) {
                $first[] = $id;
            } elseif ($id === self::REGULAR) {
                $last[] = $id;
            } else {
                $middle[] = $id;
            }
        }

        return array_merge($first, $middle, $last);
    }

    /**
     * Si el comercio marcó el medio. Sin JSON de lista (nunca guardó, o el
     * valor está corrupto) se ofrecen todos; una lista, aun vacía, es una
     * selección explícita.
     *
     * @param string $methodId
     * @param string|null $saved JSON guardado en KHIPU_ENABLED_METHODS
     *
     * @return bool
     */
    public static function isSelected($methodId, $saved)
    {
        if ($saved === null || $saved === '') {
            return true;
        }
        $list = json_decode($saved, true);
        if (!is_array($list)) {
            return true;
        }

        return in_array($methodId, $list, true);
    }

    /**
     * Selección enviada por el formulario, limitada a los ids de la cuenta y
     * sin duplicados: lo que llega por POST no es de fiar.
     *
     * @param mixed $posted
     * @param array $accountIds
     *
     * @return array
     */
    public static function normalizeSelection($posted, array $accountIds)
    {
        $selected = array();
        if (!is_array($posted)) {
            return $selected;
        }
        foreach ($posted as $id) {
            if (is_string($id) && in_array($id, $accountIds, true) && !in_array($id, $selected, true)) {
                $selected[] = $id;
            }
        }

        return $selected;
    }

    /**
     * Si el POST cambia las credenciales respecto de las guardadas. Un valor
     * null es un campo que no vino en el POST (el otro panel) y no cuenta
     * como cambio. Se comparan ya sin espacios sobrantes.
     *
     * @param mixed $savedApiKey
     * @param mixed $savedMerchantId
     * @param string|null $postedApiKey
     * @param string|null $postedMerchantId
     *
     * @return bool
     */
    public static function credentialsChanged($savedApiKey, $savedMerchantId, $postedApiKey, $postedMerchantId)
    {
        if (null !== $postedApiKey && (string) $savedApiKey !== trim((string) $postedApiKey)) {
            return true;
        }

        return null !== $postedMerchantId && (string) $savedMerchantId !== trim((string) $postedMerchantId);
    }

    /**
     * El modo embebido solo existe si la cuenta tiene transferencia
     * simplificada.
     *
     * @param array|null $methods
     *
     * @return bool
     */
    public static function embeddedAvailable($methods)
    {
        return in_array(self::SIMPLIFIED, self::accountMethodIds($methods), true);
    }

    /**
     * Medios que se ofrecen en el checkout: los de la cuenta (en su orden) que
     * el comercio dejó marcados y que además tienen controlador. Un medio sin
     * controlador puede estar marcado en el panel sin que pase nada acá.
     *
     * @param array|null $methods
     * @param string|null $saved
     *
     * @return array
     */
    public static function visibleMethodIds($methods, $saved)
    {
        $visible = array();
        foreach (self::accountMethodIds($methods) as $id) {
            if (isset(self::$labels[$id]) && self::isSelected($id, $saved)) {
                $visible[] = $id;
            }
        }

        return $visible;
    }
}
