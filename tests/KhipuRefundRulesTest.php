<?php

use PHPUnit\Framework\TestCase;

class KhipuRefundRulesTest extends TestCase
{
    // --- remainingFrom: de dónde sale el saldo reversable -------------------

    public function testRemainingCaeAlMontoPagadoCuandoNoHayReversasPrevias()
    {
        $this->assertSame(200.0, KhipuRefundRules::remainingFrom(null, '200.000000'));
    }

    public function testRemainingUsaElSnapshotDeLaUltimaReversa()
    {
        $this->assertSame(150.0, KhipuRefundRules::remainingFrom('150.0000', '200.000000'));
    }

    public function testRemainingCeroEsCeroYNoCaeAlMontoPagado()
    {
        // Un pago agotado tiene remaining 0; no debe confundirse con "sin datos".
        $this->assertSame(0.0, KhipuRefundRules::remainingFrom('0.0000', '200.000000'));
    }

    // --- validateAmount ----------------------------------------------------

    public function testFullNoExigeMonto()
    {
        $this->assertTrue(KhipuRefundRules::validateAmount(KhipuRefundRules::TYPE_FULL, null, 200.0));
    }

    public function testParcialConMontoValidoPasa()
    {
        $this->assertTrue(KhipuRefundRules::validateAmount(KhipuRefundRules::TYPE_PARTIAL, '50', 200.0));
    }

    public function testParcialAceptaDecimalesConPunto()
    {
        $this->assertTrue(KhipuRefundRules::validateAmount(KhipuRefundRules::TYPE_PARTIAL, '50.55', 200.0));
    }

    public function testParcialSinMontoFalla()
    {
        $this->assertSame(
            'Indica el monto a reversar.',
            KhipuRefundRules::validateAmount(KhipuRefundRules::TYPE_PARTIAL, null, 200.0)
        );
    }

    public function testParcialConMontoVacioFalla()
    {
        $this->assertIsString(KhipuRefundRules::validateAmount(KhipuRefundRules::TYPE_PARTIAL, '   ', 200.0));
    }

    public function testParcialConCeroFalla()
    {
        $this->assertSame(
            'El monto debe ser mayor que cero.',
            KhipuRefundRules::validateAmount(KhipuRefundRules::TYPE_PARTIAL, '0', 200.0)
        );
    }

    public function testParcialConNegativoFalla()
    {
        $this->assertIsString(KhipuRefundRules::validateAmount(KhipuRefundRules::TYPE_PARTIAL, '-5', 200.0));
    }

    public function testParcialConComaComoSeparadorFalla()
    {
        // Sin esta comprobación, (float)'50,5' daría 50.0 en silencio y se
        // reversaría un monto distinto del que el admin escribió.
        $this->assertIsString(KhipuRefundRules::validateAmount(KhipuRefundRules::TYPE_PARTIAL, '50,5', 200.0));
    }

    public function testParcialQueExcedeElSaldoFalla()
    {
        $this->assertSame(
            'El monto no puede superar el saldo reversable de este pedido.',
            KhipuRefundRules::validateAmount(KhipuRefundRules::TYPE_PARTIAL, '250', 200.0)
        );
    }

    public function testParcialIgualAlSaldoPasa()
    {
        $this->assertTrue(KhipuRefundRules::validateAmount(KhipuRefundRules::TYPE_PARTIAL, '200', 200.0));
    }

    // --- parseErrors: la gramática verificada de Khipu ---------------------

    public function testPagoAgotadoConParcialDevuelveDosErrores()
    {
        $body = array(
            'status' => 400,
            'message' => 'Error de validación',
            'errors' => array(
                array('field' => 'payment_id', 'message' => 'El pago con ID abc no es reembolsable'),
                array('field' => 'amount', 'message' => 'El monto 50 excede el saldo reembolsable del pago'),
            ),
        );
        $errors = KhipuRefundRules::parseErrors($body);
        $this->assertCount(2, $errors);
        $this->assertSame('payment_id', $errors[0]['field']);
        $this->assertSame('amount', $errors[1]['field']);
    }

    public function testPagoAgotadoConFullDevuelveUnError()
    {
        $body = array(
            'status' => 400,
            'errors' => array(
                array('field' => 'payment_id', 'message' => 'El pago con ID abc no es reembolsable'),
            ),
        );
        $this->assertCount(1, KhipuRefundRules::parseErrors($body));
    }

    public function testCuerpoSinErroresDevuelveListaVacia()
    {
        $this->assertSame(array(), KhipuRefundRules::parseErrors(null));
        $this->assertSame(array(), KhipuRefundRules::parseErrors(array('message' => 'boom')));
    }

    // --- isWalletDisabled: el gate del flag --------------------------------

