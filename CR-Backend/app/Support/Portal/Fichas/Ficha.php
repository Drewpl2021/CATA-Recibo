<?php

namespace App\Support\Portal\Fichas;

/**
 * Lo que el panel necesita saber de cada ficha del portal (una sección de
 * una sola vez, guardada en portal_secciones): cómo se valida, cómo se
 * guarda y qué respuestas del portal cambian con ella.
 *
 * Una clase por ficha (FichaSitio, FichaContacto…), registrada en
 * SeccionesController. Las reglas llevan los largos del contrato.
 */
interface Ficha
{
    /** Cómo se llama en los mensajes: 'los datos del colegio'. */
    public function nombre(): string;

    /** Las reglas de Laravel, con los campos del contrato (puntos para lo anidado). */
    public function reglas(): array;

    /** Cómo se leen los campos en los mensajes de error. */
    public function atributos(): array;

    /**
     * Lo validado → el contenido que se guarda: con las claves del contrato,
     * en su orden, y los opcionales vacíos en null.
     */
    public function aContenido(array $validado): array;

    /** Las rutas del portal que cambian con esta ficha: ['/v1/sitio']. */
    public function rutas(): array;
}
