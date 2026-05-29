<?php
/**
 * SCRIPT DE PRUEBA — limpiar_datos.php
 * Elimina TODOS los datos de prueba de la BD.
 * Úsalo cuando quieras resetear y empezar de cero.
 */

require __DIR__ . '/../CR-Backend/vendor/autoload.php';

$app = require_once __DIR__ . '/../CR-Backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🗑️  Limpiando datos de prueba...\n\n";

// Buscar el empleado de prueba
$empleado = DB::table('empleados')->where('dni', '12345678')->first();

if ($empleado) {
    $planilla  = DB::table('planilla')->where('empleado_id', $empleado->id)->delete();
    $docs      = DB::table('documentos')->where('empleado_id', $empleado->id)->delete();
    $vacas     = DB::table('vacaciones')->where('empleado_id', $empleado->id)->delete();
    $descs     = DB::table('descuentos')->where('empleado_id', $empleado->id)->delete();
    echo "✅ Planilla eliminada: {$planilla} registros\n";
    echo "✅ Documentos eliminados: {$docs} registros\n";
    echo "✅ Vacaciones eliminadas: {$vacas} registros\n";
    echo "✅ Descuentos eliminados: {$descs} registros\n";
}

$tokens = DB::table('personal_access_tokens')->where('tokenable_type', 'App\Models\User')->delete();
echo "✅ Tokens eliminados: {$tokens}\n";

$users = DB::table('users')->where('email', 'test@colegio.com')->delete();
echo "✅ Usuario de prueba eliminado: {$users}\n";

if ($empleado) {
    DB::table('empleados')->where('dni', '12345678')->delete();
    echo "✅ Empleado de prueba eliminado\n";
}

echo "\n🎉 ¡Limpieza completa! La BD está lista para datos reales.\n";
