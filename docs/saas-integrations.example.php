<?php

// Copy outside the repository, restrict file permissions, and set SAAS_INTEGRATIONS_FILE.
return [
    'UUID-DA-EMPRESA' => [
        'comms' => ['base_url' => 'https://comunicacao.example', 'api_key' => '', 'api_secret' => ''],
        'conta_azul' => ['client_id' => '', 'client_secret' => ''],
        'google_calendar' => ['client_id' => '', 'client_secret' => ''],
        'banco_do_brasil' => [],
    ],
];
