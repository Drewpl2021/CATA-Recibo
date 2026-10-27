import { Directive, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';

import { PendienteDeSeccion, ResumenDeSeccion } from './resumen-de-seccion';

/** Una entrada del índice de una pantalla del panel. */
export interface SeccionDelIndice {
  id: string;
  nombre: string;
  /** Clave del catálogo de íconos del sistema. */
  icono: string;
}

export type PendienteConSeccion = PendienteDeSeccion & { seccion: string };

/**
 * Lo común a las pantallas del panel con el molde de Ajustes: el índice a la
 * izquierda, la sección elegida a la derecha y «Pendientes» arriba (ver
 * pw-indice-de-secciones, que los dibuja).
 *
 * La sección elegida va en la dirección (?seccion=…): al recargar se vuelve
 * a ella, y un enlace puede llevar directo a una sección. Cada sección cuenta
 * cómo está con alResumir(); de ahí salen su línea del índice y sus pendientes.
 */
@Directive()
export abstract class PantallaConIndice implements OnInit {
  protected router = inject(Router);
  protected route = inject(ActivatedRoute);

  /** En el orden en que se ven en el portal. */
  abstract readonly secciones: SeccionDelIndice[];

  actual = '';
  resumenes: Record<string, ResumenDeSeccion> = {};
  pendientes: PendienteConSeccion[] = [];

  ngOnInit(): void {
    const pedida = this.route.snapshot.queryParamMap.get('seccion');
    this.actual = this.secciones.find((s) => s.id === pedida)?.id ?? this.secciones[0].id;
  }

  elegir(id: string): void {
    this.actual = id;
    this.router.navigate([], { relativeTo: this.route, queryParams: { seccion: id }, replaceUrl: true });
  }

  alResumir(id: string, resumen: ResumenDeSeccion): void {
    this.resumenes = { ...this.resumenes, [id]: resumen };
    this.pendientes = this.secciones.flatMap((s) => (this.resumenes[s.id]?.pendientes ?? []).map((p) => ({ ...p, seccion: s.id })));
  }

  volver(): void {
    this.router.navigate(['/inicio/portal-web']);
  }
}
