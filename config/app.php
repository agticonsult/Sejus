<?php

use Illuminate\Support\Facades\Facade;

return [
    'name' => env('APP_NAME', 'CONECTA EGRESSO (SEJUS/ES)'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'America/Sao_Paulo',
    'locale' => 'pt_BR',
    'fallback_locale' => 'pt_BR',
    'faker_locale' => 'pt_BR',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],
    'maintenance' => [
        'driver' => 'file',
    ],
    'lgpd_pepper' => env('LGPD_PEPPER_KEY', 'conecta_egresso_lgpd_pepper_2026_sejus_es'),
    'carteira_signing_key' => env('CARTEIRA_SIGNING_KEY', 'sejus_carteira_digital_master_key_2026'),
];
