<?php

namespace App\Support;

use App\Models\Documento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Guardar un archivo en el expediente de un trabajador, en UN solo sitio.
 *
 * Suben archivos tres caminos distintos —RR.HH. desde la ficha o desde
 * Contratos, el propio trabajador desde Mis Documentos y la importación de
 * empleados con sus CVs en lote— y los tres tienen que hacer lo mismo: poner
 * el nombre del archivo en el servidor, dejarlo en la carpeta del trabajador
 * y, si es una hoja de vida, dar de baja la anterior. Con la lógica copiada
 * en tres controladores, bastaba con arreglar uno para que los otros dos
 * siguieran guardando distinto.
 */
final class ExpedienteDigital
{
    public const HOJA_DE_VIDA = 'hoja_de_vida';

    /**
     * Los documentos que NO se firman.
     *
     * La hoja de vida es del trabajador, no algo que el colegio le entrega:
     * pedirle que "firme" su propio CV no significa nada, y además le
     * aparecía como pendiente en la campana y en el recuadro de "Por firmar".
     */
    public const SIN_FIRMA = [self::HOJA_DE_VIDA];

    /**
     * Qué se acepta, igual en todos los caminos. Word entra a propósito: la
     * mitad de las hojas de vida llegan en .docx, y obligar a convertirlas a
     * PDF es trabajo que RR.HH. acabaría haciendo a mano.
     */
    public const REGLA_ARCHIVO = 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120';

    public static function seFirma(?string $tipo): bool
    {
        return ! in_array($tipo, self::SIN_FIRMA, true);
    }

    /**
     * Guarda el archivo y registra el documento.
     *
     * @param  array  $extra  columnas adicionales del documento (contrato_id, firmado_por).
     */
    public static function guardar(string $empleadoId, string $tipo, UploadedFile $archivo, array $extra = []): Documento
    {
        // El nombre lo pone el servidor. El que trae el archivo del usuario
        // puede venir con barras, con tildes o repetido, y acabaría pisando
        // el documento de otro o saliéndose de su carpeta.
        $ruta = sprintf(
            'documentos/%s/%s-%s-%s.%s',
            $empleadoId,
            $tipo,
            now()->format('Ymd-His'),
            bin2hex(random_bytes(3)),
            strtolower($archivo->getClientOriginalExtension())
        );

        Storage::disk('local')->put($ruta, file_get_contents($archivo->getRealPath()));

        /*
         * La hoja de vida es una sola: la vigente.
         *
         * Al subir otra, la anterior se da de baja en vez de borrarse —su
         * archivo sigue en disco— para que la ficha enseñe un solo CV y aun
         * así quede rastro de cuál había antes.
         */
        if ($tipo === self::HOJA_DE_VIDA) {
            Documento::where('empleado_id', $empleadoId)
                ->where('tipo', self::HOJA_DE_VIDA)
                ->where('estado_registro', 'activo')
                ->update(['estado_registro' => 'inactivo']);
        }

        // El estado lo pone el sistema, nunca quien sube. Y estado_registro
        // va explícito: el defecto lo aplica MySQL, no Eloquent, y el
        // documento devuelto salía con null mientras la base decía "activo".
        return Documento::create(array_merge($extra, [
            'empleado_id'     => $empleadoId,
            'tipo'            => $tipo,
            'archivo'         => $ruta,
            'estado_firma'    => 'pendiente',
            'estado_registro' => 'activo',
        ]));
    }
}
