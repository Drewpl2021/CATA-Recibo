import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { AreaService, ToastService, ConfirmService } from '../../../core/services';
import { Area, AreaPayload } from '../../../core/models';
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

/**
 * Plantilla de referencia para las pantallas de Configuración Base.
 * Cargos y Sedes siguen exactamente esta misma receta: PageHeader +
 * DataTable + FormModal + ConfirmService, sin CSS propio.
 */
@Component({
  selector: 'app-areas-list',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent,
  ],
  templateUrl: './areas-list.component.html',
})
export class AreasListComponent implements OnInit {
  private fb = inject(FormBuilder);
  private areaService = inject(AreaService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  areas: Area[] = [];
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
  areaEditando: Area | null = null;

  columnas: ColumnaTabla<Area>[] = [
    { campo: 'nombre', header: 'Nombre', ancho: '35%' },
    { campo: 'descripcion', header: 'Descripción' },
    columnaEstado<Area>(),
  ];

  /** Mismo catálogo activo/inactivo que Sedes. */
  estadoOpciones = ESTADO_CATALOGO_OPCIONES;

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(100)]],
    descripcion: ['', [Validators.maxLength(255)]],
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
    this.areaService
      .getPagina({ page: this.pagina, size: this.TAMANO_PAGINA, search: this.busqueda || undefined })
      .subscribe({
      next: (res) => {
        if (res.success) {
          this.areas = res.data.content;
          this.total = res.data.totalElements;
          this.activos = res.data.activos ?? 0;
          this.inactivos = res.data.inactivos ?? 0;
        }
        this.cargando = false;
      },
      error: (err) => {
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar las áreas.'));
        this.cargando = false;
      },
    });
  }

  nueva(): void {
    this.areaEditando = null;
    this.form.reset({ estado: ESTADO_CATALOGO_POR_DEFECTO });
    this.modalVisible = true;
  }

  editar(area: Area): void {
    this.areaEditando = area;
    this.form.patchValue({
      nombre: area.nombre,
      descripcion: area.descripcion ?? '',
      estado: area.estado ?? ESTADO_CATALOGO_POR_DEFECTO,
    });
    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.areaEditando = null;
    this.form.reset({ estado: ESTADO_CATALOGO_POR_DEFECTO });
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const payload = this.form.getRawValue() as AreaPayload;
    this.guardando = true;

    const peticion = this.areaEditando
      ? this.areaService.update(this.areaEditando.id, payload)
      : this.areaService.create(payload);

    peticion.subscribe({
      next: (res) => {
        this.guardando = false;
        if (res.success) {
          this.toastService.success(
            this.areaEditando ? 'Área actualizada' : 'Área creada',
            `"${res.data.nombre}" se guardó correctamente.`
          );
          this.cerrarModal();
          this.cargar();
        }
      },
      error: (err) => {
        this.guardando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo guardar el área.'));
      },
    });
  }

  eliminar(area: Area): void {
    this.confirmService.confirmarEliminar(`el área "${area.nombre}"`, () => {
      this.areaService.delete(area.id).subscribe({
        next: () => {
          this.toastService.success('Eliminada', `El área "${area.nombre}" fue eliminada.`);
          this.cargar();
        },
        error: (err) => {
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo eliminar el área.'));
        },
      });
    });
  }
}
