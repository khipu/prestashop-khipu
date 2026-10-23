<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @author    khipu <support@khipu.com>
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Lógica pura del pago embebido (Khipu Web): qué id se le pasa a KWS y cuándo
 * se puede borrar el pago y cancelar el pedido si el comprador lo abandona.
 *
 * No depende de PrestaShop ni hace red, para poder probarla con PHPUnit.
 */
class KhipuEmbeddedRules
{
    /**
     * Id del pago que KWS necesita para abrir el modal.
     *
     * @return string|null null si la respuesta no trae uno utilizable
     */
    public static function paymentIdFrom(array $data)
    {
        return (isset($data['payment_id']) && is_string($data['payment_id']) && '' !== $data['payment_id'])
            ? $data['payment_id']
            : null;
    }

    /**
     * Si la URL del medio se puede usar de respaldo en el embebido.
     *
     * Termina en un href y en window.location: el escape de Smarty no impide
     * un `javascript:`, así que solo se acepta https con host.
     *
     * @param mixed $url
     *
     * @return bool
     */
    public static function isHttpsUrl($url)
    {
        if (!is_string($url) || '' === $url) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && 'https' === strtolower($parts['scheme'])
            && '' !== $parts['host'];
    }

    /**
     * Si se puede borrar el pago en Khipu y cancelar el pedido.
     *
     * El payment_id llega desde el navegador, así que no basta con que el
     * pedido sea del visitante: el pago tiene que ser de ESE pedido, o con la
     * referencia propia se podría borrar el pago de otro comprador. Y si Khipu
     * ya lo está verificando o lo dio por pagado, no se toca: lo decide la
     * notificación.
     *
     * @param string     $orderReference referencia del pedido de PrestaShop
     * @param bool       $orderIsOpen    si el pedido sigue en espera de pago
     * @param array|null $payment        respuesta de GET /v3/payments/{id}
     *
     * @return bool
     */
    public static function canCancel($orderReference, $orderIsOpen, $payment)
    {
        if (!$orderIsOpen || !is_string($orderReference) || '' === $orderReference || !is_array($payment)) {
            return false;
        }

        if (!isset($payment['transaction_id']) || !is_string($payment['transaction_id'])
            || $payment['transaction_id'] !== $orderReference) {
            return false;
        }

        // Sin estado en la respuesta se deja decidir al DELETE, que Khipu
        // rechaza para un pago ya pagado.
        return !isset($payment['status']) || 'pending' === $payment['status'];
    }

    /**
     * Si tras el DELETE el pago dejó de ser pagable.
     *
     * Un 404 cuenta: el pago ya no existe. Cualquier otra cosa (rechazo, sin
     * respuesta) deja el pedido abierto: cancelarlo con el pago aún pagable
     * permitiría pagar un pedido cancelado. Si nadie paga, cancelExpiredOrders
     * lo cancela al vencer.
     *
     * @param int $status código HTTP de deletePayment(); 0 si no hubo respuesta
     *
     * @return bool
     */
    public static function deleteSucceeded($status)
    {
        $status = (int) $status;

        return ($status >= 200 && $status < 300) || 404 === $status;
    }
}
