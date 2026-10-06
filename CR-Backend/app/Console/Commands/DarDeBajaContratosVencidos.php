<?php

namespace App\Console\Commands;

use App\Models\Empleado;
use App\Models\Notificacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Cada madrugada: a quien se le acabó el contrato y nadie lo renovó, se le
 * da de baja en su fecha de fin.
 *
 * Lo pidió RR.HH.: buscaban a Limachi (contrato hasta el 13/04) y seguía
 * "Activa" en Empleados. Un contrato vencido sin renovar quiere decir que
 * la persona ya no trabaja; si sí sigue, se renueva en Contratos y se la
 * vuelve a activar.
 *
 * La baja es la de siempre (Empleado::darDeBaja): inactivo desde su fecha de
 * fin, contrato cerrado y cuenta sin acceso. No se borra nada, y como el
 * contrato recuerda que lo cerró una baja, reactivarla lo deja como estaba.
 *
 * A RR.HH. y Administración les queda un aviso en la campana con los
 * nombres, para que nadie se entere por el trabajador.
 */
class DarDeBajaContratosVencidos extends Command
{
    protected $signature = 'contratos:dar-de-baja-vencidos';

    protected $description = 'Da de baja, en su fecha de fin, a quien tiene el contrato vencido y sigue activo';

    public function handle(): int
    {
        $hoy = now()->toDateString();
        $vencidos = Empleado::conContratoVencido($hoy)->orderBy('apellido')->orderBy('nombre')->get();

        if ($vencidos->isEmpty()) {
            $this->info('Nadie tiene el contrato vencido.');

            return self::SUCCESS;
        }

        $nombres = [];
        foreach ($vencidos as $empleado) {
            $fin = $empleado->finDeContrato();
            $empleado->darDeBaja($fin);
            $nombres[] = trim("{$empleado->nombre} {$empleado->apellido}") . ' (' . Carbon::parse($fin)->format('d/m/Y') . ')';
            $this->line("  De baja: {$nombres[array_key_last($nombres)]}");
        }

        $this->avisar($nombres);
        $this->info(count($nombres) . ' trabajador(es) pasaron a inactivo por contrato vencido.');

        return self::SUCCESS;
    }

    /** Un aviso en la campana de RR.HH. y Administración, con quiénes y desde cuándo. */
    private function avisar(array $nombres): void
    {
        $cuantos = count($nombres);
        $lista   = implode(', ', array_slice($nombres, 0, 8)) . ($cuantos > 8 ? ' y ' . ($cuantos - 8) . ' más' : '');

        $usuarios = User::whereHas('rol', fn ($q) => $q->whereIn('nombre', ['admin', 'rrhh']))
            ->where('estado_registro', 'activo')
            ->get();

        foreach ($usuarios as $user) {
            Notificacion::create([
                'user_id' => $user->id,
                'tipo'    => 'contratos_vencidos',
                'titulo'  => $cuantos === 1
                    ? 'Un trabajador pasó a inactivo: se le venció el contrato'
                    : "{$cuantos} trabajadores pasaron a inactivo: se les venció el contrato",
                'mensaje' => "Se les dio de baja en su fecha de fin de contrato: {$lista}. "
                    . 'Si alguno sigue trabajando, renuévale el contrato en Contratos y vuelve a activarlo en Empleados.',
            ]);
        }
    }
}
