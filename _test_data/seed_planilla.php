<?php
/**
 * SCRIPT DE PRUEBA — seed_planilla.php
 * Crea 12 registros de planilla (Enero a Diciembre 2026) para el usuario de prueba.
 * NO modificar el código del proyecto real. Solo para testing local.
 */

require __DIR__ . '/../CR-Backend/vendor/autoload.php';

$app = require_once __DIR__ . '/../CR-Backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

// Buscar el empleado de prueba
$empleado = DB::table('empleados')->where('dni', '12345678')->first();

if (!$empleado) {
    echo "❌ No se encontró el empleado de prueba. Ejecuta primero crear_usuario_prueba.php\n";
    exit(1);
}

echo "👤 Empleado encontrado: {$empleado->nombre} {$empleado->apellido}\n\n";

$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$anio = 2026;
$insertados = 0;

foreach ($meses as $mes => $nombre) {
    // Verificar si ya existe
    $existe = DB::table('planilla')
        ->where('empleado_id', $empleado->id)
        ->where('mes', $mes)
        ->where('anio', $anio)
        ->exists();

    if ($existe) {
        echo "⏩ {$nombre} {$anio} ya existe, omitiendo...\n";
        continue;
    }

    $sueldo_base    = 2500.00;
    $bonificaciones = rand(100, 500) + (rand(0, 99) / 100);
    $descuentos     = rand(50, 200) + (rand(0, 99) / 100);
    $total          = $sueldo_base + $bonificaciones - $descuentos;

    DB::table('planilla')->insert([
        'id'             => Str::uuid(),
        'empleado_id'    => $empleado->id,
        'mes'            => $mes,
        'anio'           => $anio,
        'sueldo_base'    => $sueldo_base,
        'bonificaciones' => round($bonificaciones, 2),
        'descuentos'     => round($descuentos, 2),
        'total'          => round($total, 2),
        'created_at'     => now()->setMonth($mes)->setDay(rand(25, 28)),
        'updated_at'     => now()->setMonth($mes)->setDay(rand(25, 28)),
    ]);

    echo "✅ {$nombre} {$anio} — Sueldo: S/ {$sueldo_base} | Bonif: S/ " . round($bonificaciones,2) . " | Desc: S/ " . round($descuentos,2) . " | Total: S/ " . round($total,2) . "\n";
    $insertados++;
}

echo "\n🎉 ¡Listo! Se insertaron {$insertados} registros de planilla para el año {$anio}.\n";
