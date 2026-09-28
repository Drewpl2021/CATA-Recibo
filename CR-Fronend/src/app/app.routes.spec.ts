import { Route } from '@angular/router';
import { routes } from './app.routes';

/**
 * El bug real (2026-09-27): a /inicio/dashboard le faltaba el canActivate
 * que sí tienen todas sus pantallas hermanas. No se notaba mirando el menú
 * —a un trabajador no se le muestra el enlace—, pero las migas de pan y el
 * logo apuntan ahí SIEMPRE, para cualquier rol (layout.component.ts), así
 * que un trabajador que le daba clic a "Inicio" entraba igual: la API
 * rechazaba los datos, pero la pantalla se llegaba a cargar.
 *
 * Esta prueba no repite el bug puntual: revisa la LISTA completa de rutas
 * dentro de /inicio, para que la próxima pantalla de RR.HH./Admin que se
 * agregue sin su guard falle aquí, en vez de esperar a que alguien la
 * encuentre a mano.
 */
describe('rutas de /inicio', () => {
  const inicio = routes.find((r) => r.path === 'inicio');
  const hijos = (inicio?.children ?? []) as Route[];

  /**
   * Las únicas pantallas de /inicio que un trabajador SÍ debe poder abrir:
   * lo suyo (sus boletas, sus documentos, sus vacaciones), la ruta vacía
   * (que redirige, no carga nada) y el comodín (que también redirige).
   */
  const SIN_RESTRICCION_DE_ROL = new Set(['', 'mis-boletas', 'mis-documentos', 'mis-vacaciones', '**']);

  it('no se queda sin hijos por un cambio accidental en el archivo', () => {
    expect(hijos.length).toBeGreaterThan(20);
  });

  it('el panel de control exige rol de RR.HH. o Admin', () => {
    const dashboard = hijos.find((r) => r.path === 'dashboard');
    expect(dashboard?.canActivate?.length).toBeGreaterThan(0);
  });

  it('toda pantalla que no es de uso compartido lleva su canActivate', () => {
    const sinGuardia = hijos.filter(
      (r) => !SIN_RESTRICCION_DE_ROL.has(r.path ?? '') && !(r.canActivate && r.canActivate.length > 0)
    );

    expect(sinGuardia.map((r) => r.path)).toEqual([]);
  });
});
