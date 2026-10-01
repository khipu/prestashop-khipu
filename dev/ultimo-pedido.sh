#!/usr/bin/env bash
#
# Imprime el último pedido de Khipu con el monto ya redondeado como lo espera
# la notificación, listo para pasárselo a notificar.sh.
#
#   ./dev/ultimo-pedido.sh

set -euo pipefail

docker exec dev-db-1 mysql -uroot -pprestashop prestashop -N -e "
SELECT o.reference,
       CASE WHEN c.iso_code = 'CLP' THEN ROUND(o.total_paid_tax_incl, 0)
            ELSE ROUND(o.total_paid_tax_incl, 2) END,
       c.iso_code,
       osl.name,
       IFNULL(op.transaction_id, '-')
FROM ps_orders o
JOIN ps_currency c ON c.id_currency = o.id_currency
LEFT JOIN ps_order_state_lang osl ON osl.id_order_state = o.current_state AND osl.id_lang = 1
LEFT JOIN ps_order_payment op ON op.order_reference = o.reference
WHERE o.module = 'khipupayment'
ORDER BY o.id_order DESC LIMIT 1;" 2>/dev/null |
while IFS=$'\t' read -r ref total iso estado payment_id; do
    echo "referencia : $ref"
    echo "monto      : $total ($iso)"
    echo "estado     : $estado"
    echo "payment_id : $payment_id"
    echo
    echo "Para simular la notificación de pago:"
    echo "  KHIPU_SECRET=<llave secreta> KHIPU_RECEIVER_ID=<id cobrador> \\"
    echo "    ./dev/notificar.sh $ref $total"
done
