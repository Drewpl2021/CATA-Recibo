<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Consulta de DNI para dar de alta a un trabajador
    |--------------------------------------------------------------------------
    |
    | RR.HH. escribe el DNI y el sistema trae nombres y apellidos en vez de
    | tipearlos: menos tiempo y, sobre todo, menos erratas en el nombre que
    | va a salir impreso en la boleta.
    |
    | Se busca en dos sitios, en este orden:
    |
    |   1. La base propia (RENSUN). Es una copia del padrón que ya tiene el
    |      colegio: no cuesta nada consultarla y responde al instante.
    |   2. Decolecta. Se paga por consulta, así que solo se llama cuando la
    |      base propia no tiene a esa persona.
    |
    | Lo que se encuentre se guarda en caché: un DNI no cambia de dueño, y
    | así volver a consultarlo no gasta otra consulta.
    |
    */

    'cache_dias' => (int) env('CONSULTA_DNI_CACHE_DIAS', 30),

    'decolecta' => [
        'base_url' => rtrim((string) env('DECOLECTA_BASE_URL', 'https://api.decolecta.com'), '/'),
        'token'    => env('DECOLECTA_TOKEN'),
        // La ruta de la consulta. Los planes con más datos usan otra
        // (.../dni/full), por eso se deja en el .env y no aquí en duro.
        'ruta'     => env('DECOLECTA_RUTA_DNI', '/v1/reniec/dni'),
        'segundos' => (int) env('DECOLECTA_TIMEOUT', 8),
    ],

    'rensun' => [
        // La base del colegio vive en otro servidor. Sin RENSUN_DB_HOST
        // puesto, el sistema ni lo intenta y va directo a Decolecta.
        'activo' => (bool) env('RENSUN_DB_HOST', false),

        /*
        | Dónde están los datos dentro de esa base. No la creamos nosotros,
        | así que los nombres de la tabla y sus columnas se configuran en vez
        | de darlos por sabidos. `php artisan dni:diagnostico` los lista.
        */
        'tabla'   => env('RENSUN_TABLA', 'padron'),
        'columnas' => [
            'dni'               => env('RENSUN_COL_DNI', 'dni'),
            'nombres'           => env('RENSUN_COL_NOMBRES', 'nombres'),
            'apellido_paterno'  => env('RENSUN_COL_APE_PATERNO', 'apellido_paterno'),
            'apellido_materno'  => env('RENSUN_COL_APE_MATERNO', 'apellido_materno'),
            'fecha_nacimiento'  => env('RENSUN_COL_NACIMIENTO', 'fecha_nacimiento'),
            'direccion'         => env('RENSUN_COL_DIRECCION', 'direccion'),
        ],
    ],

];
