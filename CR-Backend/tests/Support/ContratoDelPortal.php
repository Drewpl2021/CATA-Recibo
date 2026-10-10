<?php

namespace Tests\Support;

use App\Models\Portal\RedSocial;
use App\Models\Portal\Seccion;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Lo que comparten las pruebas de la API del portal: leer los mocks del
 * contrato, cargarlos en la base y validar respuestas contra el esquema.
 * Ver tests/Fixtures/portal/README.md.
 */
trait ContratoDelPortal
{
    private static function rutaFixtures(string $archivo = ''): string
    {
        return base_path('tests/Fixtures/portal/' . $archivo);
    }

    /**
     * Un mock del contrato. Los escenarios solo traen los archivos que
     * cambian; lo que no traen se toma de `tipico`.
     */
    protected function ejemplo(string $escenario, string $archivo): array
    {
        $ruta = self::rutaFixtures("mock/{$escenario}/{$archivo}");

        if (! is_file($ruta)) {
            $ruta = self::rutaFixtures("mock/tipico/{$archivo}");
        }

        return json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
    }

    /** Falla con la lista de errores si el cuerpo no cumple el esquema del endpoint. */
    protected function assertCumpleElContrato(string $endpoint, string $cuerpo): void
    {
        $esquema = json_decode(file_get_contents(self::rutaFixtures('api-contract.schema.json')));
        $definicion = $esquema->definitions->{"GET {$endpoint}"} ?? null;

        $this->assertNotNull($definicion, "El esquema no tiene «GET {$endpoint}».");

        $resultado = (new Validator())->validate(json_decode($cuerpo), $definicion);

        $this->assertTrue(
            $resultado->isValid(),
            "GET {$endpoint} no cumple el contrato:\n" . json_encode(
                $resultado->hasError() ? (new ErrorFormatter())->format($resultado->error()) : [],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
        );
    }

    /**
     * Igual al ejemplo, salvo el orden de las claves dentro de cada objeto
     * (JSON no lo tiene, y MySQL lo cambia). Lo demás cuenta: el orden de
     * las listas, cada null y el tipo exacto (440 no es "440.00").
     */
    protected function assertIgualAlEjemplo(array $esperado, array $real, string $mensaje = ''): void
    {
        $this->assertSame(self::sinOrdenDeClaves($esperado), self::sinOrdenDeClaves($real), $mensaje);
    }

    private static function sinOrdenDeClaves(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return $valor;
        }

        if (! array_is_list($valor)) {
            ksort($valor);
        }

        return array_map(fn ($v) => self::sinOrdenDeClaves($v), $valor);
    }

    /** Carga en la base lo que devuelve GET /v1/sitio en un escenario. */
    protected function cargarSitio(array $sitio): void
    {
        $redes = $sitio['redes'];
        unset($sitio['redes']);

        Seccion::create(['clave' => 'sitio', 'contenido' => $sitio]);

        foreach ($redes as $orden => $red) {
            RedSocial::create($red + ['orden' => $orden + 1]);
        }
    }
}
