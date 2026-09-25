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
     * Los papeles que trae el propio trabajador.
     *
     * Antes todo esto entraba como "otro" y en el expediente no se
     * distinguía una foto de un certificado. Cada uno con su tipo se
     * encuentra, se cuenta y se le puede pedir a quien no lo ha traído.
     */
    public const FOTO        = 'foto';
    public const DNI         = 'dni';
    public const CERTIFICADO = 'certificado';
    public const OTRO        = 'otro';

    /** Lo que puede subir el trabajador desde Mis Documentos. */
    public const PERSONALES = [self::HOJA_DE_VIDA, self::FOTO, self::DNI, self::CERTIFICADO, self::OTRO];

    /** Todo lo que acepta el expediente, incluido lo que emite el colegio. */
    public const TIPOS = [
        'boleta', 'contrato', 'cts', 'vacaciones_truncas', 'comprobante_transferencia',
        self::HOJA_DE_VIDA, self::FOTO, self::DNI, self::CERTIFICADO, self::OTRO,
    ];

    /** Cómo se llama cada tipo en pantalla. */
    public const NOMBRES = [
        'boleta'                    => 'Boleta',
        'contrato'                  => 'Contrato',
        'cts'                       => 'CTS',
        'vacaciones_truncas'        => 'Vacaciones truncas',
        'comprobante_transferencia' => 'Comprobante de transferencia',
        self::HOJA_DE_VIDA          => 'Hoja de vida',
        self::FOTO                  => 'Foto',
        self::DNI                   => 'Copia del DNI',
        self::CERTIFICADO           => 'Certificado de estudios',
        self::OTRO                  => 'Otro',
        self::BOLETA_ANTERIOR       => 'Boleta anterior',
        self::CONTRATO_ANTERIOR     => 'Contrato anterior',
    ];

    /**
     * Los archivos de antes del sistema: boletas y contratos que RR.HH. sacó
     * del Excel de la planilla y sube en lote (DocumentosAnterioresController).
     *
     * Van con tipo propio y no como "boleta" a propósito. Todo lo que cuenta
     * boletas —las pendientes de firma, la campana, Mis Boletas, el tablero—
     * pregunta por tipo = 'boleta', y ninguna de esas preguntas tiene sentido
     * para una boleta de 2023: ya se pagó, ya se firmó en papel, y no tiene
     * planilla ni detalle en el sistema.
     */
    public const BOLETA_ANTERIOR   = 'boleta_anterior';
    public const CONTRATO_ANTERIOR = 'contrato_anterior';
    public const ANTERIORES        = [self::BOLETA_ANTERIOR, self::CONTRATO_ANTERIOR];

    /**
     * Los documentos que NO se firman.
     *
     * La hoja de vida es del trabajador, no algo que el colegio le entrega:
     * pedirle que "firme" su propio CV no significa nada, y además le
     * aparecía como pendiente en la campana y en el recuadro de "Por firmar".
     *
     * Los archivos anteriores tampoco: son copias de algo que ya pasó.
     */
    public const SIN_FIRMA = [
        self::HOJA_DE_VIDA, self::FOTO, self::DNI, self::CERTIFICADO, self::OTRO,
        self::BOLETA_ANTERIOR, self::CONTRATO_ANTERIOR,
    ];

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

    /** Lo que se le contesta a quien intenta firmar algo que no se firma. */
    public static function porQueNoSeFirma(?string $tipo): string
    {
        if (in_array($tipo, self::PERSONALES, true)) {
            return 'Es un documento tuyo, no algo que el colegio te entregue: no se firma.';
        }

        return 'Es un archivo anterior al sistema, guardado como copia: no se firma.';
    }

    /** El SHA-256 del archivo: dos archivos iguales tienen la misma huella. */
    public static function huella(UploadedFile $archivo): string
    {
        return hash_file('sha256', $archivo->getRealPath());
    }

    /**
     * Guarda el archivo y registra el documento.
     *
     * @param  array  $extra  columnas adicionales del documento (contrato_id, firmado_por,
     *                        periodo_mes, periodo_anio).
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
        // La hoja de vida y la foto son UNA: la vigente. Al subir otra, la
        // anterior se da de baja en vez de borrarse —su archivo sigue en
        // disco— para que el expediente enseñe una sola y aun así quede
        // rastro de cuál había antes. Los certificados no: de esos se
        // guardan todos los que traiga.
        if (in_array($tipo, [self::HOJA_DE_VIDA, self::FOTO], true)) {
            Documento::where('empleado_id', $empleadoId)
                ->where('tipo', $tipo)
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
            'huella'          => self::huella($archivo),
            'estado_firma'    => 'pendiente',
            'estado_registro' => 'activo',
        ]));
    }
}
