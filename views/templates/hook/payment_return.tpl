{if isset($status) && $status == 'ok'}
    <p>Su pago ha sido procesado correctamente. Gracias por su compra.</p>
    <p>Número de pedido: {$id_order}</p>
    <p>Total pagado: {$total_to_pay}</p>

    {if isset($customer_data)}
        <h3>Datos del pagador:</h3>
        <p>Nombre: {$customer_data.first_name} {$customer_data.last_name}</p>
        <p>Correo electrónico: {$customer_data.email}</p>
        <!-- Agrega otros campos según sea necesario -->
    {/if}
{else}
    <p>Lo sentimos, ha ocurrido un error durante el procesamiento de su pago.</p>
    <p>Póngase en contacto con nosotros para obtener ayuda.</p>
{/if}
