<?php
require __DIR__ . '/../CR-Backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../CR-Backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "======================================================\n";
echo "  Áreas en la BD\n";
echo "======================================================\n";
$areas = DB::table('areas')->get();
if ($areas->isEmpty()) {
    echo "⚠️  La tabla 'areas' está VACÍA.\n";
} else {
    foreach ($areas as $a) {
        echo "  ID: {$a->id} | Nombre: {$a->nombre}\n";
    }
}

echo "\n======================================================\n";
echo "  Cargos en la BD\n";
echo "======================================================\n";
$cargos = DB::table('cargos')->get();
if ($cargos->isEmpty()) {
    echo "⚠️  La tabla 'cargos' está VACÍA.\n";
} else {
    foreach ($cargos as $c) {
        echo "  ID: {$c->id} | Nombre: {$c->nombre}\n";
    }
}

echo "\n======================================================\n";
echo "  Empleados en la BD\n";
echo "======================================================\n";
$empleados = DB::table('empleados')
    ->leftJoin('areas', 'empleados.area_id', '=', 'areas.id')
    ->leftJoin('cargos', 'empleados.cargo_id', '=', 'cargos.id')
    ->select('empleados.nombre', 'empleados.apellido', 'empleados.dni', 'areas.nombre as area', 'cargos.nombre as cargo', 'empleados.estado')
    ->get();

if ($empleados->isEmpty()) {
    echo "⚠️  La tabla 'empleados' está VACÍA.\n";
    echo "   (Esto es normal si aún no has registrado empleados reales)\n";
} else {
    foreach ($empleados as $e) {
        echo "  {$e->nombre} {$e->apellido} | DNI: {$e->dni} | Cargo: {$e->cargo} | Área: {$e->area} | Estado: {$e->estado}\n";
    }
}
