<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * El certificado digital de una persona para firmar boletas desde el
 * sistema. Ver la migración certificados_para_firmar_boletas.
 */
class CertificadoFirma extends Model
{
    protected $table = 'certificados_firma';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    /** El archivo nunca sale en una respuesta, ni cifrado. */
    protected $hidden = ['archivo_cifrado'];

    protected function casts(): array
    {
        return [
            'valido_desde'   => 'datetime',
            'valido_hasta'   => 'datetime',
            'desactivado_en' => 'datetime',
            'activo'         => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $certificado) {
            $certificado->id ??= (string) Str::uuid7();
        });
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** El certificado que esta persona tiene puesto para firmar, si tiene. */
    public static function activoDe(User $usuario): ?self
    {
        return static::where('user_id', $usuario->id)->where('activo', true)->latest()->first();
    }

    /**
     * El .pfx se guarda cifrado (AES-256-GCM) con una llave que sale de la
     * APP_KEY del servidor. No se usa Crypt de Laravel a propósito: la
     * APP_KEY de este servidor no tiene el formato que Crypt exige, y
     * corregirla invalidaría los QR de verificación ya impresos en las
     * boletas (se firmaron con ella tal como está). Esto funciona con
     * cualquier APP_KEY.
     *
     * Si la APP_KEY cambia (otra instalación), el archivo ya no se puede
     * abrir: la persona vuelve a poner su certificado y listo.
     */
    public static function cifrar(string $pfx): string
    {
        $iv = random_bytes(12);
        $cifrado = openssl_encrypt($pfx, 'aes-256-gcm', self::llave(), OPENSSL_RAW_DATA, $iv, $etiqueta);
        if ($cifrado === false) {
            throw new \RuntimeException('No se pudo cifrar el certificado.');
        }

        return 'v1:' . base64_encode($iv . $etiqueta . $cifrado);
    }

    public function archivo(): string
    {
        $crudo = base64_decode(substr((string) $this->archivo_cifrado, 3), true);
        $pfx = $crudo === false ? false : openssl_decrypt(
            substr($crudo, 28), 'aes-256-gcm', self::llave(), OPENSSL_RAW_DATA, substr($crudo, 0, 12), substr($crudo, 12, 16)
        );
        if ($pfx === false) {
            throw new \RuntimeException('Tu certificado guardado ya no se puede abrir (cambió la instalación del sistema): quítalo y ponlo de nuevo en Ajustes.');
        }

        return $pfx;
    }

    private static function llave(): string
    {
        $base = (string) config('app.key');
        if ($base === '') {
            throw new \RuntimeException('El servidor no tiene APP_KEY: no se puede guardar el certificado con seguridad. Avisa a soporte.');
        }

        return hash_hmac('sha256', 'cata-recibo/certificados-de-firma', $base, true);
    }

    public function vencido(): bool
    {
        return $this->valido_hasta !== null && $this->valido_hasta->isPast();
    }

    /** Días que le quedan (0 si ya venció). */
    public function diasParaVencer(): ?int
    {
        return $this->valido_hasta === null ? null : max(0, (int) floor(now()->diffInDays($this->valido_hasta, false)));
    }

    /** Lo que se muestra en pantalla. */
    public function resumen(): array
    {
        return [
            'id'              => $this->id,
            'nombre'          => $this->nombre,
            'dni'             => $this->dni,
            'organizacion'    => $this->organizacion,
            'ruc'             => $this->ruc,
            'emisor'          => $this->emisor,
            'valido_desde'    => $this->valido_desde?->toDateString(),
            'valido_hasta'    => $this->valido_hasta?->toDateString(),
            'dias_para_vencer' => $this->diasParaVencer(),
            'vencido'         => $this->vencido(),
            'subido_en'       => $this->created_at?->toIso8601String(),
        ];
    }
}
