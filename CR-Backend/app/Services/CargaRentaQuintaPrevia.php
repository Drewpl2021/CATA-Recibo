<?php

namespace App\Services;

use App\Models\Empleado;
use App\Models\Planilla;
use App\Models\RentaQuintaPrevia;
use App\Traits\CalculaConceptosPlanilla;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Lee enero y febrero del Excel de 5ta de RR.HH. ("Calculo 5ta.xlsx") y los
 * guarda en RentaQuintaPrevia. Lo usan la pantalla de Ajustes y el comando
 * `renta5ta:cargar-previos`.
 *
 * Las columnas se buscan por su título (MODULAR, Enero, Febrero, IRQ
 * DESCONTADO ENERO / FEBRERO), así que no importa en qué letra estén. Se
 * puede cargar las veces que haga falta: pisa lo de antes de ese año. Al
 * final se recalcula la 5ta de las planillas de marzo en adelante.
 */
class CargaRentaQuintaPrevia
{
    use CalculaConceptosPlanilla;

    /** Título de la columna → qué es. */
    private const COLUMNAS = [
        'modular'                => 'dni',
        'paterno'                => 'nombre',
        'enero'                  => 'cobrado_1',
        'febrero'                => 'cobrado_2',
        'irq descontado enero'   => 'retenido_1',
        'irq descontado febrero' => 'retenido_2',
    ];

    /**
     * @return array{leidos:int, con_datos:int, recalculadas:int, no_encontrados:string[]}
     */
    public function cargar(string $ruta, int $anio, string $origen): array
    {
        $libro = IOFactory::load($ruta);
        $hoja = $this->hojaConLasColumnas($libro);

        $filas = $hoja->toArray(null, true, false, false);
        $titulos = array_map(fn ($t) => $this->normalizar((string) $t), array_shift($filas));
        $indice = [];
        foreach (self::COLUMNAS as $titulo => $clave) {
            $indice[$clave] = array_search($titulo, $titulos, true);
        }

        $leidos = 0;
        $faltan = [];
        $afectados = [];
        $numero = fn ($v) => round((float) $v, 2);

        $progreso = \App\Support\Progreso::actual()->etapa('Leyendo enero y febrero de cada trabajador', count($filas));
        DB::transaction(function () use ($filas, $indice, $anio, $origen, $numero, $progreso, &$leidos, &$faltan, &$afectados) {
            foreach ($filas as $fila) {
                $progreso->avanzar();
                $dni = ltrim(trim((string) ($fila[$indice['dni']] ?? '')), '0');
                if ($dni === '' || ! ctype_alnum($dni)) {
                    continue;
                }

                $empleado = Empleado::whereIn('dni', [$dni, str_pad($dni, 8, '0', STR_PAD_LEFT), str_pad($dni, 9, '0', STR_PAD_LEFT)])->first();
                if (! $empleado) {
                    $faltan[] = trim((string) ($fila[$indice['nombre']] ?? '')) . " (DNI {$dni})";
                    continue;
                }

                foreach ([1, 2] as $mes) {
                    $cobrado  = $numero($fila[$indice["cobrado_{$mes}"]] ?? 0);
                    $retenido = $numero($fila[$indice["retenido_{$mes}"]] ?? 0);
                    $clave = ['empleado_id' => $empleado->id, 'anio' => $anio, 'mes' => $mes];

                    if ($cobrado <= 0 && $retenido <= 0) {
                        RentaQuintaPrevia::where($clave)->delete();
                        continue;
                    }

                    RentaQuintaPrevia::updateOrCreate($clave, ['remuneracion' => $cobrado, 'retencion' => $retenido, 'origen' => $origen]);
                    $afectados[$empleado->id] = $empleado;
                }
                $leidos++;
            }
        });

        // Su 5ta de marzo en adelante cambia: se recalcula la de las planillas que ya existan.
        $recalculadas = 0;
        $progreso->etapa('Recalculando la 5ta de sus planillas', count($afectados));
        foreach ($afectados as $empleado) {
            $progreso->avanzar();
            Planilla::where('empleado_id', $empleado->id)->where('anio', $anio)->where('mes', '>=', 3)->get()
                ->each(function ($planilla) use ($empleado, &$recalculadas) {
                    $this->generarYPersistirRenta5ta($planilla, $empleado);
                    $recalculadas++;
                });
        }

        return [
            'leidos'         => $leidos,
            'con_datos'      => count($afectados),
            'recalculadas'   => $recalculadas,
            'no_encontrados' => $faltan,
        ];
    }

    /** La primera hoja que tenga todas las columnas que hacen falta. */
    private function hojaConLasColumnas($libro)
    {
        foreach ($libro->getWorksheetIterator() as $hoja) {
            $primera = $hoja->rangeToArray('A1:' . $hoja->getHighestColumn() . '1', null, false, false)[0] ?? [];
            $titulos = array_map(fn ($t) => $this->normalizar((string) $t), $primera);
            if (array_diff(array_keys(self::COLUMNAS), $titulos) === []) {
                return $hoja;
            }
        }

        throw new RuntimeException('El Excel no tiene las columnas MODULAR, PATERNO, Enero, Febrero, IRQ DESCONTADO ENERO e IRQ DESCONTADO FEBRERO en su primera fila.');
    }

    private function normalizar(string $texto): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $texto)));
    }
}
