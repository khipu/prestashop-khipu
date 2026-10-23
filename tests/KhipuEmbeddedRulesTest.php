<?php

use PHPUnit\Framework\TestCase;

class KhipuEmbeddedRulesTest extends TestCase
{
    // --- canCancel: cuándo se puede borrar el pago y cancelar el pedido -----

    public function testPedidoAbiertoYPagoDelPedidoSePuedeCancelar()
    {
        $payment = array('payment_id' => 'p1', 'transaction_id' => 'ABCDEF', 'status' => 'pending');

        $this->assertTrue(KhipuEmbeddedRules::canCancel('ABCDEF', true, $payment));
    }

    public function testSinEstadoEnLaRespuestaBastaConQueCoincidaLaReferencia()
    {
        $this->assertTrue(KhipuEmbeddedRules::canCancel('ABCDEF', true, array('transaction_id' => 'ABCDEF')));
    }

    public function testPagoDeOtroPedidoNoSeCancela()
    {
        // El payment_id llega desde el navegador: sin esto, con la referencia
        // propia se podía borrar el pago de otro comprador.
        $this->assertFalse(KhipuEmbeddedRules::canCancel('ABCDEF', true, array('transaction_id' => 'ZZZZZZ')));
    }

    public function testPedidoQueYaNoEstaAbiertoNoSeCancela()
    {
        $this->assertFalse(KhipuEmbeddedRules::canCancel('ABCDEF', false, array('transaction_id' => 'ABCDEF')));
    }

    public function testSinPagoNoSeCancela()
    {
        // getPayment() devuelve null si Khipu no respondió: ante la duda no se toca.
        $this->assertFalse(KhipuEmbeddedRules::canCancel('ABCDEF', true, null));
        $this->assertFalse(KhipuEmbeddedRules::canCancel('ABCDEF', true, array()));
    }

    public function testReferenciaVaciaNoCoincideConTransactionIdVacio()
    {
        $this->assertFalse(KhipuEmbeddedRules::canCancel('', true, array('transaction_id' => '')));
    }

    public function testTransactionIdQueNoEsTextoNoCoincide()
    {
        $this->assertFalse(KhipuEmbeddedRules::canCancel('123', true, array('transaction_id' => 123)));
        $this->assertFalse(KhipuEmbeddedRules::canCancel('ABCDEF', true, array('transaction_id' => array('ABCDEF'))));
    }

    public function testPagoQueKhipuYaEstaVerificandoOPagadoNoSeCancela()
    {
        $this->assertFalse(KhipuEmbeddedRules::canCancel('ABCDEF', true, array('transaction_id' => 'ABCDEF', 'status' => 'verifying')));
        $this->assertFalse(KhipuEmbeddedRules::canCancel('ABCDEF', true, array('transaction_id' => 'ABCDEF', 'status' => 'done')));
    }

    // --- deleteSucceeded: si el pago dejó de ser pagable ----------------------

    public function testBorradoAceptadoOPagoInexistenteCuentanComoBorrado()
    {
        $this->assertTrue(KhipuEmbeddedRules::deleteSucceeded(200));
        $this->assertTrue(KhipuEmbeddedRules::deleteSucceeded(204));
        $this->assertTrue(KhipuEmbeddedRules::deleteSucceeded(404));
    }

    public function testBorradoRechazadoOSinRespuestaNoCuenta()
    {
        $this->assertFalse(KhipuEmbeddedRules::deleteSucceeded(0));
        $this->assertFalse(KhipuEmbeddedRules::deleteSucceeded(400));
        $this->assertFalse(KhipuEmbeddedRules::deleteSucceeded(403));
        $this->assertFalse(KhipuEmbeddedRules::deleteSucceeded(500));
    }

    // --- paymentIdFrom: el id que necesita KWS ---------------------------------

    public function testPaymentIdDeLaRespuesta()
    {
        $this->assertSame('abc123', KhipuEmbeddedRules::paymentIdFrom(array('payment_id' => 'abc123')));
    }

    public function testSinPaymentIdUtilizableEsNull()
    {
        $this->assertNull(KhipuEmbeddedRules::paymentIdFrom(array()));
        $this->assertNull(KhipuEmbeddedRules::paymentIdFrom(array('payment_id' => '')));
        $this->assertNull(KhipuEmbeddedRules::paymentIdFrom(array('payment_id' => array('x'))));
    }

    // --- isHttpsUrl: la URL de respaldo que va al href y a location ------------

    public function testUrlHttpsEsValida()
    {
        $this->assertTrue(KhipuEmbeddedRules::isHttpsUrl('https://khipu.com/payment/simplified/abc'));
        $this->assertTrue(KhipuEmbeddedRules::isHttpsUrl('HTTPS://khipu.com/x'));
    }

    public function testUrlQueNoEsHttpsNoSirve()
    {
        $this->assertFalse(KhipuEmbeddedRules::isHttpsUrl('http://khipu.com/x'));
        $this->assertFalse(KhipuEmbeddedRules::isHttpsUrl('javascript:alert(1)'));
        $this->assertFalse(KhipuEmbeddedRules::isHttpsUrl('//khipu.com/x'));
        $this->assertFalse(KhipuEmbeddedRules::isHttpsUrl('https://'));
        $this->assertFalse(KhipuEmbeddedRules::isHttpsUrl(''));
        $this->assertFalse(KhipuEmbeddedRules::isHttpsUrl(null));
        $this->assertFalse(KhipuEmbeddedRules::isHttpsUrl(array('https://khipu.com')));
    }
}