    public function testFlagOffSeDetectaPorElCampoReceiverId()
    {
        $errors = array(array(
            'field' => 'receiver_id',
            'message' => 'La cuenta de cobro con ID 1234 no está habilitada para hacer reversas.',
        ));
        $this->assertTrue(KhipuRefundRules::isWalletDisabled($errors));
    }

    public function testFlagOffSeDetectaPorElTextoAunqueCambieElCampo()
    {
        // Defensa en profundidad: si Khipu mueve el error a otro campo, el
        // texto sigue delatándolo.
        $errors = array(array(
            'field' => 'otro',
            'message' => 'La cuenta no está habilitada para hacer reversas.',
        ));
        $this->assertTrue(KhipuRefundRules::isWalletDisabled($errors));
    }

    public function testFlagOffSeDetectaConLaRedaccionNuevaDeKhipu()
    {
        // Septiembre 2026: Khipu cambió "reversas" por "devoluciones" en este
        // mensaje. La subcadena está cortada antes del sustantivo justamente
        // para sobrevivir al cambio. Verificado contra la API real.
        $errors = array(array(
            'field' => 'otro',
            'message' => 'La cuenta de cobro con ID 525329 no está habilitada para hacer devoluciones.',
        ));
        $this->assertTrue(KhipuRefundRules::isWalletDisabled($errors));
    }

    public function testIsFullyRefundedDistingueAgotadoDeParcial()
    {
        $this->assertTrue(KhipuRefundRules::isFullyRefunded('fully-refunded'));
        $this->assertTrue(KhipuRefundRules::isFullyRefunded('  fully-refunded  '));
        $this->assertFalse(KhipuRefundRules::isFullyRefunded('partially-refunded'));
        $this->assertFalse(KhipuRefundRules::isFullyRefunded('normal'));
        $this->assertFalse(KhipuRefundRules::isFullyRefunded(''));
        $this->assertFalse(KhipuRefundRules::isFullyRefunded(null));
    }

    public function testNoEsReembolsableNoEsFlagOff()
    {
        // "no es reembolsable" es AMBIGUO y NO debe leerse como flag apagado.
        $errors = array(array(
            'field' => 'payment_id',
            'message' => 'El pago con ID abc no es reembolsable',
        ));
        $this->assertFalse(KhipuRefundRules::isWalletDisabled($errors));
    }

    // --- isNotRefundable: "no es reembolsable" ------------------------------

    public function testNotRefundableSeDetectaEnPaymentId()
    {
        $errors = array(array(
            'field' => 'payment_id',
            'message' => 'El pago con ID abc no es reembolsable',
        ));
        $this->assertTrue(KhipuRefundRules::isNotRefundable($errors));
    }

    public function testFlagOffNoSeConfundeConNotRefundable()
    {
        // El mensaje de flag-off no debe leerse como "no es reembolsable".
        $errors = array(array(
            'field' => 'receiver_id',
            'message' => 'La cuenta de cobro con ID 1234 no está habilitada para hacer reversas.',
        ));
        $this->assertFalse(KhipuRefundRules::isNotRefundable($errors));
    }

    // --- errorText ---------------------------------------------------------

    public function testErrorTextUneLosMensajes()
    {
        $errors = array(
            array('field' => 'payment_id', 'message' => 'uno'),
            array('field' => 'amount', 'message' => 'dos'),
        );
        $this->assertSame('uno dos', KhipuRefundRules::errorText($errors, 400));
    }

    public function testErrorTextCaeAlCodigoHttpSiNoHayMensajes()
    {
        $this->assertSame('Error HTTP 502', KhipuRefundRules::errorText(array(), 502));
    }

    // --- classifyOutcome: ok / rejected / unknown ---------------------------

    public function test200ConCuerpoValidoEsOk()
    {
        $this->assertSame(
            KhipuRefundRules::OUTCOME_OK,
            KhipuRefundRules::classifyOutcome(200, '', true)
        );
    }

    public function test200ConCuerpoInvalidoEsDesconocido()
    {
        // Khipu pudo haber procesado la reversa y perdimos la respuesta: no se
        // puede afirmar que se hizo, pero tampoco que falló.
        $this->assertSame(
            KhipuRefundRules::OUTCOME_UNKNOWN,
            KhipuRefundRules::classifyOutcome(200, '', false)
        );
    }

    public function test400EsRechazado()
    {
        $this->assertSame(
            KhipuRefundRules::OUTCOME_REJECTED,
            KhipuRefundRules::classifyOutcome(400, '', true)
        );
    }

    public function test422EsRechazado()
    {
        $this->assertSame(
            KhipuRefundRules::OUTCOME_REJECTED,
            KhipuRefundRules::classifyOutcome(422, '', true)
        );
    }

