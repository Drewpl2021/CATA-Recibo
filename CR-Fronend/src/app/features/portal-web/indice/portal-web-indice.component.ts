import { AfterViewInit, Component, ElementRef, OnInit, QueryList, ViewChildren, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';

import { ToastService } from '../../../core/services';
import { mensajeErrorApi } from '../../../core/utils';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import {
  CATEGORIAS_DEL_PORTAL,
  CategoriaDelPortal,
  IdCategoria,
  ParteDelPortal,
  ResumenPortal,
} from '../portal-web.models';
import { PortalWebService } from '../portal-web.service';

/** Lista: se ve en el portal. Borrador: tiene registros guardados sin publicar. Falta: no se ve (ficha sin cargar o lista vacía). */
type TonoDeParte = 'lista' | 'borrador' | 'falta';

interface TramoDeParte {
  nombre: string;
  tono: TonoDeParte;
  texto: string;
}

interface TarjetaDelPortal {
  categoria: CategoriaDelPortal;
  url: string;
  /** Un tramo de la barra por parte; vacío si no se pudo traer el resumen. */
  tramos: TramoDeParte[];
  faltan: TramoDeParte[];
  borradores: number;
  listas: number;
}

/**
 * La última categoría abierta: al volver, su tarjeta recibe el foco y la
 * rejilla no repite la entrada escalonada.
 */
let ultimaAbierta: IdCategoria | null = null;

/** Lo que dura la salida de la rejilla antes de cambiar de pantalla (portal-web-indice.component.scss). */
const DURACION_SALIDA = 300;

/**
 * La entrada al panel «Portal Web»: una tarjeta por página del portal, con
 * lo que contiene y cómo está hoy. Arriba, cuántas partes no se ven en el
 * portal; las tarjetas no se reordenan por eso, quedan siempre en su lugar.
 *
 * Una parte «falta» si no se ve en el portal (ficha sin cargar o lista
 * vacía). Los borradores van aparte: están guardados, pero no faltan.
 */
@Component({
  selector: 'app-portal-web-indice',
  standalone: true,
  imports: [CommonModule, IconComponent, PageHeaderComponent],
  templateUrl: './portal-web-indice.component.html',
  styleUrls: ['./portal-web-indice.component.scss'],
})
export class PortalWebIndiceComponent implements OnInit, AfterViewInit {
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);
  private router = inject(Router);

  @ViewChildren('enlace') enlaces!: QueryList<ElementRef<HTMLAnchorElement>>;

  readonly esqueletos = Array.from({ length: CATEGORIAS_DEL_PORTAL.length });
  tarjetas: TarjetaDelPortal[] = [];
  cargando = true;
  /** Sin resumen (falló la carga): las tarjetas se muestran sin estado. */
  conEstado = false;
  /** La rejilla entra escalonada, salvo al volver de una categoría. */
  entrando = ultimaAbierta === null;
  abriendo: IdCategoria | null = null;

  partesQueFaltan = 0;
  paginasConFaltas = 0;
  paginasCompletas = 0;
  borradores = 0;

  ngOnInit(): void {
    this.portalWebService.getResumen().subscribe({
      next: (res) => this.armar(res.data),
      error: (err) => {
        this.armar(null);
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar el estado del portal.'));
      },
    });
  }

  ngAfterViewInit(): void {
    // Las tarjetas aparecen al llegar el resumen; al volver de una categoría, el foco vuelve a la suya.
    this.enlaces.changes.subscribe(() => {
      if (!ultimaAbierta) return;
      const id = ultimaAbierta;
      ultimaAbierta = null;
      this.enlaces.find((e) => e.nativeElement.dataset['id'] === id)?.nativeElement.focus();
    });
  }

  /**
   * Abre la pantalla de una categoría, después de la salida de la rejilla. Un
   * clic con Ctrl, Mayús o la rueda queda para el navegador (pestaña nueva).
   */
  abrir(evento: MouseEvent, t: TarjetaDelPortal): void {
    if (evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) return;
    evento.preventDefault();
    if (this.abriendo) return;

    ultimaAbierta = t.categoria.id;
    if (this.sinMovimiento()) {
      this.router.navigateByUrl(t.url);
      return;
    }
    this.abriendo = t.categoria.id;
    setTimeout(() => this.router.navigateByUrl(t.url), DURACION_SALIDA);
  }

  porId(_i: number, t: TarjetaDelPortal): IdCategoria {
    return t.categoria.id;
  }

  /** El sello de la tarjeta. */
  estadoDe(t: TarjetaDelPortal): string {
    if (t.faltan.length) return t.faltan.length === 1 ? '1 pendiente' : `${t.faltan.length} pendientes`;
    return t.borradores ? `${t.borradores} en borrador` : 'Completa';
  }

  /** Lo que oye un lector de pantalla tras el nombre: el estado, qué falta y lo que contiene. */
  descripcionDe(t: TarjetaDelPortal): string {
    let texto = `${t.categoria.descripcion} `;
    if (!this.conEstado) return texto;
    texto += t.faltan.length ? `${this.estadoDe(t)}: ` + t.faltan.map((f) => `${f.nombre}, ${f.texto.toLowerCase()}`).join('; ') + '.' : 'Completa.';
    if (t.faltan.length && t.borradores) texto += ` ${t.borradores} en borrador.`;
    return texto;
  }

  private armar(resumen: ResumenPortal | null): void {
    this.conEstado = !!resumen;
    this.tarjetas = CATEGORIAS_DEL_PORTAL.map((c) => {
      const tramos = resumen ? c.partes.map((p) => this.tramoDe(p, resumen)) : [];
      return {
        categoria: c,
        // La de Inicio vive en «portada»: un segmento «inicio» no llegaría a las migas.
        url: this.router.serializeUrl(this.router.createUrlTree(['/inicio/portal-web', c.id === 'inicio' ? 'portada' : c.id])),
        tramos,
        faltan: tramos.filter((t) => t.tono === 'falta'),
        // Los de una parte que falta ya cuentan en «falta»: aquí van solo los de las partes que se ven.
        borradores: resumen ? c.partes.reduce((s, p, i) => s + (p.lista && tramos[i].tono !== 'falta' ? resumen.listas[p.lista]?.borradores ?? 0 : 0), 0) : 0,
        listas: tramos.filter((t) => t.tono !== 'falta').length,
      };
    });
    this.partesQueFaltan = this.tarjetas.reduce((s, t) => s + t.faltan.length, 0);
    this.paginasConFaltas = this.tarjetas.filter((t) => t.faltan.length).length;
    this.paginasCompletas = this.tarjetas.length - this.paginasConFaltas;
    this.borradores = this.tarjetas.reduce((s, t) => s + t.borradores, 0);
    this.cargando = false;
  }

  private tramoDe(p: ParteDelPortal, resumen: ResumenPortal): TramoDeParte {
    if (p.ficha) {
      return resumen.fichas.includes(p.ficha)
        ? { nombre: p.nombre, tono: 'lista', texto: 'Cargada' }
        : { nombre: p.nombre, tono: 'falta', texto: 'Sin cargar' };
    }

    const conteo = resumen.listas[p.lista ?? ''] ?? { publicados: 0, borradores: 0 };
    if (conteo.publicados + conteo.borradores === 0) return { nombre: p.nombre, tono: 'falta', texto: 'Vacía' };
    // Con todo en borrador, la lista tampoco se ve en el portal.
    if (conteo.publicados === 0) return { nombre: p.nombre, tono: 'falta', texto: `${conteo.borradores} en borrador, nada publicado` };

    const publicados = conteo.publicados === 1 ? '1 publicado' : `${conteo.publicados} publicados`;
    return conteo.borradores > 0
      ? { nombre: p.nombre, tono: 'borrador', texto: `${publicados} · ${conteo.borradores} en borrador` }
      : { nombre: p.nombre, tono: 'lista', texto: publicados };
  }

  private sinMovimiento(): boolean {
    return typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches;
  }
}
