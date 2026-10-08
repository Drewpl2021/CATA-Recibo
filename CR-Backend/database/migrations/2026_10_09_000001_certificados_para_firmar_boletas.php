<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los certificados digitales (.pfx / .p12) con que se firman las boletas
 * desde el propio sistema, sin bajar el .zip ni pasar por ReFirma. Ver
 * App\Http\Controllers\CertificadoDeFirmaController.
 *
 * Cada uno es de UNA persona (su cuenta) y solo ella lo usa. El archivo se
 * guarda cifrado con la llave de la aplicación, y además sigue protegido por
 * su propia clave, que el sistema no guarda nunca: se pide cada vez que se
 * firma. Al quitarlo o reemplazarlo se borra el archivo y queda solo el
 * registro de quién era y hasta cuándo valía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados_firma', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->string('dni', 15)->nullable();
            $table->string('organizacion', 200)->nullable();
            $table->string('ruc', 20)->nullable();
            $table->string('emisor', 200)->nullable();
            $table->string('serie', 100);
            $table->string('huella_sha256', 64);
            $table->timestamp('valido_desde')->nullable();
            $table->timestamp('valido_hasta')->nullable();
            $table->longText('archivo_cifrado')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('desactivado_en')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'activo'], 'certificados_firma_usuario_activo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_firma');
    }
};
