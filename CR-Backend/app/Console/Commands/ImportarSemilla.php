<?php

namespace App\Console\Commands;

use App\Models\Empleado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Carga los catálogos guardados por `semilla:exportar`.
 *
 *   php artisan semilla:importar
 *   docker compose exec app php artisan semilla:importar
 *
 * Sirve para dejar un servidor listo sin correr los seeders: se migra y se
 * carga esto. Es la misma información que deja `db:seed`, así que en una
 * instalación nueva da igual cuál de los dos uses.
 *
 * Lleva freno: la carga vacía los catálogos antes de escribirlos, y si ya hay
 * trabajadores dados de alta, esos catálogos son a los que apuntan sus
 * fichas. Con gente dentro no se corre sin decirlo dos veces.
 */
class ImportarSemilla extends Command
{
    protected $signature = 'semilla:importar
        {--archivo= : De dónde leerlo (por defecto database/semilla/instalacion-basica.sql)}
        {--force : Cargarla aunque ya haya trabajadores dados de alta}';

    protected $description = 'Carga los catálogos del sistema desde el .sql de la semilla';

    public function handle(): int
    {
        $ruta = $this->option('archivo') ?: database_path('semilla/instalacion-basica.sql');

        if (! is_file($ruta)) {
            $this->error("No encuentro {$ruta}.");
            $this->line('Genera la semilla con: php artisan semilla:exportar');

            return self::FAILURE;
        }

        $empleados = Empleado::count();

        if ($empleados > 0 && ! $this->option('force')) {
            $this->error("Esta base ya tiene {$empleados} trabajador(es) dados de alta.");
            $this->warn('Cargar la semilla rehace las áreas, los cargos y las sedes, que son');
            $this->warn('justo a lo que apuntan sus fichas. Si aun así es lo que quieres:');
            $this->warn('    php artisan semilla:importar --force');

            return self::FAILURE;
        }

        try {
            DB::unprepared(file_get_contents($ruta));
        } catch (Throwable $e) {
            $this->error('No se pudo cargar: ' . $e->getMessage());
            $this->line('¿Corriste antes `php artisan migrate`? La semilla trae los datos, no las tablas.');

            return self::FAILURE;
        }

        $this->info('Catálogos cargados desde ' . $ruta);

        foreach (ExportarSemilla::TABLAS as $tabla) {
            $this->line(sprintf('   %-18s %d', $tabla, DB::table($tabla)->count()));
        }

        $this->newLine();
        $this->line('Las cuentas de acceso no vienen en la semilla: se crean con');
        $this->line('`php artisan db:seed --class=CuentasInicialesSeeder`, que las toma del .env.');

        return self::SUCCESS;
    }
}
