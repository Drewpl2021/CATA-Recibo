<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Cargo;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\IdentidadFirma;
use App\Models\Periodo;
use App\Models\Planilla;
use App\Models\Sede;
use App\Models\User;
use App\Support\ExpedienteDigital;
use Illuminate\Http\Request;

/**
 * La guía de primeros pasos: qué hacer al entrar por primera vez.
 *
 * Quien entra recién no sabe qué le toca. Al trabajador le toca poner su
 * contraseña, registrar su firma y firmar sus boletas; a RR.HH. le toca
 * dejar listas las sedes, dar de alta al personal y armar la planilla del
 * mes. Esta guía se lo dice en orden y lo lleva a cada sitio.
 *
 * Lo que ya está hecho NO se guarda en ninguna tabla: se pregunta cada vez a
 * los datos de verdad. Si se guardara, el sistema tendría dos verdades sobre
 * lo mismo —la marca diría "ya firmó" y sus boletas dirían otra cosa— y la
 * que envejece siempre es la marca. Lo único que se guarda (users.guia_vista_en)
 * es si la guía ya se le abrió sola alguna vez.
 */
class PrimerosPasosController extends Controller
{
    /** GET /my-first-steps */
    public function ver(Request $request)
    {
        $usuario = $request->user();
        $rol     = $usuario->rol?->nombre;

        $pasos = $rol === 'empleado'
            ? $this->pasosDelTrabajador($usuario)
            : $this->pasosDeRecursosHumanos($usuario);

        $hechos = count(array_filter($pasos, fn (array $p) => $p['hecho']));

        return response()->json([
            'success' => true,
            'data'    => [
                // false = todavía no se le ha abierto sola: la pantalla la
                // abre al entrar. Después se abre solo cuando él quiera.
                'vista'  => $usuario->guia_vista_en !== null,
                'rol'    => $rol,
                'pasos'  => $pasos,
                'hechos' => $hechos,
                'total'  => count($pasos),
            ],
        ]);
    }

    /** POST /my-first-steps/seen — "Entendido": ya no se abre sola. */
    public function marcarVista(Request $request)
    {
        $usuario = $request->user();

        if ($usuario->guia_vista_en === null) {
            $usuario->forceFill(['guia_vista_en' => now()])->save();
        }

        return response()->json(['success' => true, 'data' => ['vista' => true]]);
    }

