<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Area;
use App\Models\Cargo;
use App\Models\Contrato;
use App\Models\Empleado;
use App\Models\Rol;
use App\Models\Sede;
use App\Models\User;

class UsuarioDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        User::truncate();
        Empleado::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $rolAdmin    = Rol::where('nombre', 'admin')->firstOrFail();
        $rolRrhh     = Rol::where('nombre', 'rrhh')->firstOrFail();
        $rolEmpleado = Rol::where('nombre', 'empleado')->firstOrFail();

        $areaComunicacion = Area::where('nombre', 'Comunicación')->first();
        $cargoDocente      = Cargo::where('nombre', 'Docente')->first();
        $sedeCataTest      = Sede::where('nombre', 'CATA')->first();

        // ── Empleado + usuario de prueba (autoservicio) ──────────
        // Datos completos a propósito (área, cargo, sede, sueldo, AFP, cuenta
        // bancaria) para poder hacer el flujo completo (planilla -> boleta ->
        // firma) apenas se siembra la BD, sin que RRHH tenga que completar nada.
        $empleadoTest = Empleado::create([
            'dni'                => '12345678',
            'nombre'             => 'Test',
            'apellido'           => 'Usuario',
            'area_id'            => $areaComunicacion?->id,
            'cargo_id'           => $cargoDocente?->id,
            'sede_id'            => $sedeCataTest?->id,
            'telefono'           => '999999999',
            'direccion'          => 'Av. Lima 123',
            'fecha_ingreso'      => '2024-01-01',
            'fecha_nacimiento'   => '1990-05-15',
            'estado'             => 'activo',
            'sistema_pensiones'  => 'AFP',
            'afp'                => 'Integra',
            'cuspp'              => '12345678901',
            'entidad_financiera' => 'BCP',
            'numero_cuenta'      => '19412345678012',
            'tiene_hijos'        => true,
            'sueldo_base'        => 2500.00,
            'tipo_contrato'      => 'indeterminado',
            'forma_pago'         => 'banco',
        ]);

        User::create([
            'name'        => 'Test Usuario',
            'email'       => 'test@colegio.com',
            'password'    => Hash::make('empleado123'),
            'rol_id'      => $rolEmpleado->id,
            'empleado_id' => $empleadoTest->id,
        ]);

        Contrato::create([
            'empleado_id'   => $empleadoTest->id,
            'tipo_contrato' => 'indeterminado',
            'fecha_inicio'  => '2024-01-01',
            'estado'        => 'vigente',
            'observaciones' => 'Contrato de prueba (seeder)',
        ]);

        // ── Empleado + usuario Administrador ──────────────────────
        $empleadoAdmin = Empleado::create([
            'dni'           => '81577364',
            'nombre'        => 'Administrador',
            'apellido'      => 'Apellidos',
            'fecha_ingreso' => now()->toDateString(),
            'estado'        => 'activo',
        ]);

        User::create([
            'name'        => 'Administrador',
            'email'       => 'admin@colegio.com',
            'password'    => Hash::make('admin123'),
            'rol_id'      => $rolAdmin->id,
            'empleado_id' => $empleadoAdmin->id,
        ]);

        // ── Empleado + usuario Recursos Humanos ───────────────────
        $empleadoRrhh = Empleado::create([
            'dni'           => '81577382',
            'nombre'        => 'Recursos Humanos',
            'apellido'      => 'Apellidos',
            'fecha_ingreso' => now()->toDateString(),
            'estado'        => 'activo',
        ]);

        User::create([
            'name'        => 'Recursos Humanos',
            'email'       => 'rrhh@colegio.com',
            'password'    => Hash::make('rrhh123'),
            'rol_id'      => $rolRrhh->id,
            'empleado_id' => $empleadoRrhh->id,
        ]);

        // ── Empleado sin usuario (ejemplo de docente) ─────────────
        $areaReligion  = Area::where('nombre', 'Religión')->first();
        $cargoAuxiliar = Cargo::where('nombre', 'Auxiliar')->first();
        $sedeCata      = Sede::where('nombre', 'CATA')->first();

        Empleado::create([
            'dni'           => '60065632',
            'nombre'        => 'Deivi',
            'apellido'      => 'Apaza Lucana',
            'area_id'       => $areaReligion?->id,
            'cargo_id'      => $cargoAuxiliar?->id,
            'sede_id'       => $sedeCata?->id,
            'telefono'      => '111111111',
            'direccion'     => 'Jr. Gonzales Prada',
            'fecha_ingreso' => '2025-05-25',
            'estado'        => 'activo',
            'sueldo_base'   => 3000.00,
        ]);
    }
}
