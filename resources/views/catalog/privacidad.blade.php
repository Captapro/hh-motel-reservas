<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Aviso de privacidad — HH Motel</title>
    @include('catalog._app-head')
    <style>
        :root { color-scheme: light; --hh-accent:#ff7918; --hh-canvas:#f5f5f4; --hh-ink:#202124; }
        * { box-sizing:border-box; }
        body { background:var(--hh-canvas); color:var(--hh-ink); font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin:0; line-height:1.55; }
        .wrap { max-width:640px; margin:0 auto; padding:24px 16px calc(40px + var(--safe-b)); }
        h1 { font-size:22px; margin:0 0 6px; letter-spacing:-.02em; }
        h2 { font-size:15px; margin:24px 0 6px; }
        p, li { font-size:14px; color:#3d4046; }
        ul { padding-left:20px; margin:6px 0; }
        .updated { font-size:12.5px; color:#7a7d83; margin-bottom:8px; }
        a { color:var(--hh-accent); }
    </style>
</head>
<body>
    @include('catalog._app-bar', ['appBack' => route('catalog.index'), 'appTitle' => 'Privacidad'])
    <main class="wrap">
        <h1>Aviso de privacidad</h1>
        <div class="updated">Última actualización: octubre de 2026</div>

        <h2>Quién usa tus datos</h2>
        <p>HH Motel, ubicado en Almte. Barroso 334, Santiago, es quien decide para qué se usan los datos que nos entregas al reservar.</p>

        <h2>Qué datos pedimos</h2>
        <ul>
            <li>Nombre y apellido, teléfono y correo electrónico, al reservar en línea.</li>
            <li>Fecha de nacimiento, solo si usas un cupón que depende de la edad.</li>
            <li>Documento de identidad, que se verifica en persona al llegar al motel.</li>
        </ul>

        <h2>Para qué los usamos</h2>
        <ul>
            <li>Gestionar tu reserva y avisarte de ella: confirmación, pago pendiente y cambios.</li>
            <li>Cobrar tu reserva. El pago en línea lo procesa Mercado Pago; nosotros no vemos los datos de tu tarjeta.</li>
            <li>Cumplir obligaciones del negocio, como boletas y registros de ventas.</li>
            <li>Enviarte ofertas y novedades, <strong>solo si marcaste esa opción</strong>. Puedes retirar ese permiso cuando quieras.</li>
        </ul>

        <h2>Quién más recibe tus datos</h2>
        <p>Para operar usamos proveedores que tratan datos por encargo nuestro: el servicio donde está alojado el sistema, la plataforma de mensajería y correo con la que te escribimos, Meta (WhatsApp) y Mercado Pago. No vendemos tus datos.</p>

        <h2>Cuánto tiempo los guardamos</h2>
        <p>Mientras sean necesarios para gestionar tu reserva y cumplir las obligaciones legales y tributarias del negocio. Después los eliminamos o dejamos de identificarte.</p>

        <h2>Tus derechos</h2>
        <p>Puedes pedirnos saber qué datos tuyos tenemos, corregirlos, eliminarlos, oponerte a su uso o llevártelos. También puedes retirar el permiso para recibir ofertas.</p>
        <p>Para ejercer estos derechos escríbenos por WhatsApp desde el botón de contacto del catálogo{{ filled(config('privacy.contact_email')) ? ' o al correo '.config('privacy.contact_email') : '' }}, indicando tu nombre y teléfono para poder encontrarte.</p>
    </main>
</body>
</html>
