<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;

/**
 * Una imagen del portal en un uso concreto. Ver la migración de
 * portal_imagenes: una fila por uso, porque el `alt` depende del lugar.
 */
class Imagen extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_imagenes';

    protected $fillable = ['ruta', 'url_externa', 'alt', 'ancho', 'alto', 'mime', 'bytes'];

    protected array $camposAuditables = ['ruta', 'url_externa', 'alt', 'ancho', 'alto'];
    protected string $entidadAuditada = 'portal: imagen';

    protected function casts(): array
    {
        return ['ancho' => 'integer', 'alto' => 'integer', 'bytes' => 'integer'];
    }

    /**
     * La dirección pública, siempre absoluta. Un archivo subido se sirve por
     * la API del portal; una imagen de fuera, tal como se guardó.
     */
    public function url(): string
    {
        return $this->url_externa
            ?? rtrim(config('portal.url_medios'), '/') . '/' . ltrim((string) $this->ruta, '/');
    }

    /** El tipo Imagen del contrato (§1.7). */
    public function aContrato(): array
    {
        return [
            'url'   => $this->url(),
            'alt'   => trim((string) $this->alt),
            'ancho' => $this->ancho,
            'alto'  => $this->alto,
        ];
    }

    public function nombreAuditado(): string
    {
        return '«' . ($this->alt !== '' ? mb_strimwidth($this->alt, 0, 60, '…') : basename($this->url())) . '»';
    }
}
