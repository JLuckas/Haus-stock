<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Saipos Order API
    |--------------------------------------------------------------------------
    | Docs: https://saipos-docs-order-api.readme.io/reference/introducao
    | Credentials (idPartner / secret / store code) come from Saipos Developer.
    */

    'base_url' => env('SAIPOS_BASE_URL', 'https://order-api.saipos.com'),

    'id_partner' => env('SAIPOS_ID_PARTNER'),

    'secret' => env('SAIPOS_SECRET'),

    'cod_store' => env('SAIPOS_COD_STORE'),

    // Saipos works in Brazilian local time; date filters are sent in this timezone.
    'timezone' => env('SAIPOS_TIMEZONE', 'America/Sao_Paulo'),

    'timeout_seconds' => (int) env('SAIPOS_TIMEOUT', 15),

    // The docs state tokens are valid for 2 days. Cache for 47h so a token never expires mid-cycle.
    'token_ttl_minutes' => (int) env('SAIPOS_TOKEN_TTL_MINUTES', 47 * 60),

    'endpoints' => [
        'auth' => '/auth',
        // Documented: GET /order?cod_store=&order_id= (single order detail).
        'order' => '/order',
        // NOT in the public Order API docs: they describe no endpoint that lists orders.
        // Set this to the listing endpoint Saipos provides for your account.
        'orders' => env('SAIPOS_ORDERS_ENDPOINT'),
    ],

    // Query-string names used by the listing endpoint above. Adjust them to match it.
    'list_query' => [
        'store' => 'cod_store',
        'from' => 'start_date',
        'to' => 'end_date',
        'date_format' => 'Y-m-d\TH:i:s',
    ],

    'polling' => [
        'interval_seconds' => (int) env('SAIPOS_POLL_INTERVAL', 30),
        // Each cycle re-reads this window. Overlap is harmless: orders are idempotent locally.
        'lookback_minutes' => (int) env('SAIPOS_POLL_LOOKBACK_MINUTES', 60),
    ],

];
