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

    'workcolbeef' => [
        'enabled' => env('WORKCOLBEEF_SSO_ENABLED', false),
        'base_url' => env('WORKCOLBEEF_BASE_URL', 'http://127.0.0.1:8200'),
        'exchange_path' => env('WORKCOLBEEF_SSO_EXCHANGE_PATH', '/api/sso/exchange'),
        'client_id' => env('WORKCOLBEEF_SSO_CLIENT_ID', 'lenguas'),
        'client_secret' => env('WORKCOLBEEF_SSO_CLIENT_SECRET', ''),
        'audience' => env('WORKCOLBEEF_SSO_AUDIENCE', 'lenguas'),
        'timeout' => env('WORKCOLBEEF_SSO_TIMEOUT_SECONDS', 5),
        'provisioning_secret' => env('WORKCOLBEEF_PROVISIONING_SECRET', ''),
        'provisioning_max_skew' => 300,
    ],

];
