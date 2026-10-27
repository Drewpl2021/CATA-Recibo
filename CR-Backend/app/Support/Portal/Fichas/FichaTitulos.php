<?php

namespace App\Support\Portal\Fichas;

use App\Support\Portal\Navegacion;

/**
 * Los títulos de sección, botones y rótulos de cada página del portal
 * (contrato 2.13, `titulos` de /v1/navegacion). Los campos y sus largos son
 * los de Navegacion::TITULOS; todos obligatorios.
 */
class FichaTitulos implements Ficha
{
    /** Cómo se lee cada campo en un mensaje: la página y lo que es. */
    private const NOMBRES = [
        'inicio' => [
            'quienesSomos' => 'título de Quiénes somos', 'verHistoria' => 'enlace a la reseña histórica',
            'emblema' => 'título del emblema', 'niveles' => 'título de Niveles (si no cargan)',
            'verPropuesta' => 'enlace a la propuesta educativa', 'sedes' => 'título de las sedes (si no cargan)',
            'sedesVarias' => 'título de varias sedes', 'sedesUna' => 'título de una sola sede',
            'noticias' => 'título de las noticias',
        ],
        'nosotros' => ['accion' => 'botón de la cabecera', 'rotuloAnio' => 'rótulo del año de fundación'],
        'propuestaEducativa' => [
            'pilares' => 'título de los pilares', 'rotuloPilar' => 'rótulo de cada pilar',
            'niveles' => 'título de los niveles', 'plataformas' => 'título de las plataformas',
        ],
        'matricula' => [
            'accionPrincipal' => 'botón principal', 'accionSecundaria' => 'botón secundario',
            'rotuloAnio' => 'rótulo del año', 'procesos' => 'título de pasos y requisitos',
            'vacantes' => 'título de las vacantes', 'preguntarGrado' => 'botón de las vacantes',
            'fechas' => 'título de las fechas', 'preguntas' => 'título de las preguntas',
        ],
        'contacto' => [
            'accionPrincipal' => 'botón principal', 'accionSecundaria' => 'botón secundario',
            'rotuloWhatsapp' => 'rótulo del WhatsApp', 'sedes' => 'título de las sedes',
            'sedesUna' => 'título de una sola sede', 'mapa' => 'título del mapa', 'redes' => 'título de las redes',
        ],
        'proyectos' => [
            'accion' => 'botón de la cabecera', 'contacto' => 'botón de contacto', 'rotulo' => 'rótulo de la cuenta',
            'rotuloUno' => 'rótulo de un solo proyecto', 'lista' => 'título de la lista',
            'sobre' => 'título de la descripción', 'galeria' => 'título de la galería',
            'otros' => 'título de otros proyectos', 'volver' => 'botón para volver', 'verTodos' => 'enlace a todos',
        ],
        'logros' => [
            'accion' => 'botón de la cabecera', 'rotulo' => 'rótulo de la cuenta', 'rotuloUno' => 'rótulo de un solo logro',
            'destacados' => 'título de los destacados', 'todos' => 'título de la lista',
        ],
    ];

    private const PAGINAS = [
        'inicio' => 'Inicio', 'nosotros' => 'Nosotros', 'propuestaEducativa' => 'Propuesta Educativa',
        'matricula' => 'Matrícula', 'contacto' => 'Contacto', 'proyectos' => 'Proyectos', 'logros' => 'Logros',
    ];

    public function nombre(): string
    {
        return 'los títulos de las páginas';
    }

    public function reglas(): array
    {
        $reglas = [];
        foreach (Navegacion::TITULOS as $pagina => $campos) {
            $reglas[$pagina] = ['required', 'array:' . implode(',', array_keys($campos))];
            foreach ($campos as $campo => $maximo) {
                $reglas["{$pagina}.{$campo}"] = ['required', 'string', "max:{$maximo}"];
            }
        }
        // El de varias sedes lleva el número: sin {n}, diría lo mismo con una que con ocho.
        $reglas['inicio.sedesVarias'][] = 'regex:/\{n\}/';

        return $reglas;
    }

    public function atributos(): array
    {
        $atributos = [];
        foreach (self::NOMBRES as $pagina => $campos) {
            foreach ($campos as $campo => $nombre) {
                $atributos["{$pagina}.{$campo}"] = "{$nombre} de " . self::PAGINAS[$pagina];
            }
        }

        return $atributos;
    }

    public function aContenido(array $v): array
    {
        return collect(Navegacion::TITULOS)
            ->map(fn (array $campos, string $pagina) => collect(array_keys($campos))->mapWithKeys(fn ($campo) => [$campo => $v[$pagina][$campo]])->all())
            ->all();
    }

    public function rutas(): array
    {
        return ['/v1/navegacion'];
    }
}
