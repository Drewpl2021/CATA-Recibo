<?php
/**
 * SCRIPT — reset_passwords.php
 * Resetea contraseñas de los usuarios existentes y crea faltantes.
 */

require __DIR__ . '/../CR-Backend/vendor/autoload.php';

$app = require_once __DIR__ . '/../CR-Backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Reset admin@colegio.com -> admin123, rol: admin
DB::table('users')->where('email', 'admin@colegio.com')->update([
    'password'   => Hash::make('admin123'),
    'rol'        => 'admin',
    'updated_at' => now(),
]);
echo "✅ admin@colegio.com -> password: admin123 | rol: admin\n";

// Reset test@colegio.com -> empleado123, rol: empleado
DB::table('users')->where('email', 'test@colegio.com')->update([
    'password'   => Hash::make('empleado123'),
    'rol'        => 'empleado',
    'updated_at' => now(),
]);
echo "✅ test@colegio.com  -> password: empleado123 | rol: empleado\n";

// Verificar que rrhh@colegio.com exista
$rrhhExists = DB::table('users')->where('email', 'rrhh@colegio.com')->exists();
if ($rrhhExists) {
    DB::table('users')->where('email', 'rrhh@colegio.com')->update([
        'password'   => Hash::make('rrhh123'),
        'updated_at' => now(),
    ]);
    echo "✅ rrhh@colegio.com  -> password: rrhh123  | rol: rrhh\n";
}

echo "\n============================================================\n";
echo "🎉 ¡Credenciales listas para usar en el login:\n";
echo "   ADMIN:    admin@colegio.com    / admin123\n";
echo "   RRHH:     rrhh@colegio.com    / rrhh123\n";
echo "   EMPLEADO: test@colegio.com    / empleado123\n";
