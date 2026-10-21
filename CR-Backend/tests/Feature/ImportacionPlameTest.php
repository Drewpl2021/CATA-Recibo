<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\PaymentConcept;
use App\Models\PayrollDetalle;
use App\Models\Planilla;
use App\Models\PlanillaCorrida;
use App\Support\ConceptosDePago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Importar el PLAME de RR.HH.: un .xls con la misma forma que el de marzo
 * (cabecera en tres filas con celdas combinadas, fila de TOTALES), con gente
 * ficticia.
 */
class ImportacionPlameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            ConceptosDePago::BONIFICACION_CARGO => 'bonificacion', ConceptosDePago::ASIGNACION_FAMILIAR => 'bonificacion',
            ConceptosDePago::GRATIFICACION => 'bonificacion', ConceptosDePago::ONP => 'descuento',
            ConceptosDePago::SPP_FONDO => 'descuento', ConceptosDePago::SPP_COMISION => 'descuento',
            ConceptosDePago::SPP_PRIMA_SEGURO => 'descuento', ConceptosDePago::RENTA_5TA => 'descuento',
            ConceptosDePago::DIEZMO => 'descuento', ConceptosDePago::ESSALUD => 'aportacion',
            ConceptosDePago::OTROS_INGRESOS => 'bonificacion', ConceptosDePago::OTROS_DESCUENTOS => 'descuento',
            ConceptosDePago::VACACIONES_TRUNCAS => 'bonificacion', ConceptosDePago::MOVILIDAD => 'bonificacion',
            'Compensación por Tiempo de Servicios' => 'bonificacion', 'Descuento - Pago de Escolaridad Mensual' => 'descuento',
            'Descuento Serv. Alimentación' => 'descuento', 'Descuento Serv. Copias' => 'descuento',
            'Descuento Autorizado - Tardanzas y Faltas' => 'descuento', 'Adelanto de Sueldo' => 'adelanto', 'SCTR' => 'aportacion',
        ] as $nombre => $tipo) {
            PaymentConcept::firstOrCreate(['nombre' => $nombre], ['tipo' => $tipo]);
        }
    }

    public function test_crea_la_planilla_del_mes_tal_como_se_pago_y_junta_los_extras_en_otros(): void
    {
        $empleado = $this->crearEmpleado(['dni' => '40000001', 'sistema_pensiones' => 'AFP', 'afp' => 'Integra', 'cuspp' => '612561WHYNC2', 'tiene_hijos' => 1]);
        $fila = $this->fila(['dni' => '40000001', 'nombre' => 'PRUEBA, ANA', 'hijos' => 'SI', 'asignacion' => 113, 'afp' => 'INTEGRA',
            'cuspp' => '612561WHYNC2', 'fondo' => 211.3, 'comision' => 32.75, 'prima' => 28.95, 'curso' => 60, 'corbatas' => 12]);

        $this->importar('apply', $this->plame([$fila]))->assertOk();

        $planilla = Planilla::where('empleado_id', $empleado->id)->where('mes', 3)->firstOrFail();
        $this->assertTrue($planilla->corrida->vieneDelPlame());
        $this->assertTrue($planilla->corrida->estaCerrada());
        $this->assertEqualsWithDelta(2000, (float) $planilla->sueldo_base, 0.001);

        $lineas = PayrollDetalle::with('paymentConcept')->where('planilla_id', $planilla->id)->get()
            ->mapWithKeys(fn ($d) => [$d->paymentConcept->nombre => $d]);
        $this->assertEqualsWithDelta(113, (float) $lineas[ConceptosDePago::ASIGNACION_FAMILIAR]->monto_calculado, 0.001);
        $this->assertEqualsWithDelta(211.3, (float) $lineas[ConceptosDePago::SPP_FONDO]->monto_calculado, 0.001);
        $this->assertEqualsWithDelta(72, (float) $lineas[ConceptosDePago::OTROS_DESCUENTOS]->monto_calculado, 0.001);
        // Como lo escribió RR.HH. a mano en setiembre.
        $this->assertSame('Curso IA (S/ 60.00) y Corbatas y polos (S/ 12.00)', $lineas[ConceptosDePago::OTROS_DESCUENTOS]->descripcion);
        // El subtotal «Aporte Obligatorio» no entra como descuento: el neto es el del PLAME.
        $this->assertEqualsWithDelta(1568.0, $fila['neto'], 0.001);
        $this->assertEqualsWithDelta($fila['neto'], (float) $planilla->total, 0.001);
    }

    public function test_si_una_fila_no_cuadra_no_se_guarda_nada(): void
    {
        $this->crearEmpleado(['dni' => '40000001']);
        $fila = $this->fila(['dni' => '40000001']);
        $fila['neto'] += 50;

        $res = $this->importar('apply', $this->plame([$fila]))->assertStatus(422);

        $this->assertStringContainsString('No cuadra', collect($res->json('data.errores'))->pluck('mensaje')->implode(' '));
        $this->assertSame(0, Planilla::count());
        $this->assertSame(0, PlanillaCorrida::count());
    }

    public function test_quien_no_esta_en_el_sistema_entra_activo_o_cesado_segun_su_contrato(): void
    {
        $anio = (int) now()->year;
        $this->importar('apply', $this->plame([
            $this->fila(['dni' => '40000002', 'nombre' => 'QUISPE MAMANI JUAN CARLOS', 'cese' => '31/12/' . $anio]),
            $this->fila(['dni' => '40000003', 'nombre' => 'ROJAS, LUZ', 'cese' => '31/03/' . $anio]),
            $this->fila(['dni' => '40000004', 'nombre' => 'TICONA, PEDRO', 'categoria' => 'Plazo indeterminado', 'cese' => 'INDETERMINADO']),
        ]), anio: $anio)->assertOk();

        $activo = Empleado::where('dni', '40000002')->firstOrFail();
        $this->assertSame('activo', $activo->estado);
        $this->assertSame('Quispe Mamani', $activo->apellido);
        $this->assertSame('Juan Carlos', $activo->nombre);
        $this->assertSame('ONP', $activo->sistema_pensiones);
        $this->assertSame('vigente', $activo->contratos()->first()->estado);
        $this->assertNull($activo->usuario, 'Entra sin cuenta: el PLAME no trae correo');

        // Su contrato terminó en marzo y hoy ya pasó.
        $this->assertSame('inactivo', Empleado::where('dni', '40000003')->value('estado'));
        $this->assertSame('activo', Empleado::where('dni', '40000004')->value('estado'));
        $this->assertSame(3, Planilla::count());
    }

    public function test_rrhh_puede_marcar_como_cesado_a_quien_el_contrato_daba_por_vigente(): void
    {
        $anio = (int) now()->year;
        $this->importar('apply', $this->plame([
            $this->fila(['dni' => '40000002', 'nombre' => 'QUISPE, JUAN', 'cese' => '31/12/' . $anio]),
        ]), ['personas' => ['40000002' => 'cesado']], anio: $anio)->assertOk();

        $empleado = Empleado::where('dni', '40000002')->firstOrFail();
        $this->assertSame('inactivo', $empleado->estado);
        $this->assertSame("{$anio}-03-31", \Carbon\Carbon::parse($empleado->fecha_cese)->toDateString());
    }

    public function test_volver_a_importar_el_mes_reemplaza_lo_importado_antes(): void
    {
        $empleado = $this->crearEmpleado(['dni' => '40000001']);

        $this->importar('apply', $this->plame([$this->fila(['dni' => '40000001', 'sueldo' => 2000])]))->assertOk();
        $res = $this->importar('apply', $this->plame([$this->fila(['dni' => '40000001', 'sueldo' => 2100])]))->assertOk();

        $this->assertSame('reemplazar', $res->json('data.resumen.modo'));
        $this->assertSame(1, Planilla::where('empleado_id', $empleado->id)->count());
        $this->assertSame(1, PlanillaCorrida::count());
        $this->assertEqualsWithDelta(2100, (float) Planilla::first()->sueldo_base, 0.001);
    }

    public function test_un_mes_con_planillas_del_sistema_solo_se_compara(): void
    {
        $empleado = $this->crearEmpleado(['dni' => '40000001']);
        $corrida = PlanillaCorrida::create(['nombre' => 'Planilla General', 'mes' => 3, 'anio' => (int) now()->year, 'estado' => 'abierta']);
        Planilla::create(['empleado_id' => $empleado->id, 'mes' => 3, 'anio' => (int) now()->year, 'corrida_id' => $corrida->id, 'sueldo_base' => 1900, 'total' => 1900]);

        $res = $this->importar('preview', $this->plame([$this->fila(['dni' => '40000001'])]))->assertOk();
        $this->assertSame('comparar', $res->json('data.resumen.modo'));
        $this->assertSame('con_diferencias', $res->json('data.trabajadores.0.situacion'));
        $this->assertContains(ConceptosDePago::REMUNERACION_BASICA, collect($res->json('data.trabajadores.0.diferencias'))->pluck('concepto'));

        $this->importar('apply', $this->plame([$this->fila(['dni' => '40000001'])]))->assertStatus(422);
        $this->assertSame(1, Planilla::count());
    }

    public function test_avisa_cuando_el_plame_no_coincide_con_la_ficha(): void
    {
        $this->crearEmpleado(['dni' => '40000001', 'tiene_hijos' => 0]);

        $res = $this->importar('preview', $this->plame([$this->fila(['dni' => '40000001', 'hijos' => 'SI', 'asignacion' => 113])]))->assertOk();

        $this->assertStringContainsString('asignación familiar', implode(' ', $res->json('data.trabajadores.0.avisos')));
    }

    public function test_una_planilla_del_plame_no_se_reabre(): void
    {
        $this->crearEmpleado(['dni' => '40000001']);
        $this->importar('apply', $this->plame([$this->fila(['dni' => '40000001'])]))->assertOk();

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->putJson('/api/payroll-runs/' . PlanillaCorrida::first()->id, ['estado' => 'abierta'])
            ->assertStatus(422);
        $this->assertSame('cerrada', PlanillaCorrida::first()->estado);
    }

    /**
     * El PLAME de prueba del repositorio (postman/fixtures): la hoja PLANILLA
     * del PLAME real, con 24 personas inventadas (DNI 990001xx) y cuatro
     * conceptos que no son de siempre. Sirve para probar el importe a mano
     * en una base de demostración, y esta prueba cuida que siga entrando.
     */
    public function test_el_plame_de_prueba_del_repositorio_entra_completo(): void
    {
        $ruta = base_path('postman/fixtures/PLAME DE PRUEBA (personal ficticio).xls');
        $copia = tempnam(sys_get_temp_dir(), 'plame') . '.xls';
        copy($ruta, $copia);
        $archivo = new UploadedFile($copia, basename($ruta), 'application/vnd.ms-excel', null, true);

        $res = $this->importar('apply', $archivo, mes: 5)->assertOk();

        $this->assertSame(24, $res->json('data.resumen.trabajadores'));
        $this->assertSame(24, Empleado::where('dni', 'like', '990001%')->count());
        $this->assertSame(24, Planilla::where('mes', 5)->count());
        $detalles = PayrollDetalle::whereNotNull('descripcion')->pluck('descripcion')->implode(' | ');
        $this->assertStringContainsString('Bono por productividad (S/ 200.00)', $detalles);
        $this->assertStringContainsString('Curso IA (S/ 60.00)', $detalles);
        $this->assertStringContainsString('Uniforme deportivo (S/ 45.00)', $detalles);
    }

    public function test_un_trabajador_no_puede_importar_el_plame(): void
    {
        $this->actingAs($this->crearUsuario('empleado'), 'sanctum')
            ->post('/api/plame-import/preview', ['mes' => 3, 'anio' => (int) now()->year, 'archivo' => $this->plame([])], ['Accept' => 'application/json'])
            ->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────

    private function importar(string $paso, UploadedFile $archivo, array $decisiones = [], ?int $mes = 3, ?int $anio = null)
    {
        return $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->post("/api/plame-import/{$paso}", [
            'mes' => $mes, 'anio' => $anio ?? (int) now()->year, 'archivo' => $archivo,
            'decisiones' => json_encode($decisiones ?: new \stdClass()),
        ], ['Accept' => 'application/json']);
    }

    /** Una fila del PLAME con sus totales ya sumados, como los trae el Excel. */
    private function fila(array $d): array
    {
        $d += [
            'dni' => '40000001', 'nombre' => 'PRUEBA, ANA', 'cargo' => 'Docente de Primaria', 'centro' => 'PRIMARIA',
            'categoria' => 'CONTRATADO', 'ingreso' => '01/03/2020', 'cese' => '31/12/' . now()->year, 'hijos' => 'NO',
            'sueldo' => 2000, 'bonificacion' => 0, 'asignacion' => 0, 'gratificacion' => 0,
            'onp' => null, 'afp' => null, 'cuspp' => null, 'tipo' => null, 'fondo' => 0, 'comision' => 0, 'prima' => 0,
            'quinta' => 0, 'diezmo' => 200, 'curso' => 0, 'corbatas' => 0, 'essalud' => 180,
        ];
        if ($d['onp'] === null && ! $d['afp']) {
            $d['onp'] = round(($d['sueldo'] + $d['bonificacion'] + $d['asignacion']) * 0.13, 2);
        }
        $d['onp'] ??= 0;
        $d['bruto'] = $d['sueldo'] + $d['bonificacion'] + $d['asignacion'] + $d['gratificacion'];
        $d['pension'] = $d['onp'] + $d['fondo'] + $d['comision'] + $d['prima'];
        $d['descuentos'] = $d['pension'] + $d['quinta'] + $d['diezmo'] + $d['curso'] + $d['corbatas'];
        $d['neto'] = round($d['bruto'] - $d['descuentos'], 2);

        return $d;
    }

    /** El .xls con la forma del PLAME de RR.HH. */
    private function plame(array $filas): UploadedFile
    {
        $libro = new Spreadsheet();
        $libro->getActiveSheet()->setTitle('datos')->setCellValue('A1', 'Hoja que no es la planilla');
        $h = $libro->createSheet()->setTitle('PLANILLA');

        // El cuadro de tasas de arriba, que no es la cabecera.
        $h->setCellValue('C2', 'RMV')->setCellValue('N2', 'SNP/ ONP')->setCellValue('O2', 'PRIMA');
        $h->setCellValue('B8', 'PLANILLA DE REMUNERACIONES');

        $una = ['B' => 'ORDEN', 'D' => 'APELLIDOS Y NOMBRES', 'F' => 'CARGO U OCUPACIÓN', 'G' => 'CENTRO DE COSTOS',
            'H' => 'CATEGORÍA', 'I' => 'FECHA  INGRESO', 'J' => 'FECHA  CESE', 'N' => 'ASIGNACIÓN FAMILIAR',
            'V' => 'TOTAL REMUNERACIÓN BRUTA', 'AN' => 'REMUNERACIÓN NETA'];
        foreach ($una as $col => $titulo) {
            $h->setCellValue("{$col}12", $titulo)->mergeCells("{$col}12:{$col}14");
        }
        $h->setCellValue('E13', 'DNI');
        $h->setCellValue('Q12', 'INGRESOS DEL TRABAJADOR')->setCellValue('W12', 'RETENCIONES  A  CARGO  DEL  TRABAJADOR')
            ->setCellValue('AO12', 'APORTACIONES DEL EMPLEADOR')->mergeCells('AO12:AP12');
        $dos = ['O' => 'SUELDO BÁSICO', 'P' => 'BONIFICACIÓN POR FUNCIÓN', 'Q' => 'ASIGNACIÓN FAMILIAR', 'S' => 'GRATIFICACIONES',
            'AE' => 'IR. 5ta Categoría', 'AF' => 'Aporte Obligatorio', 'AG' => 'Diezmo', 'AH' => 'DSCTO CURSO IA',
            'AI' => 'CORBATAS Y POLOS', 'AM' => 'TOTAL DESCUENTO', 'AO' => 'ESSALUD'];
        foreach ($dos as $col => $titulo) {
            $h->setCellValue("{$col}13", $titulo)->mergeCells("{$col}13:{$col}14");
        }
        $h->setCellValue('W13', 'SNP / ONP')->mergeCells('W13:X14');
        $h->setCellValue('Y13', 'SISTEMA PRIVADO DE PENSIONES - AFP');
        foreach (['Y' => 'CODIGO CUSSP', 'Z' => 'TIPO COMISIÓN', 'AA' => 'AFP', 'AB' => 'APORTE OBLIGATORIO',
            'AC' => 'COMISIÓN % SOBRE R.A.', 'AD' => 'PRIMA DE SEGURO'] as $col => $titulo) {
            $h->setCellValue("{$col}14", $titulo);
        }

        $r = 16; // la 15 va vacía, como en el PLAME
        foreach ($filas as $i => $f) {
            $valores = [
                'B' => $i + 1, 'D' => $f['nombre'], 'E' => $f['dni'], 'F' => $f['cargo'], 'G' => $f['centro'], 'H' => $f['categoria'],
                'I' => $f['ingreso'], 'J' => $f['cese'], 'N' => $f['hijos'], 'O' => $f['sueldo'], 'P' => $f['bonificacion'] ?: null,
                'Q' => $f['asignacion'] ?: null, 'S' => $f['gratificacion'] ?: null, 'V' => $f['bruto'],
                'W' => $f['afp'] ? 'NO' : 'SI', 'X' => $f['onp'] ?: null, 'Y' => $f['cuspp'], 'Z' => $f['tipo'], 'AA' => $f['afp'],
                'AB' => $f['fondo'] ?: null, 'AC' => $f['comision'] ?: null, 'AD' => $f['prima'] ?: null,
                'AE' => $f['quinta'] ?: null, 'AF' => $f['pension'], 'AG' => $f['diezmo'], 'AH' => $f['curso'] ?: null,
                'AI' => $f['corbatas'] ?: null, 'AM' => $f['descuentos'], 'AN' => $f['neto'], 'AO' => $f['essalud'],
            ];
            foreach ($valores as $col => $valor) {
                if ($valor !== null) {
                    $h->setCellValueExplicit("{$col}{$r}", $valor, is_string($valor) ? 's' : 'n');
                }
            }
            $r++;
        }
        $h->setCellValue('B' . ($r + 1), 'TOTALES S/.')->setCellValue('O' . ($r + 1), 999999);

        $ruta = tempnam(sys_get_temp_dir(), 'plame') . '.xls';
        IOFactory::createWriter($libro, 'Xls')->save($ruta);

        return new UploadedFile($ruta, 'PLANILLA MARZO PLAME.xls', 'application/vnd.ms-excel', null, true);
    }
}
