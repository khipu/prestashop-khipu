<?php
/**
 * Una fila por reversa solicitada con éxito a Khipu.
 *
 * Es un LOG, no solo un snapshot: la API de Khipu no ofrece ningún endpoint
 * para consultar o listar reversas, así que esta tabla es el único registro
 * que existe de la plata devuelta. Por eso uninstall() NO la borra.
 */
class KhipuRefund extends ObjectModel
{
    public $id_order;
    public $id_shop;
    public $khipu_refund_id;
    public $payment_id;
    public $type;
    public $refunded_amount;
    public $total_refunded;
    public $remaining;
    public $currency;
    public $message;
    public $id_employee;
    public $date_add;

    public static $definition = array(
        'table' => 'khipu_refund',
        'primary' => 'id_khipu_refund',
        'fields' => array(
            'id_order' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'id_shop' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'khipu_refund_id' => array('type' => self::TYPE_STRING, 'size' => 64),
            'payment_id' => array('type' => self::TYPE_STRING, 'size' => 64),
            'type' => array('type' => self::TYPE_STRING, 'size' => 16),
            'refunded_amount' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'total_refunded' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'remaining' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'currency' => array('type' => self::TYPE_STRING, 'size' => 3),
            'message' => array('type' => self::TYPE_HTML),
            'id_employee' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    );

    /**
     * Crea la tabla. Idempotente: se llama desde install() y desde el upgrade.
     *
     * @return bool
     */
    public static function installTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'khipu_refund` (
            `id_khipu_refund` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `id_order` int(10) unsigned NOT NULL,
            `id_shop` int(10) unsigned NOT NULL DEFAULT 1,
            `khipu_refund_id` varchar(64) NOT NULL DEFAULT "",
            `payment_id` varchar(64) NOT NULL DEFAULT "",
            `type` varchar(16) NOT NULL DEFAULT "",
            `refunded_amount` decimal(20,6) NOT NULL DEFAULT 0,
            `total_refunded` decimal(20,6) NOT NULL DEFAULT 0,
            `remaining` decimal(20,6) NOT NULL DEFAULT 0,
            `currency` varchar(3) NOT NULL DEFAULT "",
            `message` text,
            `id_employee` int(10) unsigned NOT NULL DEFAULT 0,
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_khipu_refund`),
            KEY `id_order` (`id_order`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        return Db::getInstance()->execute($sql);
    }

    /**
     * La reversa más reciente de un pedido, o false si no hay ninguna.
     *
     * @param int $idOrder
     *
     * @return array|false
     */
    public static function getLatestForOrder($idOrder)
    {
        return Db::getInstance()->getRow('
            SELECT * FROM `' . _DB_PREFIX_ . 'khipu_refund`
            WHERE `id_order` = ' . (int) $idOrder . '
            ORDER BY `id_khipu_refund` DESC');
    }

    /**
     * Historial completo, de la más reciente a la más antigua.
     *
     * @param int $idOrder
     *
     * @return array
     */
    public static function getHistoryForOrder($idOrder)
    {
        $rows = Db::getInstance()->executeS('
            SELECT * FROM `' . _DB_PREFIX_ . 'khipu_refund`
            WHERE `id_order` = ' . (int) $idOrder . '
            ORDER BY `id_khipu_refund` DESC');

        return $rows ? $rows : array();
    }

    /**
     * La fila de order_payment que lleva el payment_id de Khipu.
     *
     * Se busca la que TIENE transaction_id, no la primera de la colección, y
     * solo entre las de Khipu (ver khipuPaymentMethodNames()). Si el comercio
     * anotó a mano un pago de otro medio con su propio identificador, esa fila
     * no es la de Khipu: reversar con su identificador y su monto le mandaba a
     * Khipu un pago que no es suyo. Sin fila de Khipu no hay panel de reversa.
     *
     * @return array|false con claves transaction_id y amount
     */
    public static function getPaymentRowForOrder(Order $order)
    {
        $names = array();
        foreach (self::khipuPaymentMethodNames($order) as $name) {
            $names[] = '"' . pSQL($name) . '"';
        }

        return Db::getInstance()->getRow('
            SELECT `transaction_id`, `amount`
            FROM `' . _DB_PREFIX_ . 'order_payment`
            WHERE `order_reference` = "' . pSQL($order->reference) . '"
              AND `transaction_id` IS NOT NULL
              AND `transaction_id` != ""
              AND LOWER(TRIM(`payment_method`)) IN (' . implode(', ', $names) . ')
            ORDER BY `id_order_payment` ASC');
    }

    /**
     * Si un medio de pago de order_payment es el de Khipu.
     *
     * @param string $method
     *
     * @return bool
     */
    public static function isKhipuPaymentMethod($method, Order $order)
    {
        return in_array(Tools::strtolower(trim((string) $method)), self::khipuPaymentMethodNames($order), true);
    }

    /**
     * Nombres con que puede venir el medio de pago de Khipu en una fila, en
     * minúsculas.
     *
     * El del pedido (lo copia validateOrder() del displayName del módulo) y
     * «khipu», que es el displayName —en mayúsculas o no— de todas las
     * versiones, y el que usa PrestaShop en la fila que crea al pasar a
     * pagado. Va en minúsculas porque así compara MySQL esa columna. No se
     * instancia el módulo para leer su displayName: esto corre en el webhook,
     * que es público, y construirlo cuesta varias consultas.
     *
     * @return array
     */
    public static function khipuPaymentMethodNames(Order $order)
    {
        $names = array('khipu', Tools::strtolower(trim((string) $order->payment)));

        return array_values(array_unique(array_filter($names, 'strlen')));
    }

    /**
     * Saldo reversable del pedido.
     *
     * Se toma el MÍNIMO `remaining` de las reversas del pedido, no el de la más
     * reciente: con dos reversas concurrentes, la fila insertada última puede
     * traer un `remaining` mayor (la petición que respondió primero se insertó
     * después) e inflaría el saldo local.
     *
     * @param int          $idOrder
     * @param string|float $paidAmount monto del pago registrado
     *
     * @return float
     */
    public static function getRemainingForOrder($idOrder, $paidAmount)
    {
        $min = Db::getInstance()->getValue('
            SELECT MIN(`remaining`) FROM `' . _DB_PREFIX_ . 'khipu_refund`
            WHERE `id_order` = ' . (int) $idOrder);

        return KhipuRefundRules::remainingFrom((null === $min || false === $min) ? null : $min, $paidAmount);
    }
}
