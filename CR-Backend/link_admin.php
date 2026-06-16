require __DIR__ . '/../CR-Backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../CR-Backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$adminId = Str::uuid()->toString();
$rrhhId = Str::uuid()->toString();

DB::table('empleados')->insert([
    ['id' => $adminId, 'nombre' => 'Admin', 'apellido' => 'General', 'dni' => '00000001', 'fecha_ingreso' => '2020-01-01', 'area_id' => null, 'cargo_id' => null, 'estado' => 'Activo'],
    ['id' => $rrhhId, 'nombre' => 'Recursos', 'apellido' => 'Humanos', 'dni' => '00000002', 'fecha_ingreso' => '2020-01-01', 'area_id' => null, 'cargo_id' => null, 'estado' => 'Activo']
]);

DB::table('users')->where('email', 'admin@colegio.com')->update(['empleado_id' => $adminId]);
DB::table('users')->where('email', 'rrhh@colegio.com')->update(['empleado_id' => $rrhhId]);

echo 'Empleados creados y enlazados para Admin y RRHH.';
