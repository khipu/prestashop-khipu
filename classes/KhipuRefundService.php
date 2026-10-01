<?php
require_once dirname(__FILE__) . '/KhipuVersion.php';
require_once dirname(__FILE__) . '/KhipuRefundRules.php';
require_once dirname(__FILE__) . '/KhipuHttp.php';

/**
 * Cliente de los endpoints de reversa de la API de Khipu v3.
 *
 * No sabe qué es un pedido ni toca la base de datos: solo habla HTTP.
 * El transporte se recibe por constructor para poder probar sin red.
 */
class KhipuRefundService
{
    const CONNECT_TIMEOUT = 10;
    const TIMEOUT = 20;

    /** @var string */
    private $apiKey;

    /** @var callable */
    private $transport;

    /**
     * @param string        $apiKey
     * @param callable|null $transport callable($method, $url, array $headers, $body)
     *                                 que devuelve array('status','body','error').
     *                                 Si es null se usa cURL.
     */
    public function __construct($apiKey, $transport = null)
    {
        $this->apiKey = (string) $apiKey;
        $this->transport = (null === $transport)
            ? KhipuHttp::curlTransport(self::CONNECT_TIMEOUT, self::TIMEOUT)
            : $transport;
    }

    /**
     * GET /v3/refund-wallet/balance
     *
     * Responder con saldo (aunque sea 0) significa que la cuenta tiene el flag
     * de billetera habilitado. Un 400 con field=receiver_id significa que no.
     *
     * @return array
     */
    public function getWalletBalance()
    {
        return $this->request('GET', '/refund-wallet/balance', null, KhipuRefundRules::REQUIRED_BALANCE_FIELDS);
    }

    /**
     * POST /v3/refunds
     *
     * @param string            $paymentId
     * @param string            $type      KhipuRefundRules::TYPE_FULL o TYPE_PARTIAL
     * @param string|float|null $amount    obligatorio en parcial; se OMITE en total
     *
     * @return array
     */
    public function refund($paymentId, $type, $amount = null)
    {
        $payload = array(
            'type' => (string) $type,
            'payment_id' => (string) $paymentId,
        );

        // `full` reversa el saldo restante: mandar `amount` sería incorrecto.
        if (KhipuRefundRules::TYPE_PARTIAL === $type) {
            $payload['amount'] = (string) $amount;
        }

        return $this->request('POST', '/refunds', $payload, KhipuRefundRules::REQUIRED_REFUND_FIELDS);
    }

    /**
     * GET /v3/payments/{id}
     *
     * Se usa para resolver un desenlace desconocido: su `status_detail` dice si
     * el pago tiene alguna reversa. No hay endpoint para consultar una reversa
     * concreta, así que esto es lo más cerca que se puede llegar.
     *
     * @param string $paymentId
     *
     * @return array mismo formato que los demás métodos
     */
    public function getPayment($paymentId)
    {
        return $this->request('GET', '/payments/' . rawurlencode((string) $paymentId), null, KhipuRefundRules::REQUIRED_PAYMENT_FIELDS);
    }

    /**
     * @param string     $method
     * @param string     $path
     * @param array|null $payload
     * @param array      $required campos que el 200 debe traer para valer
     *
     * @return array
     */
    private function request($method, $path, $payload, array $required = array())
    {
        $raw = KhipuHttp::request($this->transport, $this->apiKey, $method, $path, $payload);

        return $this->interpret($raw, $required);
    }

    /**
     * @param array $raw      array('status' => int, 'body' => string, 'error' => string)
     * @param array $required campos obligatorios del 200 de este endpoint
     *
     * @return array
     */
    private function interpret($raw, array $required = array())
    {
        $status = isset($raw['status']) ? (int) $raw['status'] : 0;
        $rawBody = isset($raw['body']) ? (string) $raw['body'] : '';
        $transportError = isset($raw['error']) ? (string) $raw['error'] : '';

        $result = array(
            'ok' => false,
            'http' => $status,
            'data' => array(),
            'errors' => array(),
            'message' => '',
            'outcome' => KhipuRefundRules::OUTCOME_UNKNOWN,
        );

        if ('' !== $transportError) {
            $result['message'] = $transportError;
            $result['outcome'] = KhipuRefundRules::classifyOutcome($status, $transportError, false);

            return $result;
        }

        $decoded = json_decode($rawBody, true);

        if (200 === $status) {
            // Un 200 solo vale si trae los campos del endpoint. Lo contrario
            // deja pasar el `200 {"message":"..."}` de un proxy como si fuera
            // una reversa, y el resto del flujo escribe ceros sobre plata real.
            $missing = KhipuRefundRules::missingFields($decoded, $required);
            $bodyIsValid = is_array($decoded) && !$missing;
            $result['outcome'] = KhipuRefundRules::classifyOutcome($status, $transportError, $bodyIsValid);

            if (!$bodyIsValid) {
                $result['message'] = is_array($decoded)
                    ? 'Respuesta incompleta de Khipu: faltan los campos ' . implode(', ', $missing) . '.'
                    : 'Respuesta inválida de Khipu (cuerpo vacío o mal formado).';

                return $result;
            }
            $result['ok'] = true;
            $result['data'] = $decoded;

            return $result;
        }

        $result['errors'] = KhipuRefundRules::parseErrors($decoded);
        $result['message'] = KhipuRefundRules::errorText($result['errors'], $status);
        $result['outcome'] = KhipuRefundRules::classifyOutcome($status, $transportError, is_array($decoded));

        return $result;
    }

}
