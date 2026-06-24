<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $e = App\Models\Empleado::first();
    if (!$e) {
        echo "No hay empleados";
        exit;
    }
    
    $req = Illuminate\Http\Request::create('/api/empleados/' . $e->id, 'PUT', [
        'dni' => $e->dni,
        'nombre' => $e->nombre,
        'apellido' => $e->apellido,
        'cargo_id' => $e->cargo_id,
        'area_id' => $e->area_id,
        'estado' => 'Activo',
        'sueldo_base' => 2000,
        'sede_id' => $e->sede_id,
        'fecha_ingreso' => '2025-09-29'
    ]);
    
    // Simulate API request
    $req->headers->set('Accept', 'application/json');
    
    // Authenticate
    $user = App\Models\User::where('email', 'admin@colegio.com')->first();
    $app->make('auth')->guard('sanctum')->setUser($user);

    $res = app()->handle($req);
    echo "Status: " . $res->getStatusCode() . "\n";
    echo "Body: " . $res->getContent() . "\n";
} catch (\Throwable $ex) {
    echo "Exception: " . $ex->getMessage() . "\n" . $ex->getTraceAsString();
}
