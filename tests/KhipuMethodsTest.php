<?php

use PHPUnit\Framework\TestCase;

class KhipuMethodsTest extends TestCase
{
    // --- label / composeTitle ----------------------------------------------

    public function testEtiquetaDeCadaMedioConocido()
    {
        $this->assertSame('Transferencia normal', KhipuMethods::label('REGULAR_TRANSFER'));
        $this->assertSame('Transferencia simplificada', KhipuMethods::label('SIMPLIFIED_TRANSFER'));
        $this->assertSame('LigoPay', KhipuMethods::label('LIGOPAY'));
        $this->assertSame('Pago por QR', KhipuMethods::label('NAVE'));
    }

    public function testEtiquetaDeUnIdDesconocidoEsElIdLegible()
    {
        $this->assertSame('X', KhipuMethods::label('X'));
        $this->assertSame('Algun medio nuevo', KhipuMethods::label('ALGUN_MEDIO_NUEVO'));
    }

    public function testTituloCompuestoUsaTituloYEtiqueta()
    {
        $this->assertSame('Khipu - LigoPay', KhipuMethods::composeTitle('Khipu', 'LIGOPAY'));
        $this->assertSame('Mi tienda - LigoPay', KhipuMethods::composeTitle('Mi tienda', 'LIGOPAY'));
        $this->assertSame('Khipu - Pago por QR', KhipuMethods::composeTitle('Khipu', 'NAVE'));
    }

    public function testTituloEnBlancoUsaKhipu()
    {
        $this->assertSame('Khipu - Transferencia normal', KhipuMethods::composeTitle('  ', 'REGULAR_TRANSFER'));
        $this->assertSame('Khipu - Transferencia normal', KhipuMethods::composeTitle('', 'REGULAR_TRANSFER'));
    }

    // --- accountMethodIds --------------------------------------------------

    public function testIdsDeLaCuentaSonUnicosEnOrdenYIncluyenLosDesconocidos()
    {
        $methods = array(
            array('id' => 'LIGOPAY'),
            array('id' => 'REGULAR_TRANSFER'),
            array('id' => 'LIGOPAY'),
            array('id' => 'OTRO'),
        );

        $this->assertSame(array('LIGOPAY', 'OTRO', 'REGULAR_TRANSFER'), KhipuMethods::accountMethodIds($methods));
    }

    public function testDirectDebitSeOmiteDeLosIdsDeLaCuenta()
    {
        // Respuesta real de una cuenta con débito directo activado.
        $methods = array(
            array('id' => 'REGULAR_TRANSFER', 'name' => 'REGULAR_TRANSFER', 'logo_url' => 'https://s3.amazonaws.com/x.png'),
            array('id' => 'DIRECT_DEBIT', 'name' => 'DIRECT_DEBIT', 'logo_url' => ''),
            array('id' => 'SIMPLIFIED_TRANSFER', 'name' => 'SIMPLIFIED_TRANSFER', 'logo_url' => 'https://s3.amazonaws.com/y.png'),
        );

        $this->assertSame(array('SIMPLIFIED_TRANSFER', 'REGULAR_TRANSFER'), KhipuMethods::accountMethodIds($methods));
        $this->assertSame(array('SIMPLIFIED_TRANSFER', 'REGULAR_TRANSFER'), KhipuMethods::visibleMethodIds($methods, null));
    }

    public function testElementosSinIdOMalFormadosSeIgnoran()
    {
        $methods = array('x', array('nombre' => 'sin id'), array('id' => ''), array('id' => array('a')), array('id' => 'LIGOPAY'));

        $this->assertSame(array('LIGOPAY'), KhipuMethods::accountMethodIds($methods));
    }

    public function testIdsDeLaCuentaConNullOVacioSonVacios()
    {
        $this->assertSame(array(), KhipuMethods::accountMethodIds(null));
        $this->assertSame(array(), KhipuMethods::accountMethodIds(array()));
    }

    // --- isSelected --------------------------------------------------------

    public function testSinSeleccionGuardadaSeOfreceTodo()
    {
        $this->assertTrue(KhipuMethods::isSelected('LIGOPAY', null));
        $this->assertTrue(KhipuMethods::isSelected('LIGOPAY', ''));
        $this->assertTrue(KhipuMethods::isSelected('LIGOPAY', 'basura'));
    }

