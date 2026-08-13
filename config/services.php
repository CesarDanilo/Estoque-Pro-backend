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

    'abstract' => [
        'email_validation_key' => env('ABSTRACT_EMAIL_API_KEY'),
        'email_validation_url' => env('ABSTRACT_EMAIL_API_URL', 'https://emailreputation.abstractapi.com/v1/'),
    ],

    'cpfhub' => [
        'api_key' => env('CPFHUB_API_KEY'),
        'api_url' => env('CPFHUB_API_URL', 'https://api.cpfhub.io/cpf'),
    ],

    'brasilapi' => [
        'cnpj_url' => env('BRASILAPI_CNPJ_URL', 'https://brasilapi.com.br/api/cnpj/v1'),
    ],
        

];
