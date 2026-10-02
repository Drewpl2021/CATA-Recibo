<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * El tipo de contrato deja de ser un ENUM fijo en el código y pasa a ser un
 * catálogo administrable, como Área, Cargo, Sede y Rol.
 *
 * Los 4 valores que tenía ('indeterminado', 'plazo_fijo', 'suplencia',
 * 'practicas') no eran los reales del colegio. Comparado contra el PLAME
 * real (hoja PLANILLA, columna CATEGORÍA, marzo 2026) el colegio usa
 * "CONTRATADO" (77 trabajadores), "Plazo indeterminado" (14), "Contrato/
 * Parcial" (1 — se verificó su fila completa: usa las mismas fórmulas de
 * ONP/EsSalud/Diezmo que un Contratado normal, ninguna regla de cálculo
 * distinta) y "Prácticas" (0 este mes, pero es una categoría real). No
 * existe "Suplencia" en el PLAME real ni hay ningún empleado real en el
 * sistema con ese valor hoy — se fusiona en "Contratado".
 *
 * Solo "Plazo indeterminado" es especial: no pide fecha de fin y es el
 * único que puede tomar vacaciones reales (los otros tres cobran
 * Vacaciones Truncas al terminar el contrato). Esos dos comportamientos,
 * que antes vivían hardcodeados como `=== 'indeterminado'` en más de una
 * decena de archivos, pasan a ser los flags `requiere_fecha_fin` y
 * `permite_vacaciones` de esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_contrato', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre', 100)->unique();
            $table->boolean('requiere_fecha_fin')->default(true);
            $table->boolean('permite_vacaciones')->default(false);
            // Dar de baja no borra: el tipo sigue nombrado en los contratos viejos.
            $table->string('estado', 20)->default('activo');
            $table->timestamps();
        });

        $ahora = now();
        $tipos = [
            'Contratado'           => ['requiere_fecha_fin' => true,  'permite_vacaciones' => false],
            'Contrato/Parcial'     => ['requiere_fecha_fin' => true,  'permite_vacaciones' => false],
            'Plazo indeterminado'  => ['requiere_fecha_fin' => false, 'permite_vacaciones' => true],
            'Prácticas'            => ['requiere_fecha_fin' => true,  'permite_vacaciones' => false],
        ];

        $ids = [];
        foreach ($tipos as $nombre => $flags) {
            $id = (string) Str::uuid7();
            $ids[$nombre] = $id;
            DB::table('tipos_contrato')->insert([
                'id' => $id, 'nombre' => $nombre,
                'requiere_fecha_fin' => $flags['requiere_fecha_fin'],
                'permite_vacaciones' => $flags['permite_vacaciones'],
                'estado' => 'activo', 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }

        // Lo que tenía cada ENUM viejo, a dónde va en el catálogo nuevo.
        $mapaViejoANuevo = [
            'indeterminado' => $ids['Plazo indeterminado'],
            'plazo_fijo'    => $ids['Contratado'],
            'practicas'     => $ids['Prácticas'],
            // No existe en el PLAME real ni hay nadie real así hoy: se fusiona.
            'suplencia'     => $ids['Contratado'],
        ];

        Schema::table('empleados', function (Blueprint $table) {
            $table->uuid('tipo_contrato_id')->nullable()->after('tipo_contrato');
        });
        Schema::table('contratos', function (Blueprint $table) {
            $table->uuid('tipo_contrato_id')->nullable()->after('tipo_contrato');
        });

        foreach ($mapaViejoANuevo as $viejo => $nuevoId) {
            DB::table('empleados')->where('tipo_contrato', $viejo)->update(['tipo_contrato_id' => $nuevoId]);
            DB::table('contratos')->where('tipo_contrato', $viejo)->update(['tipo_contrato_id' => $nuevoId]);
        }

        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn('tipo_contrato');
            $table->foreign('tipo_contrato_id')->references('id')->on('tipos_contrato')->restrictOnDelete();
        });
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('tipo_contrato');
            $table->uuid('tipo_contrato_id')->nullable(false)->change();
            $table->foreign('tipo_contrato_id')->references('id')->on('tipos_contrato')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropForeign(['tipo_contrato_id']);
            $table->enum('tipo_contrato', ['indeterminado', 'plazo_fijo', 'suplencia', 'practicas'])->nullable()->after('tipo_contrato_id');
        });
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropForeign(['tipo_contrato_id']);
            $table->enum('tipo_contrato', ['indeterminado', 'plazo_fijo', 'suplencia', 'practicas'])->nullable()->after('tipo_contrato_id');
        });

        $nombrePorId = DB::table('tipos_contrato')->pluck('nombre', 'id');
        $mapaNuevoAViejo = [
            'Plazo indeterminado' => 'indeterminado',
            'Contratado'          => 'plazo_fijo',
            'Contrato/Parcial'    => 'plazo_fijo',
            'Prácticas'           => 'practicas',
        ];

        foreach (DB::table('empleados')->whereNotNull('tipo_contrato_id')->get(['id', 'tipo_contrato_id']) as $fila) {
            $nombre = $nombrePorId[$fila->tipo_contrato_id] ?? null;
            if ($nombre && isset($mapaNuevoAViejo[$nombre])) {
                DB::table('empleados')->where('id', $fila->id)->update(['tipo_contrato' => $mapaNuevoAViejo[$nombre]]);
            }
        }
        foreach (DB::table('contratos')->whereNotNull('tipo_contrato_id')->get(['id', 'tipo_contrato_id']) as $fila) {
            $nombre = $nombrePorId[$fila->tipo_contrato_id] ?? null;
            if ($nombre && isset($mapaNuevoAViejo[$nombre])) {
                DB::table('contratos')->where('id', $fila->id)->update(['tipo_contrato' => $mapaNuevoAViejo[$nombre]]);
            }
        }

        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn('tipo_contrato_id');
        });
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('tipo_contrato_id');
        });

        Schema::dropIfExists('tipos_contrato');
    }
};
