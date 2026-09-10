<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentConcept;
use App\Support\ConceptosDePago;

/**
 * El catálogo de conceptos, igual que la boleta física del colegio.
 *
 * Las cuatro categorías y su orden salen del documento en papel: primero los
 * ingresos, después los descuentos, las aportaciones del colegio y por
 * último los adelantos. Cada bloque cierra con una bolsa "Otros Conceptos",
 * que es donde entra lo que le pasa a UNA persona ese mes concreto —un
 * subsidio de maternidad, un descuento puntual— sin tener que inventarle un
 * concepto nuevo al catálogo cada vez: se agrega la línea con su descripción
 * y la boleta imprime "Otros Conceptos: Subsidio de Maternidad".
 *
 * Sobre las columnas:
 *
 *   calculo/valor    la regla POR DEFECTO. Al agregar el concepto a una
 *                    planilla se copia, y ahí se puede ajustar sin tocar el
 *                    catálogo (que se lo cambiaría a todo el colegio).
 *   aplica_a_todos   si se le mete solo a cada planilla que se crea. Se usa
 *                    con cuentagotas: casi todo es caso por caso.
 *
 * Los seis de cálculo especial (pensión, EsSalud, Renta de 5ta) llevan su
 * valor de referencia, pero el monto real lo calcula el motor por empleado:
 * depende de su sistema de pensión, de su AFP concreta, de si cobra
 * asignación familiar y de lo que lleve retenido en el año.
 */
class PaymentConceptSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        PaymentConcept::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $conceptos = [
            // ══ INGRESOS ═══════════════════════════════════════════
            [
                'nombre' => 'Remuneración Básica',
                'tipo' => 'bonificacion', 'calculo' => null, 'valor' => null,
                'descripcion' => 'El sueldo del mes. NO se agrega como línea: sale de la ficha del trabajador y la boleta lo imprime en su propia fila. Está en el catálogo para que se vea completo.',
            ],
            [
                'nombre' => 'Bonificación por Cargo',
                'tipo' => 'bonificacion', 'calculo' => null, 'valor' => null,
                'descripcion' => 'Lo que se le paga de más por el puesto que ocupa (coordinación, jefatura). El monto es de cada quien, así que se agrega caso por caso.',
            ],
            [
                'nombre' => 'Asignación Familiar',
                'tipo' => 'bonificacion', 'calculo' => 'fijo', 'valor' => 113.00,
                'descripcion' => 'El 10% de la RMV para quien tiene hijos menores de 18, o hasta 24 si siguen estudiando. Lo calcula el sistema mirando "tiene hijos" en su ficha.',
            ],
            [
                'nombre' => 'Vacaciones Truncas',
                'tipo' => 'bonificacion', 'calculo' => null, 'valor' => null,
                'descripcion' => 'Las vacaciones ganadas y no tomadas que se pagan al cesar: un doceavo del sueldo por cada mes completo trabajado desde su último periodo.',
            ],
            [
                'nombre' => 'Gratificaciones Fiestas Patrias - Ley 29351 y 30334',
                'tipo' => 'bonificacion', 'calculo' => null, 'valor' => null,
                'descripcion' => 'La gratificación de julio y la de diciembre. Es un sueldo completo si trabajó el semestre entero, o la parte proporcional a los meses que sí trabajó.',
            ],
            [
                'nombre' => 'Bonif. Extraord. Temporal - Ley 29351 y 30334',
                'tipo' => 'bonificacion', 'calculo' => 'porcentaje', 'valor' => 9.00,
                'descripcion' => 'El 9% de la gratificación: es el aporte a EsSalud que la ley ordena entregarle al trabajador en vez de retenerlo. Solo sale en los meses con gratificación.',
            ],
            [
                'nombre' => 'Bonificaciones',
                'tipo' => 'bonificacion', 'calculo' => null, 'valor' => null,
                'descripcion' => 'Bonificación general, distinta de la de cargo y de la extraordinaria. Para lo que el colegio otorgue sin una regla fija.',
            ],
            [
                'nombre' => 'Compensación por Tiempo de Servicios',
                'tipo' => 'bonificacion', 'calculo' => null, 'valor' => null,
                'descripcion' => 'La CTS. Normalmente se deposita en su cuenta en mayo y noviembre; acá solo aparece cuando se le paga junto con la boleta.',
            ],
            [
                'nombre' => ConceptosDePago::OTROS_INGRESOS,
                'etiqueta_boleta' => 'Otros Conceptos',
                'tipo' => 'bonificacion', 'calculo' => null, 'valor' => null,
                'descripcion' => 'La bolsa para un ingreso puntual de UNA persona: subsidio de maternidad, reintegro, movilidad de un viaje. Se escribe el detalle al agregarlo y la boleta imprime "Otros Conceptos: Subsidio de Maternidad".',
            ],

            // ══ DESCUENTOS ═════════════════════════════════════════
            [
                'nombre' => ConceptosDePago::ONP,
                'tipo' => 'descuento', 'calculo' => 'porcentaje', 'valor' => 13.00,
                'descripcion' => 'El aporte a la ONP. Solo para quien está en el Sistema Nacional; lo calcula el sistema sobre el sueldo más la asignación familiar.',
            ],
            [
                'nombre' => ConceptosDePago::SPP_FONDO,
                'tipo' => 'descuento', 'calculo' => 'porcentaje', 'valor' => 10.00,
                'descripcion' => 'El aporte obligatorio a la AFP: el 10% que se va a su fondo de jubilación. Solo para quien está en una AFP.',
            ],
            [
                'nombre' => ConceptosDePago::SPP_PRIMA_SEGURO,
                'tipo' => 'descuento', 'calculo' => 'porcentaje', 'valor' => null,
                'descripcion' => 'La tasa que cobra su AFP y que cambia según cuál sea (Habitat, Integra, Prima, Profuturo). No tiene un valor fijo acá: lo pone el sistema según su ficha.',
            ],
            [
                'nombre' => ConceptosDePago::SPP_COMISION,
                'tipo' => 'descuento', 'calculo' => 'porcentaje', 'valor' => 1.37,
                'descripcion' => 'La prima del seguro de invalidez y sobrevivencia, igual para todas las AFP. Lo calcula el sistema.',
            ],
            [
                'nombre' => ConceptosDePago::RENTA_5TA,
                'tipo' => 'descuento', 'calculo' => null, 'valor' => null,
                'descripcion' => 'La retención de quinta categoría. No es un porcentaje fijo: se calcula por tramos sobre lo que va a ganar en el año y descontando lo ya retenido, así que solo le sale a quien pasa las 7 UIT.',
            ],
            [
                'nombre' => 'Descuento Serv. Alimentación',
                'tipo' => 'descuento', 'calculo' => null, 'valor' => null,
                'descripcion' => 'Lo que consumió en el comedor del colegio y se le descuenta del sueldo.',
            ],
            [
                'nombre' => 'Descuento Autorizado - Diezmo',
                'tipo' => 'descuento', 'calculo' => 'porcentaje', 'valor' => 10.00,
                'descripcion' => 'El diezmo, que el trabajador autoriza por escrito a que se le descuente y se entregue a la iglesia. Sin esa autorización no se aplica.',
            ],
            [
                'nombre' => 'Descuento - Pago de Escolaridad Mensual',
                'tipo' => 'descuento', 'calculo' => null, 'valor' => null,
                'descripcion' => 'La pensión de sus hijos estudiando en el colegio, descontada del sueldo en vez de pagarla en caja.',
            ],
            [
                'nombre' => ConceptosDePago::OTROS_DESCUENTOS,
                'etiqueta_boleta' => 'Otros Conceptos',
                'tipo' => 'descuento', 'calculo' => null, 'valor' => null,
                'descripcion' => 'La bolsa para un descuento puntual de UNA persona: un préstamo, una tardanza, un material que se le entregó. Se escribe el detalle al agregarlo y la boleta imprime "Otros Conceptos: Préstamo".',
            ],

            // ══ APORTACIONES DEL COLEGIO ═══════════════════════════
            // Informativas: las paga el empleador y NO se restan del neto.
            [
                'nombre' => ConceptosDePago::ESSALUD,
                'tipo' => 'aportacion', 'calculo' => 'porcentaje', 'valor' => 9.00,
                'aplica_a_todos' => true,
                'descripcion' => 'El 9% que el colegio aporta por cada trabajador para su seguro de salud. No sale de su sueldo: se imprime para que sepa cuánto se paga por él.',
            ],
            [
                'nombre' => 'SCTR',
                'tipo' => 'aportacion', 'calculo' => null, 'valor' => null,
                'descripcion' => 'Seguro Complementario de Trabajo de Riesgo, para los puestos que la ley considera riesgosos. La tasa depende de la actividad y de la aseguradora contratada.',
            ],

            // ══ ADELANTOS ══════════════════════════════════════════
            // Restan del neto, pero van en su propio bloque: no son un
            // descuento, es dinero que ya se le entregó antes.
            [
                'nombre' => 'Adelanto de Sueldo',
                'tipo' => 'adelanto', 'calculo' => null, 'valor' => null,
                'descripcion' => 'Dinero del sueldo del mes que se le entregó por adelantado. Se le resta del neto al pagar.',
            ],
            [
                'nombre' => 'Adelanto de Bonificaciones',
                'tipo' => 'adelanto', 'calculo' => null, 'valor' => null,
                'descripcion' => 'Igual que el anterior, pero sobre una bonificación o gratificación que todavía no tocaba pagar.',
            ],
        ];

        foreach ($conceptos as $concepto) {
            PaymentConcept::create($concepto);
        }

        $porTipo = collect($conceptos)->countBy('tipo');
        $this->command?->info(sprintf(
            '   %d conceptos de pago: %d ingresos, %d descuentos, %d aportaciones, %d adelantos',
            count($conceptos),
            $porTipo['bonificacion'] ?? 0,
            $porTipo['descuento'] ?? 0,
            $porTipo['aportacion'] ?? 0,
            $porTipo['adelanto'] ?? 0
        ));
    }
}
