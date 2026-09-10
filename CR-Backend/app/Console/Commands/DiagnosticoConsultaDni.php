<?php

namespace App\Console\Commands;

use App\Services\ConsultaDni;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Revisa que la búsqueda por DNI esté bien enchufada.
 *
 *   php artisan dni:diagnostico
 *   php artisan dni:diagnostico 12345678
 *
 * Existe porque la base del padrón (RENSUN) no es nuestra: no sabemos cómo
 * se llama su tabla ni sus columnas, y adivinarlas en el código sería
 * romperse en silencio. Esto se conecta, lista lo que hay dentro y dice
 * exactamente qué poner en el .env.
 */
class DiagnosticoConsultaDni extends Command
{
    protected $signature = 'dni:diagnostico {dni? : Un DNI de prueba para consultar de verdad}';

    protected $description = 'Revisa la conexión con la base del padrón y con Decolecta';

    public function handle(ConsultaDni $consulta): int
    {
        $this->info('── Base del colegio (RENSUN) ──');
        $this->revisarBasePropia();

        $this->newLine();
        $this->info('── Decolecta ──');
        $this->revisarDecolecta();

        $dni = $this->argument('dni');

        if ($dni) {
            $this->newLine();
            $this->info("── Consulta de prueba: {$dni} ──");
            $persona = $consulta->buscar($dni);

            if (! $persona) {
                $this->warn('No se encontró a esa persona en ninguna de las dos fuentes.');
                return self::SUCCESS;
            }

            foreach ($persona as $campo => $valor) {
                $this->line(sprintf('  %-18s %s', $campo, $valor ?? '—'));
            }
        }

        return self::SUCCESS;
    }

    private function revisarBasePropia(): void
    {
        if (! config('consulta_dni.rensun.activo')) {
            $this->warn('  No está configurada (falta RENSUN_DB_HOST en el .env).');
            $this->line('  El sistema irá directo a Decolecta, que se paga por consulta.');
            return;
        }

        $tabla = (string) config('consulta_dni.rensun.tabla');

        try {
            $conexion = DB::connection('rensun');
            $base     = $conexion->getDatabaseName();
            $tablas   = array_map(
                fn ($fila) => array_values((array) $fila)[0],
                $conexion->select('SHOW TABLES')
            );
        } catch (Throwable $e) {
            $this->error('  No se pudo conectar: ' . $e->getMessage());
            $this->line('  Revisa RENSUN_DB_HOST, RENSUN_DB_USERNAME y RENSUN_DB_PASSWORD.');
            return;
        }

        $this->line("  Conectado a <info>{$base}</info> — " . count($tablas) . ' tabla(s).');
        $this->line('  Tablas: ' . implode(', ', array_slice($tablas, 0, 15)) . (count($tablas) > 15 ? ', …' : ''));

        if (! in_array($tabla, $tablas, true)) {
            $this->warn("  La tabla configurada (RENSUN_TABLA={$tabla}) no está en esa base.");
            $this->line('  Elige una de las de arriba y ponla en RENSUN_TABLA.');
            return;
        }

        $columnas = $conexion->getSchemaBuilder()->getColumnListing($tabla);
        $this->line("  Columnas de <info>{$tabla}</info>: " . implode(', ', $columnas));

        foreach ((array) config('consulta_dni.rensun.columnas') as $papel => $columna) {
            $existe = in_array($columna, $columnas, true);
            $this->line(sprintf(
                '  %s %-18s → %s',
                $existe ? '<info>✔</info>' : '<comment>✘</comment>',
                $papel,
                $columna
            ));
        }
    }

    private function revisarDecolecta(): void
    {
        $token = config('consulta_dni.decolecta.token');

        if (! $token) {
            $this->warn('  Sin token (falta DECOLECTA_TOKEN en el .env).');
            return;
        }

        $this->line('  Token: <info>' . substr((string) $token, 0, 8) . '…</info>');
        $this->line('  URL:   ' . config('consulta_dni.decolecta.base_url') . config('consulta_dni.decolecta.ruta'));
    }
}
