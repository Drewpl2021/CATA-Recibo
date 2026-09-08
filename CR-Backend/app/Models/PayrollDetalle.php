<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PayrollDetalle extends Model
{
    protected $table = 'payroll_detalles';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'planilla_id', 'payment_concept_id', 'monto_calculado', 'calculo', 'valor', 'descripcion', 'estado'];

    protected $casts = [
        'monto_calculado' => 'decimal:2',
        'valor'           => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = $m->id ?: Str::uuid());
    }

    public function planilla()
    {
        return $this->belongsTo(Planilla::class, 'planilla_id');
    }

    public function paymentConcept()
    {
        return $this->belongsTo(PaymentConcept::class, 'payment_concept_id');
    }

    /**
     * Cómo se escribió esta línea, en corto: "5%" o "S/ 100.00".
     *
     * Vacío en las líneas que se pusieron directamente en soles, que es como
     * funcionaba todo antes de que se pudiera escribir el porcentaje.
     */
    public function getComoSeCalculoAttribute(): ?string
    {
        if ($this->calculo === null || $this->valor === null) {
            return null;
        }

        return $this->calculo === 'porcentaje'
            ? rtrim(rtrim(number_format((float) $this->valor, 2, '.', ''), '0'), '.') . '%'
            : 'S/ ' . number_format((float) $this->valor, 2);
    }

    /**
     * Lo que se imprime en la boleta: el nombre del concepto, y si esta aplicación
     * puntual trae una descripción (ej. concepto genérico "Otros Conceptos" +
     * descripcion "Subsidio de Maternidad"), se le pega detrás con ": ".
     */
    protected $appends = ['como_se_calculo'];

    public function getEtiquetaAttribute(): string
    {
        $nombre = $this->paymentConcept?->nombre ?? '';
        return $this->descripcion ? "{$nombre}: {$this->descripcion}" : $nombre;
    }
}