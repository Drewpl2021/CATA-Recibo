<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Guarda los catálogos del sistema en un .sql versionable.
 *
 *   php artisan semilla:exportar
 *
 * Los catálogos son lo que NO cambia de un colegio a otro ni de un mes a
 * otro: los roles, el menú, las áreas, los cargos, las sedes y los conceptos
 * de pago. Es lo mismo que deja `db:seed`, pero en un archivo que se puede
 * cargar de un tirón en un servidor sin correr los seeders.
 *
 * Dos cosas que este archivo NO lleva, y no es un olvido:
 *
 *  - **Personas.** Ni usuarios, ni trabajadores, ni planillas, ni boletas.
 *    Un volcado con personal dentro no debe existir fuera del servidor, y
 *    menos en un repositorio —privado o no—. La lista de tablas está fijada
 *    abajo y no se puede ampliar por parámetro a propósito.
 *  - **La estructura.** Solo los datos. Las tablas las crea `migrate`, que
 *    es quien sabe en qué versión va cada una.
 *
 * Para volver a cargarlo: `php artisan semilla:importar`.
 */
class ExportarSemilla extends Command
{
    protected $signature = 'semilla:exportar {--archivo= : Dónde escribirlo (por defecto database/semilla/instalacion-basica.sql)}';

    protected $description = 'Escribe los catálogos del sistema en un .sql (sin personas)';

    /**
     * Las únicas tablas que salen, en orden de dependencia: primero la que
     * manda y después la que la apunta, para que al cargarlas no falte una
     * llave foránea.
     */
    public const TABLAS = [
        'roles',
        'modulo_padre',
        'modulos',
        'rol_modulo',
        'areas',
        'cargos',
        'area_cargo',
        'sedes',
        'payment_concepts',
    ];

    public function handle(): int
    {
        $ruta = $this->option('archivo') ?: database_path('semilla/instalacion-basica.sql');

        if (! is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0755, true);
        }

        $sql = $this->cabecera();
        $resumen = [];

        foreach (self::TABLAS as $tabla) {
            $filas = DB::table($tabla)->get();
            $resumen[$tabla] = $filas->count();

            $sql .= "\n-- ── {$tabla} ({$filas->count()}) ──\n";
            // Se vacía antes de escribir: así cargar la semilla dos veces
            // deja lo mismo y no duplica los catálogos.
            $sql .= "DELETE FROM `{$tabla}`;\n";

            foreach ($filas as $fila) {
                $sql .= $this->insert($tabla, (array) $fila);
            }
        }

        $sql .= "\nSET FOREIGN_KEY_CHECKS=1;\n";

        file_put_contents($ruta, $sql);

        $this->info('Semilla escrita en ' . $ruta);
        foreach ($resumen as $tabla => $cuantas) {
            $this->line(sprintf('   %-18s %d', $tabla, $cuantas));
        }
        $this->newLine();
        $this->warn('Revisa antes de subirlo: este archivo NO debe llevar personas dentro.');

        return self::SUCCESS;
    }

    private function cabecera(): string
    {
        return "-- ─────────────────────────────────────────────────────────────\n"
            . "--  CATA-Recibo — los catálogos del sistema\n"
            . "-- ─────────────────────────────────────────────────────────────\n"
            . "--  Roles, menú, áreas, cargos, sedes y conceptos de pago.\n"
            . "--  Sin personas: ni usuarios, ni trabajadores, ni planillas.\n"
            . "--\n"
            . "--  Se genera con:  php artisan semilla:exportar\n"
            . "--  Se carga con:   php artisan semilla:importar\n"
            . "--\n"
            . "--  Las TABLAS las crea `php artisan migrate`. Esto son solo los\n"
            . "--  datos: cargarlo sobre una base sin migrar no funciona.\n"
            . "-- ─────────────────────────────────────────────────────────────\n\n"
            . "SET NAMES utf8mb4;\n"
            . "SET FOREIGN_KEY_CHECKS=0;\n";
    }

    /** Una línea INSERT con los nombres de columna escritos, para que se lea. */
    private function insert(string $tabla, array $fila): string
    {
        $columnas = array_map(fn ($c) => "`{$c}`", array_keys($fila));
        $valores  = array_map(fn ($v) => $this->valor($v), array_values($fila));

        return "INSERT INTO `{$tabla}` (" . implode(', ', $columnas) . ') VALUES ('
            . implode(', ', $valores) . ");\n";
    }

    private function valor(mixed $valor): string
    {
        if ($valor === null) {
            return 'NULL';
        }

        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }

        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }

        // Por el PDO y no a mano: es quien sabe escapar comillas y acentos
        // para esta conexión.
        return DB::getPdo()->quote((string) $valor);
    }
}
