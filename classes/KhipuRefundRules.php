<?php
/**
 * Lógica pura de la reversa de Khipu: validación, derivación del saldo y
 * parseo de la gramática de errores de la API.
 *
 * No depende de PrestaShop ni hace red. Es deliberado: así se puede probar
 * con PHPUnit sin arrancar la tienda.
 */
class KhipuRefundRules
{
    const TYPE_FULL = 'full';
    const TYPE_PARTIAL = 'partial';

    const OUTCOME_OK = 'ok';
    const OUTCOME_REJECTED = 'rejected';
    const OUTCOME_UNKNOWN = 'unknown';

    const REFUNDS_NONE = 'none';
    const REFUNDS_SOME = 'some';
    const REFUNDS_UNKNOWN = 'unknown';

    /** Texto exacto con el que Khipu delata una cuenta sin el flag de billetera. */
    /**
     * Subcadena con la que Khipu delata una cuenta sin el flag de billetera.
     *
     * Deliberadamente CORTADA antes del sustantivo: en agosto de 2026 el mensaje
     * decía "…para hacer reversas" y en septiembre pasó a "…para hacer
     * devoluciones" (verificado contra la API real). El texto es una señal
     * frágil; la fuente de verdad es `field === 'receiver_id'`, y esto es el
     * refuerzo que sobrevive a los dos vocabularios.
     */
    const WALLET_DISABLED_NEEDLE = 'no está habilitada para hacer';

    /** Texto exacto con el que Khipu delata un pago sin saldo reversable (agotado, reversado por otro medio, o fuera de la ventana de 180 días). */
    const NOT_REFUNDABLE_NEEDLE = 'no es reembolsable';

    /**
     * Saldo reversable de un pedido.
     *
     * Khipu no expone ningún GET para consultarlo: solo llega en la respuesta
     * del POST. Por eso el saldo es el `remaining` de la última reversa
     * guardada y, si no hay ninguna, el monto efectivamente pagado.
     *
     * @param string|float|null $latestRemaining `remaining` de la última reversa, o null si no hay
     * @param string|float      $paidAmount      monto del pago registrado
     *
     * @return float
     */
    public static function remainingFrom($latestRemaining, $paidAmount)
    {
        if (null === $latestRemaining || '' === $latestRemaining) {
            return (float) $paidAmount;
        }

        return (float) $latestRemaining;
    }

    /**
     * @param string            $type      self::TYPE_FULL o self::TYPE_PARTIAL
     * @param string|float|null $amount    monto pedido (solo relevante en parcial)
     * @param float             $remaining saldo reversable local
     *
     * @return true|string true si valida; el mensaje de error si no
     */
    public static function validateAmount($type, $amount, $remaining)
    {
        if (self::TYPE_PARTIAL !== $type) {
            return true;
        }

        $raw = (null === $amount) ? '' : trim((string) $amount);

        if ('' === $raw) {
            return 'Indica el monto a reversar.';
        }

        // Solo dígitos y un punto. Sin esto, (float)'50,5' daría 50.0 en
        // silencio y se reversaría un monto distinto del que se escribió.
        if (!preg_match('/^[0-9]+(\.[0-9]+)?$/', $raw)) {
            return 'El monto solo admite dígitos y un punto como separador decimal.';
        }

        $value = (float) $raw;

        if ($value <= 0) {
            return 'El monto debe ser mayor que cero.';
        }

        if ($value > (float) $remaining) {
            return 'El monto no puede superar el saldo reversable de este pedido.';
        }

        return true;
    }

    /**
     * Normaliza el cuerpo de error de Khipu, cuyo formato verificado es
     * {"status":400,"message":"Error de validación","errors":[{"field","message"}]}.
     *
     * @param array|null $body cuerpo ya decodificado
     *
     * @return array lista de array('field' => string, 'message' => string)
     */
    public static function parseErrors($body)
    {
        $out = array();

        if (!is_array($body) || !isset($body['errors']) || !is_array($body['errors'])) {
            return $out;
        }

        foreach ($body['errors'] as $error) {
            if (!is_array($error)) {
                continue;
            }
            $out[] = array(
                'field' => isset($error['field']) ? (string) $error['field'] : '',
                'message' => isset($error['message']) ? (string) $error['message'] : '',
            );
        }

        return $out;
    }

