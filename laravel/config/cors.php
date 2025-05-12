<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'], // Zezwala na dostęp z dowolnej domeny

    'allowed_origins_patterns' => ['*'],

    'allowed_headers' => ['*'], // Zezwala na wszystkie nagłówki, w tym Authorization

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
