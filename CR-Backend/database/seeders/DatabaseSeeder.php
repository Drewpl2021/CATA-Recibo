<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;

/**
 * La siembra de una instalación NUEVA.
 *
 * Deja el sistema listo para empezar a trabajar y nada más: los roles, el
 * menú, las áreas, los cargos, los conceptos de pago, las cuatro sedes y las
 * dos cuentas con las que se entra (administrador y RR.HH.).
 *
 * NO siembra personal. Antes dejaba veinte trabajadores de ejemplo con sus
 * contratos, y en una instalación de verdad eso es basura que hay que borrar
 * a mano antes de empezar —con el riesgo de que alguno sobreviva y acabe en
 * una planilla—. El personal se da de alta desde la pantalla de Empleados,
 * o en lote desde Importar empleados.
 *
 * Para una demo con gente dentro (desarrollo, capacitación):
 *
 *     php artisan db:seed --class=UsuarioDemoSeeder
 *
 * Esa sí crea las fichas de ejemplo, y es la que usa la colección de Postman.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Antes que nada: ¿están las tablas?
        //
        // Sembrar sobre una base sin migrar reventaba con un
        // "Base table or view not found: area_cargo", que no le dice a nadie
        // qué hacer. Pasa más de lo que parece: las migraciones corren al
        // arrancar el contenedor y tardan unos segundos, así que un db:seed
        // lanzado de inmediato llega antes que ellas.
        $faltan = array_filter(
            ['roles', 'areas', 'cargos', 'area_cargo', 'sedes', 'modulos', 'users'],
            fn (string $tabla) => ! \Illuminate\Support\Facades\Schema::hasTable($tabla)
        );

        if ($faltan) {
            $this->command?->error('  Esta base todavía no tiene las tablas: falta ' . implode(', ', $faltan) . '.');
            $this->command?->warn('  Corre primero las migraciones y vuelve a sembrar:');
            $this->command?->warn('      php artisan migrate --force');
            $this->command?->warn('      php artisan db:seed --force');
            $this->command?->warn('  O las dos cosas de un tirón, si quieres empezar de cero:');
            $this->command?->warn('      php artisan migrate:fresh --seed --force');

            return;
        }

        // Freno de mano: esta siembra vacía los catálogos y vuelve a crear los
        // roles, así que en un sistema que ya está en uso dejaría al personal
        // sin área, sin cargo y sin permisos. Si ya hay gente dentro, no se
        // corre sola.
        $usuarios  = \App\Models\User::count();
        $empleados = \App\Models\Empleado::count();

        if (($usuarios || $empleados) && ! env('SEMBRAR_IGUAL')) {
            $this->command?->error("  Esta base YA tiene datos: {$usuarios} usuario(s) y {$empleados} trabajador(es).");
            $this->command?->warn('  db:seed es para una instalación nueva. Para sembrar una cosa suelta:');
            $this->command?->warn('      php artisan db:seed --class=PaymentConceptSeeder');
            $this->command?->warn('  Y si de verdad quieres rehacerlo todo, sabiendo que se pierde lo que hay:');
            $this->command?->warn('      SEMBRAR_IGUAL=1 php artisan db:seed --force');

            return;
        }

        $this->call([
            RolSeeder::class,
            AreaSeeder::class,
            CargoSeeder::class,
            PaymentConceptSeeder::class,
            ModuloSeeder::class,
            SedeSeeder::class,
            CuentasInicialesSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('Sistema listo. Entra con la cuenta de administrador y da de alta al personal.');
    }
}