    public function test500EsDesconocido()
    {
        // En QA, Khipu devolvió 500 habiendo procesado la reversa. Afirmar
        // "falló" acá es falso.
        $this->assertSame(
            KhipuRefundRules::OUTCOME_UNKNOWN,
            KhipuRefundRules::classifyOutcome(500, '', true)
        );
    }

    public function test503EsDesconocido()
    {
        $this->assertSame(
            KhipuRefundRules::OUTCOME_UNKNOWN,
            KhipuRefundRules::classifyOutcome(503, '', true)
        );
    }

    public function testStatusCeroConErrorDeTransporteEsDesconocido()
    {
        $this->assertSame(
            KhipuRefundRules::OUTCOME_UNKNOWN,
            KhipuRefundRules::classifyOutcome(0, 'Connection timed out', false)
        );
    }

    public function testErrorDeTransporteMandaADesconocidoAunqueElCodigoSea200()
    {
        // Si hubo error de transporte, el código HTTP que haya quedado no es
        // confiable: el desenlace es desconocido de todas formas.
        $this->assertSame(
            KhipuRefundRules::OUTCOME_UNKNOWN,
            KhipuRefundRules::classifyOutcome(200, 'Connection timed out', true)
        );
    }

    // --- refundStateFrom: ¿tiene el pago alguna reversa? --------------------

    public function testStatusDetailNormalEsSinReversas()
    {
        $this->assertSame(KhipuRefundRules::REFUNDS_NONE, KhipuRefundRules::refundStateFrom('normal'));
    }

    public function testStatusDetailPartiallyRefundedEsAlgunaReversa()
    {
        $this->assertSame(KhipuRefundRules::REFUNDS_SOME, KhipuRefundRules::refundStateFrom('partially-refunded'));
    }

    public function testStatusDetailFullyRefundedEsAlgunaReversa()
    {
        $this->assertSame(KhipuRefundRules::REFUNDS_SOME, KhipuRefundRules::refundStateFrom('fully-refunded'));
    }

    public function testStatusDetailVacioEsAlgunaReversa()
    {
        // Convención heredada: versiones anteriores de la API devolvían ''
        // cuando el pago tenía al menos una reversa.
        $this->assertSame(KhipuRefundRules::REFUNDS_SOME, KhipuRefundRules::refundStateFrom(''));
    }

    public function testStatusDetailReversedEsAlgunaReversa()
    {
        $this->assertSame(KhipuRefundRules::REFUNDS_SOME, KhipuRefundRules::refundStateFrom('reversed'));
    }

    public function testStatusDetailNuloEsDesconocido()
    {
        $this->assertSame(KhipuRefundRules::REFUNDS_UNKNOWN, KhipuRefundRules::refundStateFrom(null));
    }

    public function testStatusDetailDesconocidoEsDesconocido()
    {
        $this->assertSame(KhipuRefundRules::REFUNDS_UNKNOWN, KhipuRefundRules::refundStateFrom('un-valor-inventado'));
    }

    public function testStatusDetailConEspaciosNoRompeLaClasificacion()
    {
        $this->assertSame(KhipuRefundRules::REFUNDS_NONE, KhipuRefundRules::refundStateFrom('  normal  '));
        $this->assertSame(KhipuRefundRules::REFUNDS_SOME, KhipuRefundRules::refundStateFrom('  partially-refunded  '));
    }

    // --- missingFields -------------------------------------------------------

    public function testMissingFieldsListaLoQueFalta()
    {
        $missing = KhipuRefundRules::missingFields(
            array('id' => 'u1', 'payment_id' => 'p1'),
            KhipuRefundRules::REQUIRED_REFUND_FIELDS
        );

        $this->assertSame(array('refunded_amount', 'total_refunded', 'remaining'), $missing);
    }

    public function testMissingFieldsAceptaUnCuerpoCompleto()
    {
        $complete = array(
            'id' => 'u1', 'payment_id' => 'p1', 'refunded_amount' => '50',
            'total_refunded' => '50', 'remaining' => '150',
        );

        $this->assertSame(array(), KhipuRefundRules::missingFields($complete, KhipuRefundRules::REQUIRED_REFUND_FIELDS));
    }

    /**
     * `null` no es un monto: dejarlo pasar reintroduce el cero por defecto que
     * esta validación existe para impedir.
     */
    public function testUnCampoNuloCuentaComoAusente()
    {
        $missing = KhipuRefundRules::missingFields(
            array('balance' => null),
            KhipuRefundRules::REQUIRED_BALANCE_FIELDS
        );

        $this->assertSame(array('balance'), $missing);
    }

    public function testUnCuerpoQueNoEsArrayLosDaTodosPorFaltantes()
    {
        $this->assertSame(
            KhipuRefundRules::REQUIRED_PAYMENT_FIELDS,
            KhipuRefundRules::missingFields('no soy json', KhipuRefundRules::REQUIRED_PAYMENT_FIELDS)
        );
    }
}
