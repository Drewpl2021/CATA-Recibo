<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Area;

/**
 * Las áreas del colegio: unidades de la organización, no asignaturas.
 *
 * Antes sembraba "Matemáticas, Comunicación, Ciencias, Historia, Educación
 * Física, Inglés, Arte, Religión". Eso son los cursos que se dictan, y el área
 * en este sistema es otra cosa: es la unidad a la que pertenece el trabajador
 * y por la que se agrupa la planilla. Un contador no está en "Arte", y la
 * pregunta que RR.HH. le hace al Panel de Control es "cuánto cuesta la plana
 * docente" o "cuánto Administración", no "cuánto Historia".
 *
 * La asignatura que dicta cada docente no se pierde: vive en el campo
 * `especialidad` de su ficha, que es su sitio.
 */
class AreaSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Area::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $areas = [
            // ── Gobierno del colegio ──────────────────────────
            ['Dirección', 'Dirección general del colegio.'],
            ['Subdirección Académica', 'Coordinación pedagógica y supervisión de la plana docente.'],

            // ── Administración y soporte ──────────────────────
            ['Administración', 'Recursos humanos, planillas, logística y compras.'],
            ['Contabilidad y Tesorería', 'Contabilidad, pagos, pensiones y cobranzas.'],
            ['Secretaría Académica', 'Matrícula, actas, certificados y archivo de alumnos.'],
            ['TIC', 'Tecnologías de la información: sistemas, red y equipos.'],
            ['Admisión y Comunicaciones', 'Admisión de nuevos alumnos, difusión e imagen institucional.'],

            // ── Plana docente, por nivel ──────────────────────
            ['Plana Docente — Inicial', 'Docentes y auxiliares del nivel inicial.'],
            ['Plana Docente — Primaria', 'Docentes y auxiliares del nivel primaria.'],
            ['Plana Docente — Secundaria', 'Docentes del nivel secundaria.'],

            // ── Acompañamiento al alumno ──────────────────────
            ['Tutoría y Psicopedagogía', 'Tutoría, orientación y departamento psicopedagógico.'],
            ['Pastoral y Capellanía', 'Vida espiritual, capellanía y actividades pastorales.'],
            ['Biblioteca', 'Biblioteca escolar y material de apoyo.'],

            // ── Servicios ─────────────────────────────────────
            ['Mantenimiento y Limpieza', 'Mantenimiento de la infraestructura y limpieza.'],
            ['Vigilancia y Portería', 'Control de acceso y seguridad del local.'],
        ];

        foreach ($areas as [$nombre, $descripcion]) {
            Area::create([
                'nombre'      => $nombre,
                'descripcion' => $descripcion,
            ]);
        }

        $this->command?->info('   ' . count($areas) . ' áreas de la organización');
    }
}
