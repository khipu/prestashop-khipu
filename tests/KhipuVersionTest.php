<?php

use PHPUnit\Framework\TestCase;

/**
 * La versión vive en cuatro sitios que PrestaShop y Khipu leen por separado.
 * Que se desincronicen no rompe ningún test funcional: simplemente el módulo
 * le miente a Khipu sobre su versión, o —peor— PrestaShop no ejecuta la
 * actualización y la tienda queda con el código nuevo y la base vieja.
 *
 * Estos tests son la única red que hay contra eso.
 */
class KhipuVersionTest extends TestCase
{
    /** @return string */
    private function raiz()
    {
        return dirname(__FILE__) . '/..';
    }

    /**
     * @param string $archivo
     *
     * @return string
     */
    private function versionDeXml($archivo)
    {
        $xml = simplexml_load_file($this->raiz() . '/' . $archivo);

        return (string) $xml->version;
    }

    public function testConfigXmlDeclaraLaMismaVersion()
    {
        $this->assertSame(KhipuVersion::PLUGIN, $this->versionDeXml('config.xml'));
    }

    public function testConfigEsXmlDeclaraLaMismaVersion()
    {
        $this->assertSame(KhipuVersion::PLUGIN, $this->versionDeXml('config_es.xml'));
    }

    /**
     * PrestaShop decide si actualiza comparando la versión instalada con la de
     * config.xml, y ejecuta `upgrade/upgrade-<version>.php`. Sin ese archivo la
     * tienda sube de versión sin crear la tabla, el tab ni los hooks: el módulo
     * queda actualizado y la feature muerta, sin ningún error visible.
     */
    public function testExisteElArchivoDeUpgradeDeEstaVersion()
    {
        $esperado = $this->raiz() . '/upgrade/upgrade-' . KhipuVersion::PLUGIN . '.php';

        $this->assertFileExists(
            $esperado,
            'Falta upgrade-' . KhipuVersion::PLUGIN . '.php: las tiendas que actualicen no crearán la tabla ni el tab.'
        );
    }

    public function testLaFuncionDeUpgradeSeLlamaComoLaVersion()
    {
        $archivo = $this->raiz() . '/upgrade/upgrade-' . KhipuVersion::PLUGIN . '.php';
        if (!file_exists($archivo)) {
            // Ya lo denuncia el test de arriba; leerlo aquí sólo añadiría un
            // warning de PHP encima del fallo real.
            $this->markTestSkipped('No existe upgrade-' . KhipuVersion::PLUGIN . '.php');
        }

        $fuente = file_get_contents($archivo);
        $esperada = 'upgrade_module_' . str_replace('.', '_', KhipuVersion::PLUGIN);

        // PrestaShop invoca la función por nombre derivado de la versión: si no
        // coincide, incluye el archivo y no llama a nada.
        $this->assertStringContainsString('function ' . $esperada . '(', $fuente);
    }

    public function testElUserAgentSeArmaConLasDosVersiones()
    {
        $this->assertSame(
            'khipu-api-php-client/' . KhipuVersion::API . '|prestashop-khipu/' . KhipuVersion::PLUGIN,
            KhipuVersion::USER_AGENT
        );
    }

    /** El literal duplicado que motivó esta clase no debe volver. */
    public function testElServicioNoTieneSuPropiaCopiaDelUserAgent()
    {
        $this->assertSame(KhipuVersion::USER_AGENT, KhipuRefundService::USER_AGENT);
    }
}
