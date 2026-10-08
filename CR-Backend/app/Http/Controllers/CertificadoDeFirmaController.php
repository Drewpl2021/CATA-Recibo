<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\CertificadoFirma;
use App\Support\CertificadoDigital;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * «Mi certificado para firmar»: el certificado digital (.pfx / .p12) con que
 * una persona firma las boletas desde el sistema (Emisión → Firma digital →
 * «Firmar aquí»). Cada uno sube y usa SOLO el suyo.
 *
 *   GET    /my-signing-certificate   el que tengo puesto, si tengo
 *   POST   /my-signing-certificate   revisarlo (sin guardar) o guardarlo
 *   DELETE /my-signing-certificate   quitarlo (se borra el archivo)
 *
 * La clave del certificado se usa para abrirlo y comprobarlo, y se olvida:
 * no se guarda en ninguna parte. Se vuelve a pedir cada vez que se firma.
 */
class CertificadoDeFirmaController extends Controller
{
    public function mostrar(Request $request)
    {
        $certificado = CertificadoFirma::activoDe($request->user());

        return response()->json(['success' => true, 'data' => $certificado?->resumen()]);
    }

    /**
     * Con «confirmar» vacío solo lo revisa y devuelve lo que leyó, para que la
     * persona vea de quién es antes de guardarlo. Con «confirmar» lo guarda
     * (y desactiva el que tenía antes).
     */
    public function guardar(Request $request)
    {
        $datos = $request->validate([
            'archivo'   => 'required|file|max:200',
            'clave'     => 'required|string|max:200',
            'confirmar' => 'nullable|boolean',
        ], [
            'archivo.required' => 'Elige el archivo de tu certificado (.pfx o .p12).',
            'archivo.max'      => 'Ese archivo es demasiado grande para ser un certificado (los .pfx pesan unos pocos KB).',
            'clave.required'   => 'Escribe la clave de tu certificado.',
        ]);

        $extension = strtolower($datos['archivo']->getClientOriginalExtension());
        if (! in_array($extension, ['pfx', 'p12'], true)) {
            return $this->error('Elige el archivo .pfx o .p12 que te dio la entidad de certificación.');
        }

        $pfx = (string) file_get_contents($datos['archivo']->getRealPath());
        try {
            $leido = CertificadoDigital::abrir($pfx, $datos['clave']);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        // Si la cuenta tiene DNI (está unida a su ficha de trabajador), el
        // certificado tiene que ser de esa misma persona.
        $usuario = $request->user();
        $dniCuenta = $usuario->empleado?->dni;
        if ($dniCuenta && $leido['dni'] && $dniCuenta !== $leido['dni']) {
            return $this->error("Este certificado es de {$leido['nombre']} (DNI {$leido['dni']}), no tuyo. Cada persona sube solo el suyo.");
        }

        $resumen = [
            'nombre'       => $leido['nombre'],
            'dni'          => $leido['dni'],
            'organizacion' => $leido['organizacion'],
            'ruc'          => $leido['ruc'],
            'emisor'       => $leido['emisor'],
            'valido_desde' => $leido['desde']?->format('Y-m-d'),
            'valido_hasta' => $leido['hasta']?->format('Y-m-d'),
            'dias_para_vencer' => $leido['hasta'] ? max(0, (int) floor(now()->diffInDays($leido['hasta'], false))) : null,
            'vencido'      => false,
        ];

        if (! $request->boolean('confirmar')) {
            return response()->json(['success' => true, 'data' => $resumen + ['guardado' => false]]);
        }

        $nuevo = DB::transaction(function () use ($usuario, $leido, $pfx) {
            $this->desactivarDe($usuario->id);

            return CertificadoFirma::create([
                'user_id'         => $usuario->id,
                'nombre'          => mb_substr($leido['nombre'], 0, 150),
                'dni'             => $leido['dni'],
                'organizacion'    => $leido['organizacion'] ? mb_substr($leido['organizacion'], 0, 200) : null,
                'ruc'             => $leido['ruc'],
                'emisor'          => $leido['emisor'] ? mb_substr($leido['emisor'], 0, 200) : null,
                'serie'           => mb_substr($leido['serie'], 0, 100),
                'huella_sha256'   => $leido['huella'],
                'valido_desde'    => $leido['desde'],
                'valido_hasta'    => $leido['hasta'],
                'archivo_cifrado' => CertificadoFirma::cifrar($pfx),
                'activo'          => true,
            ]);
        });

        Auditoria::registrar(
            'creó',
            'certificado de firma',
            $nuevo->id,
            "Puso su certificado digital para firmar boletas desde el sistema: {$leido['nombre']}"
                . ($leido['dni'] ? " (DNI {$leido['dni']})" : '')
                . ', emitido por ' . ($leido['emisor'] ?? 'una entidad sin nombre')
                . ', vale hasta el ' . ($leido['hasta']?->format('d/m/Y') ?? '—') . '.'
        );

        return response()->json(['success' => true, 'data' => $nuevo->resumen() + ['guardado' => true]]);
    }

    public function quitar(Request $request)
    {
        $certificado = CertificadoFirma::activoDe($request->user());
        if (! $certificado) {
            return $this->error('No tienes ningún certificado puesto.', 404);
        }

        $this->desactivarDe($request->user()->id);

        Auditoria::registrar(
            'borró',
            'certificado de firma',
            $certificado->id,
            "Quitó su certificado digital ({$certificado->nombre}): ya no puede firmar boletas desde el sistema hasta poner otro."
        );

        return response()->json(['success' => true, 'message' => 'Certificado quitado. Las boletas que ya firmaste siguen siendo válidas.']);
    }

    /** Desactiva el que tenga puesto y borra su archivo: solo queda el registro. */
    private function desactivarDe(int $usuarioId): void
    {
        CertificadoFirma::where('user_id', $usuarioId)->where('activo', true)->update([
            'activo'          => false,
            'archivo_cifrado' => null,
            'desactivado_en'  => now(),
        ]);
    }

    private function error(string $mensaje, int $estado = 422)
    {
        return response()->json(['success' => false, 'message' => $mensaje], $estado);
    }
}
