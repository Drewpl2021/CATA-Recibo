<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Una línea del registro de auditoría: quién hizo qué, y cuándo.
 *
 * Solo se escribe. No hay pantalla ni ruta que edite o borre una fila:
 * un registro de auditoría que se puede retocar no prueba nada.
 */
class Auditoria extends Model
{
    protected $table = 'auditoria';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cambios'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** Solo la foto de quien lo hizo, para el circulito: {usuario_id, version}. */
    public function foto()
    {
        return $this->belongsTo(FotoDePerfil::class, 'user_id')->select('id', 'foto');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Anota algo en el registro.
     *
     * Sin una persona detrás —seeders, migraciones, comandos de consola— no
     * se anota: no hay a quién atribuirlo, y llenaría el registro con miles
     * de filas que no responden a ninguna pregunta.
     *
     * Y si anotar fallara, NO se tumba la operación: que la auditoría tenga
     * un problema no puede impedir que RR.HH. guarde un sueldo.
     */
    public static function registrar(
        string $accion,
        string $entidad,
        ?string $entidadId,
        string $descripcion,
        ?array $cambios = null
    ): void {
        $usuario = auth()->user();

        if (! $usuario) {
            return;
        }

        try {
            static::create([
                'user_id'        => $usuario->id,
                'usuario_nombre' => $usuario->name,
                'accion'         => $accion,
                'entidad'        => $entidad,
                'entidad_id'     => $entidadId,
                'descripcion'    => mb_substr($descripcion, 0, 255),
                'cambios'        => $cambios ?: null,
                'ip'             => request()?->ip(),
                // Desde la consola no hay navegador: Laravel pone «Symfony».
                'navegador'      => app()->runningInConsole() && ! app()->runningUnitTests()
                    ? null
                    : (mb_substr((string) request()?->userAgent(), 0, 255) ?: null),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
