<?php

namespace App\Support\Portal\Fichas;

use Illuminate\Validation\Rule;

/**
 * Los datos del colegio (contrato §3.1, sin las redes, que son su propia
 * lista): lo que usan la cabecera, el pie y el botón de WhatsApp de todas las
 * páginas del portal. Los largos son los del contrato.
 */
class FichaSitio implements Ficha
{
    /** E.164, como lo exige el contrato para los enlaces tel: y de WhatsApp. */
    private const TELEFONO = '/^\+\d{8,15}$/';

    public function nombre(): string
    {
        return 'los datos del colegio';
    }

    public function reglas(): array
    {
        return [
            'nombre'                 => ['required', 'string', 'max:80'],
            'nombreCorto'            => ['required', 'string', 'max:20'],
            'subtitulo'              => ['nullable', 'string', 'max:90'],
            'ciudad'                 => ['required', 'string', 'max:40'],
            'anioFundacion'          => ['required', 'integer', 'between:1800,' . now()->year],
            'telefono.numero'        => ['required', 'string', 'regex:' . self::TELEFONO],
            'telefono.visible'       => ['required', 'string', 'max:20'],
            'whatsapp.numero'        => ['required', 'string', 'regex:' . self::TELEFONO],
            'whatsapp.visible'       => ['required', 'string', 'max:20'],
            'direccion.linea'        => ['required', 'string', 'max:80'],
            'direccion.referencia'   => ['nullable', 'string', 'max:100'],
            'direccion.distrito'     => ['nullable', 'string', 'max:80'],
            // Sin plataforma, el portal no muestra el botón. Con ella, los dos datos.
            'portalAcademico'        => ['nullable', 'array'],
            'portalAcademico.nombre' => [Rule::requiredIf(fn () => self::conPlataforma(request('portalAcademico'))), 'nullable', 'string', 'max:20'],
            'portalAcademico.url'    => [Rule::requiredIf(fn () => self::conPlataforma(request('portalAcademico'))), 'nullable', 'url', 'starts_with:https://', 'max:255'],
            'promotora'              => ['nullable', 'string', 'max:120'],
        ];
    }

    public function atributos(): array
    {
        return [
            'nombre'                 => 'nombre del colegio',
            'nombreCorto'            => 'sigla',
            'subtitulo'              => 'subtítulo',
            'ciudad'                 => 'ciudad',
            'anioFundacion'          => 'año de fundación',
            'telefono.numero'        => 'número del teléfono (con +51)',
            'telefono.visible'       => 'teléfono como se lee',
            'whatsapp.numero'        => 'número de WhatsApp (con +51)',
            'whatsapp.visible'       => 'WhatsApp como se lee',
            'direccion.linea'        => 'dirección',
            'direccion.referencia'   => 'referencia',
            'direccion.distrito'     => 'distrito',
            'portalAcademico.nombre' => 'nombre de la plataforma académica',
            'portalAcademico.url'    => 'dirección de la plataforma académica',
            'promotora'              => 'promotora',
        ];
    }

    public function aContenido(array $v): array
    {
        $academico = $v['portalAcademico'] ?? null;

        return [
            'nombre'          => $v['nombre'],
            'nombreCorto'     => $v['nombreCorto'],
            'subtitulo'       => $v['subtitulo'] ?? null,
            'ciudad'          => $v['ciudad'],
            'anioFundacion'   => (int) $v['anioFundacion'],
            'telefono'        => ['numero' => $v['telefono']['numero'], 'visible' => $v['telefono']['visible']],
            'whatsapp'        => ['numero' => $v['whatsapp']['numero'], 'visible' => $v['whatsapp']['visible']],
            'direccion'       => [
                'linea'      => $v['direccion']['linea'],
                'referencia' => $v['direccion']['referencia'] ?? null,
                'distrito'   => $v['direccion']['distrito'] ?? null,
            ],
            'portalAcademico' => self::conPlataforma($academico) ? ['nombre' => $academico['nombre'], 'url' => $academico['url']] : null,
            'promotora'       => $v['promotora'] ?? null,
        ];
    }

    /** Con plataforma si trae al menos uno de sus dos datos; los dos vacíos es «sin botón». */
    private static function conPlataforma(mixed $academico): bool
    {
        return is_array($academico) && array_filter($academico, fn ($v) => filled($v)) !== [];
    }

    public function rutas(): array
    {
        return ['/v1/sitio'];
    }
}
