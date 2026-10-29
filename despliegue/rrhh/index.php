<?php

/*
 * Puente entre /public_html/rrhh y el backend Laravel, que vive FUERA de
 * public_html, en /apps/rrhh_backend (así su código, su .env y las imágenes
 * subidas no se pueden descargar desde internet).
 *
 * Apache manda aquí solo las direcciones https://cata.edu.pe/rrhh/api/… (ver
 * el .htaccess de esta carpeta). Laravel ve la ruta completa «api/…», igual
 * que si estuviera en su propia carpeta public/. Todo lo demás es el Angular.
 *
 * Si el backend está en otra carpeta, cambia solo la línea de $backend.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// /home/USUARIO/public_html/rrhh  →  /home/USUARIO/apps/rrhh_backend
$backend = dirname(__DIR__, 2) . '/apps/rrhh_backend';

// Modo mantenimiento (php artisan down).
if (file_exists($mantenimiento = $backend . '/storage/framework/maintenance.php')) {
    require $mantenimiento;
}

require $backend . '/vendor/autoload.php';

/** @var Application $app */
$app = require_once $backend . '/bootstrap/app.php';

$app->handleRequest(Request::capture());
