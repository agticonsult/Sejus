<?php

return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'lgpd' => [
        'pepper' => env('LGPD_PEPPER_KEY', 'conecta_egresso_lgpd_pepper_2026_sejus_es'),
    ],

    'carteira' => [
        'signing_key' => env('CARTEIRA_SIGNING_KEY', 'sejus_carteira_digital_master_key_2026'),
    ],

    'document_generator' => [
        'url' => env('DOCUMENT_GENERATOR_URL', 'http://localhost:8080'),
        'key' => env('DOCUMENT_GENERATOR_KEY', 'token-secreto-dev'),
        'timeout' => (int) env('DOCUMENT_GENERATOR_TIMEOUT', 5),
    ],

    'webrtc' => [
        'service_url' => env('WEBRTC_SERVICE_URL', 'http://python:8001'),
        'webhook_secret' => env('WEBRTC_WEBHOOK_SECRET', 'sejus_webrtc_webhook_secret_2026'),
        'jwt_secret' => env('WEBRTC_JWT_SECRET', 'sejus_jwt_shared_secret_2026'),
        'coturn' => [
            'host' => env('COTURN_HOST', 'coturn'),
            'port' => env('COTURN_PORT', 3478),
            'secret' => env('COTURN_SECRET', 'sejus_turn_secret_key_2026'),
            'realm' => env('COTURN_REALM', 'sejus.es.gov.br'),
        ],
    ],
];
