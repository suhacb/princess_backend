<?php

return [

    'name' => env('APP_NAME'),

    'auth' => [
        'url_frontend'  => env('AUTH_FRONTEND_URL'),
        'port_frontend' => env('AUTH_FRONTEND_PORT'),
        'url_backend'   => env('AUTH_BACKEND_URL'),
        'port_backend'  => env('AUTH_BACKEND_PORT'),
    ],

    'backend' => [
        'url'  => env('APP_BACKEND_URL'),
        'port' => env('APP_BACKEND_PORT'),
    ],

    'frontend' => [
        'url'  => env('APP_FRONTEND_URL'),
        'port' => env('APP_FRONTEND_PORT'),
    ],

    'zinc' => [
        'base_uri' => env('ZINC_BASE_URI'),
        'user'     => env('ZINC_USER'),
        'password' => env('ZINC_PASSWORD'),
    ],

    'qdrant' => [
        'base_url' => env('QDRANT_BASE_URL'),
    ],

    'ollama' => [
        'base_url'     => env('OLLAMA_BASE_URL'),
        'model'        => env('OLLAMA_MODEL', 'gemma4:e4b'),
        'model_fast'   => env('OLLAMA_MODEL_FAST', 'gemma4:e4b'),
        'model_smart'  => env('OLLAMA_MODEL_SMART', 'gemma4:26b'),
        'timeout'      => (int) env('OLLAMA_TIMEOUT', 300),
    ],

    'm365' => [
        'tenant_id'     => env('M365_TENANT_ID'),
        'client_id'     => env('M365_CLIENT_ID'),
        'client_secret' => env('M365_CLIENT_SECRET'),
    ],

    'e2e' => [
        'token' => env('E2E_TOKEN'),
    ],

];
