<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Buscar a una persona por su DNI.
 *
 * Sirve para dar de alta a un trabajador sin tipear su nombre: RR.HH. pone
 * los ocho dígitos y salen los nombres tal como están en el padrón. Eso
 * importa más de lo que parece — el nombre que se escribe aquí es el que
 * sale impreso en todas sus boletas, y una errata ahí se arrastra un año.
 *
 * Se busca en dos sitios y en este orden:
 *
 *   1. RENSUN, la base del propio colegio. No cuesta nada y responde al
 *      instante.
 *   2. Decolecta, que se paga por consulta. Solo si la primera no la tiene.
 *
 * Nada de esto puede tumbar el alta de un trabajador: si la base no está
 * levantada o la API no contesta, se devuelve null, queda anotado en el log
 * y RR.HH. escribe los datos a mano como siempre.
 */
class ConsultaDni
{
    /**
     * Los datos de esa persona, o null si no aparece en ningún lado.
     *
     * @return array{dni:string,nombres:string,apellidos:string,apellido_paterno:string,apellido_materno:string,nombre_completo:string,fecha_nacimiento:?string,direccion:?string,fuente:string}|null
     */
    public function buscar(string $dni): ?array
    {
        $dni = preg_replace('/\D/', '', $dni) ?? '';

        if (strlen($dni) !== 8) {
            return null;
        }

        $dias = (int) config('consulta_dni.cache_dias', 30);

        // Un DNI no cambia de dueño: lo que se encontró una vez vale meses,
        // y así una segunda alta de la misma persona no gasta otra consulta.
        // Lo que NO se guarda es el "no existe": puede ser un DNI recién
        // emitido, o que la base propia todavía no lo tenga.
        $enCache = Cache::get($this->llave($dni));
        if (is_array($enCache)) {
            return $enCache;
        }

        $persona = $this->enBasePropia($dni) ?? $this->enDecolecta($dni);

        if ($persona) {
            Cache::put($this->llave($dni), $persona, now()->addDays($dias));
        }

        return $persona;
    }

    private function llave(string $dni): string
    {
        return "consulta_dni:{$dni}";
    }

    // ────────── 1. La base del colegio ──────────

    /**
     * El padrón propio. Es una base ajena —no la creamos ni la migramos
     * nosotros—, así que la tabla y sus columnas se leen de la
     * configuración: `php artisan dni:diagnostico` dice cuáles poner.
     */
    private function enBasePropia(string $dni): ?array
    {
        if (! config('consulta_dni.rensun.activo')) {
            return null;
        }

        $tabla = (string) config('consulta_dni.rensun.tabla');
        $col   = (array) config('consulta_dni.rensun.columnas');

        try {
            $fila = DB::connection('rensun')
                ->table($tabla)
                ->where($col['dni'], $dni)
                ->first();
        } catch (Throwable $e) {
            // Servidor caído, credenciales cambiadas, tabla que se llama de
            // otra forma: se sigue con Decolecta en vez de romper el alta.
            Log::warning('Consulta de DNI: la base RENSUN no respondió', [
                'dni'    => $dni,
                'motivo' => $e->getMessage(),
            ]);
            return null;
        }

        if (! $fila) {
            return null;
        }

        $datos = (array) $fila;
        $dato  = fn (string $clave) => isset($col[$clave], $datos[$col[$clave]])
            ? trim((string) $datos[$col[$clave]])
            : '';

        $paterno = $dato('apellido_paterno');
        $materno = $dato('apellido_materno');

        return $this->normalizar([
            'dni'              => $dni,
            'nombres'          => $dato('nombres'),
            'apellido_paterno' => $paterno,
            'apellido_materno' => $materno,
            'fecha_nacimiento' => $dato('fecha_nacimiento') ?: null,
            'direccion'        => $dato('direccion') ?: null,
            'fuente'           => 'Base del colegio',
        ]);
    }

    // ────────── 2. Decolecta ──────────

