<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * El RUC del colegio sale impreso en cada boleta, hoja de CTS y de
 * gratificación. Tres plantillas llevaron meses uno con un dígito errado
 * (20156630733): esto lo habría atajado.
 */
class RucDelColegioTest extends TestCase
{
    public function test_el_ruc_del_colegio_pasa_el_digito_verificador_de_sunat(): void
    {
        $ruc = (string) config('institucion.ruc');
        $this->assertMatchesRegularExpression('/^(10|15|17|20)\d{9}$/', $ruc);

        $suma = 0;
        foreach ([5, 4, 3, 2, 7, 6, 5, 4, 3, 2] as $i => $peso) {
            $suma += (int) $ruc[$i] * $peso;
        }
        $digito = 11 - $suma % 11;
        $digito = match ($digito) { 10 => 0, 11 => 1, default => $digito };

        $this->assertSame($digito, (int) $ruc[10], "El RUC {$ruc} no pasa el dígito verificador.");
    }

    public function test_las_plantillas_no_llevan_el_ruc_escrito_a_mano(): void
    {
        foreach (['boleta', 'cts', 'gratificacion'] as $vista) {
            $codigo = file_get_contents(resource_path("views/{$vista}.blade.php"));
            $this->assertDoesNotMatchRegularExpression('/\b20\d{9}\b/', $codigo, "{$vista}.blade.php tiene un RUC escrito a mano.");
        }
    }
}
