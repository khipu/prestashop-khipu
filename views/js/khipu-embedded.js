/**
 * Khipu embebido (Khipu Web, KWS) en la página de pago del módulo.
 * Portado del cliente de WooCommerce; sin dependencias.
 *
 * OK / WARNING / CONTINUE -> retorno normal (validate): la notificación de
 *                            Khipu decide el estado del pedido.
 * ERROR / abandono        -> muestra el motivo y hace POST al controlador embedded (en segundo
 *                            plano), que borra el pago, cancela el pedido y rehace el carro;
 *                            ofrece volver, sin navegar sola para dar tiempo de leer el error.
 * KWS no carga en 10 s    -> respaldo: la URL del medio en el sitio de Khipu.
 */
(function () {
    'use strict';

    var root = document.getElementById('khipu-web-root');
    if (!root) { return; }
    var d = root.dataset;
    var started = false;

    // Recargar o volver con «Atrás» no puede caer en el carro ya consumido: la
    // dirección pasa a la que retoma este pedido (mismo origen, o no se toca).
    if (d.resumeUrl && window.history && typeof window.history.replaceState === 'function') {
        try { window.history.replaceState(null, '', d.resumeUrl); } catch (e) { /* queda la dirección actual */ }
    }

    function go(url) { if (url) { window.location.href = url; } }
    // El servidor solo entrega una URL https; si no lo fuera, el retorno normal.
    function fallback() {
        go(/^https:\/\//i.test(d.fallbackUrl || '') ? d.fallbackUrl : d.returnUrl);
    }

    function loadKws(done) {
        if (typeof Khipu !== 'undefined') { done(true); return; }
        var finished = false;
        function end(ok) { if (!finished) { finished = true; clearTimeout(timer); done(ok); } }
        var timer = setTimeout(function () { end(false); }, 10000);
        var s = document.createElement('script');
        s.src = d.kwsUrl || 'https://js.khipu.com/v1/kws.js';
        s.onload = function () { end(typeof Khipu !== 'undefined'); };
        s.onerror = function () { end(false); };
        document.head.appendChild(s);
    }

    // Mensaje bajo el modal: queda a la vista aunque Khipu cierre su ventana, para
    // que el comprador pueda leer por qué falló el pago. Todo va como texto.
    function showError(res) {
        var msg = document.querySelector('.khipu-embedded-msg');
        if (!msg) {
            msg = document.createElement('p');
            msg.className = 'khipu-embedded-msg';
            root.parentNode.insertBefore(msg, root.nextSibling);
        }
        while (msg.firstChild) { msg.removeChild(msg.firstChild); }
        var reason = (res && (res.exitMessage || res.failureReason)) || '';
        msg.appendChild(document.createTextNode('El pago no se completó' + (reason ? ': ' + reason : '.') + ' '));
        var slot = document.createElement('span');
        slot.id = 'khipu-embedded-after';
        msg.appendChild(slot);
    }

    function setLink(url, label) {
        var slot = document.getElementById('khipu-embedded-after');
        if (!slot || !url) { return; }
        var a = document.createElement('a');
        a.href = url;
        a.textContent = label;
        slot.appendChild(a);
    }

    // No se navega sola: se cancela el pedido en segundo plano y el comprador
    // vuelve cuando termine de leer.
    function cancel(res) {
        showError(res);
        var body = new URLSearchParams();
        body.append('reference', d.reference);
        body.append('payment_id', d.paymentId);
        body.append('token', d.token);
        fetch(d.cancelUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            body: body
        })
            // Los rechazos llegan con 403 y el mismo JSON: fetch no falla por
            // el código HTTP, así que se lee el cuerpo igual.
            .then(function (r) { return r.json(); })
            .then(function (j) {
                // Con ok=false no se canceló nada (ya pagado, Khipu no aceptó
                // borrar...): el retorno normal decide con el estado real.
                if (j && j.ok && j.redirect) { setLink(j.redirect, 'Volver a mi carrito'); return; }
                setLink((j && j.redirect) || d.returnUrl, 'Ver mi pedido');
            })
            .catch(function () { setLink(d.returnUrl, 'Ver mi pedido'); });
    }

    // El modal de Khipu Web se monta dentro de #khipu-web-root. Si ese contenedor
    // queda dentro del contenido del tema, la barra de navegación fija del tema
    // (con su propio z-index) puede taparle la parte de arriba, incluido el botón de
    // cerrar. Se saca al nivel del <body> y se le da el z-index máximo para que
    // quede por encima de cualquier cabecera.
    function lift() {
        if (root.parentNode !== document.body) { document.body.appendChild(root); }
        root.style.position = 'relative';
        root.style.zIndex = '2147483000';
    }

    function start() {
        if (started) { return; }
        started = true;
        try {
            lift();
            var khipu = new Khipu();
            khipu.startOperation(d.paymentId, function (res) {
                var r = res && res.result;
                if (r === 'ERROR') { cancel(res); } else { go(d.returnUrl); }
            }, {
                mountElement: root,
                modal: true,
                modalOptions: { maxWidth: 450, maxHeight: 860 },
                options: { style: { primaryColor: '#8347AD' }, skipExitPage: false }
            });
        } catch (e) {
            if (window.console) { console.error('Khipu KWS', e); }
            fallback();
        }
    }

    loadKws(function (ok) { if (ok) { start(); } else { fallback(); } });
})();
