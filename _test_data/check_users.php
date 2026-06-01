<?php
/**
 * SCRIPT DE PRUEBA — check_users.php
 * Lista usuarios existentes y crea usuarios de prueba si no existen.
 */

require __DIR__ . '/../CR-Backend/vendor/autoload.php';

$app = require_once __DIR__ . '/../CR-Backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

echo "👥 Usuarios existentes en la BD:\n";
echo str_repeat("-", 60) . "\n";

$users = DB::table('users')->get(['name', 'email', 'rol', 'empleado_id']);

if ($users->isEmpty()) {
    echo "⚠️  No hay usuarios en la BD.\n";
} else {
    foreach ($users as $u) {
        echo "📧 Email: {$u->email} | Rol: {$u->rol} | Nombre: {$u->name}\n";
    }
}

echo "\n";

// ======= CREAR USUARIO ADMIN (si no existe) =======
$adminExists = DB::table('users')->where('email', 'admin@colegio.com')->exists();
if (!$adminExists) {
    DB::table('users')->insert([
        'name'       => 'Administrador',
        'email'      => 'admin@colegio.com',
        'password'   => Hash::make('admin123'),
        'rol'        => 'admin',
        'empleado_id'=> null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "✅ Usuario ADMIN creado:\n";
    echo "   Email:    admin@colegio.com\n";
    echo "   Password: admin123\n";
    echo "   Rol:      admin\n\n";
} else {
    echo "⏩ Usuario ADMIN ya existe (admin@colegio.com)\n\n";
}

// ======= CREAR USUARIO RRHH (si no existe) =======
$rrhhExists = DB::table('users')->where('email', 'rrhh@colegio.com')->exists();
if (!$rrhhExists) {
    DB::table('users')->insert([
        'name'       => 'Recursos Humanos',
        'email'      => 'rrhh@colegio.com',
        'password'   => Hash::make('rrhh123'),
        'rol'        => 'rrhh',
        'empleado_id'=> null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "✅ Usuario RRHH creado:\n";
    echo "   Email:    rrhh@colegio.com\n";
    echo "   Password: rrhh123\n";
    echo "   Rol:      rrhh\n\n";
} else {
    echo "⏩ Usuario RRHH ya existe (rrhh@colegio.com)\n\n";
}

// ======= CREAR USUARIO EMPLEADO (si no existe) =======
$empleadoExists = DB::table('users')->where('email', 'test@colegio.com')->exists();
if (!$empleadoExists) {
    DB::table('users')->insert([
        'name'       => 'Empleado de Prueba',
        'email'      => 'test@colegio.com',
        'password'   => Hash::make('empleado123'),
        'rol'        => 'empleado',
        'empleado_id'=> null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "✅ Usuario EMPLEADO creado:\n";
    echo "   Email:    test@colegio.com\n";
    echo "   Password: empleado123\n";
    echo "   Rol:      empleado\n\n";
} else {
    echo "⏩ Usuario EMPLEADO ya existe (test@colegio.com)\n\n";
}

echo str_repeat("=", 60) . "\n";
echo "🎉 ¡Listo! Resumen de credenciales de prueba:\n";
echo "   ADMIN:    admin@colegio.com    / admin123\n";
echo "   RRHH:     rrhh@colegio.com    / rrhh123\n";
echo "   EMPLEADO: test@colegio.com    / empleado123\n";
