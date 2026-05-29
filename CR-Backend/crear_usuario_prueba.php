<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Str;
use App\Models\Empleado;
use App\Models\User;

// Buscar empleado existente o crear uno nuevo
$empleado = Empleado::where('dni', '12345678')->first();

if (!$empleado) {
    $empleado = Empleado::create([
        'id'            => Str::uuid(),
        'dni'           => '12345678',
        'nombre'        => 'Test',
        'apellido'      => 'Usuario',
        'cargo'         => 'Desarrollador',
        'area'          => 'TI',
        'telefono'      => '999999999',
        'direccion'     => 'Lima',
        'fecha_ingreso' => '2024-01-01',
        'estado'        => 'activo',
    ]);
}

// Crear usuario de prueba vinculado al empleado
$user = User::create([
    'id'          => Str::uuid(),
    'empleado_id' => $empleado->id,
    'name'        => 'Test Usuario',
    'email'       => 'test@colegio.com',
    'password'    => 'password123',
    'rol'         => 'empleado',
]);

echo "✅ ¡Creado exitosamente!\n";
echo "   Email:    test@colegio.com\n";
echo "   Password: password123\n";
echo "   Empleado: {$empleado->nombre} {$empleado->apellido} (ID: {$empleado->id})\n";
