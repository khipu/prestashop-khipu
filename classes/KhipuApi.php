<?php
require_once dirname(__FILE__) . '/KhipuVersion.php';
require_once dirname(__FILE__) . '/KhipuHttp.php';

/**
 * Cliente de los endpoints de pago de la API de Khipu v3.
 *
 * Reúne las dos llamadas del checkout —listar los medios de pago de la cuenta
 * y crear el pago— que antes estaban copiadas en tres archivos, cada copia con
 * su propio cURL y ninguna con timeout.
 *
 * Las reversas tienen su propio cliente (KhipuRefundService): su gramática de
 * errores y su clasificación de desenlaces no se parecen a esto, donde la
 * pregunta es binaria —hay URL a la que mandar al pagador, o no la hay—.
 *
 * No depende de PrestaShop.
 */
class KhipuApi
{
    /**
     * Timeouts del checkout, deliberadamente más cortos que los de la reversa
     * (10/20): acá hay un comprador esperando que le cargue una página. La
     * reversa la lanza un administrador, que sí puede esperar.
     */
    const CONNECT_TIMEOUT = 5;
    const TIMEOUT = 15;

    /** @var string */
    private $apiKey;

    /** @var callable */
    private $transport;

    /**
     * @param string        $apiKey
     * @param callable|null $transport callable($method, $url, array $headers, $body);
     *                                 si es null se usa cURL
     */
    public function __construct($apiKey, $transport = null)
    {
        $this->apiKey = (string) $apiKey;
        $this->transport = (null === $transport)
            ? KhipuHttp::curlTransport(self::CONNECT_TIMEOUT, self::TIMEOUT)
            : $transport;
    }

    /**
     * Decimales con que se redondea un monto de pago en esta moneda.
     *
     * El checkout y la notificación tienen que usar el mismo: la notificación
     * compara el total del pedido redondeado con el monto que Khipu cobró, y si
     * el checkout mandaba 11900.5 y la notificación esperaba 11901, el pago se
     * rechazaba en cada reintento aunque el comprador hubiera pagado.
     *
     * @param string $isoCode
     *
     * @return int
     */
    public static function amountPrecision($isoCode)
    {
        return ('CLP' === (string) $isoCode) ? 0 : 2;
    }

    /**
     * @param array|null $methods lista devuelta por getPaymentMethods()
     * @param string[]   $ids
     *
     * @return bool
     */
    public static function methodsIncludeAny($methods, array $ids)
    {
        if (!is_array($methods)) {
            return false;
        }

        foreach ($methods as $method) {
            if (isset($method['id']) && in_array($method['id'], $ids, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * URL a la que se manda al pagador tras crear el pago: la del medio propio
     * de la fila elegida. La moneda no decide nada; si Khipu no devolvió esa
     * URL, el llamador trata el pago como fallido.
     *
     * @param array  $data   cuerpo de la respuesta de POST /payments
     * @param string $ownKey clave propia del medio (transfer_url, ligopay_url, ...)
     *
     * @return string|null
     */
    public static function selectRedirectUrl(array $data, $ownKey)
    {
        return (isset($data[$ownKey]) && is_string($data[$ownKey]) && '' !== $data[$ownKey])
            ? $data[$ownKey]
            : null;
    }

    /**
     * GET /v3/merchants/{id}/paymentMethods
     *
     * Devuelve null —y no un array vacío— cuando la consulta falla, para que
     * quien la llame pueda distinguir «esta cuenta no tiene medios» de «no se
     * pudo preguntar».
     *
     * @param string $merchantId
     *
     * @return array|null
     */
    public function getPaymentMethods($merchantId)
    {
        $response = $this->request('GET', '/merchants/' . rawurlencode((string) $merchantId) . '/paymentMethods');

        if (!$response['ok'] || !isset($response['data']['paymentMethods'])) {
            return null;
        }

        return $response['data']['paymentMethods'];
    }

    /**
     * POST /v3/payments
     *
     * @param array $payload cuerpo del pago tal como lo espera Khipu
     *
     * @return array array('ok' => bool, 'status' => int, 'data' => array, 'error' => string)
     */
    public function createPayment(array $payload)
    {
        return $this->request('POST', '/payments', $payload);
    }

    /**
     * GET /v3/payments/{id}
     *
     * @param string $paymentId
     *
     * @return array|null datos del pago si Khipu respondió 2xx con un objeto; si no, null
     */
    public function getPayment($paymentId)
    {
        $response = $this->request('GET', '/payments/' . rawurlencode((string) $paymentId));

        if ($response['status'] < 200 || $response['status'] > 299 || empty($response['data'])) {
            return null;
        }

        return $response['data'];
    }

    /**
     * DELETE /v3/payments/{id}
     *
     * @param string $paymentId
     *
     * @return int código HTTP de Khipu; 0 si no hubo respuesta
     */
    public function deletePayment($paymentId)
    {
        $response = $this->request('DELETE', '/payments/' . rawurlencode((string) $paymentId));

        return $response['status'];
    }

    /**
     * @param string     $method
     * @param string     $path
     * @param array|null $payload
     *
     * @return array array('ok' => bool, 'status' => int, 'data' => array, 'error' => string)
     *               `error` es el motivo del transporte (timeout, DNS, TLS);
     *               sin él, el log de un timeout decía solo «HTTP 0».
     */
    private function request($method, $path, $payload = null)
    {
        $raw = KhipuHttp::request($this->transport, $this->apiKey, $method, $path, $payload);

        $status = isset($raw['status']) ? (int) $raw['status'] : 0;
        $decoded = json_decode(isset($raw['body']) ? (string) $raw['body'] : '', true);

        return array(
            'ok' => (200 === $status && is_array($decoded)),
            'status' => $status,
            'data' => is_array($decoded) ? $decoded : array(),
            'error' => isset($raw['error']) ? (string) $raw['error'] : '',
        );
    }
}
