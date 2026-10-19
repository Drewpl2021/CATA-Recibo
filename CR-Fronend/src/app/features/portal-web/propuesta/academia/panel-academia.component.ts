import { Component, EventEmitter, OnInit, Output, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../../core/services';
import { mensajeErrorApi } from '../../../../core/utils';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { enfocarPrimerError, mostrarErrorDelPanel } from '../../compartido/panel';
import { cuantos, ResumenDeSeccion } from '../../compartido/resumen-de-seccion';
import { AcademiaPortal, UniversidadPortal } from '../../portal-web.models';
import { PortalWebService } from '../../portal-web.service';
import { PanelCiclosComponent } from './panel-ciclos.component';
import { PanelUniversidadesComponent } from './panel-universidades.component';

/**
 * Propuesta Educativa › Academia preuniversitaria: su título, descripción y
 * aviso, las universidades y, debajo, los ciclos de la universidad elegida.
 * Los textos se leen arriba y se cambian en su modal.
 */
@Component({
  selector: 'app-panel-academia',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, FormModalComponent, PanelUniversidadesComponent, PanelCiclosComponent],
  templateUrl: './panel-academia.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelAcademiaComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  @ViewChild(PanelUniversidadesComponent) private panelUniversidades?: PanelUniversidadesComponent;

  /** Cómo está la sección, para el índice de la pantalla. */
  @Output() resumen = new EventEmitter<ResumenDeSeccion>();

  universidades: UniversidadPortal[] = [];
  universidadElegida: string | null = null;

  cargando = true;
  guardando = false;
  modalVisible = false;
  private version: string | null = null;
  /** Lo guardado: lo que se lee arriba. */
  guardada: AcademiaPortal | null = null;
  private universidadesCargadas = false;

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

  alCambiarUniversidades(lista: UniversidadPortal[]): void {
    this.universidades = lista;
    this.universidadesCargadas = true;
    this.resumir();
  }

  /** Las universidades publicadas; las que no tienen ciclos no se pueden publicar y suben a Pendientes. */
  private resumir(): void {
    if (this.cargando || !this.universidadesCargadas) return;
    if (!this.guardada) {
      this.resumen.emit({ estado: 'Sin textos: no se ve', aviso: true, pendientes: [{ cuantos: 1, texto: 'academia sin sus textos' }] });
      return;
    }
    const publicadas = this.universidades.filter((u) => u.estado === 'publicado').length;
    const sinCiclos = this.universidades.filter((u) => !u.ciclos).length;
    let estado = this.universidades.length ? cuantos(publicadas, 'universidad publicada', 'universidades publicadas') : 'Sin universidades';
    if (sinCiclos) estado += ` · ${sinCiclos} sin ciclos`;
    this.resumen.emit({
      estado,
      aviso: sinCiclos > 0,
      pendientes: sinCiclos ? [{ cuantos: sinCiclos, texto: sinCiclos === 1 ? 'universidad sin ciclos: no se puede publicar' : 'universidades sin ciclos: no se pueden publicar' }] : [],
    });
  }

  /** Cambió cuántos ciclos tiene una: su cuenta (y si se puede publicar) está en la tabla de universidades. */
  recargarUniversidades(): void {
    this.panelUniversidades?.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.portalWebService.getFicha<AcademiaPortal>('propuesta-academia').subscribe({
      next: (res) => {
        this.version = res.data.version;
        this.guardada = res.data.contenido ?? null;
        this.cargando = false;
        this.resumir();
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los textos de la academia.'));
      },
    });
  }

  /** El modal empieza con lo guardado: lo que se canceló antes no queda a medias. */
  abrir(): void {
    const c = this.guardada;
    this.form.reset({ titulo: c?.titulo ?? '', descripcion: c?.descripcion ?? '', aclaracion: c?.aclaracion ?? '' });
    this.modalVisible = true;
  }

  invalido(campo: 'titulo' | 'descripcion' | 'aclaracion'): boolean {
    const c = this.form.controls[campo];
    return c.invalid && c.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      enfocarPrimerError();
      return;
    }

    const v = this.form.getRawValue();
    const datos = { titulo: v.titulo!, descripcion: v.descripcion || null, aclaracion: v.aclaracion || null };
    this.guardando = true;
    this.portalWebService
      .guardarFicha<AcademiaPortal>('propuesta-academia', datos, this.version)
      .subscribe({
        next: (res) => {
          this.guardando = false;
          this.version = res.data.version;
          this.guardada = datos as AcademiaPortal;
          this.modalVisible = false;
          this.resumir();
          this.toastService.success('Academia guardada', 'El portal la mostrará en hasta un minuto.');
        },
        error: (err: HttpErrorResponse) => {
          this.guardando = false;
          mostrarErrorDelPanel(this.toastService, err, 'No se pudieron guardar los textos de la academia.');
        },
      });
  }
}