    private function enDecolecta(string $dni): ?array
    {
        $token = config('consulta_dni.decolecta.token');

        if (! $token) {
            return null;
        }

        $url = config('consulta_dni.decolecta.base_url') . config('consulta_dni.decolecta.ruta');

        try {
            $respuesta = Http::withToken($token)
                ->acceptJson()
                ->timeout((int) config('consulta_dni.decolecta.segundos', 8))
                ->get($url, ['numero' => $dni]);
        } catch (Throwable $e) {
            Log::warning('Consulta de DNI: Decolecta no respondió', [
                'dni'    => $dni,
                'motivo' => $e->getMessage(),
            ]);
            return null;
        }

        // 404 es "ese DNI no está en RENIEC", que no es un error del sistema.
        if ($respuesta->status() === 404) {
            return null;
        }

        if (! $respuesta->successful()) {
            Log::warning('Consulta de DNI: Decolecta devolvió un error', [
                'dni'    => $dni,
                'codigo' => $respuesta->status(),
            ]);
            return null;
        }

        $cuerpo = $respuesta->json();

        // Los planes con más datos responden envueltos en {success, data} y
        // avisan del "no encontrado" con un 200. Se contemplan las dos formas.
        if (! is_array($cuerpo) || (isset($cuerpo['success']) && $cuerpo['success'] === false)) {
            return null;
        }

        $d = is_array($cuerpo['data'] ?? null) ? $cuerpo['data'] : $cuerpo;

        $nombres = $this->primero($d, ['first_name', 'nombres', 'names']);
        $paterno = $this->primero($d, ['first_last_name', 'apellido_paterno', 'paternal_surname']);
        $materno = $this->primero($d, ['second_last_name', 'apellido_materno', 'maternal_surname']);

        if ($nombres === '' && $paterno === '') {
            return null;
        }

        return $this->normalizar([
            'dni'              => $dni,
            'nombres'          => $nombres,
            'apellido_paterno' => $paterno,
            'apellido_materno' => $materno,
            'fecha_nacimiento' => $this->fecha($this->primero($d, ['date_of_birth', 'fecha_nacimiento', 'birth_date'])),
            'direccion'        => $this->primero($d, ['address', 'direccion', 'full_address']) ?: null,
            'fuente'           => 'RENIEC',
        ]);
    }

    /** El primer campo con algo dentro: cada plan los llama distinto. */
    private function primero(array $datos, array $claves): string
    {
        foreach ($claves as $clave) {
            if (! empty($datos[$clave]) && is_scalar($datos[$clave])) {
                return trim((string) $datos[$clave]);
            }
        }

        return '';
    }

    /** A 'AAAA-MM-DD', que es lo que espera el campo de fecha del formulario. */
    private function fecha(string $texto): ?string
    {
        if ($texto === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
            $fecha = \DateTime::createFromFormat($formato, substr($texto, 0, 10));
            if ($fecha && $fecha->format($formato) === substr($texto, 0, 10)) {
                return $fecha->format('Y-m-d');
            }
        }

        return null;
    }

    // ────────── Lo que sale de aquí ──────────

    /**
     * Una sola forma de respuesta, venga de donde venga.
     *
     * Los apellidos van juntos en `apellidos` porque la ficha del trabajador
     * tiene un solo campo para los dos —así está la tabla—, pero se devuelven
     * también por separado por si algún día hacen falta.
     */
    private function normalizar(array $datos): array
    {
        $apellidos = trim($datos['apellido_paterno'] . ' ' . $datos['apellido_materno']);

        return [
            'dni'              => $datos['dni'],
            'nombres'          => $this->enMayusculaInicial($datos['nombres']),
            'apellidos'        => $this->enMayusculaInicial($apellidos),
            'apellido_paterno' => $this->enMayusculaInicial($datos['apellido_paterno']),
            'apellido_materno' => $this->enMayusculaInicial($datos['apellido_materno']),
            'nombre_completo'  => $this->enMayusculaInicial(trim($apellidos . ', ' . $datos['nombres'])),
            'fecha_nacimiento' => $datos['fecha_nacimiento'],
            'direccion'        => $datos['direccion'],
            'fuente'           => $datos['fuente'],
        ];
    }

    /**
     * El padrón devuelve todo en mayúsculas ("MARIA ELENA QUISPE"). En la
     * boleta y en la pantalla queda mejor "María Elena Quispe", y RR.HH. no
     * tiene por qué reescribirlo.
     */
    private function enMayusculaInicial(string $texto): string
    {
        return mb_convert_case(mb_strtolower(trim($texto), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
}
