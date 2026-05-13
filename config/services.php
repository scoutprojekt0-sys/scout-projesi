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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'webhook_tolerance_seconds' => env('STRIPE_WEBHOOK_TOLERANCE_SECONDS', 300),
    ],

    'iyzico' => [
        'api_key' => env('IYZICO_API_KEY'),
        'secret_key' => env('IYZICO_SECRET_KEY'),
        'base_url' => env('IYZICO_BASE_URL', 'https://sandbox-api.iyzipay.com'),
        'callback_url' => env('IYZICO_CALLBACK_URL'),
        'default_identity_number' => env('IYZICO_DEFAULT_IDENTITY_NUMBER', ''),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'secret' => env('PAYPAL_SECRET'),
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'webhook_secret' => env('PAYPAL_WEBHOOK_SECRET'),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY', ''),
        'base_url' => env('BREVO_BASE_URL', 'https://api.brevo.com/v3'),
        'sender_email' => env('BREVO_SENDER_EMAIL', env('MAIL_FROM_ADDRESS', '')),
        'sender_name' => env('BREVO_SENDER_NAME', env('MAIL_FROM_NAME', 'NextScout')),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'support_model' => env('OPENAI_SUPPORT_MODEL', 'gpt-5.4-mini'),
        'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 30),
    ],

    'firebase_messaging' => [
        'project_id' => env('FIREBASE_PROJECT_ID', 'scout-40154'),
        'credentials_json' => env('FIREBASE_SERVICE_ACCOUNT_JSON', ''),
        'credentials_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH', ''),
        'timeout_seconds' => (int) env('FIREBASE_MESSAGING_TIMEOUT_SECONDS', 10),
    ],

];