    public function testSeleccionExplicitaEsPertenencia()
    {
        $this->assertFalse(KhipuMethods::isSelected('LIGOPAY', '[]'));
        $this->assertFalse(KhipuMethods::isSelected('LIGOPAY', '["REGULAR_TRANSFER"]'));
        $this->assertTrue(KhipuMethods::isSelected('LIGOPAY', '["LIGOPAY"]'));
    }

    // --- normalizeSelection ------------------------------------------------

    public function testNormalizarDejaSoloIdsDeLaCuentaSinDuplicados()
    {
        $this->assertSame(
            array('LIGOPAY'),
            KhipuMethods::normalizeSelection(array('LIGOPAY', 'HACK', 'LIGOPAY'), array('LIGOPAY', 'REGULAR_TRANSFER'))
        );
    }

    public function testNormalizarUnValorQueNoEsListaDaVacio()
    {
        $this->assertSame(array(), KhipuMethods::normalizeSelection('x', array('LIGOPAY')));
        $this->assertSame(array(), KhipuMethods::normalizeSelection(null, array('LIGOPAY')));
    }

    // --- embeddedAvailable -------------------------------------------------

    public function testEmbebidoDisponibleSoloConSimplificada()
    {
        $this->assertTrue(KhipuMethods::embeddedAvailable(array(array('id' => 'SIMPLIFIED_TRANSFER'))));
        $this->assertFalse(KhipuMethods::embeddedAvailable(array(array('id' => 'LIGOPAY'))));
        $this->assertFalse(KhipuMethods::embeddedAvailable(array(array('id' => 'NAVE'))));
        $this->assertFalse(KhipuMethods::embeddedAvailable(null));
    }

    // --- orden de visualización ---------------------------------------------

    private function ids(array $ids)
    {
        return array_map(function ($id) {
            return array('id' => $id);
        }, $ids);
    }

    public function testConSoloSimplificadaYNormalLaSimplificadaVaPrimero()
    {
        $this->assertSame(
            array('SIMPLIFIED_TRANSFER', 'REGULAR_TRANSFER'),
            KhipuMethods::accountMethodIds($this->ids(array('REGULAR_TRANSFER', 'SIMPLIFIED_TRANSFER')))
        );
        $this->assertSame(
            array('SIMPLIFIED_TRANSFER', 'REGULAR_TRANSFER'),
            KhipuMethods::accountMethodIds($this->ids(array('SIMPLIFIED_TRANSFER', 'REGULAR_TRANSFER')))
        );
    }

    public function testLosDemasMediosQuedanEnMedioEnElOrdenDeKhipu()
    {
        // "x simplificada manual" -> "simplificada x manual"
        $this->assertSame(
            array('SIMPLIFIED_TRANSFER', 'LIGOPAY', 'REGULAR_TRANSFER'),
            KhipuMethods::accountMethodIds($this->ids(array('LIGOPAY', 'SIMPLIFIED_TRANSFER', 'REGULAR_TRANSFER')))
        );
        // La normal primero y la simplificada última en la respuesta: se invierten sin tocar el medio del centro.
        $this->assertSame(
            array('SIMPLIFIED_TRANSFER', 'LIGOPAY', 'REGULAR_TRANSFER'),
            KhipuMethods::accountMethodIds($this->ids(array('REGULAR_TRANSFER', 'LIGOPAY', 'SIMPLIFIED_TRANSFER')))
        );
        // Varios en el centro conservan su orden relativo.
        $this->assertSame(
            array('SIMPLIFIED_TRANSFER', 'B', 'LIGOPAY', 'A', 'REGULAR_TRANSFER'),
            KhipuMethods::accountMethodIds($this->ids(array('B', 'REGULAR_TRANSFER', 'LIGOPAY', 'SIMPLIFIED_TRANSFER', 'A')))
        );
    }

    public function testLasDosReglasSonIndependientes()
    {
        // Sin simplificada: la normal igual va al final.
        $this->assertSame(
            array('LIGOPAY', 'REGULAR_TRANSFER'),
            KhipuMethods::accountMethodIds($this->ids(array('REGULAR_TRANSFER', 'LIGOPAY')))
        );
        // Sin normal: la simplificada igual va primero.
        $this->assertSame(
            array('SIMPLIFIED_TRANSFER', 'LIGOPAY'),
            KhipuMethods::accountMethodIds($this->ids(array('LIGOPAY', 'SIMPLIFIED_TRANSFER')))
        );
        // Un solo medio.
        $this->assertSame(array('REGULAR_TRANSFER'), KhipuMethods::accountMethodIds($this->ids(array('REGULAR_TRANSFER'))));
    }

