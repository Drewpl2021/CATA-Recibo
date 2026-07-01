<?php
namespace App\Traits;

trait CalculaConceptosPlanilla
{
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
    private float $uitValor = 5500.00;

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

        $monto = round($sueldoBase * ($this->porcentajeOnp / 100), 2);
        return [
            'tipo'    => 'ONP',
            'detalle' => [
                ['concepto' => "ONP ({$this->porcentajeOnp}%)", 'monto' => $monto],
            ],
            'total' => $monto,
        ];
    }

    protected function calcularAsignacionFamiliar($empleado): float
    {
        return $empleado->tiene_hijos ? $this->asignacionFamiliarMonto : 0.00;
    }

    protected function calcularGratificacion($sueldoBase, $mes): float
    {
        return in_array((int) $mes, [7, 12]) ? round((float) $sueldoBase, 2) : 0.00;
    }

    protected function calcularEssalud($sueldoBase): float
    {
        return round((float) $sueldoBase * ($this->porcentajeEssalud / 100), 2);
    }

    /**
     * Calcula retención mensual de Renta de 5ta Categoría 2026
     * UIT 2026 = S/ 5,500 — Exento: 7 UIT = S/ 38,500 anuales
     */
    protected function calcularRenta5taCategoria($sueldoBase, $bonificaciones, $mes): float
    {
        $sueldoBase     = (float) $sueldoBase;
        $bonificaciones = (float) $bonificaciones;
        $mes            = (int) $mes;

        // Proyección anual: 12 sueldos + 2 gratificaciones + bonificaciones anualizadas
        $ingresoAnual = ($sueldoBase * 12)
            + ($sueldoBase * 2)   // gratificaciones julio y diciembre
            + ($bonificaciones * 12);

        $exento = $this->uitValor * 7; // S/ 38,500

        if ($ingresoAnual <= $exento) {
            return 0.00;
        }

        $rentaNeta = $ingresoAnual - $exento;

        // Tramos progresivos acumulativos
        $uit = $this->uitValor;
        $impuesto = 0.00;

        // 8% hasta 5 UIT (S/ 27,500)
        $tramo1 = $uit * 5;
        if ($rentaNeta > 0) {
            $base = min($rentaNeta, $tramo1);
            $impuesto += $base * 0.08;
            $rentaNeta -= $base;
        }

        // 14% de 5 a 20 UIT (S/ 82,500)
        $tramo2 = $uit * 15;
        if ($rentaNeta > 0) {
            $base = min($rentaNeta, $tramo2);
            $impuesto += $base * 0.14;
            $rentaNeta -= $base;
        }

        // 17% de 20 a 35 UIT (S/ 82,500)
        $tramo3 = $uit * 15;
        if ($rentaNeta > 0) {
            $base = min($rentaNeta, $tramo3);
            $impuesto += $base * 0.17;
            $rentaNeta -= $base;
        }

        // 20% de 35 a 45 UIT (S/ 55,000)
        $tramo4 = $uit * 10;
        if ($rentaNeta > 0) {
            $base = min($rentaNeta, $tramo4);
            $impuesto += $base * 0.20;
            $rentaNeta -= $base;
        }

        // 30% más de 45 UIT
        if ($rentaNeta > 0) {
            $impuesto += $rentaNeta * 0.30;
        }

        // Retención mensual = impuesto anual / 12
        return round($impuesto / 12, 2);
    }
}