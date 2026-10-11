import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { mensajeErrorApi } from '../../../../core/utils';
import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { mostrarErrorDelPanel } from '../../compartido/panel';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { SelectorImagenComponent } from '../../compartido/selector-imagen/selector-imagen.component';
import { EstadoPortal, ImagenPortal, NivelPortal, SeccionNiveles } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

/**
 * Inicio › Niveles: el título de la sección, el enlace a la propuesta
 * educativa y hasta 4 niveles con su foto 3:2.
 *
 * Son los de Inicio: Propuesta Educativa tiene su propia lista de niveles.
 */
@Component({
  selector: 'app-panel-niveles',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent, SelectorImagenComponent],
  templateUrl: './panel-niveles.component.html',
})
export class PanelNivelesComponent extends PanelDeLista<NivelPortal> implements OnInit {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.niveles;
  protected readonly nombre = { singular: 'nivel', femenino: false };

  foto: ImagenPortal | null = null;

  columnas: ColumnaTabla<NivelPortal>[] = [
    this.columnaDeOrden(),
    { campo: 'nombre', header: 'Nivel', ancho: '16%' },
    { campo: 'rango', header: 'Rango', formatear: (r) => r ?? '—' },
    { campo: 'sede', header: 'Sede', formatear: (s) => s ?? '—' },
    { campo: 'imagen_id', header: 'Foto', ancho: '8%', formatear: (f) => (f ? 'Sí' : 'No') },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(24)]],
    rango: ['', [Validators.maxLength(40)]],
    sede: ['', [Validators.maxLength(60)]],
    reconocimiento: ['', [Validators.maxLength(60)]],
    estado: ['borrador' as EstadoPortal],
  });

  // ───────── La ficha: título y enlace ─────────

  cargandoSeccion = true;
  guardandoSeccion = false;
  private versionSeccion: string | null = null;

  seccion = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    propuestaUrl: ['', [Validators.pattern(/^https:\/\/\S+$/)]],
  });

  override ngOnInit(): void {
    super.ngOnInit();
    this.cargarSeccion();
  }

  protected describir(n: NivelPortal): string {
    return `el nivel «${n.nombre}» de Inicio`;
  }

  nuevo(): void {
    this.editando = null;
    this.foto = null;
    this.form.reset({ nombre: '', rango: '', sede: '', reconocimiento: '', estado: 'borrador' });
    this.modalVisible = true;
  }

  editar(n: NivelPortal): void {
    this.editando = n;
    this.foto = n.imagen;
    this.form.reset({ nombre: n.nombre, rango: n.rango ?? '', sede: n.sede ?? '', reconocimiento: n.reconocimiento ?? '', estado: n.estado });
    this.modalVisible = true;
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  invalidoSeccion(campo: string): boolean {
    const c = this.seccion.get(campo);
    return !!c && c.invalid && c.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    this.enviar({
      nombre: v.nombre, rango: v.rango || null, sede: v.sede || null, reconocimiento: v.reconocimiento || null,
      imagen_id: this.foto?.id ?? null, estado: v.estado,
    });
  }

  // ───────── La ficha ─────────

  cargarSeccion(): void {
    this.cargandoSeccion = true;
    this.portalWebService.getFicha<SeccionNiveles>('niveles').subscribe({
      next: (res) => {
        this.versionSeccion = res.data.version;
        this.seccion.reset({ titulo: res.data.contenido?.titulo ?? '', propuestaUrl: res.data.contenido?.propuestaUrl ?? '' });
        this.cargandoSeccion = false;
      },
      error: (err) => {
        this.cargandoSeccion = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la sección Niveles.'));
      },
    });
  }

  guardarSeccion(): void {
    if (this.seccion.invalid) {
      this.seccion.markAllAsTouched();
      return;
    }

    const v = this.seccion.getRawValue();
    this.guardandoSeccion = true;
    this.portalWebService.guardarFicha<SeccionNiveles>('niveles', { titulo: v.titulo, propuestaUrl: v.propuestaUrl || null }, this.versionSeccion).subscribe({
      next: (res) => {
        this.guardandoSeccion = false;
        this.versionSeccion = res.data.version;
        this.seccion.markAsPristine();
        this.toastService.success('Sección guardada', 'El portal la mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoSeccion = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar la sección Niveles.');
      },
    });
  }
}
