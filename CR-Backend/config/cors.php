<?php

/*
 * Quién puede llamar a la API desde un navegador.
 *
 * Antes no había este archivo y valía el de Laravel: cualquier página de
 * internet podía llamar a la API desde el navegador de alguien. En Docker la
 * pantalla y la API van por la misma dirección (el contenedor web reenvía
 * /api), así que no les hace falta; solo se deja pasar a FRONTEND_URL, que
 * en desarrollo es el `ng serve` (localhost:4200).
 */
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('FRONTEND_URL', 'http://localhost:4200'))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Para que la pantalla pueda leer el nombre del archivo en las descargas.
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    // La sesión va con token (Bearer), no con cookies.
    'supports_credentials' => false,
];
