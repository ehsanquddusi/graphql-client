<?php

return [

    'endpoint' => env('GRAPHQL_ENDPOINT'),

    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ],

    'auth' => [
        'type' => env('GRAPHQL_AUTH_TYPE', 'none'),
        'token' => env('GRAPHQL_TOKEN'),
        'username' => env('GRAPHQL_USERNAME'),
        'password' => env('GRAPHQL_PASSWORD'),
    ],

    'timeout' => env('GRAPHQL_TIMEOUT', 30),

    'connect_timeout' => env('GRAPHQL_CONNECT_TIMEOUT', 10),

    'retry' => [
        'times' => env('GRAPHQL_RETRY_TIMES', 0),
        'sleep' => env('GRAPHQL_RETRY_SLEEP', 100),
    ],

    'cache' => [
        'enabled' => env('GRAPHQL_CACHE_ENABLED', false),
        'ttl' => env('GRAPHQL_CACHE_TTL', 300),
        'store' => env('GRAPHQL_CACHE_STORE'),
    ],

    'query_path' => env('GRAPHQL_QUERY_PATH'),

];
