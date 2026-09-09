<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Cargo;

/**
 * Los cargos del colegio: qué hace cada persona, con su descripción.
 *
 * Antes eran siete nombres sueltos y sin explicar ('Director', 'Docente',
 * 'Auxiliar'...). Con eso, quien da de alta a un trabajador tiene que
 * adivinar dónde encaja un contador, un portero o un practicante — y lo que
 * pasaba en la práctica es que todo lo que no era docente terminaba de
 * "Administrativo", así que la planilla no distinguía a la secretaria del
 * personal de limpieza.
 *
 * Los siete de antes siguen existiendo con el mismo nombre a propósito: hay
 * fichas que ya los usan y renombrarlos las dejaría sin cargo.
 *
 * Dos apuntes sobre cómo está armada la lista:
 *
 *   - Cargo no es lo mismo que área. El área dice DÓNDE está la persona
 *     (Plana Docente — Primaria); el cargo dice QUÉ hace (Docente). Por eso
 *     no hay un "Docente de Primaria": esa combinación ya la dan los dos
 *     campos juntos, y duplicarla obliga a mantenerla en dos sitios.
 *   - Tampoco es la asignatura. Que un docente dicte Matemática vive en el
 *     campo `especialidad` de su ficha.
 *
 * Las prácticas van separadas en dos porque legalmente lo son: la
 * preprofesional es del que todavía estudia y la profesional del que ya
 * egresó, y no se pagan ni se contratan igual.
 */
class CargoSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Cargo::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $cargos = [
            // ── Dirección y coordinación ──────────────────────────
            ['Director', 'Máxima autoridad del colegio. Representa a la institución y responde por la marcha académica y administrativa.'],
            ['Subdirector', 'Apoya a la dirección y la reemplaza en su ausencia. Supervisa el trabajo pedagógico del día a día.'],
            ['Coordinador Académico', 'Organiza el plan de estudios, los horarios y el acompañamiento a los docentes.'],
            ['Coordinador de Nivel', 'Responsable de un nivel completo (inicial, primaria o secundaria): docentes, aulas y familias.'],
            ['Coordinador de Tutoría', 'Conduce la tutoría y orientación del estudiante, y acompaña a los tutores de aula.'],
            ['Coordinador de Pastoral', 'Organiza los cultos, las semanas de énfasis espiritual y las actividades misioneras.'],
            ['Jefe de Personal', 'Lleva las planillas, los contratos, las vacaciones y todo lo laboral del personal.'],

            // ── Aula ──────────────────────────────────────────────
            ['Docente', 'Dicta clases y tiene a su cargo un aula o un curso. Evalúa, registra notas y atiende a los padres.'],
            ['Auxiliar', 'Auxiliar de educación: acompaña al docente en el aula, en el recreo y en la formación de los estudiantes.'],
            ['Docente de Taller', 'Dicta talleres y actividades complementarias: música, arte, deporte, computación.'],
            ['Entrenador Deportivo', 'Prepara a las selecciones del colegio y conduce los entrenamientos fuera del horario de clase.'],

            // ── Acompañamiento al estudiante ──────────────────────
            ['Psicólogo', 'Atiende el departamento psicopedagógico: evaluaciones, orientación y seguimiento de casos.'],
            ['Capellán', 'Atiende la vida espiritual de estudiantes y personal: culto, consejería y visitación.'],
            ['Enfermero', 'A cargo del tópico: atiende emergencias, lleva el control de salud y coordina las campañas.'],
            ['Bibliotecario', 'Administra la biblioteca, el préstamo de libros y el material de apoyo al aula.'],

            // ── Administración y finanzas ─────────────────────────
            ['Administrativo', 'Personal de oficina que apoya la gestión administrativa general del colegio.'],
            ['Contador', 'Responsable de la contabilidad, los estados financieros y las obligaciones ante la SUNAT.'],
            ['Asistente Contable', 'Apoya al contador: registro de comprobantes, conciliaciones y archivo de documentos.'],
            ['Tesorero', 'Maneja los ingresos y egresos, los pagos a proveedores y el control de caja.'],
            ['Cajero', 'Atiende la ventanilla: cobro de pensiones y matrículas, y entrega de comprobantes.'],
            ['Secretaria', 'Atiende a padres y estudiantes, lleva la documentación académica y el archivo de la dirección.'],
            ['Asistente Administrativo', 'Apoya a las jefaturas en trámites, correspondencia y atención al público.'],
            ['Encargado de Logística', 'Compras, almacén y control de los materiales y equipos del colegio.'],
            ['Promotor de Admisión', 'Atiende a las familias nuevas, hace la difusión del colegio y acompaña la matrícula.'],
            ['Soporte Técnico', 'Mantiene los equipos, la red y los sistemas del colegio, y atiende los problemas del día a día.'],

            // ── Servicios ─────────────────────────────────────────
            ['Personal de Limpieza', 'Limpieza y orden de aulas, oficinas y servicios higiénicos.'],
            ['Personal de Mantenimiento', 'Arregla y conserva la infraestructura: instalaciones, mobiliario, pintura y jardines.'],
            ['Portero', 'Controla el ingreso y la salida del local, y registra a las visitas.'],
            ['Vigilante', 'Cuida el local y los bienes del colegio, principalmente en turno noche y fines de semana.'],
            ['Conductor', 'Conduce la movilidad del colegio y responde por el traslado seguro de los estudiantes.'],
            ['Personal de Cocina', 'Prepara y sirve los alimentos del comedor o del quiosco escolar.'],

            // ── En formación ──────────────────────────────────────
            ['Practicante Pre-Profesional', 'Estudiante universitario o de instituto que hace sus prácticas mientras sigue estudiando.'],
            ['Practicante Profesional', 'Egresado que hace su práctica profesional para completar su formación antes de titularse.'],
            ['Voluntario Misionero', 'Sirve un año en el colegio dentro del programa de voluntariado de la iglesia, con estipendio.'],
        ];

        foreach ($cargos as [$nombre, $descripcion]) {
            Cargo::create([
                'nombre'      => $nombre,
                'descripcion' => $descripcion,
            ]);
        }

        $this->command?->info('   ' . count($cargos) . ' cargos con su descripción');
    }
}
