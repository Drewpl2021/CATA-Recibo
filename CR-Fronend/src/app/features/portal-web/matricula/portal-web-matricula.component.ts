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
import { SelectorImagenComponent } from '../compartido/selector-imagen/selector-imagen.component';
import { ImagenPortal, MatriculaCabecera } from '../portal-web.models';
import { PortalWebService } from '../portal-web.service';
import { PanelFechasComponent } from './fechas/panel-fechas.component';
import { PanelGradosComponent } from './grados/panel-grados.component';
import { PanelPreguntasComponent } from './preguntas/panel-preguntas.component';
import { PanelProcesosComponent } from './procesos/panel-procesos.component';
import { PanelVacantesComponent } from './vacantes/panel-vacantes.component';

/**
 * Panel «Portal Web» › Matrícula: la cabecera de la página (con el año del
 * proceso y el recorte de un estudiante) y, en el orden en que se ven en el
 * portal, los procesos, las vacantes, el cronograma, las preguntas y las
 * opciones de grado del formulario. Cada parte es su propio panel.
 */
@Component({
  selector: 'app-portal-web-matricula',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, PageHeaderComponent, IconComponent, SelectorImagenComponent,
    PanelProcesosComponent, PanelVacantesComponent, PanelFechasComponent, PanelPreguntasComponent, PanelGradosComponent,
  ],
  templateUrl: './portal-web-matricula.component.html',
})
export class PortalWebMatriculaComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);
  private router = inject(Router);

  cargandoCabecera = true;
  guardandoCabecera = false;
  private versionCabecera: string | null = null;
  figura: ImagenPortal | null = null;
  figuraCambiada = false;

  cabecera = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    bajada: ['', [Validators.maxLength(160)]],
    anio: [null as number | null, [Validators.min(2000), Validators.max(2100), Validators.pattern(/^\d{4}$/)]],
  });

  ngOnInit(): void {
    this.cargarCabecera();
  }

  volver(): void {
    this.router.navigate(['/inicio/portal-web']);
  }

  cargarCabecera(): void {
    this.cargandoCabecera = true;
    this.portalWebService.getFicha<MatriculaCabecera>('matricula-cabecera').subscribe({
      next: (res) => {
        const c = res.data.contenido;
        this.versionCabecera = res.data.version;
        this.cabecera.reset({ titulo: c?.titulo ?? '', bajada: c?.bajada ?? '', anio: c?.anio ?? null });
        this.figura = c?.figura ? res.data.imagenes[c.figura.imagenId] ?? null : null;
        this.figuraCambiada = false;
        this.cargandoCabecera = false;
      },
      error: (err) => {
        this.cargandoCabecera = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la cabecera de Matrícula.'));
      },
    });
  }

  alCambiarFigura(imagen: ImagenPortal | null): void {
    this.figura = imagen;
    this.figuraCambiada = true;
  }

  get cabeceraConCambios(): boolean {
    return this.cabecera.dirty || this.figuraCambiada;
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
    const anio = v.anio === null || (v.anio as unknown) === '' ? null : Number(v.anio);

    this.guardandoCabecera = true;
    this.portalWebService
      .guardarFicha<MatriculaCabecera>('matricula-cabecera', { titulo: v.titulo, bajada: v.bajada || null, anio, figura: this.figura?.id ?? null }, this.versionCabecera)
      .subscribe({
        next: (res) => {
          this.guardandoCabecera = false;
          this.versionCabecera = res.data.version;
          this.cabecera.markAsPristine();
          this.figuraCambiada = false;
          this.toastService.success('Cabecera guardada', 'El portal la mostrará en hasta un minuto.');
        },
        error: (err: HttpErrorResponse) => {
          this.guardandoCabecera = false;
          mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar la cabecera.');
        },
      });
  }
}
