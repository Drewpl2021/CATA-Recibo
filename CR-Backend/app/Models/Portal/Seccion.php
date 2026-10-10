<?php

namespace App\Models\Portal;

use App\Support\Portal\SeccionSinConfigurar;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Una ficha única del portal: clave → contenido (JSON). Ver la migración de
 * portal_secciones.
 *
 * Los modelos del portal van en App\Models\Portal porque varios se llaman
 * igual que los de RR.HH. (la Sede del portal no es la Sede de la planilla).
 */
class Seccion extends Model
{
    use Auditable;

    protected $table = 'portal_secciones';
    protected $primaryKey = 'clave';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['clave', 'contenido'];

    protected array $camposAuditables = ['contenido'];
    protected string $entidadAuditada = 'portal: sección';

    /** Lo que se lee en la auditoría en vez de la clave técnica. */
    public const NOMBRES = [
        'sitio'              => 'los datos del colegio',
        'quienes-somos'      => 'Quiénes somos (Inicio)',
        'niveles'            => 'el título de Niveles (Inicio)',
        'propuesta-cabecera' => 'la cabecera de Propuesta Educativa',
        'propuesta-pilares'  => 'la introducción de los pilares',
        'propuesta-academia' => 'la academia preuniversitaria',
        'matricula-cabecera' => 'la cabecera de Matrícula',
        'matricula-vacantes' => 'la nota de vacantes',
        'matricula-fechas'   => 'la nota del cronograma de matrícula',
        'solicitud'          => 'el formulario «Solicitar información»',
        'contacto'           => 'la cabecera de Contacto',
        'proyectos'          => 'la cabecera de Proyectos',
        'nosotros'           => 'el índice de Nosotros',
        'logros'             => 'la cabecera de Logros',
    ];

    protected function casts(): array
    {
        return ['contenido' => 'array'];
    }

    public function nombreAuditado(): string
    {
        return self::NOMBRES[$this->clave] ?? "la sección «{$this->clave}»";
    }

    /**
     * El contenido de una sección. Si no se cargó, el portal no tiene qué
     * mostrar y se lo dice con un 503 (ver SeccionSinConfigurar).
     */
    public static function contenido(string $clave): array
    {
        $seccion = static::find($clave);

        if ($seccion === null) {
            throw SeccionSinConfigurar::falta($clave);
        }

        return $seccion->contenido ?? [];
    }
}
