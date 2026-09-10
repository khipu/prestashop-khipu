<?php

use PHPUnit\Framework\TestCase;

class KhipuRefundServiceTest extends TestCase
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

    // --- getWalletBalance --------------------------------------------------

    public function testBalanceExitosoDevuelveLosDatos()
    {
        $spy = array();
        $body = '{"balance":"10.0000","currency":"CLP","add_funds_url":"https://khipu.com/dashboard/bills"}';
        $service = new KhipuRefundService('KEY', $this->fakeTransport(array('body' => $body), $spy));

        $result = $service->getWalletBalance();

        $this->assertTrue($result['ok']);
        $this->assertSame('10.0000', $result['data']['balance']);
        $this->assertSame('CLP', $result['data']['currency']);
        $this->assertSame('GET', $spy['method']);
        $this->assertSame('https://payment-api.khipu.com/v3/refund-wallet/balance', $spy['url']);
        $this->assertContains('x-api-key: KEY', $spy['headers']);
    }

    public function testBalanceConFlagApagadoSeReconoce()
    {
        $spy = array();
        $body = '{"status":400,"message":"Error de validación","errors":[{"field":"receiver_id","message":"La cuenta de cobro con ID 1 no está habilitada para hacer reversas."}]}';
        $service = new KhipuRefundService('KEY', $this->fakeTransport(array('status' => 400, 'body' => $body), $spy));

        $result = $service->getWalletBalance();

        $this->assertFalse($result['ok']);
        $this->assertSame(400, $result['http']);
        $this->assertTrue(KhipuRefundRules::isWalletDisabled($result['errors']));
    }

    // --- refund ------------------------------------------------------------

    public function testReversaParcialMandaElMonto()
    {
        $spy = array();
        $body = '{"id":"uuid-1","payment_id":"pay-1","refunded_amount":"50.0000","total_refunded":"50.0000","remaining":"150.0000","currency":"CLP","message":"La reversa está en proceso."}';
        $service = new KhipuRefundService('KEY', $this->fakeTransport(array('body' => $body), $spy));

        $result = $service->refund('pay-1', KhipuRefundRules::TYPE_PARTIAL, '50');

        $this->assertTrue($result['ok']);
        $this->assertSame('150.0000', $result['data']['remaining']);
        $this->assertSame('POST', $spy['method']);
        $this->assertSame('https://payment-api.khipu.com/v3/refunds', $spy['url']);

        $sent = json_decode($spy['body'], true);
        $this->assertSame('partial', $sent['type']);
        $this->assertSame('pay-1', $sent['payment_id']);
        $this->assertSame('50', $sent['amount']);
    }

    public function testReversaTotalNoMandaElMonto()
    {
        // `full` reversa el saldo RESTANTE; mandar `amount` sería un error de contrato.
        $spy = array();
        $body = '{"id":"uuid-2","payment_id":"pay-1","refunded_amount":"150.0000","total_refunded":"200.0000","remaining":"0.0000","currency":"CLP"}';
        $service = new KhipuRefundService('KEY', $this->fakeTransport(array('body' => $body), $spy));

        $service->refund('pay-1', KhipuRefundRules::TYPE_FULL, '150');

        $sent = json_decode($spy['body'], true);
        $this->assertSame('full', $sent['type']);
        $this->assertArrayNotHasKey('amount', $sent);
    }

    public function testReversaRechazadaDevuelveLosErroresDeKhipu()
    {
        $spy = array();
        $body = '{"status":400,"message":"Error de validación","errors":[{"field":"amount","message":"El monto excede el saldo reembolsable del pago"}]}';
        $service = new KhipuRefundService('KEY', $this->fakeTransport(array('status' => 400, 'body' => $body), $spy));

        $result = $service->refund('pay-1', KhipuRefundRules::TYPE_PARTIAL, '999');

        $this->assertFalse($result['ok']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('amount', $result['errors'][0]['field']);
        $this->assertSame('El monto excede el saldo reembolsable del pago', $result['message']);
    }

    public function testFalloDeRedDevuelveNoOkConElMensajeDelTransporte()
    {
        $spy = array();
        $service = new KhipuRefundService('KEY', $this->fakeTransport(
            array('status' => 0, 'body' => '', 'error' => 'Connection timed out'),
            $spy
        ));

        $result = $service->getWalletBalance();

        $this->assertFalse($result['ok']);
        $this->assertSame(0, $result['http']);
        $this->assertSame('Connection timed out', $result['message']);
    }

    public function testRespuesta200ConCuerpoInvalidoNoSeTomaComoExito()
    {
        $spy = array();
        $service = new KhipuRefundService('KEY', $this->fakeTransport(array('body' => '<html>oops</html>'), $spy));

        $result = $service->getWalletBalance();

        $this->assertFalse($result['ok']);
        $this->assertNotSame('', $result['message']);
    }

    public function testElUserAgentIdentificaAlPlugin()
    {
        $spy = array();
        $service = new KhipuRefundService('KEY', $this->fakeTransport(array('body' => '{"balance":"0"}'), $spy));
        $service->getWalletBalance();

        $this->assertContains('User-Agent: ' . KhipuVersion::USER_AGENT, $spy['headers']);
    }

    // --- outcome: ok / rejected / unknown ------------------------------------

    public function testUn500DelTransporteFalsoProduceOutcomeDesconocidoYNoOk()
    {
        // En QA, Khipu devolvió 500 habiendo procesado la reversa. El servicio
        // no puede afirmar "falló": el desenlace debe quedar como desconocido.
        $spy = array();
        $service = new KhipuRefundService('KEY', $this->fakeTransport(
            array('status' => 500, 'body' => 'Internal Server Error'),
            $spy
        ));

        $result = $service->refund('pay-1', KhipuRefundRules::TYPE_FULL);

        $this->assertFalse($result['ok']);
        $this->assertSame(KhipuRefundRules::OUTCOME_UNKNOWN, $result['outcome']);
    }

    public function testUn400ProduceOutcomeRechazado()
    {
        $spy = array();
        $body = '{"status":400,"message":"Error de validación","errors":[{"field":"amount","message":"El monto excede el saldo reembolsable del pago"}]}';
        $service = new KhipuRefundService('KEY', $this->fakeTransport(array('status' => 400, 'body' => $body), $spy));

        $result = $service->refund('pay-1', KhipuRefundRules::TYPE_PARTIAL, '999');

        $this->assertFalse($result['ok']);
        $this->assertSame(KhipuRefundRules::OUTCOME_REJECTED, $result['outcome']);
    }

    // --- getPayment ----------------------------------------------------------

    public function testGetPaymentPideLaUrlCorrectaPorGet()
    {
        $spy = array();
        $body = '{"transaction_id":"pay-1","payment_id":"pay-1","status":"done","status_detail":"partially-refunded"}';
        $service = new KhipuRefundService('KEY', $this->fakeTransport(array('body' => $body), $spy));

        $result = $service->getPayment('pay-1');

        $this->assertTrue($result['ok']);
        $this->assertSame('GET', $spy['method']);
        $this->assertSame('https://payment-api.khipu.com/v3/payments/pay-1', $spy['url']);
        $this->assertSame('partially-refunded', $result['data']['status_detail']);
    }

    // --- validación de campos obligatorios en el 200 -------------------------

    /**
     * El caso real que motiva la validación: un proxy o un WAF que contesta 200
     * con un cuerpo JSON propio. Sin validar, eso pasa por reversa exitosa y el
     * flujo escribe ceros en la nota del pedido sobre plata que sí se movió.
     */
    public function testUn200SinLosCamposObligatoriosNoCuentaComoReversa()
    {
        $spy = array();
        $service = new KhipuRefundService('KEY', $this->fakeTransport(
            array('body' => '{"message":"Request accepted"}'),
            $spy
        ));

        $result = $service->refund('pay-1', KhipuRefundRules::TYPE_FULL);

        $this->assertFalse($result['ok']);
        $this->assertSame(KhipuRefundRules::OUTCOME_UNKNOWN, $result['outcome']);
        $this->assertStringContainsString('refunded_amount', $result['message']);
    }

    public function testUn200AlQueLeFaltaUnSoloCampoTampocoCuenta()
    {
        $spy = array();
        $body = '{"id":"uuid-1","payment_id":"pay-1","refunded_amount":"50.0000","total_refunded":"50.0000"}';
        $service = new KhipuRefundService('KEY', $this->fakeTransport(array('body' => $body), $spy));

        $result = $service->refund('pay-1', KhipuRefundRules::TYPE_PARTIAL, '50');

        $this->assertFalse($result['ok']);
        $this->assertSame(KhipuRefundRules::OUTCOME_UNKNOWN, $result['outcome']);
        $this->assertStringContainsString('remaining', $result['message']);
    }

    public function testMessageSigueSiendoOpcionalEnLaReversa()
    {
        $spy = array();
        $body = '{"id":"uuid-2","payment_id":"pay-1","refunded_amount":"150.0000","total_refunded":"200.0000","remaining":"0.0000"}';
        $service = new KhipuRefundService('KEY', $this->fakeTransport(array('body' => $body), $spy));

        $result = $service->refund('pay-1', KhipuRefundRules::TYPE_FULL);

        $this->assertTrue($result['ok']);
    }

    public function testUnBalanceSinElCampoBalanceNoSeDaPorBueno()
    {
        $spy = array();
        $service = new KhipuRefundService('KEY', $this->fakeTransport(
            array('body' => '{"currency":"CLP"}'),
            $spy
        ));

        $result = $service->getWalletBalance();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('balance', $result['message']);
    }

    public function testUnPagoSinTransactionIdNoSeDaPorBueno()
    {
        $spy = array();
        $service = new KhipuRefundService('KEY', $this->fakeTransport(
            array('body' => '{"status_detail":"fully-refunded"}'),
            $spy
        ));

        $result = $service->getPayment('pay-1');

        $this->assertFalse($result['ok']);
    }
}
