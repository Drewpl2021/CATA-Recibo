<?php

/**
 * Las dos cuentas con las que se entra a un sistema recién instalado.
 *
 * Los valores salen del .env del despliegue, NO del código: una contraseña
 * escrita aquí acabaría en el repositorio, que es público, y sería la misma
 * en todas las instalaciones del mundo.
 *
 * Si no se rellenan en el .env, la siembra inventa una contraseña larga, la
 * enseña UNA vez por pantalla y obliga a cambiarla al entrar. Así un
 * despliegue apurado tampoco nace con "admin123".
 *
 * Va en config/ y no con env() suelto a propósito: el contenedor cachea la
 * configuración al arrancar (`config:cache`), y a partir de ahí env() devuelve
 * null en cualquier otro sitio.
 */
return [

    'admin' => [
        'nombre'   => env('ADMIN_NOMBRE', 'Administrador del sistema'),
        'email'    => env('ADMIN_EMAIL', 'admin@colegio.com'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'rrhh' => [
        'nombre'   => env('RRHH_NOMBRE', 'Recursos Humanos'),
        'email'    => env('RRHH_EMAIL', 'rrhh@colegio.com'),
        'password' => env('RRHH_PASSWORD'),
    ],

];
