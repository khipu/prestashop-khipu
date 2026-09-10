<?php
/**
 * Cliente de los endpoints de reversa de la API de Khipu v3.
 *
 * No sabe qué es un pedido ni toca la base de datos: solo habla HTTP.
 * El transporte se recibe por constructor para poder probar sin red.
 */
class KhipuRefundService
{
    const BASE_URL = 'https://payment-api.khipu.com/v3';
    const USER_AGENT = KhipuVersion::USER_AGENT;
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
        $this->transport = (null === $transport) ? self::curlTransport() : $transport;
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
        $headers = array(
            'x-api-key: ' . $this->apiKey,
            'User-Agent: ' . self::USER_AGENT,
        );

        if ('POST' === $method) {
            $headers[] = 'Content-Type: application/json';
        }

        $body = null;
        if (null !== $payload) {
            $body = json_encode($payload);
        }

        $transport = $this->transport;
        $raw = call_user_func($transport, $method, self::BASE_URL . $path, $headers, $body);

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

    /**
     * Transporte de producción.
     *
     * @return callable
     */
    private static function curlTransport()
    {
        return function ($method, $url, array $headers, $body) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, KhipuRefundService::CONNECT_TIMEOUT);
            curl_setopt($ch, CURLOPT_TIMEOUT, KhipuRefundService::TIMEOUT);

            if ('POST' === $method) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $responseBody = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            return array(
                'status' => $status,
                'body' => (false === $responseBody) ? '' : $responseBody,
                'error' => (false === $responseBody) ? ($error ? $error : 'Error de comunicación con Khipu.') : '',
            );
        };
    }
}
