<?php

namespace App\Support\Portal;

use Illuminate\Support\Facades\Log;

/**
 * Lo que Bloques encuentra al armar una página, bloque por bloque, en
 * palabras de quien edita: «Bloque 3 (Lista): el elemento 2 no tiene texto».
 *
 * Bloques arma con ella tanto lo que sirve el portal (y entonces solo anota
 * en el registro lo que omite) como la revisión del panel (y entonces junta
 * todos los mensajes para no dejar guardar). Así las dos usan las mismas
 * reglas, en el mismo recorrido.
 */
final class RevisionDeBloques
{
    /** Cómo se llama cada tipo en los mensajes. */
    public const NOMBRES = [
        'texto'       => 'Texto',
        'destacado'   => 'Destacado',
        'lista'       => 'Lista',
        'hitos'       => 'Línea de tiempo',
        'imagen'      => 'Imagen',
        'personas'    => 'Personas',
        'mensaje'     => 'Carta',
        'letra'       => 'Letra',
        'audio'       => 'Audio',
        'organigrama' => 'Organigrama',
        'enlaces'     => 'Documentos',
    ];

    private array $problemas = [];
    private int $posicion = 0;
    private ?string $tipo = null;
    private ?string $primerMotivo = null;

    /**
     * @param  string|null  $pagina  con página, lo omitido va al registro (el portal);
     *                               sin ella, solo se juntan los mensajes (el panel)
     */
    public function __construct(private readonly ?string $pagina = null) {}

    public function empezarBloque(int $posicion, mixed $tipo): void
    {
        $this->posicion = $posicion;
        $this->tipo = is_string($tipo) ? $tipo : null;
        $this->primerMotivo = null;
    }

    /** Algo del bloque actual que no cumple el contrato. */
    public function anotar(string $que): void
    {
        $this->primerMotivo ??= $que;
        $nombre = self::NOMBRES[$this->tipo] ?? null;
        $this->problemas[] = 'Bloque ' . $this->posicion . ($nombre ? " ({$nombre})" : '') . ": {$que}";
    }

    /** Un problema de la página entera, no de un bloque. */
    public function anotarEnLaPagina(string $que): void
    {
        $this->problemas[] = $que;
    }

    /** El bloque actual no se manda: en el portal, queda en el registro con el porqué. */
    public function omitirBloque(): void
    {
        if ($this->pagina === null) {
            return;
        }

        Log::warning('Portal: se omitió un bloque que no cumple el contrato', [
            'pagina'   => $this->pagina,
            'posicion' => $this->posicion,
            'tipo'     => $this->tipo,
            'motivo'   => $this->primerMotivo,
        ]);
    }

    /** El organigrama pasa de 5 niveles y se corta (en el portal, al registro). */
    public function cortarOrganigrama(string $cargo): void
    {
        $this->anotar("«{$cargo}» está en el quinto nivel del organigrama y tiene cargos debajo: el organigrama llega hasta 5 niveles.");

        if ($this->pagina !== null) {
            Log::warning('Portal: el organigrama pasa de 5 niveles; se cortó en el quinto', ['cargo' => $cargo]);
        }
    }

    public function problemas(): array
    {
        return $this->problemas;
    }
}
