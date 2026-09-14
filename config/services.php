<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('SES_AWS_ACCESS_KEY_ID'),
        'secret' => env('SES_AWS_SECRET_ACCESS_KEY'),
        'token' => env('SES_AWS_SESSION_TOKEN'),
        'region' => env('SES_AWS_DEFAULT_REGION', 'us-east-1'),
        'configuration_set' => env('SES_CONFIGURATION_SET'),
    ],

    'comms' => [
        'base_url' => env('COMMS_BASE_URL'),
        'api_key' => env('COMMS_API_KEY'),
        'api_secret' => env('COMMS_API_SECRET'),
        'timeout' => env('COMMS_TIMEOUT', 10),
        'enabled' => (bool) env('COMMS_ENABLED', false),
        'store_only' => (bool) env('COMMS_STORE_ONLY', true),
    ],

];
