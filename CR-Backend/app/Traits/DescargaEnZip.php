<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bajar en un .zip las hojas de muchos trabajadores de una vez (CTS,
 * gratificación), del mismo modo que las boletas (EmpleadoController::
 * boletasEnZip): la pantalla pide un enlace firmado que vence en 10
 * minutos y el .zip lo baja el NAVEGADOR, no la página —bajado por dentro,
 * un antivirus o un gestor de descargas lo cortaba a la mitad—.
 *
 * Los marcados no caben en la dirección: se guardan unos minutos en el
 * servidor y el enlace lleva solo su referencia, firmada con lo demás.
 */
trait DescargaEnZip
{
    /** El enlace firmado a la ruta $nombreRuta, con los marcados (si llegaron) guardados aparte. */
    protected function enlaceAlZip(Request $request, string $nombreRuta, array $parametros, int $cantidad): JsonResponse
    {
        if ($request->filled('ids')) {
            $referencia = (string) Str::uuid();
            Cache::put('zip-marcados:' . $referencia, array_values($request->input('ids')), now()->addMinutes(15));
            $parametros['marcados'] = $referencia;
        }

        $url = URL::temporarySignedRoute(
            $nombreRuta,
            now()->addMinutes(10),
            $parametros + ['usuario' => $request->user()->id],
            absolute: false
        );

        return response()->json(['success' => true, 'data' => ['url' => $url, 'cantidad' => $cantidad]]);
    }

    /**
     * Los marcados que trae el pedido: del cuerpo al pedir el enlace, o de la
     * referencia al bajarlo. null = no se marcó a nadie (van todos);
     * false = la referencia venció.
     */
    protected function marcadosDelPedido(Request $request): array|false|null
    {
        if ($request->filled('marcados')) {
            $ids = Cache::get('zip-marcados:' . $request->query('marcados'));

            return is_array($ids) ? $ids : false;
        }

        return $request->filled('ids') ? array_values($request->input('ids')) : null;
    }

    /** La persona del enlace sigue activa y con el módulo: si no, el enlace ya no vale. */
    protected function exigirQuienPidioElZip(Request $request, string $modulo): void
    {
        $usuario = \App\Models\User::find($request->query('usuario'));
        if (! $usuario || $usuario->estado_registro !== 'activo' || ! $usuario->puede($modulo)) {
            abort(403, 'Este enlace ya no es válido para tu cuenta.');
        }
    }

    /**
     * Arma el .zip. $hojas es [nombre del archivo => fn (): ?string], que
     * devuelve los bytes del PDF o null si no se pudo; las que fallan se
     * listan en NO_INCLUIDAS.txt en vez de cortar la descarga entera.
     * $sinHoja son los marcados que no tienen hoja («Quispe Ana: su CTS
     * todavía no se calculó»): van en el mismo archivo, para que nadie
     * crea que se perdieron.
     *
     * @param  array<string, callable(): ?string>  $hojas
     * @param  list<string>  $sinHoja
     */
    protected function zipDeHojas(array $hojas, string $nombreZip, string $etapa, array $sinHoja = []): BinaryFileResponse
    {
        $ruta = tempnam(sys_get_temp_dir(), 'hojas');
        $zip = new \ZipArchive();
        $zip->open($ruta, \ZipArchive::OVERWRITE);

        $faltan = [];
        $progreso = \App\Support\Progreso::actual()->etapa($etapa, count($hojas));
        foreach ($hojas as $nombre => $contenido) {
            $progreso->avanzar();
            try {
                $bytes = $contenido();
            } catch (\Throwable $e) {
                report($e);
                $bytes = null;
            }
            $bytes === null ? $faltan[] = $nombre : $zip->addFromString($nombre, $bytes);
        }

        $aviso = '';
        if ($sinHoja) {
            $aviso .= "Marcados que no tienen hoja:\n\n" . implode("\n", $sinHoja) . "\n\n";
        }
        if ($faltan) {
            $aviso .= "Estas hojas no se pudieron armar. Ábrelas una por una desde el sistema:\n\n" . implode("\n", $faltan) . "\n";
        }
        if ($aviso !== '') {
            $zip->addFromString('NO_INCLUIDAS.txt', $aviso);
        }
        $zip->close();

        return response()->download($ruta, $nombreZip, ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    /** Los marcados ($ids) que no están entre $conHoja: «Apellido Nombre (DNI …): $motivo». */
    protected function marcadosSinHoja(?array $ids, array $conHoja, string $motivo): array
    {
        if ($ids === null) {
            return [];
        }

        return \App\Models\Empleado::whereIn('id', array_diff($ids, $conHoja))
            ->orderBy('apellido')->orderBy('nombre')->get(['dni', 'nombre', 'apellido'])
            ->map(fn ($e) => "{$e->apellido} {$e->nombre} (DNI {$e->dni}): {$motivo}")
            ->all();
    }

    /** «Quispe Mamani Ana» → «Quispe_Mamani_Ana», para el nombre del archivo. */
    protected function nombreParaArchivo(string $texto): string
    {
        return (string) Str::of($texto)->ascii()->replaceMatches('/[^A-Za-z0-9 ]/', '')->squish()->replace(' ', '_');
    }
}
