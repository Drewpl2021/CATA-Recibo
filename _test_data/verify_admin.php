<?php
require __DIR__ . '/../CR-Backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../CR-Backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "======================================================\n";
echo "VERIFICACIÓN: Roles de usuarios admin/rrhh\n";
echo "======================================================\n\n";

$users = DB::table('users')
    ->leftJoin('roles', 'users.rol_id', '=', 'roles.id')
    ->select('users.name', 'users.email', 'roles.nombre as rol', 'users.rol_id', 'users.empleado_id')
    ->whereIn('users.email', ['admin@colegio.com', 'rrhh@colegio.com', 'test@colegio.com'])
    ->get();

foreach ($users as $u) {
    echo "Email: {$u->email}\n";
    echo "  Nombre: {$u->name}\n";
    echo "  rol_id: " . ($u->rol_id ?? 'NULL') . "\n";
    echo "  rol nombre: " . ($u->rol ?? 'NULL') . "\n";
    echo "  empleado_id: " . ($u->empleado_id ?? 'NULL') . "\n";
    echo "\n";
}

echo "======================================================\n";
echo "VERIFICACIÓN: Tabla roles\n";
echo "======================================================\n\n";

$roles = DB::table('roles')->get();
if ($roles->isEmpty()) {
    echo "⚠️  No hay roles en la tabla 'roles'\n";
} else {
    foreach ($roles as $r) {
        echo "ID: {$r->id} | Nombre: {$r->nombre}\n";
    }
}

echo "\n======================================================\n";
echo "DIAGNÓSTICO: ¿Puede admin ver 'Mis Boletas'?\n";
echo "======================================================\n\n";

$admin = DB::table('users')
    ->leftJoin('roles', 'users.rol_id', '=', 'roles.id')
    ->select('users.email', 'roles.nombre as rol', 'users.empleado_id')
    ->where('users.email', 'admin@colegio.com')
    ->first();

if ($admin) {
    $rolNombre = strtolower($admin->rol ?? '');
    echo "El usuario admin@colegio.com tiene:\n";
    echo "  - Rol: " . ($admin->rol ?? 'SIN ROL') . "\n";
    echo "  - empleado_id: " . ($admin->empleado_id ?? 'NULL (sin empleado vinculado)') . "\n\n";
    
    if (!$admin->empleado_id) {
        echo "❌ PROBLEMA ENCONTRADO:\n";
        echo "   El admin NO tiene un 'empleado_id' vinculado.\n";
        echo "   El endpoint 'mis-boletas' en el backend requiere que el usuario\n";
        echo "   tenga un empleado vinculado para descargar su boleta.\n";
        echo "   (Línea 17-23 en MiBoletaController.php)\n\n";
        echo "💡 SOLUCIÓN: Tu compañero Jordan debe crear un registro de Empleado\n";
        echo "   para el admin y asignarlo en el campo 'empleado_id' del usuario.\n";
    } else {
        echo "✅ El admin SÍ tiene empleado vinculado. Debería poder ver sus boletas.\n";
    }
}
