<?php

use PHPUnit\Framework\TestCase;

class KhipuApiTest extends TestCase
{
    /**
     * Transporte falso: registra lo que se le pidió y devuelve lo que se le dijo.
     *
     * @param array $response array('status' => int, 'body' => string, 'error' => string)
     * @param array $spy      se llena por referencia con la petición
     *
     * @return callable
     */
    private function fakeTransport(array $response, array &$spy)
    {
        $response = array_merge(array('status' => 200, 'body' => '', 'error' => ''), $response);

        return function ($method, $url, array $headers, $body) use ($response, &$spy) {
            $spy = array('method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body);

            return $response;
        };
    }

    // --- getPaymentMethods -------------------------------------------------

    public function testMediosDePagoDevuelveLaListaDeLaCuenta()
    {
        $spy = array();
        $body = '{"paymentMethods":[{"id":"SIMPLIFIED_TRANSFER","logo_url":"//x/logo.png"}]}';
        $api = new KhipuApi('KEY', $this->fakeTransport(array('body' => $body), $spy));

        $methods = $api->getPaymentMethods('12345');

        $this->assertCount(1, $methods);
        $this->assertSame('SIMPLIFIED_TRANSFER', $methods[0]['id']);
        $this->assertSame('GET', $spy['method']);
        $this->assertSame('https://payment-api.khipu.com/v3/merchants/12345/paymentMethods', $spy['url']);
        $this->assertContains('x-api-key: KEY', $spy['headers']);
    }

    public function testMediosDePagoDevuelveNullSiLaLlamadaFalla()
    {
        $spy = array();
        $api = new KhipuApi('KEY', $this->fakeTransport(array('status' => 401, 'body' => '{"message":"unauthorized"}'), $spy));

        $this->assertNull($api->getPaymentMethods('12345'));
    }

    public function testMediosDePagoDevuelveNullSiElCuerpoNoTraeLaLista()
    {
        $spy = array();
        $api = new KhipuApi('KEY', $this->fakeTransport(array('body' => '{"message":"ok"}'), $spy));

        $this->assertNull($api->getPaymentMethods('12345'));
    }

    /**
     * Un 200 con HTML es lo que devuelve un proxy o un portal cautivo por su
     * cuenta. Si pasara por lista de medios vacía, el checkout se quedaría sin
     * Khipu sin que nadie supiera por qué.
     */
    public function testMediosDePagoDevuelveNullSiElCuerpoNoEsJson()
    {
        $spy = array();
        $api = new KhipuApi('KEY', $this->fakeTransport(array('body' => '<html>oops</html>'), $spy));

        $this->assertNull($api->getPaymentMethods('12345'));
    }

    public function testElIdDeCobradorSeEscapaEnLaUrl()
    {
        $spy = array();
        $api = new KhipuApi('KEY', $this->fakeTransport(array('body' => '{"paymentMethods":[]}'), $spy));

        $api->getPaymentMethods('a b/c');

        $this->assertSame('https://payment-api.khipu.com/v3/merchants/a%20b%2Fc/paymentMethods', $spy['url']);
    }

    // --- createPayment -----------------------------------------------------

    public function testCrearPagoMandaElCuerpoComoJsonPorPost()
    {
        $spy = array();
        $body = '{"payment_id":"abc","simplified_transfer_url":"https://khipu.com/s/abc"}';
        $api = new KhipuApi('KEY', $this->fakeTransport(array('body' => $body), $spy));

        $result = $api->createPayment(array('subject' => 'Tienda Carro #1', 'amount' => 1990));

        $this->assertTrue($result['ok']);
        $this->assertSame('https://khipu.com/s/abc', $result['data']['simplified_transfer_url']);
        $this->assertSame('POST', $spy['method']);
        $this->assertSame('https://payment-api.khipu.com/v3/payments', $spy['url']);
        $this->assertContains('Content-Type: application/json', $spy['headers']);
        $this->assertSame(array('subject' => 'Tienda Carro #1', 'amount' => 1990), json_decode($spy['body'], true));
    }

    public function testCrearPagoRechazadoNoEsOkPeroConservaElCuerpo()
    {
        $spy = array();
        $body = '{"status":400,"errors":[{"field":"amount","message":"Monto inválido"}]}';
        $api = new KhipuApi('KEY', $this->fakeTransport(array('status' => 400, 'body' => $body), $spy));

        $result = $api->createPayment(array('amount' => 0));

        $this->assertFalse($result['ok']);
        $this->assertSame(400, $result['status']);
        // El cuerpo se conserva: es lo que el checkout le muestra al pagador.
        $this->assertSame('Monto inválido', $result['data']['errors'][0]['message']);
    }

    public function testFalloDeRedNoEsOkYNoTraeDatos()
    {
        $spy = array();
        $api = new KhipuApi('KEY', $this->fakeTransport(
            array('status' => 0, 'body' => '', 'error' => 'Operation timed out'),
            $spy
        ));

        $result = $api->createPayment(array('amount' => 1990));

        $this->assertFalse($result['ok']);
        $this->assertSame(array(), $result['data']);
    }

    /**
     * Sin el motivo, el log de un timeout decía solo «HTTP 0» y no había cómo
     * distinguirlo de un problema de DNS o de TLS.
     */
    public function testFalloDeRedConservaElMotivoDelTransporte()
    {
        $spy = array();
        $api = new KhipuApi('KEY', $this->fakeTransport(
            array('status' => 0, 'body' => '', 'error' => 'Operation timed out after 15000 milliseconds'),
            $spy
        ));

        $result = $api->createPayment(array('amount' => 1990));

        $this->assertSame('Operation timed out after 15000 milliseconds', $result['error']);
    }

    public function testRespuestaBuenaNoTraeMotivoDeError()
    {
        $spy = array();
        $api = new KhipuApi('KEY', $this->fakeTransport(array('body' => '{"payment_id":"abc"}'), $spy));

        $this->assertSame('', $api->createPayment(array('amount' => 1990))['error']);
    }

    // --- amountPrecision ---------------------------------------------------

    /**
     * El checkout y la notificación redondean con esto. Si difieren, un total
     * CLP con decimales no se reconcilia nunca: el comprador paga y el pedido
     * termina cancelado.
     */
    public function testLosPesosChilenosSeRedondeanSinDecimales()
    {
        $this->assertSame(0, KhipuApi::amountPrecision('CLP'));
    }

    public function testLasDemasMonedasSeRedondeanADosDecimales()
    {
        $this->assertSame(2, KhipuApi::amountPrecision('USD'));
        $this->assertSame(2, KhipuApi::amountPrecision('EUR'));
    }

    public function testElUserAgentIdentificaAlPlugin()
    {
        $spy = array();
        $api = new KhipuApi('KEY', $this->fakeTransport(array('body' => '{"paymentMethods":[]}'), $spy));

        $api->getPaymentMethods('12345');

        $this->assertContains('User-Agent: ' . KhipuVersion::USER_AGENT, $spy['headers']);
    }

    /**
     * Los timeouts del checkout tienen que ser más cortos que los de la
     * reversa: acá hay un comprador esperando una página.
     */
    public function testLosTimeoutsDelCheckoutSonMasCortosQueLosDeLaReversa()
    {
        $this->assertLessThan(KhipuRefundService::TIMEOUT, KhipuApi::TIMEOUT);
        $this->assertLessThanOrEqual(KhipuRefundService::CONNECT_TIMEOUT, KhipuApi::CONNECT_TIMEOUT);
    }
}
