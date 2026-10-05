<?php

namespace App\Console\Commands;

use App\Models\Empleado;
use App\Models\Planilla;
use App\Models\RentaQuintaPrevia;
use App\Traits\CalculaConceptosPlanilla;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Carga enero y febrero desde el Excel de 5ta de RR.HH. ("Calculo 5ta.xlsx").
 *
 *   php artisan renta5ta:cargar-previos "postman/plame/Calculo 5ta.xlsx"
 *   docker compose exec app php artisan renta5ta:cargar-previos /tmp/calculo.xlsx --anio=2026
 *
 * Lee las columnas por su título (MODULAR, Enero, Febrero, IRQ DESCONTADO
 * ENERO / FEBRERO), así que no importa en qué letra estén. Se puede correr
 * las veces que haga falta: pisa lo cargado antes para ese año. Al final
 * recalcula la 5ta de las planillas de marzo en adelante de esa gente.
 */
class CargarRentaQuintaPrevia extends Command
{
    use CalculaConceptosPlanilla;

    protected $signature = 'renta5ta:cargar-previos
        {archivo : El Excel de cálculo de 5ta}
        {--anio=2026 : De qué año son enero y febrero}
        {--hoja=Hoja1 : La hoja que se lee}';

    protected $description = 'Carga lo cobrado y retenido en enero y febrero desde el Excel de 5ta de RR.HH.';

    /** Título de la columna → qué es. */
    private const COLUMNAS = [
        'modular'                => 'dni',
        'paterno'                => 'nombre',
        'enero'                  => 'cobrado_1',
        'febrero'                => 'cobrado_2',
        'irq descontado enero'   => 'retenido_1',
        'irq descontado febrero' => 'retenido_2',
    ];

    public function handle(): int
    {
        $ruta = $this->argument('archivo');
        if (! is_file($ruta)) {
            $this->error("No encuentro {$ruta}.");

            return self::FAILURE;
        }

        $anio = (int) $this->option('anio');
        $hoja = IOFactory::load($ruta)->getSheetByName($this->option('hoja'));
        if (! $hoja) {
            $this->error('No encuentro la hoja ' . $this->option('hoja') . '.');

            return self::FAILURE;
        }

        $filas = $hoja->toArray(null, true, false, false);
        $titulos = array_map(fn ($t) => mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $t))), array_shift($filas));
        $indice = [];
        foreach (self::COLUMNAS as $titulo => $clave) {
            $pos = array_search($titulo, $titulos, true);
            if ($pos === false) {
                $this->error("Falta la columna \"{$titulo}\" en la primera fila.");

                return self::FAILURE;
            }
            $indice[$clave] = $pos;
        }

        $cargados = 0;
        $faltan = [];
        $afectados = [];
        $numero = fn ($v) => round((float) $v, 2);

        DB::transaction(function () use ($filas, $indice, $anio, $ruta, $numero, &$cargados, &$faltan, &$afectados) {
            foreach ($filas as $fila) {
                $dni = ltrim(trim((string) ($fila[$indice['dni']] ?? '')), '0');
                if ($dni === '') {
                    continue;
                }

                $empleado = Empleado::whereIn('dni', [$dni, str_pad($dni, 8, '0', STR_PAD_LEFT), str_pad($dni, 9, '0', STR_PAD_LEFT)])->first();
                if (! $empleado) {
                    $faltan[] = trim((string) $fila[$indice['nombre']]) . " (DNI {$dni})";
                    continue;
                }

                foreach ([1, 2] as $mes) {
                    $cobrado  = $numero($fila[$indice["cobrado_{$mes}"]] ?? 0);
                    $retenido = $numero($fila[$indice["retenido_{$mes}"]] ?? 0);

                    if ($cobrado <= 0 && $retenido <= 0) {
                        RentaQuintaPrevia::where(['empleado_id' => $empleado->id, 'anio' => $anio, 'mes' => $mes])->delete();
                        continue;
                    }

                    RentaQuintaPrevia::updateOrCreate(
                        ['empleado_id' => $empleado->id, 'anio' => $anio, 'mes' => $mes],
                        ['remuneracion' => $cobrado, 'retencion' => $retenido, 'origen' => basename($ruta)]
                    );
                    $afectados[$empleado->id] = $empleado;
                }
                $cargados++;
            }
        });

        // Su 5ta de marzo en adelante cambia: se recalcula la de las planillas que ya existan.
        $recalculadas = 0;
        foreach ($afectados as $empleado) {
            Planilla::where('empleado_id', $empleado->id)->where('anio', $anio)->where('mes', '>=', 3)->get()
                ->each(function ($planilla) use ($empleado, &$recalculadas) {
                    $this->generarYPersistirRenta5ta($planilla, $empleado);
                    $recalculadas++;
                });
        }

        $this->info("Leídos {$cargados} trabajadores; " . count($afectados) . " con enero o febrero cargado.");
        $this->info("Planillas con la 5ta recalculada: {$recalculadas}.");
        foreach ($faltan as $nombre) {
            $this->warn("No está en el sistema: {$nombre}");
        }

        return self::SUCCESS;
    }
}
