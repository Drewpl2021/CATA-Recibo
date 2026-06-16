<?php
require __DIR__ . '/../CR-Backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../CR-Backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Empleado;
use App\Models\Rol;

echo "======================================================\n";
echo "ARREGLANDO CUENTAS ADMIN Y RRHH\n";
echo "======================================================\n";

$roles = Rol::all()->keyBy('nombre');
$rolAdmin = $roles['admin'] ?? null;
$rolRrhh = $roles['rrhh'] ?? null;
$rolEmpleado = $roles['empleado'] ?? null;

if (!$rolAdmin) {
    echo "❌ Error: El rol 'admin' no existe en la BD.\n";
    exit;
}

$usuarios = User::whereIn('email', ['admin@colegio.com', 'rrhh@colegio.com'])->get();
$counter = 1;

foreach ($usuarios as $user) {
    if (!$user->empleado_id) {
        $empleado = Empleado::create([
            'nombre' => $user->name,
            'apellido' => 'Apellidos',
            'dni' => substr(time(), -8) + $counter,
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'activo'
        ]);
        $user->empleado_id = $empleado->id;
        echo "✅ Creado empleado_id para {$user->email}: {$empleado->id}\n";
    }

    if ($user->email === 'admin@colegio.com') {
        $user->rol_id = $rolAdmin->id;
    } else {
        $user->rol_id = $rolRrhh->id;
    }
    
    $user->save();
    echo "✅ Asignado rol_id para {$user->email}\n";
    $counter++;
}

echo "\n======================================================\n";
echo "Todo listo. Ahora admin@colegio.com tiene empleado_id y rol_id.\n";
