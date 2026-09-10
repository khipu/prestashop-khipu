<?php
/**
 * Orquesta la reversa: valida, llama a Khipu, interpreta, persiste, refleja,
 * deja nota y redirige a la ficha del pedido con el aviso.
 *
 * Vive separado del hook de render a propósito (regla 6 del spec): mañana un
 * webhook podría disparar la misma lógica sin reescribirla.
 */

// Las clases del módulo NO se autoloadean. `Module::getInstanceByName()` que
// hace ModuleAdminController las cargaría de rebote al incluir khipupayment.php,
// pero depender de ese efecto colateral es frágil: se piden explícitamente.
require_once _PS_MODULE_DIR_ . 'khipupayment/classes/KhipuRefundRules.php';
require_once _PS_MODULE_DIR_ . 'khipupayment/classes/KhipuRefundService.php';
require_once _PS_MODULE_DIR_ . 'khipupayment/classes/KhipuRefund.php';

class AdminKhipuRefundController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    /**
     * Este controlador solo existe para recibir el POST del panel. Si alguien
     * llega por GET, vuelve al listado de pedidos en vez de mostrar una
     * pantalla vacía.
     */
    public function initContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders'));
    }

    public function postProcess()
    {
        if (!Tools::isSubmit('submitKhipuRefund')) {
            return parent::postProcess();
        }

        $idOrder = (int) Tools::getValue('id_order');
        $order = new Order($idOrder);

        if (!Validate::isLoadedObject($order)) {
            $this->errors[] = $this->l('Pedido no encontrado.');

            return false;
        }

        // Reversar es una operación sobre un pedido: se gatea con el mismo
        // permiso que los botones de reembolso nativos de PrestaShop.
        if (!Access::isGranted('ROLE_MOD_TAB_ADMINORDERS_UPDATE', $this->context->employee->id_profile)) {
            $this->redirectToOrder($idOrder, 'error', $this->l('No tienes permiso para reversar pedidos.'));

            return false;
        }

        // Nonce de un solo uso: bloquea el reenvío del formulario (botón atrás,
        // un F5, o un segundo submit si el JS no deshabilitó el botón) y también
        // el doble clic que llega como dos peticiones a la vez.
        $sentNonce = (string) Tools::getValue('khipu_nonce');

        if ('' === $sentNonce || !$this->claimNonce($sentNonce)) {
            $this->redirectToOrder($idOrder, 'error', $this->l('Este formulario de reversa ya se envió o expiró. Vuelve a abrir la ficha del pedido y reinténtalo.'));

            return false;
        }

        $paymentRow = KhipuRefund::getPaymentRowForOrder($order);
        if (!$paymentRow) {
            $this->redirectToOrder($idOrder, 'error', $this->l('Este pedido no tiene un pago de Khipu asociado.'));

            return false;
        }

        $rawType = Tools::getValue('refund_type');
        if (!is_string($rawType)
            || !in_array($rawType, array(KhipuRefundRules::TYPE_FULL, KhipuRefundRules::TYPE_PARTIAL), true)) {
            $this->redirectToOrder($idOrder, 'error', $this->l('Tipo de reversa inválido.'));

            return false;
        }
        $type = $rawType;
        $amount = Tools::getValue('amount');

        $remaining = KhipuRefund::getRemainingForOrder($idOrder, $paymentRow['amount']);

        $validation = KhipuRefundRules::validateAmount($type, $amount, $remaining);
        if (true !== $validation) {
            $this->redirectToOrder($idOrder, 'error', $validation);

            return false;
        }

        // El payment_id se lee del servidor, NO del formulario: así nadie puede
        // reversar un pago arbitrario manipulando un campo oculto.
        $paymentId = (string) $paymentRow['transaction_id'];

        $service = new KhipuRefundService(Configuration::get('KHIPU_API_KEY'));
        $result = $service->refund($paymentId, $type, $amount);

        if (!$result['ok']) {
            $this->handleFailure($order, $paymentId, $result);

            return false;
        }

        $this->handleSuccess($order, $paymentId, $type, $result);

        return true;
    }

    /**
     * Rama de fracaso: NO se muta nada. Ni fila, ni vale, ni estado, ni snapshot.
     *
     * Khipu solo confirma "no pasó nada" con un 4xx (outcome `rejected`). Un
     * 5xx, un timeout o un 200 con cuerpo ilegible dejan el resultado
     * `unknown`: en el QA se observó a Khipu devolver 500 habiendo procesado
     * la reversa, así que afirmar "falló" ahí sería falso y empujaría al
     * admin a reintentar y reversar dos veces. Por eso cada desenlace tiene su
     * propio tono, aunque ninguno mute nada.
     */
    private function handleFailure(Order $order, $paymentId, array $result)
    {
        if (KhipuRefundRules::OUTCOME_UNKNOWN === $result['outcome']) {
            $this->handleUnknownOutcome($order, $paymentId, $result);

            return;
        }

        // "no es reembolsable" es ambiguo (agotado, reversado por fuera, o
        // fuera de la ventana de 180 días) y algunas de esas causas son
        // recuperables.
        $raw = $result['message'];

        try {
            $this->addPrivateNote(
                $order,
                sprintf($this->l('Reversa Khipu RECHAZADA (HTTP %1$s): %2$s'), (int) $result['http'], $raw),
                $result['errors']
            );
        } catch (Throwable $e) {
            // La rama de fracaso no muta nada; que la nota no se pueda guardar
            // no puede impedir que el admin vea el motivo del rechazo, que es
            // la única información útil de esta rama.
            $this->logQuietly(
                'Khipu: reversa rechazada y además falló la nota del pedido '
                    . (int) $order->id . ': ' . $e->getMessage(),
                2, (int) $order->id
            );
        }

        $text = $raw;
        if (KhipuRefundRules::isNotRefundable($result['errors'])) {
            $text .= ' ' . $this->explainNotRefundable($order, $paymentId);
        } elseif (KhipuRefundRules::isWalletDisabled($result['errors'])) {
            // Único error de Khipu que se reemplaza en vez de mostrarse crudo: su
            // texto es inequívoco pero cambió de vocabulario ("reversas" en
            // agosto de 2026, "devoluciones" en septiembre), y el resto de esta
            // pantalla dice "reversas". Mostrar los dos nombres para la misma
            // cosa hace dudar al admin de si son dos cosas distintas.
            $text = $this->l('La billetera de reversas no está habilitada en tu cuenta Khipu. Solicítala a soporte@khipu.com.');
        }

        $this->redirectToOrder((int) $order->id, 'error', $text);
    }

    /**
     * Consume el nonce, o falla si ya lo consumió otro.
     *
     * Tiene que ser atómico, no un "leer y después borrar": un doble clic llega
     * como dos peticiones simultáneas que traen el mismo nonce, y las dos leen
     * lo mismo antes de que ninguna alcance a borrarlo. El DELETE condicionado
     * por valor lo resuelve en el motor — MySQL bloquea la fila, así que de dos
     * peticiones a la vez exactamente una afecta una fila y la otra cero.
     *
     * No pasa por Configuration::deleteByName() justamente porque ese camino
     * comprueba y borra en dos pasos.
     *
     * @param string $nonce el que vino en el formulario
     *
     * @return bool si esta petición se lo quedó
     */
    private function claimNonce($nonce)
    {
        $name = KhipuPayment::refundNonceName((int) $this->context->employee->id);
        $db = Db::getInstance();

        $db->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'configuration`
             WHERE `name` = \'' . pSQL($name) . '\' AND `value` = \'' . pSQL($nonce) . '\''
        );

        $claimed = (1 === (int) $db->Affected_Rows());

        if ($claimed) {
            // El DELETE crudo esquiva la caché estática de Configuration, que
            // seguiría entregando el nonce ya gastado durante el resto de esta
            // petición. Hoy nadie lo vuelve a leer —el flujo termina en un
            // redirect—, pero dejarlo desincronizado es sembrar el próximo bug.
            // deleteByName() no borra nada (la fila ya no está): sirve para que
            // Configuration se dé por enterada.
            Configuration::deleteByName($name);
        }

        return $claimed;
    }

    /**
     * Explica por qué Khipu dice que un pago "no es reembolsable".
     *
     * Ese mensaje es ambiguo: puede ser que el pago ya esté reversado por
     * completo, que lo reversaran por otro medio, o que pasaran más de 180 días
     * desde su conciliación. Desde septiembre de 2026 `GET /v3/payments/{id}`
     * distingue esos casos vía `status_detail`, así que en vez de darle al admin
     * una lista de tres causas se le puede dar la real.
     *
     * OJO CON EL VOCABULARIO: en esta feature hay DOS saldos — el reversable del
     * pedido y el de la billetera de reversas. Los textos de abajo nombran el
     * pedido explícitamente y evitan la palabra "saldo" a secas, porque el admin
     * la leería como la billetera.
     *
     * No puede lanzar: si la consulta falla, se cae a la lista de causas.
     *
     * @param string $paymentId
     *
     * @return string
     */
    private function explainNotRefundable(Order $order, $paymentId)
    {
        $fallback = $this->l('Causas posibles: este pago ya fue reversado por completo, fue reversado por otro medio, o pasaron más de 180 días desde su conciliación.');

        try {
            $service = new KhipuRefundService(Configuration::get('KHIPU_API_KEY'));
            $payment = $service->getPayment($paymentId);

            if (empty($payment['ok']) || !isset($payment['data']['status_detail'])) {
                return $fallback;
            }

            $detail = $payment['data']['status_detail'];
        } catch (Throwable $e) {
            $this->logQuietly(
                'Khipu: no se pudo consultar el pago para explicar el rechazo del pedido '
                    . (int) $order->id . ': ' . $e->getMessage(),
                2, (int) $order->id
            );

            return $fallback;
        }

        if (KhipuRefundRules::isFullyRefunded($detail)) {
            return $this->l('Khipu confirma que este pago ya fue reversado por completo: no queda nada por reversar en este pedido. Esto no se refiere al saldo de tu billetera de reversas.');
        }

        $state = KhipuRefundRules::refundStateFrom($detail);

        if (KhipuRefundRules::REFUNDS_NONE === $state) {
            return $this->l('Khipu confirma que este pago no tiene ninguna reversa, así que el rechazo no se debe a que ya esté reversado: lo más probable es que hayan pasado más de 180 días desde su conciliación, o que el pago aún no esté conciliado.');
        }

        if (KhipuRefundRules::REFUNDS_SOME === $state) {
            return $this->l('Khipu informa que este pago tiene reversas previas pero todavía no está reversado por completo, así que el rechazo se debe a otra causa: lo más probable es que hayan pasado más de 180 días desde su conciliación.');
        }

        return $fallback;
    }

    /**
     * Rama de resultado DESCONOCIDO: un 5xx, un timeout, o un 200 con cuerpo
     * ilegible. Khipu pudo haber procesado la reversa igual (así ocurrió en
     * QA: un 500 con la reversa ya reflejada en Khipu). No se puede afirmar
     * ni que se hizo ni que no, así que tampoco se muta nada acá: ni fila, ni
     * vale, ni estado. Antes se le pedía al admin "verificar en Khipu antes de
     * reintentar" sin que hubiera forma real de hacerlo. El QA encontró que sí
     * se puede, consultando el PAGO (no la reversa, que no tiene endpoint de
     * consulta): su `status_detail` dice si el pago tiene alguna reversa.
     */
    private function handleUnknownOutcome(Order $order, $paymentId, array $result)
    {
        $raw = $result['message'];

        // Registro propio del incidente, independiente de si la nota del
        // pedido se pudo guardar: un resultado ambiguo que puede significar
        // dinero movido merece quedar en el log aunque falle todo lo demás.
        $this->logQuietly(
            'Khipu: reversa de RESULTADO DESCONOCIDO (HTTP ' . (int) $result['http'] . ') en el pedido '
                . (int) $order->id . ': ' . $raw,
            3, (int) $order->id
        );

        // Esta consulta NUNCA puede romper el flujo: cualquier excepción, o un
        // status_detail que no se reconoce, cae al mismo mensaje que un
        // resultado desconocido.
        $refundState = KhipuRefundRules::REFUNDS_UNKNOWN;
        try {
            $paymentService = new KhipuRefundService(Configuration::get('KHIPU_API_KEY'));
            $paymentResult = $paymentService->getPayment($paymentId);
            if ($paymentResult['ok']) {
                $statusDetail = isset($paymentResult['data']['status_detail']) ? $paymentResult['data']['status_detail'] : null;
                $refundState = KhipuRefundRules::refundStateFrom($statusDetail);
            }
        } catch (Throwable $e) {
            $this->logQuietly(
                'Khipu: no se pudo verificar el pago ' . $paymentId . ' tras un resultado desconocido en el pedido '
                    . (int) $order->id . ': ' . $e->getMessage(),
                2, (int) $order->id
            );
        }

        if (KhipuRefundRules::REFUNDS_NONE === $refundState) {
            $verification = $this->l('Khipu confirma que este pago no tiene ninguna reversa registrada: la operación no se procesó. Puedes reintentar.');
        } elseif (KhipuRefundRules::REFUNDS_SOME === $refundState) {
            $verification = $this->l('Khipu informa que este pago ya tiene al menos una reversa: es probable que esta operación SÍ se procesara. NO reintentes sin revisar antes el monto reversado.');
        } else {
            $verification = $this->l('No se pudo confirmar si la reversa se procesó. Escribe a soporte@khipu.com indicando el ID de pago antes de reintentar.');
        }

        $noticeText = sprintf($this->l('Reversa Khipu de RESULTADO DESCONOCIDO (HTTP %1$s): %2$s'), (int) $result['http'], $raw)
            . ' ' . $verification;

        // La nota del pedido lleva el payment_id: es el dato concreto que el
        // comerciante necesita para escribirle a soporte@khipu.com.
        $noteText = $noticeText . ' ' . sprintf($this->l('ID de pago (payment_id): %s.'), $paymentId);

        try {
            $this->addPrivateNote($order, $noteText, $result['errors']);
        } catch (Throwable $e) {
            $this->logQuietly(
                'Khipu: reversa de resultado desconocido y además falló la nota del pedido '
                    . (int) $order->id . ': ' . $e->getMessage(),
                2, (int) $order->id
            );
        }

        $this->redirectToOrder((int) $order->id, 'error', $noticeText);
    }

    /**
     * Rama de éxito: un HTTP 200 se considera reversado, con o sin `message`.
     *
     * Desde aquí el dinero YA se movió en Khipu y no hay vuelta atrás: ningún
     * paso puede lanzar. Cada uno se protege por separado para que el fallo de
     * uno no impida los demás ni deje al admin un error crudo sobre una reversa
     * ya cobrada. El orden es deliberado: primero la fila de auditoría, que es
     * el único registro que existe de la plata devuelta.
     */
    private function handleSuccess(Order $order, $paymentId, $type, array $result)
    {
        $data = $result['data'];

        // Sin valores por defecto, a propósito: KhipuRefundService ya exigió los
        // campos obligatorios para dar `ok`, así que llegar acá garantiza que
        // están. Un `?: 0.0` acá escribiría "reversado: $0" sobre plata real y
        // corrompería el único registro permanente que va a existir de esta
        // operación; si el cuerpo viene mutilado, el flujo tiene que caer en la
        // rama de resultado desconocido, no aterrizar acá con ceros.
        $refundedAmount = (float) $data['refunded_amount'];
        $remaining = (float) $data['remaining'];
        $message = isset($data['message']) ? (string) $data['message'] : '';

        // ANTES de tocar nada de PrestaShop: la plata ya se movió en Khipu, y si
        // el guardado de más abajo falla y se revierte, esta línea es la única
        // evidencia de que se devolvió el dinero. Khipu no es transaccional; lo
        // único que se puede garantizar es la evidencia.
        $this->logQuietly(
            'Khipu: reversa aceptada por Khipu ' . json_encode(array(
                'payment_id' => (string) $paymentId,
                'refund_id' => isset($data['id']) ? (string) $data['id'] : '',
                'order' => (int) $order->id,
                'reference' => (string) $order->reference,
                'amount' => $refundedAmount,
                'currency' => isset($data['currency']) ? (string) $data['currency'] : '',
                'remaining' => $remaining,
            )),
            1, (int) $order->id
        );

        $persisted = $this->persistRefund($order, $paymentId, $type, $data, $refundedAmount, $remaining, $message);
        $this->noteSuccess($order, $refundedAmount, $remaining, $message);
        $this->reflectInOrder($order, $refundedAmount, $remaining);

        $text = ('' !== $message)
            ? $this->l('Reversa solicitada correctamente. Quedó en proceso y se concretará en el próximo ciclo de reversas de Khipu.')
            : $this->l('Reversa realizada con éxito.');

        // Si no se pudo guardar la fila, el admin tiene que saberlo: la plata se
        // devolvió igual, pero el saldo reversable de este pedido ya no es fiable.
        if (!$persisted) {
            $text .= ' ' . $this->l('ATENCIÓN: no se pudo guardar el registro local de esta reversa. Anota el monto y revisa el pedido antes de reversar de nuevo.');
        }

        // Desde aquí el dinero YA se movió en Khipu: ningún paso puede terminar
        // en un 500 sin aviso, o el admin reintentaría y reversaría dos veces.
        $rechargeUrl = '';
        try {
            $wallet = $this->module->getWalletState(true);
            if (null !== $wallet['balance'] && $wallet['balance'] < $refundedAmount) {
                // Se AÑADE al aviso de éxito, no lo reemplaza: la reversa se encoló
                // igual, y con una billetera de saldo bajo esta rama se dispara en
                // casi todas las reversas — reemplazar borraría el "quedó en proceso"
                // y el admin solo leería la advertencia.
                $text .= ' ' . $this->l('El saldo de la billetera de reversas podría no alcanzar para concretarla: recárgala para asegurar que se complete.');
                $rechargeUrl = $wallet['add_funds_url'];
            }
        } catch (Throwable $e) {
            $this->logQuietly(
                'Khipu: reversa solicitada correctamente pero falló el aviso posterior en el pedido '
                    . (int) $order->id . ': ' . $e->getMessage(),
                2, (int) $order->id
            );
            $rechargeUrl = '';
        }

        try {
            $this->redirectToOrder((int) $order->id, 'success', $text, $rechargeUrl);
        } catch (Throwable $e) {
            // Último recurso: la reversa se hizo y NO podemos dejar un 500 en cara
            // del admin, porque reintentaría y reversaría dos veces.
            $this->logQuietly(
                'Khipu: reversa solicitada correctamente pero falló el redirect del pedido '
                    . (int) $order->id . ': ' . $e->getMessage(),
                3, (int) $order->id
            );
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders'));
        }
    }

    /**
     * Guarda la fila de auditoría. No lanza nunca.
     *
     * @return bool si quedó guardada
     */
    private function persistRefund(Order $order, $paymentId, $type, array $data, $refundedAmount, $remaining, $message)
    {
        try {
            $refund = new KhipuRefund();
            $refund->id_order = (int) $order->id;
            $refund->id_shop = (int) $order->id_shop;
            $refund->khipu_refund_id = (string) $data['id'];
            $refund->payment_id = (string) $paymentId;
            $refund->type = (string) $type;
            $refund->refunded_amount = $refundedAmount;
            $refund->total_refunded = (float) $data['total_refunded'];
            $refund->remaining = $remaining;
            $refund->currency = isset($data['currency']) ? (string) $data['currency'] : '';
            $refund->message = $message;
            $refund->id_employee = (int) $this->context->employee->id;
            $refund->date_add = date('Y-m-d H:i:s');

            return (bool) $refund->add();
        } catch (Throwable $e) {
            $this->logQuietly(
                'Khipu: reversa aceptada por la API pero no se pudo guardar el registro local del pedido '
                    . (int) $order->id . ': ' . $e->getMessage(),
                3, (int) $order->id
            );

            return false;
        }
    }

    /**
     * Nota de éxito en el pedido. No lanza nunca.
     */
    private function noteSuccess(Order $order, $refundedAmount, $remaining, $message)
    {
        try {
            $this->addPrivateNote($order, sprintf(
                $this->l('Reversa Khipu solicitada: %1$s reversado, %2$s restante por reversar. %3$s'),
                $this->formatAmount($order, $refundedAmount),
                $this->formatAmount($order, $remaining),
                ('' !== $message) ? $message : $this->l('Concretada de inmediato.')
            ), array());
        } catch (Throwable $e) {
            $this->logQuietly(
                'Khipu: no se pudo dejar la nota de la reversa en el pedido ' . (int) $order->id . ': ' . $e->getMessage(),
                2, (int) $order->id
            );
        }
    }

    /**
     * Monto con formato de moneda para la nota del pedido.
     *
     * La nota es el registro permanente y se lee meses después, cuando nadie
     * recuerda si "50" eran pesos o miles.
     *
     * `Tools::displayPrice()` necesita el contenedor de Symfony y revienta con
     * ContainerNotFoundException donde no lo hay. Acá corre dentro de un
     * controlador de admin, así que lo hay — pero esto se llama desde la rama
     * de éxito, con la plata ya movida: si algún día cambia el contexto, más
     * vale una nota con el monto crudo que ninguna nota.
     *
     * @param float $amount
     *
     * @return string
     */
    private function formatAmount(Order $order, $amount)
    {
        try {
            return Tools::displayPrice((float) $amount, new Currency((int) $order->id_currency));
        } catch (Throwable $e) {
            return sprintf('%s %s', rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.'), Currency::getIsoCodeById((int) $order->id_currency));
        }
    }

    /**
     * Refleja la reversa en la contabilidad de PrestaShop, según el ajuste.
     *
     * El circuito de reembolso de PrestaShop NUNCA llama a la pasarela, así que
     * registrar el vale es puramente contable: no hay riesgo de doble reembolso.
     * La plata vuelve una sola vez, por el POST /v3/refunds.
     *
     * Nada de lo que hay aquí puede lanzar: cuando se llega a este punto el
     * dinero YA se reversó en Khipu y la fila de auditoría ya está guardada.
     * Un fallo contable degrada a nota en el pedido, nunca a un error en cara
     * del admin.
     */
    protected function reflectInOrder(Order $order, $refundedAmount, $remaining)
    {
        if ('refund' !== Configuration::get('KHIPU_REFUND_ORDER_STATE')) {
            return; // "— No cambiar —": solo fila y nota
        }

        try {
            if ($refundedAmount > 0) {
                $this->createRefundSlip($order, (float) $refundedAmount);
            }

            // Solo al agotar el saldo el pedido pasa a "Reembolsado". Una parcial
            // deja vale pero no cambia el estado.
            if ((float) $remaining <= 0) {
                $idStateRefund = (int) Configuration::get('PS_OS_REFUND');
                if ($idStateRefund > 0 && (int) $order->current_state !== $idStateRefund) {
                    $order->setCurrentState($idStateRefund, (int) $this->context->employee->id);
                }
            }
        } catch (Throwable $e) {
            $this->logQuietly(
                'Khipu: reversa exitosa pero falló el reflejo contable en el pedido '
                    . (int) $order->id . ': ' . $e->getMessage(),
                3, (int) $order->id
            );
            try {
                $this->addPrivateNote($order, sprintf(
                    $this->l('La reversa en Khipu se solicitó correctamente, pero no se pudo reflejar en la contabilidad de PrestaShop: %s'),
                    $e->getMessage()
                ), array());
            } catch (Throwable $e2) {
                $this->logQuietly(
                    'Khipu: no se pudo dejar la nota del fallo de reflejo contable en el pedido '
                        . (int) $order->id . ': ' . $e2->getMessage(),
                    2, (int) $order->id
                );
            }
        }
    }

    /**
     * Vale contable por el monto reversado.
     *
     * No se usa `OrderSlip::createPartialOrderSlip()`: en PS 8.1.7 ese helper no
     * rellena los cuatro totales que su propio `$definition` declara requeridos
     * (`total_products_tax_excl/incl`, `total_shipping_tax_excl/incl`), así que
     * `add()` falla la validación y lanza. Se construye el objeto a mano.
     *
     * El monto reversado es bruto (lo que recibe el pagador). La parte sin
     * impuesto se prorratea con la razón del propio pedido, que es exacta para
     * el total y una aproximación razonable para una parcial.
     */
    private function createRefundSlip(Order $order, $refundedAmount)
    {
        $ratio = 1.0;
        if ((float) $order->total_paid_tax_incl > 0) {
            $ratio = (float) $order->total_paid_tax_excl / (float) $order->total_paid_tax_incl;
        }

        $slip = new OrderSlip();
        $slip->id_customer = (int) $order->id_customer;
        $slip->id_order = (int) $order->id;
        $slip->conversion_rate = (float) $order->conversion_rate;
        $slip->total_products_tax_incl = (float) $refundedAmount;
        $slip->total_products_tax_excl = (float) $refundedAmount * $ratio;
        $slip->total_shipping_tax_incl = 0;
        $slip->total_shipping_tax_excl = 0;
        $slip->amount = (float) $refundedAmount;
        $slip->shipping_cost = false;
        $slip->shipping_cost_amount = 0;
        $slip->partial = 1;

        if (!$slip->add()) {
            $this->addPrivateNote(
                $order,
                $this->l('No se pudo registrar el vale de PrestaShop para esta reversa.'),
                array()
            );
        }
    }

    /**
     * Nota privada en el pedido.
     *
     * El campo `message` de Message se valida con isCleanHtml, y ahí va el
     * texto crudo de Khipu. Si no pasara la validación se guarda una nota de
     * reemplazo: perder la nota entera sería perder el único rastro del caso.
     */
    private function addPrivateNote(Order $order, $text, array $errors)
    {
        $clean = strip_tags((string) $text);

        if (!Validate::isCleanHtml($clean)) {
            $fields = array();
            foreach ($errors as $error) {
                if (isset($error['field']) && '' !== $error['field']) {
                    $fields[] = $error['field'];
                }
            }
            $clean = sprintf(
                $this->l('Reversa Khipu: el detalle de Khipu no se pudo guardar por validación. Campos con error: %s'),
                $fields ? implode(', ', $fields) : $this->l('(ninguno informado)')
            );
        }

        $message = new Message();
        $message->id_order = (int) $order->id;
        $message->id_employee = (int) $this->context->employee->id;
        $message->private = 1;
        $message->message = $clean;
        $message->add();
    }

    /**
     * Registro que no puede fallar.
     *
     * El `addLog()` de `PrestaShopLogger` termina en `ObjectModel::add()`, que
     * ejecuta `Hook::exec('actionObjectAddBefore')` sin try/catch en el core: un
     * módulo de terceros que escuche ese hook y lance haría lanzar a nuestro
     * logging. Estos `addLog` viven dentro de `catch` cuyo trabajo es "anotar y seguir" —
     * y uno de ellos es el último recurso que garantiza el redirect tras una
     * reversa ya cobrada. Si el registro falla, se pierde el registro, nunca el
     * flujo.
     *
     * @param string $message
     * @param int    $severity
     * @param int    $idOrder
     */
    private function logQuietly($message, $severity, $idOrder)
    {
        try {
            PrestaShopLogger::addLog($message, (int) $severity, null, 'Order', (int) $idOrder, true);
        } catch (Throwable $e) {
            // Silencio deliberado: no hay a dónde escalar sin arriesgar el flujo.
        }
    }

    /**
     * Vuelve a la ficha del pedido con el aviso en la cookie del empleado.
     *
     * Desde 1.7.7 la ficha se sirve por ruta Symfony; antes, por el controlador
     * legacy con `&vieworder`. Se pide la ruta y, si la versión no la resolvió
     * (el id no aparece en el enlace), se cae al esquema antiguo.
     */
    private function redirectToOrder($idOrder, $status, $text, $rechargeUrl = '')
    {
        // Cookie::__set() lanza si el valor lleva `|` o `¤` (core classes/Cookie.php:226),
        // y acá entra texto de Khipu: su mensaje de error crudo y la add_funds_url.
        $text = str_replace(array('|', '¤'), ' ', (string) $text);
        $rechargeUrl = str_replace(array('|', '¤'), '', (string) $rechargeUrl);
        // Solo https: escapar el HTML no impediría un `javascript:` en el href.
        if (0 !== strpos($rechargeUrl, 'https://')) {
            $rechargeUrl = '';
        }

        $this->context->cookie->__set('khipu_refund_notice', json_encode(array(
            'status' => $status,
            'text' => $text,
            'recharge_url' => $rechargeUrl,
        )));
        $this->context->cookie->write();

        $link = $this->context->link->getAdminLink('AdminOrders', true, array('id_order' => (int) $idOrder, 'vieworder' => 1));

        if (false === strpos($link, '/' . (int) $idOrder . '/')) {
            $link = $this->context->link->getAdminLink('AdminOrders')
                . '&id_order=' . (int) $idOrder . '&vieworder';
        }

        Tools::redirectAdmin($link);
    }
}
