import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../../core/services';
import { mensajeErrorApi } from '../../../../core/utils';
import { EditorAccionComponent, accionDelGrupo, grupoDeAccion, llenarAccion } from '../../compartido/editor-accion/editor-accion.component';
import { mostrarErrorDelPanel } from '../../compartido/panel';
import { AccionPortal, CierrePortal } from '../../portal-web.models';
import { PortalWebService } from '../../portal-web.service';

/**
 * Inicio › Cierre (ficha `portada-cierre`, contrato 2.13): la última sección
 * de Inicio, que también cierra Nosotros, Proyectos y Logros. El título
 * completo y hasta dos botones: el dorado y el blanco.
 */
@Component({
  selector: 'app-panel-cierre',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, EditorAccionComponent],
  templateUrl: './panel-cierre.component.html',
})
export class PanelCierreComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  cargando = true;
  guardando = false;
  private version: string | null = null;

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
    this.cargando = true;
    this.portalWebService.getFicha<CierrePortal>('portada-cierre').subscribe({
      next: (res) => {
        const c = res.data.contenido;
        this.version = res.data.version;
        this.form.controls.titulo.reset(c?.titulo ?? '');
        llenarAccion(this.dorado, c?.acciones?.[0] ?? null);
        llenarAccion(this.blanco, c?.acciones?.[1] ?? null);
        this.form.markAsPristine();
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar el cierre de Inicio.'));
      },
    });
  }

  invalido(control: { invalid: boolean; touched: boolean } | null | undefined): boolean {
    return !!control && control.invalid && control.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    // Si solo lleva el blanco, pasa a ser el primero (el dorado): el portal los toma en orden.
    const acciones = [accionDelGrupo(this.dorado), accionDelGrupo(this.blanco)].filter((a): a is AccionPortal => a !== null);
    const datos: CierrePortal = { titulo: this.form.controls.titulo.value!.trim(), acciones };

    this.guardando = true;
    this.portalWebService.guardarFicha<CierrePortal>('portada-cierre', datos, this.version).subscribe({
      next: () => {
        this.guardando = false;
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
