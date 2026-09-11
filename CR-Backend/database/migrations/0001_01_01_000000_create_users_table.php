<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            // Entra con el DNI que le dieron: hasta que ponga una suya, el
            // sistema no le deja hacer nada más.
            $table->boolean('debe_cambiar_password')->default(false);

            // La firma de los términos de uso, que antes era una hoja que se
            // repartía impresa. Se guarda cuándo, qué versión y desde dónde:
            // sin la versión, "aceptó los términos" no prueba nada.
            $table->boolean('terminos_firmados')->default(false);
            $table->timestamp('terminos_firmados_en')->nullable();
            $table->string('terminos_version', 20)->nullable();
            $table->string('terminos_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();

            // El listado de Usuarios ordena por nombre.
            $table->index('name', 'users_nombre_idx');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
