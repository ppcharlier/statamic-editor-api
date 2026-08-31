<?php

return [
    'route_prefix' => 'api/editor',

    'auth' => [
        'driver' => 'file', // 'file' (tokens en fichiers) ou 'sanctum' (tokens en DB, requiert laravel/sanctum + users Eloquent)
        'token_ttl_days' => 90, // null = pas d'expiration
    ],

    'storage_path' => storage_path('statamic/editor-api/tokens'),

    'rate_limits' => [
        'auth' => 5,  // requêtes/minute sur POST /auth/tokens, par IP
        'api' => 120, // requêtes/minute sur le reste, par token
        'api_per_ip' => 480, // plafond par IP (bloque la rotation de bearers-poubelle)
    ],

    // true = tous les handles, ['a', 'b'] = liste blanche, false = désactivé
    'resources' => [
        'collections' => true,
        'assets' => true,
        'globals' => true,
        'taxonomies' => true,
        'navigations' => true,
        'forms' => true,
        'users' => false,
    ],
];
