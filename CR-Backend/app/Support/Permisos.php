<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Quién puede qué: los permisos son los MÓDULOS de cada rol.
 *
 * Lo que se marca en Roles (la tabla rol_modulo) es a la vez el menú y el
 * permiso real del servidor. Un rol nuevo («Contabilidad») con Planillas y
 * CTS marcados entra a esas rutas sin tocar código; si se le quita CTS,
 * deja de entrar en ese momento.
 *
 * Cada ruta dice qué módulo pide, por su ruta de menú:
 *
 *   ->middleware('permiso:/planillas')            hay que tener Planillas
 *   ->middleware('permiso:/planillas,/boletas')   basta con uno de los dos
 *   ->middleware('permiso:@gestion')              cualquier módulo de trabajo
 *                                                 (todo menos «Mi Espacio»):
 *                                                 los catálogos que leen
 *                                                 muchas pantallas
 *
 * El rol con `es_superadmin` entra a todo: es la llave para que nadie quede
 * fuera por quitarle un módulo a quien no debía.
 *
 * Los módulos de cada rol se guardan en caché; cualquier cambio en Roles o
 * Módulos llama a refrescar().
 */
final class Permisos
{
    /** Cualquier módulo de trabajo: todo lo que no es «Mi Espacio». */
    public const GESTION = '@gestion';

    /** Los módulos de «Mi Espacio»: los del propio trabajador, no dan acceso a lo de otros. */
    private const PROPIOS = ['/mis-boletas', '/mis-documentos', '/mis-vacaciones'];

    private const CLAVE_VERSION = 'permisos.version';

    public static function esSuperadmin(?User $usuario): bool
    {
        return (bool) $usuario?->rol?->es_superadmin;
    }

    /** ¿Tiene al menos uno de esos módulos? */
    public static function puede(?User $usuario, string ...$modulos): bool
    {
        if (! $usuario) {
            return false;
        }
        if (self::esSuperadmin($usuario)) {
            return true;
        }

        $suyos = self::delRol($usuario->rol_id);
        foreach ($modulos as $modulo) {
            if ($modulo === self::GESTION) {
                if (array_diff($suyos, self::PROPIOS)) {
                    return true;
                }
                continue;
            }
            if (in_array($modulo, $suyos, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Las rutas de los módulos activos de un rol, de la caché.
     *
     * @return list<string>
     */
    public static function delRol(?string $rolId): array
    {
        if (! $rolId) {
            return [];
        }
        $version = Cache::get(self::CLAVE_VERSION, 1);

        return Cache::remember("permisos.rol.{$rolId}.v{$version}", now()->addHour(), fn () => DB::table('rol_modulo')
            ->join('modulos', 'modulos.id', '=', 'rol_modulo.modulo_id')
            ->where('rol_modulo.rol_id', $rolId)
            ->where('modulos.estado_registro', 'activo')
            ->pluck('modulos.ruta')->filter()->values()->all());
    }

    /** Lo que la pantalla necesita saber: sus módulos, o «todo» si es superadministrador. */
    public static function paraPantalla(?User $usuario): array
    {
        return [
            'es_superadmin' => self::esSuperadmin($usuario),
            'modulos'       => self::esSuperadmin($usuario) ? ['*'] : self::delRol($usuario?->rol_id),
        ];
    }

    /** Cambió algo en Roles o Módulos: la próxima consulta lee la base. */
    public static function refrescar(): void
    {
        Cache::forever(self::CLAVE_VERSION, (int) Cache::get(self::CLAVE_VERSION, 1) + 1);
    }

    /**
     * Las cuentas activas que tienen un módulo: a quién avisar de algo (la
     * planilla pendiente, los contratos vencidos).
     */
    public static function usuariosCon(string $modulo): Builder
    {
        return User::where('estado_registro', 'activo')->whereHas('rol', fn (Builder $r) => $r
            ->where('es_superadmin', true)
            ->orWhereIn('id', DB::table('rol_modulo')
                ->join('modulos', 'modulos.id', '=', 'rol_modulo.modulo_id')
                ->where('modulos.ruta', $modulo)->where('modulos.estado_registro', 'activo')
                ->select('rol_modulo.rol_id')));
    }
}
