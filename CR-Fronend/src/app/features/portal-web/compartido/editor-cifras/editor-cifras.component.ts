import { Component, Input, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ConfirmService, ToastService } from '../../../../core/services';
import { mensajeErrorApi } from '../../../../core/utils';
import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { AmbitoDeCifras, CifraPortal, EstadoPortal } from '../../portal-web.models';
import { PortalWebService, RECURSOS } from '../../portal-web.service';
import { idsMovidos, mostrarErrorDelPanel } from '../panel';

/**
 * Las cifras destacadas de una página del portal (Inicio, Logros o Propuesta
 * Educativa): un número, su signo y qué cuenta. Hasta 4 publicadas por
 * página, en el orden en que se muestran.
 *
 *   <app-editor-cifras ambito="logros"></app-editor-cifras>
 *
 * Es un panel entero (cabecera, tabla y modal), con las mismas piezas que
 * las demás listas del panel.
 */
@Component({
  selector: 'app-editor-cifras',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent],
  templateUrl: './editor-cifras.component.html',
})
export class EditorCifrasComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  @Input({ required: true }) ambito!: AmbitoDeCifras;
  /** Dónde se ven, para la ayuda: «bajo el carrusel de Inicio». */
  @Input() dondeSeVen = '';

  readonly id = 'cifras-' + Math.random().toString(36).slice(2, 8);

  cifras: CifraPortal[] = [];
  cargando = true;
  ordenando = false;
  modalVisible = false;
  guardando = false;
  editando: CifraPortal | null = null;

  columnas: ColumnaTabla<CifraPortal>[] = [
    { campo: 'orden', header: 'Orden', ancho: '120px' },
    { campo: 'valor', header: 'Cifra', ancho: '16%', formatear: (v, c) => `${v}${c.sufijo ?? ''}` },
    { campo: 'etiqueta', header: 'Qué cuenta' },
    {
      campo: 'estado', header: 'Estado', tipo: 'badge', ancho: '12%',
      formatear: (v) => (v === 'publicado' ? 'Publicada' : 'Borrador'),
      badgeSeveridad: (v) => (v === 'publicado' ? 'success' : 'secondary'),
    },
  ];

  acciones: AccionPersonalizada<CifraPortal>[] = [
    { id: 'publicar', titulo: 'Publicar: se verá en el portal', icono: 'eye', severidad: 'success', visible: (c) => c.estado === 'borrador' },
    { id: 'ocultar', titulo: 'Pasar a borrador: deja de verse en el portal', icono: 'eye_off', visible: (c) => c.estado === 'publicado' },
  ];

  form = this.fb.group({
    valor: [null as number | null, [Validators.required, Validators.min(0), Validators.max(999999), Validators.pattern(/^\d+$/)]],
    sufijo: ['', [Validators.maxLength(3)]],
    etiqueta: ['', [Validators.required, Validators.maxLength(60)]],
    estado: ['borrador' as EstadoPortal],
  });

  ngOnInit(): void {
    this.cargar();
  }

  get publicadas(): number {
    return this.cifras.filter((c) => c.estado === 'publicado').length;
  }

  cargar(): void {
    this.cargando = true;
    this.portalWebService.getLista<CifraPortal>(RECURSOS.cifras, { ambito: this.ambito }).subscribe({
      next: (res) => {
        this.cifras = res.data;
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar las cifras.'));
      },
    });
  }

  nueva(): void {
    this.editando = null;
    this.form.reset({ valor: null, sufijo: '', etiqueta: '', estado: 'borrador' });
    this.modalVisible = true;
  }

  editar(cifra: CifraPortal): void {
    this.editando = cifra;
    this.form.reset({ valor: cifra.valor, sufijo: cifra.sufijo ?? '', etiqueta: cifra.etiqueta, estado: cifra.estado });
    this.modalVisible = true;
  }

  cerrar(): void {
    this.modalVisible = false;
    this.editando = null;
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    const datos = { valor: Number(v.valor), sufijo: v.sufijo || null, etiqueta: v.etiqueta, estado: v.estado };

    this.guardando = true;
    const peticion = this.editando
      ? this.portalWebService.editarEnLista<CifraPortal>(RECURSOS.cifras, this.editando.id, datos)
      : this.portalWebService.crearEnLista<CifraPortal>(RECURSOS.cifras, { ...datos, ambito: this.ambito });

    peticion.subscribe({
      next: (res) => {
        this.guardando = false;
        this.toastService.success(
          this.editando ? 'Cifra actualizada' : 'Cifra agregada',
          res.data.estado === 'publicado' ? 'El portal la mostrará en hasta un minuto.' : 'Quedó en borrador: publícala cuando esté lista.',
        );
        this.cerrar();
        this.cargar();
      },
      error: (err: HttpErrorResponse) => {
        this.guardando = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar la cifra.');
      },
    });
  }

  alAccionar(evento: { accion: string; fila: CifraPortal }): void {
    const estado: EstadoPortal = evento.accion === 'publicar' ? 'publicado' : 'borrador';

    this.portalWebService.cambiarEstadoEnLista<CifraPortal>(RECURSOS.cifras, evento.fila.id, estado).subscribe({
      next: () => {
        this.toastService.success(estado === 'publicado' ? 'Cifra publicada' : 'Cifra en borrador', 'El portal lo reflejará en hasta un minuto.');
        this.cargar();
      },
      error: (err: HttpErrorResponse) => mostrarErrorDelPanel(this.toastService, err, 'No se pudo cambiar el estado de la cifra.'),
    });
  }

  eliminar(cifra: CifraPortal): void {
    this.confirmService.confirmarEliminar(`la cifra «${cifra.valor}${cifra.sufijo ?? ''} ${cifra.etiqueta}»`, () => {
      this.portalWebService.eliminarDeLista(RECURSOS.cifras, cifra.id).subscribe({
        next: () => {
          this.toastService.success('Cifra eliminada', 'Ya no aparecerá en el portal.');
          this.cargar();
        },
        error: (err: HttpErrorResponse) => mostrarErrorDelPanel(this.toastService, err, 'No se pudo eliminar la cifra.'),
      });
    });
  }

  mover(cifra: CifraPortal, direccion: -1 | 1): void {
    const ids = idsMovidos(this.cifras.map((c) => c.id), cifra.id, direccion);
    if (!ids) return;

    this.ordenando = true;
    this.portalWebService.ordenarLista<CifraPortal>(RECURSOS.cifras, ids, { ambito: this.ambito }).subscribe({
      next: (res) => {
        this.cifras = res.data;
        this.ordenando = false;
      },
      error: (err: HttpErrorResponse) => {
        this.ordenando = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo cambiar el orden.');
        this.cargar();
      },
    });
  }

  esPrimera(c: CifraPortal): boolean {
    return this.cifras[0]?.id === c.id;
  }

  esUltima(c: CifraPortal): boolean {
    return this.cifras[this.cifras.length - 1]?.id === c.id;
  }
}
