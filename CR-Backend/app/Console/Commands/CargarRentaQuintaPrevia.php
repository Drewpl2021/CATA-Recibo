<?php

namespace App\Console\Commands;

use App\Services\CargaRentaQuintaPrevia;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Carga enero y febrero desde el Excel de 5ta de RR.HH. ("Calculo 5ta.xlsx").
 * Lo mismo que el botón de Ajustes del sistema → Renta de 5ta.
 *
 *   docker compose exec app php artisan renta5ta:cargar-previos /tmp/calculo.xlsx --anio=2026
 */
class CargarRentaQuintaPrevia extends Command
{
    protected $signature = 'renta5ta:cargar-previos
        {archivo : El Excel de cálculo de 5ta}
        {--anio=2026 : De qué año son enero y febrero}';

    protected $description = 'Carga lo cobrado y retenido en enero y febrero desde el Excel de 5ta de RR.HH.';

    public function handle(CargaRentaQuintaPrevia $carga): int
    {
        $ruta = $this->argument('archivo');
        if (! is_file($ruta)) {
            $this->error("No encuentro {$ruta}.");

            return self::FAILURE;
        }

        try {
            $r = $carga->cargar($ruta, (int) $this->option('anio'), basename($ruta));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Leídos {$r['leidos']} trabajadores; {$r['con_datos']} con enero o febrero cargado.");
        $this->info("Planillas con la 5ta recalculada: {$r['recalculadas']}.");
        foreach ($r['no_encontrados'] as $nombre) {
            $this->warn("No está en el sistema: {$nombre}");
        }

        return self::SUCCESS;
    }
}
