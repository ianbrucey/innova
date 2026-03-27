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

    'shopify' => [
        'domain'         => env('SHOPIFY_DOMAIN'),
        'access_token'   => env('SHOPIFY_ACCESS_TOKEN'),
        'api_version'    => '2025-01',
    ],

    'amazon' => [
        'client_id'      => env('AMAZON_SP_CLIENT_ID'),
        'client_secret'  => env('AMAZON_SP_CLIENT_SECRET'),
        'refresh_token'  => env('AMAZON_SP_REFRESH_TOKEN'),
        'marketplace_id' => env('AMAZON_SP_MARKETPLACE_ID', 'ATVPDKIKX0DER'),
        'region'         => env('AMAZON_SP_REGION', 'us-east-1'),
    ],

    // DB schema config — override these once the client schema dump is received
    'innova_schema' => [
        'tables' => [
            'order'           => env('DB_TABLE_ORDER', 'Order'),
            'order_line_item' => env('DB_TABLE_ORDER_LINE_ITEM', 'OrderLineItem'),
            'order_shipping'  => env('DB_TABLE_ORDER_SHIPPING', 'OrderShipping'),
        ],
        'columns' => [
            'admin_order_status' => env('DB_COL_ADMIN_ORDER_STATUS', 'AdminOrderStatus'),
            'requires_shipping'  => env('DB_COL_REQUIRES_SHIPPING', 'RequiresShipping'),
            'tracking_number'    => env('DB_COL_TRACKING_NUMBER', 'TrackingNumber'),
            'update_date'        => env('DB_COL_UPDATE_DATE', 'UpdateDate'),
        ],
    ],

];
