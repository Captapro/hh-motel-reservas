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
        'private_token' => env('GHL_PRIVATE_TOKEN'),
        'location_id' => env('GHL_LOCATION_ID'),
        'custom_field_pago_id' => env('GHL_CUSTOM_FIELD_PAGO_ID'),
        // Webhooks de entrada de los flujos de GHL: correo de reserva confirmada y,
        // para rescatar reservas web sin pagar, el WhatsApp de pago por vencer y
        // el de reserva liberada (GhlAbandonedBookings).
        'webhook_confirmada' => env('GHL_WEBHOOK_RESERVA_CONFIRMADA'),
        'webhook_pago_pendiente' => env('GHL_WEBHOOK_PAGO_PENDIENTE'),
        'webhook_reserva_vencida' => env('GHL_WEBHOOK_RESERVA_VENCIDA'),
        // Token que GHL agrega a la URL al llamar al sistema (baja de publicidad).
        'inbound_secret' => env('GHL_INBOUND_SECRET'),
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
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY', ''),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
    ],

];
