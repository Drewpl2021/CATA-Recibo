import { Component, EventEmitter, OnInit, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../../core/services';
import { mensajeErrorApi } from '../../../../core/utils';
import { EditorAccionComponent, accionDelGrupo, grupoDeAccion, llenarAccion } from '../../compartido/editor-accion/editor-accion.component';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { CuerpoDesplazableComponent } from '../../compartido/cuerpo-desplazable/cuerpo-desplazable.component';
import { mostrarErrorDelPanel } from '../../compartido/panel';
import { ResumenDeSeccion } from '../../compartido/resumen-de-seccion';
import { AccionPortal, CierrePortal } from '../../portal-web.models';
import { PortalWebService } from '../../portal-web.service';

/**
 * Inicio › Cierre (ficha `portada-cierre`, contrato 2.13): la última sección
 * de Inicio, que también cierra Nosotros, Proyectos y Logros. El título
 * completo y hasta dos botones: el dorado y el blanco.
 *
 * La pantalla muestra cómo se ve, con los colores del portal, y se cambia en
 * un modal. Sin ficha guardada, el portal responde «no disponible».
 */
@Component({
  selector: 'app-panel-cierre',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, FormModalComponent, EditorAccionComponent, CuerpoDesplazableComponent],
  templateUrl: './panel-cierre.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelCierreComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  /** Cómo está la sección, para el índice de Inicio. */
  @Output() resumen = new EventEmitter<ResumenDeSeccion>();

  cargando = true;
  guardando = false;
  modalVisible = false;
  private version: string | null = null;
  /** Lo guardado: lo que se ve en la vista previa. */
  guardado: CierrePortal | null = null;

  form = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    dorado: grupoDeAccion(this.fb),
    blanco: grupoDeAccion(this.fb),
  });

  get dorado(): FormGroup {
    return this.form.controls.dorado;
  }

  get blanco(): FormGroup {
    return this.form.controls.blanco;
  }

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando = !this.guardado; // al recargar tras guardar, la vista previa se queda
    this.portalWebService.getFicha<CierrePortal>('portada-cierre').subscribe({
      next: (res) => {
        this.version = res.data.version;
        this.guardado = res.data.contenido ?? null;
        this.cargando = false;
        this.resumir();
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar el cierre de Inicio.'));
      },
    });
  }

  private resumir(): void {
    const n = this.guardado?.acciones.length ?? 0;
    this.resumen.emit(
      this.guardado
        ? { estado: n ? `Título y ${n === 1 ? '1 botón' : `${n} botones`}` : 'Solo el título', aviso: false, pendientes: [] }
        : { estado: 'Sin configurar: no se ve', aviso: true, pendientes: [{ cuantos: 1, texto: 'sección sin configurar: Cierre' }] },
    );
  }

  /** El modal empieza con lo guardado: lo que se cancela no queda a medias. */
  abrir(): void {
    const c = this.guardado;
    this.form.controls.titulo.reset(c?.titulo ?? '');
    llenarAccion(this.dorado, c?.acciones?.[0] ?? null);
    llenarAccion(this.blanco, c?.acciones?.[1] ?? null);
    this.form.markAsPristine();
    this.modalVisible = true;
  }

  invalido(control: { invalid: boolean; touched: boolean } | null | undefined): boolean {
    return !!control && control.invalid && control.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      setTimeout(() => document.querySelector<HTMLElement>('.modal-container .is-invalid')?.focus());
      return;
    }

    // Si solo lleva el blanco, pasa a ser el primero (el dorado): el portal los toma en orden.
    const acciones = [accionDelGrupo(this.dorado), accionDelGrupo(this.blanco)].filter((a): a is AccionPortal => a !== null);
    const datos: CierrePortal = { titulo: this.form.controls.titulo.value!.trim(), acciones };

    this.guardando = true;
    this.portalWebService.guardarFicha<CierrePortal>('portada-cierre', datos, this.version).subscribe({
      next: () => {
        this.guardando = false;
        this.modalVisible = false;
        this.toastService.success('Cierre guardado', 'El portal lo mostrará en hasta un minuto.');
        this.cargar();
      },
      error: (err: HttpErrorResponse) => {
        this.guardando = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar el cierre de Inicio.');
      },
    });
  }
}
