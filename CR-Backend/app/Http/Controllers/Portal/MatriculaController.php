<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\FechaMatriculaResource;
use App\Http\Resources\Portal\GrupoGradosResource;
use App\Http\Resources\Portal\PreguntaResource;
use App\Http\Resources\Portal\ProcesoMatriculaResource;
use App\Http\Resources\Portal\VacanteResource;
use App\Models\Portal\FechaMatricula;
use App\Models\Portal\GrupoGrados;
use App\Models\Portal\Pregunta;
use App\Models\Portal\ProcesoMatricula;
use App\Models\Portal\Vacante;
use App\Support\Portal\Ficha;
use Illuminate\Http\JsonResponse;

/**
 * La página Matrícula del portal (§3.10 a §3.14) y las opciones de grado del
 * formulario «Solicitar información» (§3.8).
 */
class MatriculaController extends Controller
{
    /** GET /v1/matricula/cabecera (§3.10). El año lo pone el colegio; no se calcula. */
    public function cabecera(): JsonResponse
    {
        $ficha = Ficha::de('matricula-cabecera');

        return response()->json(['data' => [
            'titulo' => $ficha->texto('titulo'),
            'bajada' => $ficha->textoOpcional('bajada'),
            'anio'   => $ficha->enteroOpcional('anio'),
            'figura' => $ficha->imagen('figura'),
        ]]);
    }

    /** GET /v1/matricula/procesos (§3.11). */
    public function procesos(): JsonResponse
    {
        return response()->json(['data' => ProcesoMatriculaResource::collection(ProcesoMatricula::publicados()->get())]);
    }

    /** GET /v1/matricula/vacantes (§3.12). */
    public function vacantes(): JsonResponse
    {
        $ficha = Ficha::de('matricula-vacantes');

        return response()->json(['data' => [
            'actualizado' => $ficha->textoOpcional('actualizado'),
            'nota'        => $ficha->textoOpcional('nota'),
            'niveles'     => VacanteResource::collection(Vacante::publicados()->get()),
        ]]);
    }

    /** GET /v1/matricula/fechas (§3.13), en orden cronológico. */
    public function fechas(): JsonResponse
    {
        $ficha = Ficha::de('matricula-fechas');

        return response()->json(['data' => [
            'nota'  => $ficha->textoOpcional('nota'),
            'items' => FechaMatriculaResource::collection(FechaMatricula::cronologicas()->get()),
        ]]);
    }

    /** GET /v1/matricula/preguntas (§3.14). */
    public function preguntas(): JsonResponse
    {
        return response()->json(['data' => PreguntaResource::collection(Pregunta::publicados()->get())]);
    }

    /** GET /v1/grados (§3.8). */
    public function grados(): JsonResponse
    {
        return response()->json(['data' => GrupoGradosResource::collection(GrupoGrados::publicados()->get())]);
    }
}
