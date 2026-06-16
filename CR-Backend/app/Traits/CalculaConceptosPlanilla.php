<?php
namespace App\Traits;

trait CalculaConceptosPlanilla
{
    /**
     * Comisiones AFP (componente flujo) - referencial 2026
     * Total = 10% aporte obligatorio + 1.37% prima de seguro + comisión AFP
     */
    private array $comisionesAfp = [
        'Habitat'   => 1.47,
        'Integra'   => 1.55,
        'Prima'     => 1.60,
        'Profuturo' => 1.69,
    ];

    private float $aporteObligatorioAfp = 10.00;
    private float $primaSeguroAfp = 1.37;
    private float $porcentajeOnp = 13.00;
    private float $porcentajeEssalud = 9.00;
    private float $asignacionFamiliarMonto = 113.00;

    /**
     * Calcula el descuento por sistema de pensiones (AFP u ONP)
     */
    protected function calcularDescuentoPension($empleado, $sueldoBase): array
    {
        $sueldoBase = (float) $sueldoBase;

        if ($empleado->sistema_pensiones === 'AFP' && $empleado->afp) {
            $comisionAfp = $this->comisionesAfp[$empleado->afp] ?? 0;

            $aporte   = round($sueldoBase * ($this->aporteObligatorioAfp / 100), 2);
            $prima    = round($sueldoBase * ($this->primaSeguroAfp / 100), 2);
            $comision = round($sueldoBase * ($comisionAfp / 100), 2);

            return [
                'tipo'    => 'AFP - ' . $empleado->afp,
                'detalle' => [
                    ['concepto' => "AFP Fondo de Pensiones ({$this->aporteObligatorioAfp}%)", 'monto' => $aporte],
                    ['concepto' => "AFP Prima de Seguro ({$this->primaSeguroAfp}%)", 'monto' => $prima],
                    ['concepto' => "AFP Comisión {$empleado->afp} ({$comisionAfp}%)", 'monto' => $comision],
                ],
                'total' => round($aporte + $prima + $comision, 2),
            ];
        }

        // Por defecto ONP
        $monto = round($sueldoBase * ($this->porcentajeOnp / 100), 2);
        return [
            'tipo'    => 'ONP',
            'detalle' => [
                ['concepto' => "ONP ({$this->porcentajeOnp}%)", 'monto' => $monto],
            ],
            'total' => $monto,
        ];
    }

    /**
     * Asignación familiar S/113 si el empleado tiene hijos registrados
     */
    protected function calcularAsignacionFamiliar($empleado): float
    {
        return $empleado->tiene_hijos ? $this->asignacionFamiliarMonto : 0.00;
    }

    /**
     * Gratificación: un sueldo base completo en julio y diciembre
     */
    protected function calcularGratificacion($sueldoBase, $mes): float
    {
        return in_array((int) $mes, [7, 12]) ? round((float) $sueldoBase, 2) : 0.00;
    }

    /**
     * Aportación ESSALUD (a cargo del empleador, no descuenta del sueldo del trabajador)
     */
    protected function calcularEssalud($sueldoBase): float
    {
        return round((float) $sueldoBase * ($this->porcentajeEssalud / 100), 2);
    }
}