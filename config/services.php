<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Datos reales de la cuenta para el mensaje de WhatsApp de "instrucciones
    // de pago" (ver reservations/show.blade.php) -- sin esto configurado, ese
    // mensaje NO incluye datos de transferencia (para no mandarle a un
    // cliente una cuenta inventada o de otro negocio).
    // Cuenta/subcuenta de GoHighLevel de HH Motel -- Private Integration
    // Token (no OAuth), generado en Configuración → Integraciones privadas
    // de esa subcuenta puntual, con permisos de contactos y tags.
    // Mercado Pago (cobro en línea). Las claves van en variables de entorno de Render,
    // nunca en el código: MERCADOPAGO_ACCESS_TOKEN y MERCADOPAGO_WEBHOOK_SECRET.
    'mercadopago' => [
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
    ],

    'ghl' => [
        'base_url' => env('GHL_BASE_URL', 'https://services.leadconnectorhq.com'),
        'private_token' => env('GHL_PRIVATE_TOKEN'),
        'location_id' => env('GHL_LOCATION_ID'),
        'custom_field_pago_id' => env('GHL_CUSTOM_FIELD_PAGO_ID'),
        // Webhooks de entrada de los flujos de GHL: correo de reserva confirmada y,
        // para rescatar reservas web sin pagar, el WhatsApp de pago por vencer y
        // el de reserva liberada (GhlAbandonedBookings).
        'webhook_confirmada' => env('GHL_WEBHOOK_RESERVA_CONFIRMADA'),
        'webhook_pago_pendiente' => env('GHL_WEBHOOK_PAGO_PENDIENTE'),
        'webhook_reserva_vencida' => env('GHL_WEBHOOK_RESERVA_VENCIDA'),
        // Opiniones: pedir la opinión tras el check-out y mandar el cupón del 10% al dejarla.
        'webhook_pedir_opinion' => env('GHL_WEBHOOK_PEDIR_OPINION'),
        'webhook_cupon_opinion' => env('GHL_WEBHOOK_CUPON_OPINION'),
        // Token que GHL agrega a la URL al llamar al sistema (baja de publicidad).
        'inbound_secret' => env('GHL_INBOUND_SECRET'),
        // Embudo de GHL donde cada reserva queda como oportunidad (con su monto).
        'pipeline_name' => env('GHL_PIPELINE_RESERVAS', 'Reservas HH'),
        // Desde cuándo se cargan reservas al embudo (las anteriores no se suben).
        'opportunities_since' => env('GHL_OPPORTUNITIES_SINCE', '2026-11-01'),
    ],

    // Medición de Meta: el píxel (público, el mismo de hhmotel.cl) en el catálogo y
    // la API de conversiones para avisar las ventas desde el servidor. El token
    // se genera en el Administrador de eventos y va solo en Render (META_CAPI_TOKEN).
    'meta' => [
        'pixel_id' => env('META_PIXEL_ID', '1712431846730178'),
        'capi_token' => env('META_CAPI_TOKEN'),
        'test_event_code' => env('META_TEST_EVENT_CODE'),
    ],

    // Medición de Google: la Etiqueta de Google (GA4 "G-…" o Google Ads "AW-…") en el
    // catálogo público y, si hay etiqueta de conversión, el aviso de cada reserva pagada.
    // Todo apagado hasta que existan las variables en Render.
    'google' => [
        'tag_id' => env('GOOGLE_TAG_ID'),
        'ads_conversion_label' => env('GOOGLE_ADS_CONVERSION_LABEL'),
    ],

    'hh_payment' => [
        'bank_titular' => env('HH_BANK_TITULAR'),
        'bank_rut' => env('HH_BANK_RUT'),
        'bank_name' => env('HH_BANK_NAME'),
        'bank_account_type' => env('HH_BANK_ACCOUNT_TYPE'),
        'bank_account_number' => env('HH_BANK_ACCOUNT_NUMBER'),
        'bank_email' => env('HH_BANK_EMAIL'),
        'mercadopago_link' => env('HH_MERCADOPAGO_LINK'),
    ],

    // Cuenta de soporte (master) que se crea/repara al arrancar (docker/entrypoint.sh).
    'support' => [
        'email' => env('SUPPORT_EMAIL'),
        'password' => env('SUPPORT_PASSWORD'),
    ],

    // Cuenta de anfitrión (recepción) que se crea al arrancar si no existe (docker/entrypoint.sh).
    'host' => [
        'email' => env('HOST_EMAIL'),
        'password' => env('HOST_PASSWORD'),
    ],

    'hh_reviews' => [
        'google_review_link' => env('HH_GOOGLE_REVIEW_LINK'),
        'widget_url' => env('HH_REVIEWS_WIDGET_URL', 'https://link.hhmotel.cl/reputation/widgets/review_widget/ksYYfSiY8nP4YFvkrJFJ'),
    ],

    // Analista de ventas (IA). Sin ANTHROPIC_API_KEY la sección avisa que falta configurarla.
    'webpush' => [
        'public_key' => env('WEBPUSH_PUBLIC_KEY'),
        'private_key' => env('WEBPUSH_PRIVATE_KEY'),
        'subject' => env('WEBPUSH_SUBJECT', 'mailto:hhmotel334@gmail.com'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY', ''),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
    ],

];
