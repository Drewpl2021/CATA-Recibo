import { Component, OnInit, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../../core/services';
import { mensajeErrorApi } from '../../../../core/utils';
import { mostrarErrorDelPanel } from '../../compartido/panel';
import { AcademiaPortal, UniversidadPortal } from '../../portal-web.models';
import { PortalWebService } from '../../portal-web.service';
import { PanelCiclosComponent } from './panel-ciclos.component';
import { PanelUniversidadesComponent } from './panel-universidades.component';

/**
 * Propuesta Educativa › Academia preuniversitaria: su título, descripción y
 * aviso, las universidades y, debajo, los ciclos de la universidad elegida.
 */
@Component({
  selector: 'app-panel-academia',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, PanelUniversidadesComponent, PanelCiclosComponent],
  templateUrl: './panel-academia.component.html',
})
export class PanelAcademiaComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  @ViewChild(PanelUniversidadesComponent) private panelUniversidades?: PanelUniversidadesComponent;

  universidades: UniversidadPortal[] = [];
  universidadElegida: string | null = null;

  cargando = true;
  guardando = false;
  private version: string | null = null;

  form = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    descripcion: ['', [Validators.maxLength(240)]],
    aclaracion: ['', [Validators.maxLength(80)]],
  });

  ngOnInit(): void {
    this.cargar();
  }

  /** Desde la fila de una universidad: sus ciclos, a la vista. */
  verCiclos(u: UniversidadPortal): void {
    this.universidadElegida = u.id;
    document.getElementById('ciclos-de-la-academia')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /** Cambió cuántos ciclos tiene una: su cuenta (y si se puede publicar) está en la tabla de universidades. */
  recargarUniversidades(): void {
    this.panelUniversidades?.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.portalWebService.getFicha<AcademiaPortal>('propuesta-academia').subscribe({
      next: (res) => {
        const c = res.data.contenido;
        this.version = res.data.version;
        this.form.reset({ titulo: c?.titulo ?? '', descripcion: c?.descripcion ?? '', aclaracion: c?.aclaracion ?? '' });
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los textos de la academia.'));
      },
    });
  }

  invalido(campo: 'titulo' | 'descripcion' | 'aclaracion'): boolean {
    const c = this.form.controls[campo];
    return c.invalid && c.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    this.guardando = true;
    this.portalWebService
      .guardarFicha<AcademiaPortal>('propuesta-academia', { titulo: v.titulo, descripcion: v.descripcion || null, aclaracion: v.aclaracion || null }, this.version)
      .subscribe({
        next: (res) => {
          this.guardando = false;
          this.version = res.data.version;
          this.form.markAsPristine();
          this.toastService.success('Academia guardada', 'El portal la mostrará en hasta un minuto.');
        },
        error: (err: HttpErrorResponse) => {
          this.guardando = false;
          mostrarErrorDelPanel(this.toastService, err, 'No se pudieron guardar los textos de la academia.');
        },
      });
  }
}
