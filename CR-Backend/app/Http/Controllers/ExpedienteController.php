<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\Empleado;
use App\Support\ExpedienteDigital;
use App\Traits\ListadoPaginado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Documentos del personal: el expediente de cada trabajador, para RR.HH. y
 * Administración.
 *
 * "Documentos" abría la misma pantalla que "Mis Documentos", y RR.HH. veía
 * archivos sueltos —"Hoja de vida", "Contrato"— sin saber de quién era cada
 * uno. Aquí se parte de la persona:
 *
 *   index   el personal, con lo que le falta a cada uno
 *   show    el expediente de uno: su hoja de vida, cada contrato con los
 *           documentos que cuelgan de él, sus boletas y lo demás
 *
 * Los archivos se siguen subiendo, bajando y quitando por DocumentoController:
 * esto solo los ordena.
 */
class ExpedienteController extends Controller
{
    use ListadoPaginado;

    /** Cuántos días antes del fin un contrato cuenta como "por vencer". */
    private const DIAS_POR_VENCER = 30;

    /** GET /expedientes?page=&size=&search=&filtro= */
    public function index(Request $request)
    {
        $request->validate([
            'filtro' => 'nullable|in:sin_hoja_de_vida,boletas_por_firmar,contrato_por_vencer,de_baja',
        ]);

        $filtro = $request->input('filtro');

        $query = $this->personal($filtro === 'de_baja')
            ->with(['area:id,nombre', 'cargo:id,nombre', 'sede:id,nombre', 'contratoVigente'])
            ->withCount([
                'documentos as documentos_count'   => fn (Builder $q) => $q->where('estado_registro', 'activo'),
                'documentos as boletas_por_firmar' => fn (Builder $q) => $this->boletasPorFirmar($q),
            ])
            ->withMax(['documentos as hoja_de_vida_fecha' => fn (Builder $q) => $this->hojaDeVida($q)], 'created_at')
            ->orderBy('apellido')
            ->orderBy('nombre');

        match ($filtro) {
            'sin_hoja_de_vida'    => $query->whereDoesntHave('documentos', fn (Builder $q) => $this->hojaDeVida($q)),
            'boletas_por_firmar'  => $query->whereHas('documentos', fn (Builder $q) => $this->boletasPorFirmar($q)),
            'contrato_por_vencer' => $query->whereHas('contratos', fn (Builder $q) => $this->porVencer($q)),
            default               => null,
        };

        return $this->responderListado(
            $request,
            $query,
            ['nombre', 'apellido', 'dni', 'cargo.nombre', 'area.nombre'],
            // Los chips cuentan a TODO el personal, no a lo que dejó el
            // buscador: dicen cuánto falta en el colegio.
            fn () => ['resumen' => $this->resumen()]
        );
    }

    /** GET /expedientes/{empleadoId} — todo lo que hay a nombre de un trabajador. */
    public function show(string $empleadoId)
    {
        $empleado = Empleado::with(['area:id,nombre', 'cargo:id,nombre', 'sede:id,nombre', 'usuario:id,empleado_id,email'])
            ->findOrFail($empleadoId);

        $documentos = Documento::with('planilla:id,mes,anio')
            ->where('empleado_id', $empleado->id)
            ->orderByDesc('created_at')
            ->get();

        $activos   = $documentos->where('estado_registro', 'activo');
        $hojas     = $documentos->where('tipo', ExpedienteDigital::HOJA_DE_VIDA);
        $contratos = $empleado->contratos()->orderByDesc('fecha_inicio')->get();
        $idsContratos = $contratos->pluck('id')->all();

        // Del mes más reciente al más antiguo, por el periodo de la planilla y
        // no por cuándo se generó el PDF: una boleta regenerada no salta arriba.
        $boletas = $activos->where('tipo', 'boleta')
            ->sortByDesc(fn (Documento $d) => $d->planilla
                ? sprintf('%04d-%02d', $d->planilla->anio, $d->planilla->mes)
                : (string) $d->created_at)
            ->values();

        // Lo que no es boleta, ni hoja de vida, ni cuelga de un contrato suyo.
        $otros = $activos
            ->reject(fn (Documento $d) => in_array($d->tipo, ['boleta', ExpedienteDigital::HOJA_DE_VIDA], true)
                || in_array($d->contrato_id, $idsContratos, true))
            ->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'empleado'         => $empleado,
                'hoja_de_vida'     => $hojas->firstWhere('estado_registro', 'activo'),
                'hojas_anteriores' => $hojas->where('estado_registro', '!=', 'activo')->values(),
                'contratos'        => $contratos->map(fn ($contrato) => array_merge($contrato->toArray(), [
                    'documentos' => $activos->where('contrato_id', $contrato->id)->values(),
                ]))->values(),
                'boletas'          => $boletas,
                'otros'            => $otros,
                'dias_por_vencer'  => self::DIAS_POR_VENCER,
            ],
        ]);
    }

    private function resumen(): array
    {
        return [
            'trabajadores'        => $this->personal()->count(),
            'sin_hoja_de_vida'    => $this->personal()->whereDoesntHave('documentos', fn (Builder $q) => $this->hojaDeVida($q))->count(),
            'boletas_por_firmar'  => $this->personal()->whereHas('documentos', fn (Builder $q) => $this->boletasPorFirmar($q))->count(),
            'contrato_por_vencer' => $this->personal()->whereHas('contratos', fn (Builder $q) => $this->porVencer($q))->count(),
            'de_baja'             => $this->personal(true)->count(),
            'dias_por_vencer'     => self::DIAS_POR_VENCER,
        ];
    }

    private function personal(bool $deBaja = false): Builder
    {
        return Empleado::query()->where('estado', $deBaja ? 'inactivo' : 'activo');
    }

    private function hojaDeVida(Builder $q): Builder
    {
        return $q->where('tipo', ExpedienteDigital::HOJA_DE_VIDA)->where('estado_registro', 'activo');
    }

    private function boletasPorFirmar(Builder $q): Builder
    {
        return $q->where('tipo', 'boleta')->where('estado_registro', 'activo')->where('estado_firma', '!=', 'firmado');
    }

    /** Vigente y con fin dentro del plazo; también el que ya pasó y nadie cerró. */
    private function porVencer(Builder $q): Builder
    {
        return $q->where('estado', 'vigente')
            ->whereNotNull('fecha_fin')
            ->whereDate('fecha_fin', '<=', now()->addDays(self::DIAS_POR_VENCER));
    }
}
