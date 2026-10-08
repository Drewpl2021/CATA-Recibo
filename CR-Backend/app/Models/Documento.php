<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Documento extends Model
{
    protected $table = 'documentos';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Los estados en que la firma ya está resuelta: firmada en el sistema, o
     * firmada en papel (la boleta de un año anterior armada para registro).
     * Lo que NO está aquí es lo que le falta firmar al trabajador.
     */
    public const FIRMA_RESUELTA = ['firmado', 'en_papel'];

    /**
     * La firma digital del colegio (ver App\Support\FirmaDigitalDeBoletas):
     * mientras esté en uno de estos, la boleta todavía no es del trabajador.
     */
    public const FIRMA_COLEGIO_EN_CURSO = ['pendiente', 'parcial'];

    protected $fillable = [
        'empleado_id',
        'contrato_id',
        'tipo',
        'archivo',
        'firmado_por',
        'codigo_firma',
        'fecha_firma',
        'estado_firma',
        'planilla_id',
        'periodo_mes',
        'periodo_anio',
        'huella',
        'fecha_visto',
        'fecha_aviso',
        'aviso_correo',
        'fecha_descarga',
        'descargas',
        'estado_registro',
        'empleador_id',
        'firmado_por_empleador',
        'codigo_firma_empleador',
        'fecha_firma_empleador',
        'estado_firma_empleador',
        'firma_colegio',
        'firmas_colegio',
        'archivo_sin_firma',
        'firma_colegio_completa_en',
        'firma_colegio_subida_por',
    ];

    /**
     * Sin esto salían como texto suelto ("2026-09-08 15:13:30"), sin decir de
     * qué huso: el navegador lo leía como hora local y pintaba las cifras de
     * UTC como si fueran de Juliaca.
     */
    protected $casts = [
        'fecha_firma'           => 'datetime',
        'fecha_firma_empleador' => 'datetime',
        'fecha_visto'           => 'datetime',
        'fecha_aviso'           => 'datetime',
        'fecha_descarga'        => 'datetime',
        'periodo_mes'           => 'integer',
        'periodo_anio'          => 'integer',
        'firmas_colegio'        => 'array',
        'firma_colegio_completa_en' => 'datetime',
    ];

    /** Todavía le falta la firma digital del colegio: no se le entrega aún. */
    public function esperaFirmaDelColegio(): bool
    {
        return in_array($this->firma_colegio, self::FIRMA_COLEGIO_EN_CURSO, true);
    }

    /**
     * Ya tiene al menos una firma digital del colegio: el PDF está sellado y
     * no se vuelve a generar ni a tocar.
     */
    public function tieneFirmaDelColegio(): bool
    {
        return in_array($this->firma_colegio, ['parcial', 'completa'], true);
    }

    /** Las que el trabajador puede ver: las de siempre, o las ya firmadas por el colegio. */
    public function scopeVisiblesParaElTrabajador($query)
    {
        return $query->where(fn ($q) => $q->whereNull('documentos.firma_colegio')->orWhere('documentos.firma_colegio', 'completa'));
    }

    /**
     * La dirección que va dentro del QR de la boleta: firmada por el
     * servidor, para que nadie arme la de otra cambiando el id. Ver
     * VerificarBoletaController.
     */
    public function urlDeVerificacion(): string
    {
        return \Illuminate\Support\Facades\URL::signedRoute('boleta.verificar', ['documento' => $this->id]);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = Str::uuid7();
        });
    }

    /**
     * Deja constancia de que el trabajador se bajó su boleta.
     *
     * La fecha se guarda solo la PRIMERA vez —es la que responde "¿desde
     * cuándo la tiene?"— y el contador sube siempre. Se llama únicamente
     * cuando la baja el propio trabajador: que RR.HH. abra el PDF para
     * revisarlo no significa que el trabajador la haya recibido.
     */
    public function registrarDescarga(): void
    {
        $this->forceFill([
            'fecha_descarga' => $this->fecha_descarga ?? now(),
            'descargas'      => (int) $this->descargas + 1,
        ])->save();
    }

    /**
     * Deja constancia de a quién y cuándo se le avisó que la boleta ya está.
     *
     * El correo se congela: si el trabajador lo cambia el mes que viene, la
     * boleta de este mes tiene que seguir diciendo a dónde se mandó.
     */
    public function registrarAviso(?string $correo): void
    {
        $this->forceFill([
            'fecha_aviso'  => now(),
            'aviso_correo' => $correo,
        ])->save();
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function contrato()
    {
        return $this->belongsTo(Contrato::class);
    }

    public function planilla()
    {
        return $this->belongsTo(Planilla::class);
    }

    public function empleador()
    {
        return $this->belongsTo(Empleado::class, 'empleador_id');
    }
}