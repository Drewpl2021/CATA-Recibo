import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { SedeService, ToastService, ConfirmService } from '../../../core/services';
import { Sede, SedePayload } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import {
  ESTADO_CATALOGO_OPCIONES,
  ESTADO_CATALOGO_POR_DEFECTO,
  columnaEstado,
} from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-sedes-list',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent,
  ],
  templateUrl: './sedes-list.component.html',
})
export class SedesListComponent implements OnInit {
  private fb = inject(FormBuilder);
  private sedeService = inject(SedeService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  sedes: Sede[] = [];
  cargando = false;

  /** Filas por página; el backend corta y cuenta, acá solo se pinta. */
  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  busqueda = '';
  /** Cuántos hay en total, según el backend — no el largo de la página. */
  total = 0;
  /** Los conteos que manda el backend junto a la página. */
  activos = 0;
  inactivos = 0;

  /** Lo que pinta la cabecera: total, en uso y dados de baja. */
  get cifras(): CifraCabecera[] {
    return [
      { icono: 'layers', valor: this.total, etiqueta: 'Total', tono: 'brand' },
      { icono: 'check_circle', valor: this.activos, etiqueta: 'Activas', tono: 'success' },
      { icono: 'pause_circle', valor: this.inactivos, etiqueta: 'De baja', tono: 'muted' },
    ];
  }
  guardando = false;
  modalVisible = false;
  sedeEditando: Sede | null = null;

  /** Mismo catálogo activo/inactivo que Áreas y Cargos. */
  estadoOpciones = ESTADO_CATALOGO_OPCIONES;

  columnas: ColumnaTabla<Sede>[] = [
    { campo: 'nombre', header: 'Nombre', ancho: '25%' },
    { campo: 'direccion', header: 'Dirección' },
    { campo: 'telefono', header: 'Teléfono', ancho: '15%' },
    columnaEstado<Sede>(),
  ];

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(100)]],
    direccion: ['', [Validators.maxLength(255)]],
    telefono: ['', [Validators.maxLength(15)]],
    estado: [ESTADO_CATALOGO_POR_DEFECTO],
  });

  ngOnInit(): void {
    this.cargar();
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  /** El usuario pidió otra página. */
  irAPagina(pagina: number): void {
    this.pagina = pagina;
    this.cargar();
  }

  /** Búsqueda contra el backend; llega ya con el retardo aplicado. */
  buscar(termino: string): void {
    this.busqueda = termino;
    this.pagina = 0;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.sedeService
      .getPagina({ page: this.pagina, size: this.TAMANO_PAGINA, search: this.busqueda || undefined })
      .subscribe({
      next: (res) => {
        if (res.success) {
          this.sedes = res.data.content;
          this.total = res.data.totalElements;
          this.activos = res.data.activos ?? 0;
          this.inactivos = res.data.inactivos ?? 0;
        }
        this.cargando = false;
      },
      error: (err) => {
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar las sedes.'));
        this.cargando = false;
      },
    });
  }

  nueva(): void {
    this.sedeEditando = null;
    this.form.reset({ estado: ESTADO_CATALOGO_POR_DEFECTO });
    this.modalVisible = true;
  }

  editar(sede: Sede): void {
    this.sedeEditando = sede;
    this.form.patchValue({
      nombre: sede.nombre,
      direccion: sede.direccion ?? '',
      telefono: sede.telefono ?? '',
      estado: sede.estado ?? ESTADO_CATALOGO_POR_DEFECTO,
    });
    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.sedeEditando = null;
    this.form.reset({ estado: ESTADO_CATALOGO_POR_DEFECTO });
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const payload = this.form.getRawValue() as SedePayload;
    this.guardando = true;

    const peticion = this.sedeEditando
      ? this.sedeService.update(this.sedeEditando.id, payload)
      : this.sedeService.create(payload);

    peticion.subscribe({
      next: (res) => {
        this.guardando = false;
        if (res.success) {
          this.toastService.success(
            this.sedeEditando ? 'Sede actualizada' : 'Sede creada',
            `"${res.data.nombre}" se guardó correctamente.`
          );
          this.cerrarModal();
          this.cargar();
        }
      },
      error: (err) => {
        this.guardando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo guardar la sede.'));
      },
    });
  }

  eliminar(sede: Sede): void {
    this.confirmService.confirmarEliminar(`la sede "${sede.nombre}"`, () => {
      this.sedeService.delete(sede.id).subscribe({
        next: () => {
          this.toastService.success('Eliminada', `La sede "${sede.nombre}" fue eliminada.`);
          this.cargar();
        },
        error: (err) => {
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo eliminar la sede.'));
        },
      });
    });
  }
}
