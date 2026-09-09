<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Area;
use App\Models\Cargo;
use App\Models\Contrato;
use App\Models\Empleado;
use App\Models\Rol;
use App\Models\Sede;
use App\Models\User;

/**
 * El personal del colegio, con la ficha completa.
 *
 * Antes esto sembraba "Test Usuario", "Administrador Apellidos" y "Recursos
 * Humanos Apellidos": nombres de relleno, la mitad de los campos vacíos y sin
 * contrato. Al abrir el sistema por primera vez parecía una base rota, y no se
 * podía enseñar una pantalla a nadie sin explicar antes que eso era de prueba.
 *
 * Ahora cada trabajador viene con lo que RR.HH. llenaría de verdad: DNI,
 * nacimiento, teléfono, dirección, área, cargo, sede, sueldo, sistema de
 * pensiones (con su AFP y CUSPP cuando toca), cuenta bancaria, estudios y
 * contacto de emergencia. Y su contrato vigente, para que el historial no
 * nazca en blanco.
 *
 * Tres cosas que NO se tocan, y conviene saber por qué:
 *
 *  1. Los tres correos de acceso (admin@, rrhh@, test@colegio.com) y sus
 *     contraseñas siguen igual: son las credenciales con las que se entra a
 *     probar y están en la colección de Postman.
 *  2. Deivi Apaza Lucana se queda exactamente con su sueldo de 3000, en ONP y
 *     sin hijos: la suite de regresión comprueba sobre él que la ONP descuenta
 *     13% y que el total sale 2635.00. Cambiarle cualquiera de esos tres datos
 *     rompe la prueba.
 *  3. Al resto del personal se le crea la cuenta con su DNI de contraseña y
 *     obligado a cambiarla al entrar, que es exactamente lo que pasa cuando
 *     RR.HH. da de alta a alguien de verdad.
 */
class UsuarioDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        User::truncate();
        Empleado::truncate();
        Contrato::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $roles  = Rol::pluck('id', 'nombre');
        $areas  = Area::pluck('id', 'nombre');
        $cargos = Cargo::pluck('id', 'nombre');
        $sedes  = Sede::pluck('id', 'nombre');

        $conCuenta = 0;

        foreach ($this->personal() as $ficha) {
            $cuenta = $ficha['cuenta'] ?? null;
            unset($ficha['cuenta']);

            $contrato = $ficha['contrato'];
            unset($ficha['contrato']);

            // 'area', 'cargo' y 'sede' son nombres legibles para escribir la
            // ficha de arriba; a la base van como id y no como columna.
            $datos = array_merge($ficha, [
                'area_id'  => $areas[$ficha['area']]   ?? null,
                'cargo_id' => $cargos[$ficha['cargo']] ?? null,
                'sede_id'  => $sedes[$ficha['sede']]   ?? null,
                'estado'   => 'activo',
            ]);
            unset($datos['area'], $datos['cargo'], $datos['sede']);

            // Los nulos se quitan en vez de mandarse: sistema_pensiones no
            // admite null y su valor por defecto en la tabla es 'ONP', que es
            // justo lo que le toca a quien todavía no eligió AFP.
            $datos = array_filter($datos, fn ($valor) => $valor !== null);

            $empleado = Empleado::create($datos);

            Contrato::create([
                'empleado_id'   => $empleado->id,
                'tipo_contrato' => $ficha['tipo_contrato'],
                'fecha_inicio'  => $ficha['fecha_ingreso'],
                'fecha_fin'     => $contrato['fecha_fin'] ?? null,
                'estado'        => 'vigente',
                'observaciones' => $contrato['observaciones'],
            ]);

            if ($cuenta) {
                User::create([
                    'name'        => trim($ficha['nombre'] . ' ' . $ficha['apellido']),
                    'email'       => $cuenta['email'],
                    // Sin contraseña propia, entra con su DNI — igual que
                    // cuando RR.HH. da de alta a alguien desde el sistema.
                    'password'    => Hash::make($cuenta['password'] ?? $ficha['dni']),
                    'rol_id'      => $roles[$cuenta['rol']],
                    'empleado_id' => $empleado->id,
                    // Las tres cuentas de acceso ya tienen su contraseña; a las
                    // demás el sistema les exige cambiar el DNI al entrar.
                    'debe_cambiar_password' => ! isset($cuenta['password']),
                ]);
                $conCuenta++;
            }
        }

        $total = Empleado::count();
        $this->command?->info("   {$total} trabajadores con ficha completa, {$conCuenta} con cuenta de acceso.");
        $this->command?->info('   Entra con admin@colegio.com / admin123 — rrhh@colegio.com / rrhh123 — test@colegio.com / empleado123');
    }

    /**
     * El personal, tal como lo tendría un colegio de este tamaño.
     *
     * Ojo con los enums de la tabla: nivel_estudios solo acepta primaria,
     * secundaria, tecnico, universitario, maestria o doctorado; el detalle
     * de la carrera va en 'especialidad'.
     *
     * La mezcla es a propósito: ONP y AFP (las cuatro administradoras),
     * contratos indeterminados y a plazo fijo, las dos sedes, con y sin hijos
     * (que es lo que dispara la Asignación Familiar), y pago por banco o en
     * efectivo. Así cualquier pantalla que agrupe o filtre tiene de dónde.
     */
    private function personal(): array
    {
        return [
            // ── Dirección: la cuenta de Administrador ──────────────
            [
                'dni' => '81577364', 'nombre' => 'Rubén Alberto', 'apellido' => 'Quispe Mamani',
                'fecha_nacimiento' => '1975-03-12', 'fecha_ingreso' => '2015-03-01',
                'area' => 'Dirección', 'cargo' => 'Director', 'sede' => 'CATA',
                'telefono' => '951234567', 'direccion' => 'Jr. Túpac Amaru 245, Juliaca',
                'sistema_pensiones' => 'AFP', 'afp' => 'Prima', 'cuspp' => '751234567890',
                'entidad_financiera' => 'BCP', 'numero_cuenta' => '19198765432011',
                'tiene_hijos' => true, 'sueldo_base' => 5200.00,
                'tipo_contrato' => 'indeterminado', 'forma_pago' => 'banco',
                'nivel_estudios' => 'maestria', 'especialidad' => 'Gestión Educativa',
                'institucion_estudios' => 'Universidad Peruana Unión',
                'contacto_emergencia_nombre' => 'Marisol Mamani Condori',
                'contacto_emergencia_telefono' => '951234568',
                'contrato' => ['observaciones' => 'Dirección general del colegio.'],
                'cuenta' => ['email' => 'admin@colegio.com', 'password' => 'admin123', 'rol' => 'admin'],
            ],

            // ── Recursos Humanos: la cuenta de RR.HH. ──────────────
            [
                'dni' => '81577382', 'nombre' => 'Milagros Fabiola', 'apellido' => 'Ccama Huanca',
                'fecha_nacimiento' => '1988-11-04', 'fecha_ingreso' => '2018-03-01',
                'area' => 'Administración', 'cargo' => 'Administrativo', 'sede' => 'CATA',
                'telefono' => '952345678', 'direccion' => 'Av. Huancané 1120, Juliaca',
                'sistema_pensiones' => 'ONP', 'afp' => null, 'cuspp' => null,
                'entidad_financiera' => 'BCP', 'numero_cuenta' => '19187654321022',
                'tiene_hijos' => true, 'sueldo_base' => 3400.00,
                'tipo_contrato' => 'indeterminado', 'forma_pago' => 'banco',
                'nivel_estudios' => 'universitario', 'especialidad' => 'Administración',
                'institucion_estudios' => 'Universidad Nacional del Altiplano',
                'contacto_emergencia_nombre' => 'Julio Ccama Apaza',
                'contacto_emergencia_telefono' => '952345679',
                'contrato' => ['observaciones' => 'Encargada de planillas y legajos.'],
                'cuenta' => ['email' => 'rrhh@colegio.com', 'password' => 'rrhh123', 'rol' => 'rrhh'],
            ],

            // ── La docente con la que se prueba el autoservicio ────
            [
                'dni' => '12345678', 'nombre' => 'Elena Rosario', 'apellido' => 'Chávez Ticona',
                'fecha_nacimiento' => '1990-05-15', 'fecha_ingreso' => '2024-01-01',
                'area' => 'Plana Docente — Secundaria', 'cargo' => 'Docente', 'sede' => 'CATA',
                'telefono' => '953456789', 'direccion' => 'Jr. San Martín 480, Juliaca',
                'sistema_pensiones' => 'AFP', 'afp' => 'Integra', 'cuspp' => '12345678901',
                'entidad_financiera' => 'BCP', 'numero_cuenta' => '19412345678012',
                'tiene_hijos' => true, 'sueldo_base' => 2500.00,
                'tipo_contrato' => 'indeterminado', 'forma_pago' => 'banco',
                'nivel_estudios' => 'universitario', 'especialidad' => 'Lengua y Literatura',
                'institucion_estudios' => 'Universidad Peruana Unión',
                'contacto_emergencia_nombre' => 'Óscar Chávez Mamani',
                'contacto_emergencia_telefono' => '953456780',
                'contrato' => ['observaciones' => 'Docente del área de Comunicación.'],
                'cuenta' => ['email' => 'test@colegio.com', 'password' => 'empleado123', 'rol' => 'empleado'],
            ],

            // ── Deivi: NO tocar sueldo, pensión ni hijos (ver arriba) ──
            [
                'dni' => '60065632', 'nombre' => 'Deivi', 'apellido' => 'Apaza Lucana',
                'fecha_nacimiento' => '1996-09-08', 'fecha_ingreso' => '2025-05-25',
                'area' => 'Pastoral y Capellanía', 'cargo' => 'Capellán', 'sede' => 'CATA',
                'telefono' => '954567890', 'direccion' => 'Jr. Gonzales Prada 315, Juliaca',
                'sistema_pensiones' => null, 'afp' => null, 'cuspp' => null,
                'entidad_financiera' => 'Interbank', 'numero_cuenta' => '8983216548790',
                'tiene_hijos' => false, 'sueldo_base' => 3000.00,
                'tipo_contrato' => 'indeterminado', 'forma_pago' => 'banco',
                'nivel_estudios' => 'universitario', 'especialidad' => 'Teología',
                'institucion_estudios' => 'Universidad Peruana Unión',
                'contacto_emergencia_nombre' => 'Rosa Lucana Quispe',
                'contacto_emergencia_telefono' => '954567891',
                'contrato' => ['observaciones' => 'Capellanía escolar y acompañamiento espiritual.'],
                'cuenta' => ['email' => 'deivi.apaza@cata.edu.pe', 'rol' => 'empleado'],
            ],

            // ── Plana docente ──────────────────────────────────────
            [
                'dni' => '44238190', 'nombre' => 'Carlos Enrique', 'apellido' => 'Mamani Flores',
                'fecha_nacimiento' => '1985-07-22', 'fecha_ingreso' => '2019-03-01',
                'area' => 'Plana Docente — Secundaria', 'cargo' => 'Docente', 'sede' => 'CATA',
                'telefono' => '955678901', 'direccion' => 'Urb. La Rinconada B-14, Juliaca',
                'sistema_pensiones' => 'AFP', 'afp' => 'Habitat', 'cuspp' => '442381901234',
                'entidad_financiera' => 'BBVA', 'numero_cuenta' => '00112233445566',
                'tiene_hijos' => true, 'sueldo_base' => 2800.00,
                'tipo_contrato' => 'indeterminado', 'forma_pago' => 'banco',
                'nivel_estudios' => 'universitario', 'especialidad' => 'Matemática Aplicada',
                'institucion_estudios' => 'Universidad Nacional del Altiplano',
                'contacto_emergencia_nombre' => 'Yeny Flores Apaza',
                'contacto_emergencia_telefono' => '955678902',
                'contrato' => ['observaciones' => 'Docente de Matemáticas de secundaria.'],
                'cuenta' => ['email' => 'carlos.mamani@cata.edu.pe', 'rol' => 'empleado'],
            ],
            [
                'dni' => '46712083', 'nombre' => 'Ana Lucía', 'apellido' => 'Condori Yupanqui',
                'fecha_nacimiento' => '1992-02-18', 'fecha_ingreso' => '2021-03-01',
                'area' => 'Plana Docente — Secundaria', 'cargo' => 'Docente', 'sede' => 'CATA',
                'telefono' => '956789012', 'direccion' => 'Jr. Ayacucho 762, Juliaca',
                'sistema_pensiones' => 'ONP', 'afp' => null, 'cuspp' => null,
                'entidad_financiera' => 'BCP', 'numero_cuenta' => '19455667788099',
                'tiene_hijos' => false, 'sueldo_base' => 2650.00,
                'tipo_contrato' => 'plazo_fijo', 'forma_pago' => 'banco',
                'nivel_estudios' => 'universitario', 'especialidad' => 'Biología y Química',
                'institucion_estudios' => 'Universidad Peruana Unión',
                'contacto_emergencia_nombre' => 'Hilda Yupanqui Ramos',
                'contacto_emergencia_telefono' => '956789013',
                'contrato' => [
                    'fecha_fin' => '2026-12-31',
                    'observaciones' => 'Contrato a plazo fijo por el año escolar 2026.',
                ],
                'cuenta' => ['email' => 'ana.condori@cata.edu.pe', 'rol' => 'empleado'],
            ],
            [
                'dni' => '43980215', 'nombre' => 'Jhon Michael', 'apellido' => 'Ramos Cahuana',
                'fecha_nacimiento' => '1987-12-01', 'fecha_ingreso' => '2017-03-01',
                'area' => 'Plana Docente — Primaria', 'cargo' => 'Docente', 'sede' => 'Jerusalen',
                'telefono' => '957890123', 'direccion' => 'Av. Circunvalación 210, Juliaca',
                'sistema_pensiones' => 'AFP', 'afp' => 'Profuturo', 'cuspp' => '439802151122',
                'entidad_financiera' => 'Interbank', 'numero_cuenta' => '8987654321000',
                'tiene_hijos' => true, 'sueldo_base' => 2900.00,
                'tipo_contrato' => 'indeterminado', 'forma_pago' => 'banco',
                'nivel_estudios' => 'universitario', 'especialidad' => 'Idiomas — Inglés',
                'institucion_estudios' => 'Universidad Nacional del Altiplano',
                'contacto_emergencia_nombre' => 'Karina Cahuana Ticona',
                'contacto_emergencia_telefono' => '957890124',
                'contrato' => ['observaciones' => 'Docente de Inglés, sede Jerusalén.'],
                'cuenta' => ['email' => 'jhon.ramos@cata.edu.pe', 'rol' => 'empleado'],
            ],
            [
                'dni' => '47215639', 'nombre' => 'Rosa María', 'apellido' => 'Huanca Pari',
                'fecha_nacimiento' => '1994-06-30', 'fecha_ingreso' => '2022-03-01',
                'area' => 'Plana Docente — Secundaria', 'cargo' => 'Docente', 'sede' => 'CATA',
                'telefono' => '958901234', 'direccion' => 'Jr. Lambayeque 903, Juliaca',
                'sistema_pensiones' => 'AFP', 'afp' => 'Integra', 'cuspp' => '472156393344',
                'entidad_financiera' => 'BCP', 'numero_cuenta' => '19433221100987',
                'tiene_hijos' => false, 'sueldo_base' => 2550.00,
                'tipo_contrato' => 'plazo_fijo', 'forma_pago' => 'banco',
                'nivel_estudios' => 'universitario', 'especialidad' => 'Ciencias Sociales',
                'institucion_estudios' => 'Universidad Peruana Unión',
                'contacto_emergencia_nombre' => 'Elsa Pari Mamani',
                'contacto_emergencia_telefono' => '958901235',
                'contrato' => [
                    'fecha_fin' => '2026-12-31',
                    'observaciones' => 'Contrato a plazo fijo por el año escolar 2026.',
                ],
                'cuenta' => ['email' => 'rosa.huanca@cata.edu.pe', 'rol' => 'empleado'],
            ],
            [
                'dni' => '42558107', 'nombre' => 'Wilber', 'apellido' => 'Apaza Ccopa',
                'fecha_nacimiento' => '1983-04-09', 'fecha_ingreso' => '2016-03-01',
                'area' => 'Plana Docente — Primaria', 'cargo' => 'Docente', 'sede' => 'CATA',
                'telefono' => '959012345', 'direccion' => 'Jr. Cusco 158, Juliaca',
                'sistema_pensiones' => 'ONP', 'afp' => null, 'cuspp' => null,
                'entidad_financiera' => 'BCP', 'numero_cuenta' => '19477889900112',
                'tiene_hijos' => true, 'sueldo_base' => 2700.00,
                'tipo_contrato' => 'indeterminado', 'forma_pago' => 'banco',
                'nivel_estudios' => 'universitario', 'especialidad' => 'Educación Física',
                'institucion_estudios' => 'Universidad Nacional del Altiplano',
                'contacto_emergencia_nombre' => 'Nancy Ccopa Vilca',
                'contacto_emergencia_telefono' => '959012346',
                'contrato' => ['observaciones' => 'Docente de Educación Física y deportes.'],
                'cuenta' => ['email' => 'wilber.apaza@cata.edu.pe', 'rol' => 'empleado'],
            ],
            [
                'dni' => '48963201', 'nombre' => 'Katherine Judith', 'apellido' => 'Vilca Sucari',
                'fecha_nacimiento' => '1997-10-25', 'fecha_ingreso' => '2024-03-01',
                'area' => 'Plana Docente — Inicial', 'cargo' => 'Docente', 'sede' => 'Jerusalen',
                'telefono' => '960123456', 'direccion' => 'Jr. Moquegua 640, Juliaca',
                'sistema_pensiones' => 'AFP', 'afp' => 'Prima', 'cuspp' => '489632015566',
                'entidad_financiera' => 'BBVA', 'numero_cuenta' => '00998877665544',
                'tiene_hijos' => false, 'sueldo_base' => 2300.00,
                'tipo_contrato' => 'plazo_fijo', 'forma_pago' => 'banco',
                'nivel_estudios' => 'universitario', 'especialidad' => 'Artes Plásticas',
                'institucion_estudios' => 'Escuela Superior de Bellas Artes',
                'contacto_emergencia_nombre' => 'Luis Vilca Quispe',
                'contacto_emergencia_telefono' => '960123457',
                'contrato' => [
                    'fecha_fin' => '2026-12-31',
                    'observaciones' => 'Contrato a plazo fijo por el año escolar 2026.',
                ],
                'cuenta' => ['email' => 'katherine.vilca@cata.edu.pe', 'rol' => 'empleado'],
            ],

            // ── Apoyo y administración ─────────────────────────────
            [
                'dni' => '45120388', 'nombre' => 'Sandra Beatriz', 'apellido' => 'Turpo Cutipa',
                'fecha_nacimiento' => '1991-01-17', 'fecha_ingreso' => '2020-03-01',
                'area' => 'Tutoría y Psicopedagogía', 'cargo' => 'Psicólogo', 'sede' => 'CATA',
                'telefono' => '961234567', 'direccion' => 'Av. Manuel Núñez 55, Juliaca',
                'sistema_pensiones' => 'AFP', 'afp' => 'Habitat', 'cuspp' => '451203887788',
                'entidad_financiera' => 'BCP', 'numero_cuenta' => '19466778899001',
                'tiene_hijos' => true, 'sueldo_base' => 3100.00,
                'tipo_contrato' => 'indeterminado', 'forma_pago' => 'banco',
                'nivel_estudios' => 'universitario', 'especialidad' => 'Psicología Educativa',
                'institucion_estudios' => 'Universidad Peruana Unión',
                'contacto_emergencia_nombre' => 'Máximo Turpo Larico',
                'contacto_emergencia_telefono' => '961234568',
                'contrato' => ['observaciones' => 'Departamento psicopedagógico.'],
                'cuenta' => ['email' => 'sandra.turpo@cata.edu.pe', 'rol' => 'empleado'],
            ],
            [
                'dni' => '70458812', 'nombre' => 'Yesenia', 'apellido' => 'Coaquira Choque',
                'fecha_nacimiento' => '1999-08-03', 'fecha_ingreso' => '2023-03-01',
                'area' => 'Secretaría Académica', 'cargo' => 'Secretaria', 'sede' => 'CATA',
                'telefono' => '962345678', 'direccion' => 'Jr. Piérola 331, Juliaca',
                'sistema_pensiones' => 'ONP', 'afp' => null, 'cuspp' => null,
                'entidad_financiera' => 'BCP', 'numero_cuenta' => '19412309876543',
                'tiene_hijos' => false, 'sueldo_base' => 1600.00,
                'tipo_contrato' => 'indeterminado', 'forma_pago' => 'banco',
                'nivel_estudios' => 'tecnico', 'especialidad' => 'Secretariado Ejecutivo',
                'institucion_estudios' => 'Instituto Superior Tecnológico Juliaca',
                'contacto_emergencia_nombre' => 'Delia Choque Mamani',
                'contacto_emergencia_telefono' => '962345679',
                'contrato' => ['observaciones' => 'Secretaría de dirección.'],
                'cuenta' => ['email' => 'yesenia.coaquira@cata.edu.pe', 'rol' => 'empleado'],
            ],

            // ── Practicante: media jornada y pago en efectivo ──────
            [
                'dni' => '76551204', 'nombre' => 'Brayan Alexis', 'apellido' => 'Quenta Sucasaca',
                'fecha_nacimiento' => '2003-05-11', 'fecha_ingreso' => '2026-03-01',
                'area' => 'TIC', 'cargo' => 'Practicante Pre-Profesional', 'sede' => 'CATA',
                'telefono' => '963456789', 'direccion' => 'Jr. Bolívar 128, Juliaca',
                'sistema_pensiones' => null, 'afp' => null, 'cuspp' => null,
                'entidad_financiera' => null, 'numero_cuenta' => null,
                'tiene_hijos' => false, 'sueldo_base' => 1130.00,
                'tipo_contrato' => 'practicas', 'forma_pago' => 'efectivo',
                'nivel_estudios' => 'universitario', 'especialidad' => 'Educación Secundaria',
                'institucion_estudios' => 'Universidad Nacional del Altiplano',
                'contacto_emergencia_nombre' => 'Gladys Sucasaca Mamani',
                'contacto_emergencia_telefono' => '963456790',
                'contrato' => [
                    'fecha_fin' => '2026-12-31',
                    'observaciones' => 'Prácticas preprofesionales, media jornada.',
                ],
                'cuenta' => ['email' => 'brayan.quenta@cata.edu.pe', 'rol' => 'empleado'],
            ],
        ];
    }
}
