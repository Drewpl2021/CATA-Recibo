<?php

namespace App\Console\Commands;

use App\Support\Portal\ImportadorDeEjemplos;
use Illuminate\Console\Command;
use Throwable;

/**
 * Carga en el módulo Portal un escenario de los ejemplos del contrato.
 *
 *   php artisan portal:importar              (tipico: el contenido real del colegio)
 *   php artisan portal:importar largo        (textos largos, para probar el portal)
 *   docker compose exec app php artisan portal:importar
 *
 * Lleva freno, como semilla:importar: reemplaza TODO el contenido del portal.
 * Si ya hay algo cargado, o si es el servidor de producción, no corre sin
 * --force. En producción, además, porque los ejemplos traen datos de prueba
 * (docentes, fechas) y fotos de un dominio que no existe
 * (medios.prueba.cata.edu.pe): publicarlos sería publicar lo que no es.
 */
class ImportarEjemplosDelPortal extends Command
{
    protected $signature = 'portal:importar
        {escenario=tipico : tipico, corto, largo, sin-foto, alt-vacio o vacio}
        {--ruta= : Carpeta de los ejemplos (por defecto tests/Fixtures/portal/mock)}
        {--force : Reemplazar el contenido que ya haya, o cargar en producción}';

    protected $description = 'Carga en el módulo Portal un escenario de los ejemplos del contrato';

    public function handle(): int
    {
        $escenario = $this->argument('escenario');
        $ruta = $this->option('ruta') ?: base_path('tests/Fixtures/portal/mock');

        if (! $this->option('force')) {
            if (app()->isProduction()) {
                $this->error('Esto es producción, y los ejemplos traen datos y fotos de prueba.');
                $this->warn('Si de verdad es lo que quieres: php artisan portal:importar ' . $escenario . ' --force');

                return self::FAILURE;
            }

            if (ImportadorDeEjemplos::hayContenido()) {
                $this->error('El portal ya tiene contenido cargado, y esto lo reemplaza entero.');
                $this->warn('Si aun así es lo que quieres: php artisan portal:importar ' . $escenario . ' --force');

                return self::FAILURE;
            }
        }

        try {
            $filas = (new ImportadorDeEjemplos($ruta, $escenario))->importar();
        } catch (Throwable $e) {
            $this->error('No se cargó nada: ' . $e->getMessage());
            $this->line('¿Corriste antes `php artisan migrate`? Esto trae el contenido, no las tablas.');

            return self::FAILURE;
        }

        $this->info("Escenario «{$escenario}» cargado.");
        $this->table(['Tabla', 'Filas'], collect($filas)->map(fn ($n, $tabla) => [$tabla, $n])->values()->all());

        return self::SUCCESS;
    }
}
