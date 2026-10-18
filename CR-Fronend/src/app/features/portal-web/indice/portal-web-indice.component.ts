import { Component, OnInit, inject } from '@angular/core';
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

/**
 * La entrada al panel «Portal Web»: las páginas del portal, cada una con lo
 * que se edita en ella y cómo está hoy (cuántos registros publicados y en
 * borrador, qué fichas faltan cargar).
 *
 * Se arma como la pantalla de Ajustes del sistema —el índice a la izquierda,
 * el panel a la derecha— y con sus mismas clases: el módulo se suma al
 * sistema sin estilos propios.
 */
@Component({
  selector: 'app-portal-web-indice',
  standalone: true,
  imports: [CommonModule, IconComponent, PageHeaderComponent],
  templateUrl: './portal-web-indice.component.html',
})
export class PortalWebIndiceComponent implements OnInit {
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);
  private router = inject(Router);

  readonly categorias = CATEGORIAS_DEL_PORTAL;
  categoria: IdCategoria = 'general';
  resumen: ResumenPortal | null = null;
  cargando = true;

  ngOnInit(): void {
    this.portalWebService.getResumen().subscribe({
      next: (res) => {
        this.resumen = res.data;
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar el estado del portal.'));
      },
    });
  }

  get seleccionada(): CategoriaDelPortal {
    return this.categorias.find((c) => c.id === this.categoria) ?? this.categorias[0];
  }

  /**
   * Abre la pantalla de edición de una categoría ya habilitada. La de Inicio
   * vive en «portada»: un segmento «inicio» no llegaría a las migas.
   */
  abrir(id: IdCategoria): void {
    this.router.navigate(['/inicio/portal-web', id === 'inicio' ? 'portada' : id]);
  }

  elegir(id: IdCategoria): void {
    this.categoria = id;
  }

  porId(_i: number, c: CategoriaDelPortal): IdCategoria {
    return c.id;
  }

  /** Lo que dice el índice bajo cada categoría. */
  estadoDe(c: CategoriaDelPortal): string {
    if (!this.resumen) return this.cargando ? 'Cargando…' : '';
    const faltan = this.fichasSinCargar(c);
    if (faltan > 0) return faltan === 1 ? 'Falta cargar una sección' : `Faltan cargar ${faltan} secciones`;
    return c.disponible ? 'Lista para editar' : 'Próximamente';
  }

  /** Aviso en el índice: una página con secciones sin cargar no se ve completa en el portal. */
  avisoDe(c: CategoriaDelPortal): boolean {
    return !!this.resumen && this.fichasSinCargar(c) > 0;
  }

  /** Cómo está una parte: cuántos registros, o si su ficha está cargada. */
  estadoDeParte(p: ParteDelPortal): { texto: string; tono: string } {
    if (!this.resumen) return { texto: '—', tono: 'secondary' };

    if (p.ficha) {
      return this.resumen.fichas.includes(p.ficha)
        ? { texto: 'Cargada', tono: 'success' }
        : { texto: 'Sin cargar', tono: 'warning' };
    }

    const conteo = this.resumen.listas[p.lista ?? ''] ?? { publicados: 0, borradores: 0 };
    const total = conteo.publicados + conteo.borradores;
    if (total === 0) return { texto: 'Vacía', tono: 'secondary' };

    const publicados = conteo.publicados === 1 ? '1 publicado' : `${conteo.publicados} publicados`;
    return conteo.borradores > 0
      ? { texto: `${publicados} · ${conteo.borradores} en borrador`, tono: 'info' }
      : { texto: publicados, tono: 'success' };
  }

  private fichasSinCargar(c: CategoriaDelPortal): number {
    return c.partes.filter((p) => p.ficha && !this.resumen!.fichas.includes(p.ficha)).length;
  }
}
