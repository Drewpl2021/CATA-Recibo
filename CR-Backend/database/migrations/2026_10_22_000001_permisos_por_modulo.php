<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los permisos pasan a ser los módulos de cada rol (rol_modulo).
 *
 * Hasta ahora las rutas preguntaban por el NOMBRE del rol («rol:admin,rrhh»):
 * un rol nuevo, como «Contabilidad», veía Planillas en el menú pero el
 * servidor le respondía 403, porque no se llamaba admin ni rrhh. Ahora cada
 * ruta pide un módulo («permiso:/planillas») y entra quien lo tenga marcado
 * en Roles (ver App\Support\Permisos).
 *
 * `es_superadmin` es la llave maestra: entra a todo, aunque le quiten un
 * módulo, para que nunca nadie quede sin poder arreglar los permisos. La
 * lleva el rol «admin», por la marca y no por su nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('es_superadmin')->default(false)->after('descripcion');
        });

        DB::table('roles')->where('nombre', 'admin')->update(['es_superadmin' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('es_superadmin');
        });
    }
};
