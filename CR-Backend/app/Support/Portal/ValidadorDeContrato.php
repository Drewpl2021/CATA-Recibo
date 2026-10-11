<?php

namespace App\Support\Portal;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response as RespuestaHttp;
use Illuminate\Support\Facades\Route;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Comprueba que lo que el portal va a recibir siga cumpliendo su contrato.
 *
 * Es la última capa de la validación del panel, y la única que lo garantiza:
 * las reglas de cada formulario ven un registro suelto, pero no ven lo que
 * resulta de juntarlo con el resto (un proyecto que apunta a una categoría,
 * una lista que pasa de su tope). Por eso, después de aplicar un cambio y
 * antes de confirmarlo, se ARMA cada respuesta pública afectada con los
 * mismos controladores que la sirven y se valida contra el JSON Schema del
 * contrato (resources/portal/api-contract.schema.json). Si alguna no lo
 * cumple, el cambio se deshace y el panel dice por qué.
 *
 * Arma la respuesta llamando a la ruta pública directamente, sin pasar por
 * sus middleware: no gasta el freno del portal ni depende de CORS.
 */
class ValidadorDeContrato
{
    public const ESQUEMA = 'portal/api-contract.schema.json';

    /** Cómo se lee cada endpoint en un mensaje para quien edita. */
    private const NOMBRES = [
        'sitio'                           => 'los datos del colegio',
        'portada/banners'                 => 'el carrusel de Inicio',
        'portada/cifras'                  => 'las cifras de Inicio',
        'quienes-somos'                   => 'Quiénes somos',
        'niveles'                         => 'los niveles de Inicio',
        'sedes'                           => 'las sedes',
        'noticias'                        => 'las noticias',
        'grados'                          => 'los grados del formulario',
        'propuesta-educativa/cabecera'    => 'la cabecera de Propuesta Educativa',
        'propuesta-educativa/pilares'     => 'los pilares',
        'propuesta-educativa/niveles'     => 'los niveles de Propuesta Educativa',
        'propuesta-educativa/academia'    => 'la academia',
        'propuesta-educativa/plataformas' => 'las plataformas digitales',
        'matricula/cabecera'              => 'la cabecera de Matrícula',
        'matricula/procesos'              => 'los procesos de matrícula',
        'matricula/vacantes'              => 'las vacantes',
        'matricula/fechas'                => 'el cronograma de matrícula',
        'matricula/preguntas'             => 'las preguntas frecuentes',
        'solicitud'                       => 'el formulario «Solicitar información»',
        'contacto'                        => 'la página Contacto',
        'proyectos'                       => 'la lista de proyectos',
        'proyectos/{slug}'                => 'el proyecto',
        'nosotros'                        => 'el índice de Nosotros',
        'nosotros/paginas/{slug}'         => 'la página de Nosotros',
        'docentes'                        => 'el personal docente',
        'logros'                          => 'los logros',
    ];

    private static ?object $esquema = null;

    /**
     * Las rutas que no cumplen el contrato, con el porqué.
     *
     * @param  string[]  $rutas  rutas del contrato, con su valor: '/v1/sitio', '/v1/proyectos/robotica'
     * @return array<string, string> ruta → mensaje para quien edita (vacío = todo bien)
     */
    public function problemas(array $rutas): array
    {
        $problemas = [];

        foreach (array_unique($rutas) as $ruta) {
            $problema = $this->problema($ruta);

            if ($problema !== null) {
                $problemas[$ruta] = $problema;
            }
        }

        return $problemas;
    }

    /** Por qué esa ruta no cumple el contrato, o null si lo cumple. */
    public function problema(string $ruta): ?string
    {
        [$plantilla, $respuesta] = $this->armar($ruta);
        $nombre = self::NOMBRES[ltrim(substr($plantilla, 3), '/')] ?? $ruta;

        if ($respuesta instanceof SeccionSinConfigurar) {
            return "El portal no podría mostrar {$nombre}: {$respuesta->getMessage()}";
        }

        if ($respuesta->getStatusCode() !== RespuestaHttp::HTTP_OK) {
            return "El portal no podría mostrar {$nombre} (responde {$respuesta->getStatusCode()}).";
        }

        $definicion = self::esquema()->definitions->{"GET {$plantilla}"} ?? null;

        if ($definicion === null) {
            return "El contrato no tiene «GET {$plantilla}».";
        }

        $resultado = (new Validator())->validate(json_decode($respuesta->getContent()), $definicion);

        if ($resultado->isValid()) {
            return null;
        }

        return "El portal descartaría {$nombre}: " . $this->explicar($resultado->error());
    }

    /** El esquema del contrato, leído una vez. */
    public static function esquema(): object
    {
        return self::$esquema ??= json_decode(
            file_get_contents(resource_path(self::ESQUEMA)),
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * La respuesta de una ruta pública, armada por su propio controlador.
     *
     * @return array{0: string, 1: Response|SeccionSinConfigurar} la ruta del
     *         contrato con sus {parámetros} y lo que respondió
     */
    private function armar(string $ruta): array
    {
        $peticion = Request::create('/api/portal' . $ruta, 'GET');
        $ruteada = Route::getRoutes()->match($peticion);
        $plantilla = substr($ruteada->uri(), strlen('api/portal'));

        // El controlador lee la petición del contenedor (noticias lee
        // ?limite): se le da la suya mientras arma, y se devuelve la de quien
        // está editando.
        $actual = app('request');
        app()->instance('request', $peticion);

        try {
            return [$plantilla, Route::prepareResponse($peticion, $ruteada->bind($peticion)->run())];
        } catch (SeccionSinConfigurar $e) {
            return [$plantilla, $e];
        } catch (ModelNotFoundException) {
            // Sin el manejador de errores de por medio, el 404 de un detalle
            // llega como excepción y no como respuesta.
            return [$plantilla, new RespuestaHttp('', RespuestaHttp::HTTP_NOT_FOUND)];
        } catch (HttpExceptionInterface $e) {
            return [$plantilla, new RespuestaHttp('', $e->getStatusCode())];
        } finally {
            app()->instance('request', $actual);
        }
    }

    /**
     * El primer error del esquema, en una línea: qué y dónde.
     *
     * Lo lee quien edita, así que los errores que se ven en la práctica van
     * en sus palabras; el resto, tal como lo dice el validador (con el dónde,
     * que a soporte le basta para encontrarlo).
     */
    private function explicar(\Opis\JsonSchema\Errors\ValidationError $error): string
    {
        $errores = (new ErrorFormatter())->format($error, false);
        $donde = array_key_first($errores) ?? '/';
        $que = is_array($errores[$donde] ?? null) ? implode(' ', $errores[$donde]) : (string) ($errores[$donde] ?? '');

        $enPalabras = match (true) {
            str_contains($que, '^https') => 'una dirección no empieza con https:// (si es una imagen subida, revisa que APP_URL del servidor sea https)',
            str_contains($que, 'at most')  => 'hay más elementos de los que el portal muestra',
            str_contains($que, 'at least') => 'falta al menos un elemento',
            default                        => null,
        };

        return $enPalabras !== null
            ? "{$enPalabras} (en {$donde})."
            : trim("en {$donde}: {$que}");
    }
}
