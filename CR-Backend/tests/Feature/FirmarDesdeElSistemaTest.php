<?php

namespace Tests\Feature;

use App\Models\CertificadoFirma;
use App\Models\Configuracion;
use App\Models\Documento;
use App\Models\Notificacion;
use App\Models\Planilla;
use App\Support\FirmaDigitalDeBoletas;
use App\Support\FirmaDigitalPdf;
use App\Support\FirmadorPdf;
use App\Support\LectorPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FirmadorPdfDePrueba;
use Tests\TestCase;

/**
 * «Firmar aquí»: la persona pone su certificado digital (.pfx) una vez, y
 * cada mes firma las boletas desde el sistema con su clave, sin .zip ni
 * ReFirma. El .zip sigue funcionando, y los dos caminos se pueden mezclar.
 */
class FirmarDesdeElSistemaTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Clave-del-Certificado-2026';

    private $admin;
    private int $mes;
    private int $anio;
    /** @var list<array{0: \App\Models\Empleado, 1: Planilla}> */
    private array $trabajadores = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();

        $this->mes = (int) now()->month;
        $this->anio = (int) now()->year;
        $this->admin = $this->crearUsuario('admin');

        foreach ([['42083098', 'Gatica Quispe'], ['40000001', 'Ñahui Pérez']] as [$dni, $apellido]) {
            $empleado = $this->crearEmpleado(['dni' => $dni, 'apellido' => $apellido, 'sueldo_base' => 2000]);
            $usuario = $this->crearUsuario('empleado');
            $usuario->forceFill(['empleado_id' => (string) $empleado->id])->save();
            $planilla = Planilla::create(['empleado_id' => $empleado->id, 'mes' => $this->mes, 'anio' => $this->anio, 'sueldo_base' => 2000, 'total' => 2000]);
            $this->trabajadores[] = [$empleado, $planilla];
        }
    }

    private function emitirTodas(): void
    {
        foreach ($this->trabajadores as [$empleado]) {
            $this->actingAs($this->admin, 'sanctum')->get("/api/payslips/{$empleado->id}/{$this->mes}/{$this->anio}")->assertOk();
        }
    }

    private function boleta(int $i): Documento
    {
        return Documento::where('planilla_id', $this->trabajadores[$i][1]->id)->where('tipo', 'boleta')->firstOrFail();
    }

    private function ponerCertificado($usuario, string $pfx, bool $confirmar = true, string $clave = self::CLAVE, string $nombre = 'isidoro.pfx')
    {
        return $this->actingAs($usuario, 'sanctum')->post('/api/my-signing-certificate', [
            'archivo'   => UploadedFile::fake()->createWithContent($nombre, $pfx),
            'clave'     => $clave,
            'confirmar' => $confirmar ? '1' : '0',
        ], ['Accept' => 'application/json']);
    }

    private function firmarAqui($usuario, string $clave = self::CLAVE)
    {
        return $this->actingAs($usuario, 'sanctum')->postJson('/api/payslips/signed/sign-here', [
            'mes' => $this->mes, 'anio' => $this->anio, 'clave' => $clave,
        ]);
    }

    public function test_pone_su_certificado_y_firma_las_del_mes_de_una_vez(): void
    {
        $this->emitirTodas();
        $pfx = FirmadorPdfDePrueba::pfx('Isidoro', 'Rodriguez Mamani', '01234567', self::CLAVE);

        // Primero lo revisa: dice de quién es y no guarda nada.
        $this->ponerCertificado($this->admin, $pfx, false)
            ->assertOk()
            ->assertJsonPath('data.nombre', 'ISIDORO RODRIGUEZ MAMANI')
            ->assertJsonPath('data.dni', '01234567')
            ->assertJsonPath('data.emisor', 'ENTIDAD DE PRUEBA S.A.C.')
            ->assertJsonPath('data.guardado', false);
        $this->assertSame(0, CertificadoFirma::count());

        $this->ponerCertificado($this->admin, $pfx, true, 'otra-clave')
            ->assertStatus(422)->assertJsonPath('message', 'La clave del certificado no es correcta.');

        $this->ponerCertificado($this->admin, $pfx)->assertOk()->assertJsonPath('data.guardado', true);
        $guardado = CertificadoFirma::firstOrFail();
        // Cifrado: ni el archivo ni su clave quedan legibles en la base.
        $this->assertStringNotContainsString(base64_encode($pfx), (string) $guardado->getRawOriginal('archivo_cifrado'));
        $this->assertStringNotContainsString(self::CLAVE, json_encode($guardado->getAttributes()));
        $this->assertSame($pfx, $guardado->archivo());

        // El panel de Emisión ya sabe que puede firmar 2.
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/payslips/signed/summary?mes={$this->mes}&anio={$this->anio}")
            ->assertJsonPath('data.mi_certificado.nombre', 'ISIDORO RODRIGUEZ MAMANI')
            ->assertJsonPath('data.mi_certificado.por_firmar', 2);

        // Con la clave mal no se firma nada.
        $this->firmarAqui($this->admin, 'otra-clave')->assertStatus(422);
        $this->assertSame('pendiente', $this->boleta(0)->firma_colegio);

        $this->firmarAqui($this->admin)
            ->assertOk()
            ->assertJsonPath('data.firmadas', 2)
            ->assertJsonPath('data.entregadas', 2)
            ->assertJsonPath('data.errores', []);

        foreach ([0, 1] as $i) {
            $boleta = $this->boleta($i);
            $this->assertSame('completa', $boleta->firma_colegio);
            $this->assertSame('01234567', $boleta->firmas_colegio[0]['dni']);

            // La boleta emitida queda intacta al principio, y la firma es válida y cubre todo.
            $firmado = Storage::disk('local')->get($boleta->archivo);
            $this->assertStringStartsWith(Storage::disk('local')->get($boleta->archivo_sin_firma), $firmado);
            $firmas = FirmaDigitalPdf::firmas($firmado);
            $this->assertCount(1, $firmas);
            $this->assertTrue($firmas[0]['valida']);
            $this->assertTrue($firmas[0]['cubre_hasta_el_final']);
            $this->assertStringContainsString('/SubFilter /ETSI.CAdES.detached', $firmado);

            // Es un PDF que se puede seguir leyendo: el formulario tiene el campo de firma.
            $lector = new LectorPdf($firmado);
            $catalogo = $lector->diccionario($lector->referencia($lector->trailer()['Root'])[0]);
            $this->assertArrayHasKey('AcroForm', $catalogo);

            // Y le llegó al trabajador.
            $this->assertTrue(Notificacion::where('documento_id', $boleta->id)->exists());

            if ($i === 0 && ($ruta = getenv('GUARDAR_BOLETA_FIRMADA'))) {
                file_put_contents($ruta, $firmado);
            }
        }

        // Ya firmó todo: no hay nada más que firmar.
        $this->firmarAqui($this->admin)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'esperando tu firma'));
    }

    public function test_dos_firmas_mezclando_firmar_aqui_y_refirma(): void
    {
        Configuracion::ponerNumero(FirmaDigitalDeBoletas::AJUSTE_FIRMAS, 2);
        $this->emitirTodas();
        $this->ponerCertificado($this->admin, FirmadorPdfDePrueba::pfx('Isidoro', 'Rodriguez Mamani', '01234567', self::CLAVE))->assertOk();

        // La boleta 0 la firma primero RR.HH. con ReFirma (por el .zip).
        [$empleado, $planilla] = $this->trabajadores[0];
        [$cert, $llave] = FirmadorPdfDePrueba::certificado('Ana', 'Quispe Rojas', '76543210');
        $conRefirma = FirmadorPdfDePrueba::firmar(Storage::disk('local')->get($this->boleta(0)->archivo), $cert, $llave);
        $this->actingAs($this->admin, 'sanctum')->post('/api/payslips/signed', [
            'archivo' => UploadedFile::fake()->createWithContent("{$planilla->numeroDeBoleta()}_{$empleado->dni}_FIRMADO[R].pdf", $conRefirma),
            'mes' => $this->mes, 'anio' => $this->anio,
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.resultado', 'parcial');

        // Firmar aquí: la 0 se completa (segunda firma) y la 1 queda a medias.
        $this->firmarAqui($this->admin)
            ->assertOk()
            ->assertJsonPath('data.firmadas', 2)
            ->assertJsonPath('data.entregadas', 1)
            ->assertJsonPath('data.a_medias', 1);

        $completa = $this->boleta(0);
        $this->assertSame('completa', $completa->firma_colegio);
        $this->assertSame(['76543210', '01234567'], array_column($completa->firmas_colegio, 'dni'));
        $firmas = FirmaDigitalPdf::firmas(Storage::disk('local')->get($completa->archivo));
        $this->assertCount(2, $firmas);
        $this->assertTrue($firmas[0]['valida'] && $firmas[1]['valida'] && $firmas[1]['cubre_hasta_el_final']);

        // La 1 tiene la del administrador: ahora RR.HH. la firma con ReFirma
        // sobre ESA y se sube por el .zip, como siempre.
        $mitad = $this->boleta(1);
        $this->assertSame('parcial', $mitad->firma_colegio);
        [$empleado1, $planilla1] = $this->trabajadores[1];
        $segunda = FirmadorPdfDePrueba::firmar(Storage::disk('local')->get($mitad->archivo), $cert, $llave);
        $this->actingAs($this->admin, 'sanctum')->post('/api/payslips/signed', [
            'archivo' => UploadedFile::fake()->createWithContent("{$planilla1->numeroDeBoleta()}_{$empleado1->dni}.pdf", $segunda),
            'mes' => $this->mes, 'anio' => $this->anio,
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.resultado', 'completa');

        // Firmar dos veces la misma persona no suma: ya no hay nada suyo por firmar.
        $this->firmarAqui($this->admin)->assertStatus(422);
    }

    public function test_lo_que_no_deja_poner_ni_usar(): void
    {
        $this->emitirTodas();
        $pfx = fn (string $uso = 'firma', int $dias = 730) => FirmadorPdfDePrueba::pfx('Isidoro', 'Rodriguez Mamani', '01234567', self::CLAVE, $uso, $dias);

        $this->ponerCertificado($this->admin, $pfx('casero'))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'no lo emitió una entidad'));
        $this->ponerCertificado($this->admin, $pfx('cifrado'))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'no sirve para firmar'));
        // Vencido: el mismo certificado, tres años después.
        $deHoy = $pfx();
        $this->travel(3)->years();
        $this->ponerCertificado($this->admin, $deHoy)
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'venció'));
        $this->travelBack();
        $this->ponerCertificado($this->admin, 'no soy un certificado')
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'No es un certificado'));
        $this->ponerCertificado($this->admin, $pfx(), true, self::CLAVE, 'certificado.pdf')
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, '.pfx o .p12'));

        // Si la cuenta tiene su ficha (DNI), el certificado tiene que ser suyo.
        $conFicha = $this->crearUsuario('rrhh');
        $conFicha->forceFill(['empleado_id' => (string) $this->trabajadores[0][0]->id])->save();
        $this->ponerCertificado($conFicha, $pfx())
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'no tuyo'));

        // Cada uno usa solo el suyo: el de Isidoro no le sirve a otra cuenta.
        $this->ponerCertificado($this->admin, $pfx())->assertOk();
        $this->firmarAqui($this->crearUsuario('rrhh'))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Todavía no pusiste'));

        // Un trabajador no llega a nada de esto.
        $trabajador = $this->crearUsuario('empleado');
        $this->ponerCertificado($trabajador, $pfx())->assertForbidden();
        $this->firmarAqui($trabajador)->assertForbidden();

        // Ponerlo de nuevo reemplaza al anterior; quitarlo borra el archivo.
        $this->ponerCertificado($this->admin, $pfx())->assertOk();
        $this->assertSame(1, CertificadoFirma::where('activo', true)->count());
        $this->assertSame(1, CertificadoFirma::whereNotNull('archivo_cifrado')->count());

        // Si vence después de ponerlo, «Firmar aquí» lo dice y no firma.
        $this->travel(3)->years();
        $this->firmarAqui($this->admin)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'venció'));
        $this->travelBack();

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/my-signing-certificate')->assertOk();
        $this->assertSame(0, CertificadoFirma::whereNotNull('archivo_cifrado')->count());
        $this->firmarAqui($this->admin)->assertStatus(422);
        $this->assertSame('pendiente', $this->boleta(0)->firma_colegio);
    }

    /**
     * Por tandas: cada pedido firma unas pocas y dice desde dónde sigue. Una
     * que falla no se reintenta en la misma pasada, así la pasada termina.
     */
    public function test_firma_por_tandas_y_una_que_falla_no_la_deja_dando_vueltas(): void
    {
        $this->emitirTodas();
        $this->ponerCertificado($this->admin, FirmadorPdfDePrueba::pfx('Isidoro', 'Rodriguez Mamani', '01234567', self::CLAVE))->assertOk();

        // La primera en el orden de las tandas se queda sin su PDF: esa falla.
        $orden = Documento::where('tipo', 'boleta')->orderBy('id')->pluck('id')->all();
        Storage::disk('local')->delete(Documento::find($orden[0])->archivo);

        $tanda = fn (?string $desde) => $this->actingAs($this->admin, 'sanctum')->postJson('/api/payslips/signed/sign-here', [
            'mes' => $this->mes, 'anio' => $this->anio, 'clave' => self::CLAVE, 'limite' => 1, 'desde' => $desde,
        ])->assertOk();

        $primera = $tanda(null)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.procesadas', 1)
            ->assertJsonPath('data.firmadas', 0)
            ->assertJsonPath('data.siguiente', $orden[0])
            ->assertJsonPath('data.errores.0.mensaje', 'No se encontró su PDF: vuelve a emitirla.');

        $tanda($primera->json('data.siguiente'))
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.firmadas', 1)
            ->assertJsonPath('data.siguiente', null);

        $this->assertSame('completa', Documento::find($orden[1])->firma_colegio);
        $this->assertSame('pendiente', Documento::find($orden[0])->firma_colegio);

        // La que falló sigue esperando: otra pasada la vuelve a intentar.
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/payslips/signed/summary?mes={$this->mes}&anio={$this->anio}")
            ->assertJsonPath('data.mi_certificado.por_firmar', 1);
    }

    /** La clave no se puede adivinar a fuerza de intentos: 5 errores y 15 minutos de espera. */
    public function test_frena_a_quien_prueba_claves(): void
    {
        $this->emitirTodas();
        $this->ponerCertificado($this->admin, FirmadorPdfDePrueba::pfx('Isidoro', 'Rodriguez Mamani', '01234567', self::CLAVE))->assertOk();

        foreach (range(1, 5) as $intento) {
            $this->firmarAqui($this->admin, "intento-{$intento}")->assertStatus(422);
        }
        // Ni con la buena: hay que esperar.
        $this->firmarAqui($this->admin)->assertStatus(429)->assertJsonPath('message', fn ($m) => str_contains($m, 'Espera'));
        $this->assertSame('pendiente', $this->boleta(0)->firma_colegio);

        $this->travel(16)->minutes();
        $this->firmarAqui($this->admin)->assertOk()->assertJsonPath('data.firmadas', 2);
    }

    /**
     * Un PDF que ya pasó por otro firmador puede venir con la tabla de
     * referencias comprimida (xref stream, con predictor PNG): también se
     * firma encima, y lo de antes sigue intacto.
     */
    public function test_firma_un_pdf_con_tabla_comprimida(): void
    {
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Annots [] >>',
        ];
        $pdf = "%PDF-1.7\n";
        $posiciones = [];
        foreach ($objetos as $n => $o) {
            $posiciones[$n] = strlen($pdf);
            $pdf .= "{$n} 0 obj\n{$o}\nendobj\n";
        }
        $posXref = strlen($pdf);
        $filas = [[0, 0, 255]];
        foreach ($posiciones as $p) {
            $filas[] = [1, $p, 0];
        }
        $filas[] = [1, $posXref, 0];
        // Cada fila: tipo (1) + posición (2) + generación (1), con el predictor «Up».
        $crudo = '';
        $anterior = [0, 0, 0, 0];
        foreach ($filas as [$tipo, $pos, $gen]) {
            $fila = [$tipo, ($pos >> 8) & 0xFF, $pos & 0xFF, $gen];
            $crudo .= chr(2) . implode('', array_map(fn ($v, $a) => chr(($v - $a) & 0xFF), $fila, $anterior));
            $anterior = $fila;
        }
        $datos = gzcompress($crudo);
        $pdf .= "4 0 obj\n<< /Type /XRef /Size 5 /W [1 2 1] /Root 1 0 R /Filter /FlateDecode"
            . ' /DecodeParms << /Columns 4 /Predictor 12 >> /Length ' . strlen($datos) . " >>\nstream\n{$datos}\nendstream\nendobj\n"
            . "startxref\n{$posXref}\n%%EOF\n";

        $leido = \App\Support\CertificadoDigital::abrir(FirmadorPdfDePrueba::pfx('Isidoro', 'Rodriguez Mamani', '01234567', self::CLAVE), self::CLAVE);
        $firmado = FirmadorPdf::firmar($pdf, $leido['certificado'], $leido['llave'], $leido['cadena'], ['nombre' => 'ISIDORO']);

        $this->assertStringStartsWith($pdf, $firmado);
        $firmas = FirmaDigitalPdf::firmas($firmado);
        $this->assertCount(1, $firmas);
        $this->assertTrue($firmas[0]['valida']);
        $this->assertSame('01234567', $firmas[0]['dni']);

        $lector = new LectorPdf($firmado);
        $pagina = $lector->diccionario(3);
        $this->assertMatchesRegularExpression('/^\[\s*\d+ 0 R\]$/', $pagina['Annots']);
        $this->assertArrayHasKey('AcroForm', $lector->diccionario(1));
    }
}
