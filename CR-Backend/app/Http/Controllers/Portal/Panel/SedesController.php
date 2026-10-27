<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Imagen;
use App\Models\Portal\Sede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * /web-portal/campuses — las sedes como se publican en el portal (contrato
 * §3.6): en Inicio y en Contacto, con su foto, su horario y su mapa. No son
 * las sedes de RR.HH. (App\Models\Sede).
 *
 * Hasta 8 publicadas. Su `clave` (el id que ve el portal) se arma con el
 * nombre al crearla y no cambia después: el contrato quiere ids estables.
 */
class SedesController extends ListaDelPortalController
{
    /** E.164, como lo exige el contrato. */
    private const TELEFONO = '/^\+\d{8,15}$/';

    /** Lo único que el portal acepta para insertar el mapa (Google Maps u OpenStreetMap). */
    private const MAPA_INSERTABLE = '/^https:\/\/(www\.google\.com\/maps(\/embed)?\?|www\.openstreetmap\.org\/export\/embed\.html\?)/';

    protected function modelo(): string
    {
        return Sede::class;
    }

    protected function entidad(): string
    {
        return 'la sede';
    }

    protected function topePublicados(): ?int
    {
        return 8;
    }

    protected function plural(): string
    {
        return 'sedes publicadas';
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/sedes'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';

        return [
            'nombre'           => [$obligatorio, 'string', 'max:40'],
            'direccion'        => [$obligatorio, 'string', 'max:90'],
            'referencia'       => ['nullable', 'string', 'max:100'],
            'distrito'         => ['nullable', 'string', 'max:80'],
            // Con teléfono, los dos datos; sin él, null.
            'telefono'         => ['nullable', 'array'],
            'telefono.numero'  => ['required_with:telefono', 'nullable', 'string', 'regex:' . self::TELEFONO],
            'telefono.visible' => ['required_with:telefono', 'nullable', 'string', 'max:20'],
            'nota'             => ['nullable', 'string', 'max:160'],
            'imagen_id'        => ['nullable', 'uuid', Rule::exists('portal_imagenes', 'id')],
            'mapa_url'         => ['nullable', 'url', 'starts_with:https://', 'max:2048'],
            'correo'           => ['nullable', 'email', 'max:80'],
            'horario'          => ['sometimes', 'array', 'max:4'],
            'horario.*.dias'   => ['required', 'string', 'max:40'],
            'horario.*.horas'  => ['required', 'string', 'max:40'],
            'mapa_embed_url'   => ['nullable', 'url', 'max:2048', 'regex:' . self::MAPA_INSERTABLE],
        ];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (array_key_exists('telefono', $v)) {
            $v['telefono'] = filled($v['telefono']['numero'] ?? null) ? [
                'numero'  => $v['telefono']['numero'],
                'visible' => $v['telefono']['visible'],
            ] : null;
        }

        if (array_key_exists('horario', $v)) {
            $v['horario'] = array_map(fn ($f) => ['dias' => $f['dias'], 'horas' => $f['horas']], $v['horario']);
        }

        if (! $fila) {
            $v['horario'] ??= [];
            $v['clave'] = $this->claveUnica($v['nombre'], 'sede');
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only([
            'id', 'clave', 'nombre', 'direccion', 'referencia', 'distrito', 'telefono', 'nota',
            'imagen_id', 'mapa_url', 'correo', 'horario', 'mapa_embed_url', 'orden', 'estado',
        ]) + [
            // La foto completa, para la vista previa del panel.
            'imagen' => $fila->imagen_id ? Imagen::find($fila->imagen_id)?->aPanel() : null,
        ];
    }
}
