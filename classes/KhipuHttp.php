<?php
require_once dirname(__FILE__) . '/KhipuVersion.php';

/**
 * Transporte HTTP compartido por los clientes de la API de Khipu.
 *
 * Existe por los timeouts. Son la diferencia entre «Khipu está lento» y «el
 * checkout de la tienda se colgó»: cada cliente que se escribió aparte nació
 * sin ellos, y las llamadas del checkout quedaban esperando hasta el
 * max_execution_time del servidor. Con un solo transporte, poner un límite de
 * tiempo deja de ser algo que haya que acordarse de hacer.
 *
 * No depende de PrestaShop: se puede probar sin levantar la tienda.
 */
class KhipuHttp
{
    const BASE_URL = 'https://payment-api.khipu.com/v3';

    /**
     * Arma y manda una petición a la API v3.
     *
     * La URL base y las cabeceras son las mismas para todos los clientes. Cada
     * uno tenía su copia, así que cambiarlas era acordarse de cambiarlas en dos
     * lados.
     *
     * @param callable   $transport ver curlTransport()
     * @param string     $apiKey
     * @param string     $method
     * @param string     $path      relativo a BASE_URL, con la barra inicial
     * @param array|null $payload   se manda como JSON si no es null
     *
     * @return array lo que devuelva el transporte: array('status', 'body', 'error')
     */
    public static function request($transport, $apiKey, $method, $path, $payload = null)
    {
        $headers = array(
            'x-api-key: ' . $apiKey,
            'User-Agent: ' . KhipuVersion::USER_AGENT,
        );

        $body = null;
        if (null !== $payload) {
            $headers[] = 'Content-Type: application/json';
            $body = json_encode($payload);
        }

        return call_user_func($transport, $method, self::BASE_URL . $path, $headers, $body);
    }

    /**
     * Transporte de producción.
     *
     * @param int $connectTimeout segundos para establecer la conexión
     * @param int $timeout        segundos para la operación completa
     *
     * @return callable callable($method, $url, array $headers, $body) que
     *                  devuelve array('status' => int, 'body' => string, 'error' => string)
     */
    public static function curlTransport($connectTimeout, $timeout)
    {
        $connectTimeout = (int) $connectTimeout;
        $timeout = (int) $timeout;

        return function ($method, $url, array $headers, $body) use ($connectTimeout, $timeout) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

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
