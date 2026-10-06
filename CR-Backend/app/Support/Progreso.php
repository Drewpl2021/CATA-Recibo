<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cuánto lleva un proceso largo (importar, generar planillas, emitir), para
 * que la pantalla enseñe "va 23 de 94, falta medio minuto" en vez de un
 * botón que dice "Aplicando…" sin saber si se colgó.
 *
 * La pantalla manda un id en la cabecera X-Progreso al pedir el proceso, y
 * mientras espera pregunta por GET /progress/{id}. Aquí el proceso va
 * anotando por qué etapa va y cuántos lleva.
 *
 * Va al caché en ARCHIVO a propósito, no al de la base: casi todos estos
 * procesos corren dentro de una transacción, y lo escrito en la base no lo
 * vería la otra petición hasta el final — la barra se quedaría en cero.
 *
 * Sin la cabecera (una prueba, otra pantalla) no hace nada: los procesos lo
 * llaman siempre y no tienen que preguntar si alguien está mirando.
 */
final class Progreso
{
    /** Cada cuánto se escribe como mucho, en segundos: 94 escrituras por fila serían de más. */
    private const CADA = 0.25;

    private ?string $clave;
    private string $etapa = '';
    private int $total = 0;
    private int $hechos = 0;
    private float $desde = 0;
    private float $ultimaEscritura = 0;

    private function __construct(?string $clave)
    {
        $this->clave = $clave;
    }

    /** El de la petición en curso (o uno que no anota nada, si nadie lo pidió). */
    public static function actual(): self
    {
        $id   = request()?->header('X-Progreso');
        $user = request()?->user()?->id;

        $valido = is_string($id) && preg_match('/^[A-Za-z0-9-]{8,64}$/', $id) && $user;

        return new self($valido ? self::clave($user, $id) : null);
    }

    /** Lo que lleva, para GET /progress/{id}. Solo el dueño puede verlo. */
    public static function leer(int|string $userId, string $id): ?array
    {
        return Cache::store('file')->get(self::clave($userId, $id));
    }

    /** Empieza una etapa: "Guardando trabajadores", con cuántos va a hacer. */
    public function etapa(string $nombre, int $total): self
    {
        $this->etapa  = $nombre;
        $this->total  = max(0, $total);
        $this->hechos = 0;
        $this->desde  = microtime(true);
        $this->escribir(true);

        return $this;
    }

    /** Uno (o varios) más hechos. */
    public function avanzar(int $cuantos = 1): void
    {
        $this->hechos = min($this->total, $this->hechos + $cuantos);
        $this->escribir($this->hechos >= $this->total);
    }

    private function escribir(bool $siempre): void
    {
        if (! $this->clave) {
            return;
        }

        $ahora = microtime(true);
        if (! $siempre && $ahora - $this->ultimaEscritura < self::CADA) {
            return;
        }
        $this->ultimaEscritura = $ahora;

        Cache::store('file')->put($this->clave, [
            'etapa'        => $this->etapa,
            'total'        => $this->total,
            'hechos'       => $this->hechos,
            // Segundos que lleva la etapa: con esto y los hechos, la pantalla
            // calcula cuánto falta.
            'transcurrido' => round($ahora - $this->desde, 1),
        ], now()->addMinutes(30));
    }

    private static function clave(int|string $userId, string $id): string
    {
        return "progreso:{$userId}:{$id}";
    }
}
