<?php

declare(strict_types=1);

/**
 * Configuracion institucional de GestioBioV3 (version PHP).
 *
 * Equivale a settings.GESTIOBIO del proyecto Django: todos los textos de marca y
 * los parametros comerciales se editan aqui sin tocar el codigo.
 */

return [
    'app' => [
        'brand_name' => 'GestioBioV3',
        'brand_subtitle' => 'Soporte Postventa',
        'company' => 'Biopago C.A.',
        'currency' => 'USD',
        'signature' => 'Equipo de Soporte técnico Biopago C.A.',
        'platform_line' => 'Se envía adjunto código de oficinas.',
        'administrative_channel' => 'administracion.estaciones@biopagopdv.com.ve',
        'quote_status' => 'A cotizar',
        'channels' => [
            'pickup' => 'Retiro en Parque Cristal, Caracas, previa confirmación con el Equipo de Soporte técnico.',
            'parcel' => 'Encomiendas: el envío se realiza a nivel nacional y el costo del flete no está incluido en la cotización.',
            'bank_unsubscribe_email' => 'servicio_biopago@banvenez.com',
            'sales_email' => 'ventas@biopagobdv.com',
        ],
        'timezone' => 'America/Caracas',
        'debug' => true,
    ],
    'database' => [
        // Base SQLite propia del proyecto PHP; no comparte archivo con Django.
        'path' => __DIR__ . '/storage/gestiobio.sqlite',
    ],
    'session' => [
        'name' => 'gestiobio_php_session',
        'lifetime' => 7200,
    ],
    'auth' => [
        'login_path' => '/login/',
        'redirect_after_login' => '/gestion/',
        'redirect_after_logout' => '/',
    ],
];