    /**
     * ¿Los errores dicen que la cuenta no tiene habilitada la billetera de reversas?
     *
     * OJO: "no es reembolsable" NO cuenta. Ese mensaje es ambiguo (pago
     * agotado, reversado por fuera, o fuera de la ventana de 180 días) y
     * leerlo como flag-off mandaría al admin a perseguir el problema equivocado.
     *
     * @return bool
     */
    public static function isWalletDisabled(array $errors)
    {
        foreach ($errors as $error) {
            if (isset($error['field']) && 'receiver_id' === $error['field']) {
                return true;
            }
            if (isset($error['message']) && false !== stripos($error['message'], self::WALLET_DISABLED_NEEDLE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ¿Los errores dicen que el pago no tiene saldo reversable?
     *
     * Es AMBIGUO (pago agotado, reversado por otro medio, o fuera de la
     * ventana de 180 días) y NO debe confundirse con isWalletDisabled(): esa
     * es la única causa que esconde el panel completo.
     *
     * @return bool
     */
    public static function isNotRefundable(array $errors)
    {
        foreach ($errors as $error) {
            if (isset($error['message']) && false !== stripos($error['message'], self::NOT_REFUNDABLE_NEEDLE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Texto mostrable a partir de los errores; si no hay ninguno, el código HTTP.
     *
     * @param int $httpCode
     *
     * @return string
     */
    public static function errorText(array $errors, $httpCode)
    {
        $parts = array();

        foreach ($errors as $error) {
            if (isset($error['message']) && '' !== $error['message']) {
                $parts[] = $error['message'];
            }
        }

        if (count($parts) > 0) {
            return implode(' ', $parts);
        }

        return 'Error HTTP ' . (int) $httpCode;
    }

    /**
     * Clasifica el desenlace de una llamada a Khipu.
     *
     * Un 4xx es el único caso en que Khipu confirma que NO hizo nada. Un 5xx, un
     * timeout o un 200 con cuerpo ilegible dejan el resultado DESCONOCIDO: en el
     * QA se observó a Khipu devolver 500 habiendo procesado la reversa, así que
     * afirmar "falló" ahí es falso y empuja al admin a reintentar y reversar dos veces.
     *
     * @param int    $httpCode
     * @param string $transportError vacío si la petición llegó a completarse
     * @param bool   $bodyIsValid    si el cuerpo se pudo decodificar como JSON
     *
     * @return string una de las constantes OUTCOME_*
     */
    public static function classifyOutcome($httpCode, $transportError, $bodyIsValid)
    {
        if ('' !== (string) $transportError) {
            return self::OUTCOME_UNKNOWN;
        }

        $code = (int) $httpCode;

        if (200 === $code) {
            return $bodyIsValid ? self::OUTCOME_OK : self::OUTCOME_UNKNOWN;
        }

        if ($code >= 400 && $code < 500) {
            return self::OUTCOME_REJECTED;
        }

        return self::OUTCOME_UNKNOWN;
    }

    /**
     * Campos que Khipu DEBE devolver en cada endpoint para que un 200 cuente
     * como respuesta interpretable.
     *
     * Un proxy o un WAF puede responder `200 {"message":"..."}` sin haber
     * hablado nunca con Khipu. Sin esta validación ese cuerpo pasa por reversa
     * exitosa y la nota del pedido queda escrita con ceros: el único registro
     * permanente de la operación, corrupto. Con ella, el 200 mutilado cae en la
     * rama de resultado desconocido, que es donde pertenece.
     *
     * `message` es el ÚNICO campo opcional de la respuesta de reversa.
     */
    const REQUIRED_REFUND_FIELDS = array('id', 'payment_id', 'refunded_amount', 'total_refunded', 'remaining');
    const REQUIRED_BALANCE_FIELDS = array('balance');
    const REQUIRED_PAYMENT_FIELDS = array('transaction_id');

    /**
     * Campos obligatorios que faltan en un cuerpo de respuesta.
     *
     * Un campo presente pero nulo cuenta como ausente: `null` no es un monto, y
     * dejarlo pasar reintroduce por la puerta de atrás el cero por defecto que
     * esta validación existe para impedir.
     *
     * @param mixed $data     cuerpo decodificado
     * @param array $required nombres de campo
     *
     * @return array nombres de los que faltan; vacío si está completo
     */
    public static function missingFields($data, array $required)
    {
        if (!is_array($data)) {
            return $required;
        }

        $missing = array();
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /** Valores de `status_detail` verificados contra la API real el 2026-09-09. */
    const STATUS_DETAIL_NORMAL = 'normal';
    const STATUS_DETAIL_PARTIALLY_REFUNDED = 'partially-refunded';
    const STATUS_DETAIL_FULLY_REFUNDED = 'fully-refunded';

    /**
     * ¿Khipu declara este pago reversado POR COMPLETO?
     *
     * `refundStateFrom()` colapsa parcial y total en "tiene reversas"; acá hace
     * falta distinguirlos, porque explicar un rechazo cambia según el caso.
     *
     * @param string|null $statusDetail
     *
     * @return bool
     */
    public static function isFullyRefunded($statusDetail)
    {
        return self::STATUS_DETAIL_FULLY_REFUNDED === trim((string) $statusDetail);
    }

    /**
     * ¿Tiene este pago alguna reversa, según el `status_detail` de Khipu?
     *
     * Valores verificados contra la API real el 2026-09-09: `normal` (ninguna),
     * `partially-refunded` (queda saldo), `fully-refunded` (agotado). Se acepta
     * además `''` y `reversed`, que es lo que devolvían versiones anteriores de
     * la API para "tiene al menos una reversa" — no sabemos qué versión corre en
     * el ambiente de cada comercio.
     *
     * No informa MONTOS: para saber cuánto queda sigue haciendo falta el
     * snapshot local.
     *
     * @param string|null $statusDetail
     *
     * @return string una de las constantes REFUNDS_*
     */
    public static function refundStateFrom($statusDetail)
    {
        if (null === $statusDetail) {
            return self::REFUNDS_UNKNOWN;
        }

        $detail = trim((string) $statusDetail);

        if ('normal' === $detail) {
            return self::REFUNDS_NONE;
        }

        if (in_array($detail, array('', 'partially-refunded', 'fully-refunded', 'reversed'), true)) {
            return self::REFUNDS_SOME;
        }

        return self::REFUNDS_UNKNOWN;
    }
}
