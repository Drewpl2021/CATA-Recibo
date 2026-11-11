<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Las solicitudes del trabajador a RR.HH.: permisos por horas, licencias,
 * descansos médicos, justificación de faltas y cambio de cuenta.
 *
 * Una sola tabla, porque el camino es el mismo para todas (la pide, RR.HH.
 * la aprueba o la rechaza) y lo que cambia son unos pocos campos. Lo propio
 * de cada tipo está en App\Support\Solicitudes\TiposDeSolicitud.
 *
 * `efectos` guarda lo que hizo la aprobación (qué planillas recalculó, qué
 * faltas de CTS restó, qué cuenta había antes): así se puede revertir y
 * explicar después sin adivinar.
 *
 * También pone «Solicitudes» (RR.HH.) y «Mis Solicitudes» (todos) en el
 * menú, y en la CTS el campo de los días que le quitan las licencias, aparte
 * de las faltas que anota RR.HH.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('empleado_id');
            // permiso | licencia | descanso_medico | justificar_falta | cambio_cuenta
            $table->string('tipo', 20);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            // Solo el permiso: de qué hora a qué hora.
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            // Permiso y licencia: lo pide el trabajador y lo confirma RR.HH. al aprobar.
            $table->boolean('con_goce')->nullable();
            $table->string('motivo', 500);
            // Cambio de cuenta: destino (sueldo | cts), entidad, número y CCI nuevos.
            $table->json('datos')->nullable();
            $table->string('sustento', 255)->nullable();
            $table->string('sustento_nombre', 150)->nullable();
            // Quién abrió el sustento: un cambio de cuenta no se aprueba sin mirarlo.
            $table->uuid('sustento_visto_por')->nullable();
            // pendiente | aprobada | rechazada | cancelada | anulada
            $table->string('estado', 12)->default('pendiente');
            $table->string('respuesta', 500)->nullable();
            $table->uuid('resuelto_por')->nullable();
            $table->timestamp('resuelto_at')->nullable();
            $table->json('efectos')->nullable();
            $table->timestamps();

            $table->index(['empleado_id', 'estado']);
            $table->index(['estado', 'tipo']);
            $table->foreign('empleado_id')->references('id')->on('empleados')->cascadeOnDelete();
        });

        Schema::table('cts_liquidaciones', function (Blueprint $table) {
            // Días que le quitan las licencias sin goce y el descanso médico
            // que pasa de 60 al año. Aparte de `faltas`, que anota RR.HH.
            $table->unsignedSmallInteger('dias_licencia')->default(0)->after('faltas');
        });

        $this->agregarAlMenu();
    }

    public function down(): void
    {
        foreach (['/solicitudes', '/mis-solicitudes'] as $ruta) {
            $moduloId = DB::table('modulos')->where('ruta', $ruta)->value('id');
            if ($moduloId) {
                DB::table('rol_modulo')->where('modulo_id', $moduloId)->delete();
                DB::table('modulos')->where('id', $moduloId)->delete();
            }
        }

        Schema::table('cts_liquidaciones', function (Blueprint $table) {
            $table->dropColumn('dias_licencia');
        });
        Schema::dropIfExists('solicitudes');
    }

    private function agregarAlMenu(): void
    {
        // En una base vacía el menú lo siembra ModuloSeeder.
        if (DB::table('roles')->count() === 0) {
            return;
        }

        $nuevos = [
            ['padre' => 'Boletas y Finanzas', 'nombre' => 'Solicitudes', 'ruta' => '/solicitudes', 'orden' => 7, 'roles' => ['admin', 'rrhh']],
            ['padre' => 'Mi Espacio', 'nombre' => 'Mis Solicitudes', 'ruta' => '/mis-solicitudes', 'orden' => 4, 'roles' => ['admin', 'rrhh', 'empleado']],
        ];

        foreach ($nuevos as $m) {
            if (DB::table('modulos')->where('ruta', $m['ruta'])->exists()) {
                continue;
            }
            $padreId = DB::table('modulo_padre')->where('nombre', $m['padre'])->value('id');
            if (! $padreId) {
                continue;
            }

            $moduloId = (string) Str::uuid();
            DB::table('modulos')->insert([
                'id'              => $moduloId,
                'modulo_padre_id' => $padreId,
                'nombre'          => $m['nombre'],
                'ruta'            => $m['ruta'],
                'icono'           => 'calendar_check',
                'orden'           => $m['orden'],
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            foreach (DB::table('roles')->whereIn('nombre', $m['roles'])->pluck('id') as $rolId) {
                DB::table('rol_modulo')->insert(['rol_id' => $rolId, 'modulo_id' => $moduloId]);
            }
        }
    }
};
