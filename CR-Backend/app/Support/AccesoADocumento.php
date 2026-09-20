<?php

namespace App\Support;

use App\Models\Documento;
use App\Models\User;

/**
 * Quién puede ver y quién puede descargar un documento, en UN solo sitio.
 *
 * La regla del colegio, pensada en este orden:
 *
 *   VER       siempre. Firmar algo que no se puede leer antes no prueba
 *             nada, y la norma de boletas electrónicas pide justamente que
 *             el trabajador pueda verificarla. Al abrirla queda anotado que
 *             la revisó: eso ANTES había que marcarlo a mano y nadie lo
 *             hacía, así que la columna "Revisado" salía siempre vacía.
 *
 *   DESCARGAR después de firmar. Llevarse el PDF es el final del camino:
 *             la reviso, la firmo, me la llevo. Vale para el trabajador y
 *             para los documentos que se firman; RR.HH. y Administración
 *             descargan siempre, porque son quienes responden si alguien
 *             reclama.
 *
 * Ojo con lo que esto NO es: como ver sí está permitido, quien quiera puede
 * guardar el PDF desde el visor. Esto ordena el proceso y deja el rastro
 * completo; no es una cerradura contra el propio dueño del documento.
 */
final class AccesoADocumento
{
    public static function esRrhhOAdmin(?User $usuario): bool
    {
        return in_array($usuario?->rol?->nombre, ['rrhh', 'admin'], true);
    }

    public static function esSuyo(Documento $documento, ?User $usuario): bool
    {
        return $usuario?->empleado_id !== null && $documento->empleado_id === $usuario->empleado_id;
    }

    /** ¿Puede siquiera abrirlo? Solo su dueño, RR.HH. y Administración. */
    public static function puedeVer(Documento $documento, ?User $usuario): bool
    {
        return self::esSuyo($documento, $usuario) || self::esRrhhOAdmin($usuario);
    }

    /**
     * Por qué todavía no se puede descargar; null si sí se puede.
     *
     * El mensaje sale tal cual en la pantalla, así que dice qué hacer.
     */
    public static function porQueNoPuedeDescargar(Documento $documento, ?User $usuario): ?string
    {
        if (self::esRrhhOAdmin($usuario)) {
            return null;
        }
        if (! self::esSuyo($documento, $usuario)) {
            return 'No tienes permiso para descargar este documento.';
        }
        if (! ExpedienteDigital::seFirma($documento->tipo) || $documento->estado_firma === 'firmado') {
            return null;
        }

        return $documento->tipo === 'boleta'
            ? 'Primero firma tu boleta; después la puedes descargar.'
            : 'Primero firma este documento; después lo puedes descargar.';
    }

    /**
     * Deja constancia de que su dueño lo abrió.
     *
     * Solo la primera vez y solo lo que se firma: la hoja de vida es suya y
     * los archivos anteriores son copias de algo que ya pasó.
     */
    public static function marcarVisto(Documento $documento, ?User $usuario): void
    {
        if (! self::esSuyo($documento, $usuario)) {
            return;
        }
        if (! ExpedienteDigital::seFirma($documento->tipo) || $documento->estado_firma !== 'pendiente') {
            return;
        }

        $documento->forceFill([
            'estado_firma' => 'visto',
            'fecha_visto'  => now(),
        ])->save();
    }
}