    /**
     * Lo que le toca al trabajador: dejar su cuenta en orden y quedar al día
     * con lo suyo. El orden es el de urgencia, no el del menú.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pasosDelTrabajador(User $usuario): array
    {
        $empleadoId = $usuario->empleado_id;

        // Sus boletas sin firmar. Las de antes del sistema no cuentan: ya se
        // firmaron en papel.
        $porFirmar = $empleadoId
            ? Documento::where('empleado_id', $empleadoId)
                ->where('estado_firma', '!=', 'firmado')
                ->whereNotIn('tipo', ExpedienteDigital::SIN_FIRMA)
                ->count()
            : 0;

        $tieneHojaDeVida = $empleadoId && Documento::where('empleado_id', $empleadoId)
            ->where('tipo', ExpedienteDigital::HOJA_DE_VIDA)
            ->exists();

        $tieneFirma = $empleadoId && IdentidadFirma::where('empleado_id', $empleadoId)->exists();

        // Distinto de "no le falta ninguna": a quien todavía no le han
        // emitido ninguna boleta no se le puede dar por hecho el paso, o
        // creería que ya firmó algo.
        $tieneBoletas = $empleadoId && Documento::where('empleado_id', $empleadoId)
            ->where('tipo', 'boleta')
            ->exists();

        return [
            $this->paso(
                'clave', 'Pon una contraseña tuya',
                'Entraste con tu DNI, y tu DNI lo ve cualquiera en tu boleta. Cámbialo por una clave que solo sepas tú.',
                'lock', '/cambiar-clave', ! $usuario->debe_cambiar_password
            ),
            $this->paso(
                'terminos', 'Firma los términos de uso',
                'Es la misma hoja que antes se firmaba en papel: dice qué puedes hacer en el sistema y cómo se cuidan tus datos.',
                'signature', '/terminos', $usuario->terminosAlDia()
            ),
            $this->paso(
                'perfil', 'Revisa tus datos y pon tu foto',
                'Mira que tu nombre, tu DNI y tu teléfono estén bien. Si algo está mal, avísale a Recursos Humanos.',
                'person', '/inicio/mis-boletas', $usuario->foto !== null
            ),
            $this->paso(
                'firma', 'Registra tu firma',
                'Se dibuja una sola vez y queda guardada. Sin ella no puedes firmar tus boletas.',
                'signature', '/inicio/mis-boletas', (bool) $tieneFirma
            ),
            $this->paso(
                'boletas', $porFirmar > 0 ? "Firma tus boletas ({$porFirmar} pendiente(s))" : 'Firma tus boletas',
                $tieneBoletas
                    ? 'Firmar es tu constancia de que la recibiste. Se firma desde Mis Boletas, con tu contraseña.'
                    : 'Todavía no tienes ninguna. Cuando el colegio emita la del mes te llega acá, y la firmas con tu contraseña.',
                'receipt_long', '/inicio/mis-boletas', $tieneBoletas && $porFirmar === 0
            ),
            $this->paso(
                'hoja_de_vida', 'Sube tu hoja de vida',
                'Tu CV en PDF, para que el colegio lo tenga en tu expediente y no te lo pidan otra vez.',
                'folder_shared', '/inicio/mis-documentos', (bool) $tieneHojaDeVida
            ),
        ];
    }

    /**
     * Lo que le toca a RR.HH. y a Administración: dejar el sistema listo, en
     * el orden en que hay que hacerlo. Sin sedes no hay dónde poner al
     * trabajador, y sin trabajadores no hay planilla.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pasosDeRecursosHumanos(User $usuario): array
    {
        $mes  = (int) now()->month;
        $anio = (int) now()->year;

        $catalogosListos = Sede::count() > 0 && Area::count() > 0 && Cargo::count() > 0;
        $hayPersonal     = Empleado::count() > 0;
        $hayPeriodo      = Periodo::count() > 0;
        $hayPlanilla     = Planilla::where('mes', $mes)->where('anio', $anio)->exists();
        $hayBoletas      = Documento::where('tipo', 'boleta')
            ->whereHas('planilla', fn ($q) => $q->where('mes', $mes)->where('anio', $anio))
            ->exists();

        return [
            $this->paso(
                'clave', 'Pon una contraseña tuya',
                'La cuenta nace con una clave provisional. Cámbiala antes de seguir.',
                'lock', '/cambiar-clave', ! $usuario->debe_cambiar_password
            ),
            $this->paso(
                'catalogos', 'Revisa las sedes, áreas y cargos',
                'Es donde va a encajar cada trabajador. Se hace una vez y casi no se vuelve a tocar.',
                'domain', '/inicio/sedes', $catalogosListos
            ),
            $this->paso(
                'personal', 'Da de alta al personal',
                'Uno por uno o todos juntos desde un Excel. A cada trabajador se le crea su cuenta con el DNI como clave.',
                'people', '/inicio/empleados', $hayPersonal
            ),
            $this->paso(
                'periodo', 'Abre el año escolar',
                'El periodo con sus meses: es lo que agrupa las planillas y las boletas del año.',
                'date_range', '/inicio/periodos', $hayPeriodo
            ),
            $this->paso(
                'planilla', 'Arma la planilla del mes',
                'Con los sueldos y los conceptos de ley ya calculados. Puedes ajustar lo que haga falta antes de emitir.',
                'table_chart', '/inicio/planillas', $hayPlanilla
            ),
            $this->paso(
                'boletas', 'Emite las boletas',
                'Se generan en PDF y le llegan a cada trabajador a su cuenta, para que las vea y las firme.',
                'receipt', '/inicio/emision-boleta', $hayBoletas
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function paso(string $clave, string $titulo, string $detalle, string $icono, string $ruta, bool $hecho): array
    {
        return compact('clave', 'titulo', 'detalle', 'icono', 'ruta', 'hecho');
    }
}
