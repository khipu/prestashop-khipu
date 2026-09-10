<?php
/**
 * Números de versión del módulo, en un solo sitio.
 *
 * Existe porque la versión la necesitan dos mundos que no se pueden ver:
 * `KhipuPayment`, que sólo carga dentro de PrestaShop, y `KhipuRefundService`,
 * que es código puro y se prueba sin levantar la plataforma. Antes cada uno
 * tenía su copia y el User-Agent de las reversas se quedaba atrás en cada
 * subida de versión, reportándole a Khipu una versión que no era.
 *
 * Sin dependencias a propósito: cualquiera de los dos lados puede cargarla.
 *
 * Al subir de versión hay que tocar, además de PLUGIN: `config.xml`,
 * `config_es.xml` y el nombre del archivo en `upgrade/`, que es lo que
 * PrestaShop compara para decidir si corre la actualización.
 */
class KhipuVersion
{
    /** Versión del módulo. Debe coincidir con config.xml y config_es.xml. */
    const PLUGIN = '4.4.0';

    /** Versión de la API de Khipu contra la que habla. */
    const API = '3.0';

    /** Lo que se manda en la cabecera User-Agent de cada llamada. */
    const USER_AGENT = 'khipu-api-php-client/' . self::API . '|prestashop-khipu/' . self::PLUGIN;
}