    public function testElOrdenTambienSeAplicaALasFilasDelCheckout()
    {
        $methods = $this->ids(array('REGULAR_TRANSFER', 'LIGOPAY', 'SIMPLIFIED_TRANSFER', 'DIRECT_DEBIT'));

        $this->assertSame(
            array('SIMPLIFIED_TRANSFER', 'LIGOPAY', 'REGULAR_TRANSFER'),
            KhipuMethods::visibleMethodIds($methods, null)
        );
        // Con una selección guardada el orden es el mismo, solo con los marcados.
        $this->assertSame(
            array('SIMPLIFIED_TRANSFER', 'REGULAR_TRANSFER'),
            KhipuMethods::visibleMethodIds($methods, '["REGULAR_TRANSFER","SIMPLIFIED_TRANSFER"]')
        );
    }

    // --- visibleMethodIds --------------------------------------------------

    public function testUnMedioSinControladorMarcadoNoGeneraFila()
    {
        $methods = array(array('id' => 'REGULAR_TRANSFER'), array('id' => 'ALGUN_MEDIO_NUEVO'));

        $this->assertSame(array('ALGUN_MEDIO_NUEVO', 'REGULAR_TRANSFER'), KhipuMethods::accountMethodIds($methods));
        $this->assertSame(array('REGULAR_TRANSFER'), KhipuMethods::visibleMethodIds($methods, null));
        $this->assertSame(array('REGULAR_TRANSFER'), KhipuMethods::visibleMethodIds($methods, '["REGULAR_TRANSFER","ALGUN_MEDIO_NUEVO"]'));
    }


    public function testVisiblesSonLosDeLaCuentaQueEstanSeleccionados()
    {
        $methods = array(array('id' => 'REGULAR_TRANSFER'), array('id' => 'LIGOPAY'));

        $this->assertSame(array('LIGOPAY'), KhipuMethods::visibleMethodIds($methods, '["LIGOPAY"]'));
        $this->assertSame(array('LIGOPAY', 'REGULAR_TRANSFER'), KhipuMethods::visibleMethodIds($methods, null));
        $this->assertSame(array(), KhipuMethods::visibleMethodIds($methods, '[]'));
    }

    public function testElQrDeArgentinaTieneFilaJuntoALaSimplificada()
    {
        $methods = array(array('id' => 'NAVE'), array('id' => 'SIMPLIFIED_TRANSFER'));

        $this->assertSame(array('SIMPLIFIED_TRANSFER', 'NAVE'), KhipuMethods::visibleMethodIds($methods, null));
        $this->assertSame(array('NAVE'), KhipuMethods::visibleMethodIds($methods, '["NAVE"]'));
    }

    // --- credentialsChanged ------------------------------------------------

    public function testCredencialesIgualesNoCuentanComoCambio()
    {
        $this->assertFalse(KhipuMethods::credentialsChanged('k', 'm', 'k', 'm'));
        $this->assertFalse(KhipuMethods::credentialsChanged('k', 'm', ' k ', 'm '));
        $this->assertFalse(KhipuMethods::credentialsChanged(false, false, '', ''));
    }

    public function testCambiarApiKeyOCobradorEsCambio()
    {
        $this->assertTrue(KhipuMethods::credentialsChanged('k', 'm', 'k2', 'm'));
        $this->assertTrue(KhipuMethods::credentialsChanged('k', 'm', 'k', 'm2'));
        $this->assertTrue(KhipuMethods::credentialsChanged(false, false, 'k', 'm'));
    }

    public function testUnCampoAusenteNoCuentaComoCambio()
    {
        $this->assertFalse(KhipuMethods::credentialsChanged('k', 'm', null, null));
        $this->assertFalse(KhipuMethods::credentialsChanged('k', 'm', 'k', null));
        $this->assertTrue(KhipuMethods::credentialsChanged('k', 'm', null, 'm2'));
    }
}
