import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { Router } from '@angular/router';

import { ToastService } from '../../../core/services';
import { mensajeErrorApi } from '../../../core/utils';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { mostrarErrorDelPanel } from '../compartido/panel';
import { CabeceraSimple, CategoriaProyectoPortal } from '../portal-web.models';
import { PortalWebService } from '../portal-web.service';
import { PanelCategoriasComponent } from './categorias/panel-categorias.component';
import { PanelProyectosComponent } from './proyectos/panel-proyectos.component';

/**
 * Panel «Portal Web» › Proyectos: la cabecera de la página, las categorías
 * del filtro y los proyectos, cada uno con su detalle y su galería.
 */
@Component({
  selector: 'app-portal-web-proyectos',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, PageHeaderComponent, IconComponent, PanelCategoriasComponent, PanelProyectosComponent],
  templateUrl: './portal-web-proyectos.component.html',
})
export class PortalWebProyectosComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);
  private router = inject(Router);

  /** Las del panel de categorías, para el selector de cada proyecto. */
  categorias: CategoriaProyectoPortal[] = [];

  cargandoCabecera = true;
  guardandoCabecera = false;
  private versionCabecera: string | null = null;

  cabecera = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    bajada: ['', [Validators.maxLength(240)]],
  });

  ngOnInit(): void {
    this.cargarCabecera();
  }

  volver(): void {
    this.router.navigate(['/inicio/portal-web']);
  }

  cargarCabecera(): void {
    this.cargandoCabecera = true;
    this.portalWebService.getFicha<CabeceraSimple>('proyectos').subscribe({
      next: (res) => {
        this.versionCabecera = res.data.version;
        this.cabecera.reset({ titulo: res.data.contenido?.titulo ?? '', bajada: res.data.contenido?.bajada ?? '' });
        this.cargandoCabecera = false;
      },
      error: (err) => {
        this.cargandoCabecera = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la cabecera de Proyectos.'));
      },
    });
  }

  invalidoCabecera(campo: string): boolean {
    const c = this.cabecera.get(campo);
    return !!c && c.invalid && c.touched;
  }

  guardarCabecera(): void {
    if (this.cabecera.invalid) {
      this.cabecera.markAllAsTouched();
      return;
    }

    const v = this.cabecera.getRawValue();
    this.guardandoCabecera = true;
    this.portalWebService.guardarFicha<CabeceraSimple>('proyectos', { titulo: v.titulo, bajada: v.bajada || null }, this.versionCabecera).subscribe({
      next: (res) => {
        this.guardandoCabecera = false;
        this.versionCabecera = res.data.version;
        this.cabecera.markAsPristine();
        this.toastService.success('Cabecera guardada', 'El portal la mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoCabecera = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar la cabecera.');
      },
    });
  }
}
