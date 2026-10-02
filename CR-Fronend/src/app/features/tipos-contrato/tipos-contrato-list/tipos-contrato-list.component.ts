import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { TipoContratoService, ToastService, ConfirmService } from '../../../core/services';
import { TipoContrato, TipoContratoPayload } from '../../../core/models';
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
import { IconComponent } from '../../../shared/components/icon/icon.component';

/**
 * Antes 'indeterminado', 'plazo_fijo', 'suplencia' y 'practicas' eran un
 * ENUM fijo: agregar o renombrar un tipo pedía tocar código y desplegar.
 * Ahora es un catálogo más, con los mismos dos interruptores que de verdad
 * cambian cómo se calcula: si pide fecha de fin, y si puede tomar
 * vacaciones reales (el resto cobra Vacaciones Truncas al terminar).
 */
@Component({
  selector: 'app-tipos-contrato-list',
  standalone: true,
  imports: [IconComponent,
    CommonModule, ReactiveFormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent,
  ],
  templateUrl: './tipos-contrato-list.component.html',
})
export class TiposContratoListComponent implements OnInit {
  private fb = inject(FormBuilder);
  private tipoContratoService = inject(TipoContratoService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  tipos: TipoContrato[] = [];
  cargando = false;

  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  busqueda = '';
  total = 0;
  activos = 0;
  inactivos = 0;

  get cifras(): CifraCabecera[] {
    return [
      { icono: 'layers', valor: this.total, etiqueta: 'Total', tono: 'brand' },
      { icono: 'check_circle', valor: this.activos, etiqueta: 'Activos', tono: 'success' },
      { icono: 'pause_circle', valor: this.inactivos, etiqueta: 'De baja', tono: 'muted' },
    ];
  }

  guardando = false;
  modalVisible = false;
  tipoEditando: TipoContrato | null = null;

  columnas: ColumnaTabla<TipoContrato>[] = [
    { campo: 'nombre', header: 'Nombre', ancho: '30%' },
    {
      campo: 'requiere_fecha_fin', header: 'Pide fecha de fin', ancho: '20%',
      formatear: (v) => (v ? 'Sí' : 'No'),
    },
    {
      campo: 'permite_vacaciones', header: 'Toma vacaciones reales', ancho: '24%',
      formatear: (v) => (v ? 'Sí' : 'No — cobra Vacaciones Truncas'),
    },
    columnaEstado<TipoContrato>(),
  ];

  estadoOpciones = ESTADO_CATALOGO_OPCIONES;

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(100)]],
    requiere_fecha_fin: [true],
    permite_vacaciones: [false],
    estado: [ESTADO_CATALOGO_POR_DEFECTO],
  });

  ngOnInit(): void {
    this.cargar();
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  irAPagina(pagina: number): void {
    this.pagina = pagina;
    this.cargar();
  }

  buscar(termino: string): void {
    this.busqueda = termino;
    this.pagina = 0;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.tipoContratoService
      .getPagina({ page: this.pagina, size: this.TAMANO_PAGINA, search: this.busqueda || undefined })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.tipos = res.data.content;
            this.total = res.data.totalElements;
            this.activos = res.data.activos ?? 0;
            this.inactivos = res.data.inactivos ?? 0;
          }
          this.cargando = false;
        },
        error: (err) => {
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los tipos de contrato.'));
          this.cargando = false;
        },
      });
  }

  nuevo(): void {
    this.tipoEditando = null;
    this.form.reset({ requiere_fecha_fin: true, permite_vacaciones: false, estado: ESTADO_CATALOGO_POR_DEFECTO });
    this.modalVisible = true;
  }

  editar(tipo: TipoContrato): void {
    this.tipoEditando = tipo;
    this.form.patchValue({
      nombre: tipo.nombre,
      requiere_fecha_fin: tipo.requiere_fecha_fin,
      permite_vacaciones: tipo.permite_vacaciones,
      estado: tipo.estado ?? ESTADO_CATALOGO_POR_DEFECTO,
    });
    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.tipoEditando = null;
    this.form.reset({ requiere_fecha_fin: true, permite_vacaciones: false, estado: ESTADO_CATALOGO_POR_DEFECTO });
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const payload = this.form.getRawValue() as TipoContratoPayload;
    this.guardando = true;

    const peticion = this.tipoEditando
      ? this.tipoContratoService.update(this.tipoEditando.id, payload)
      : this.tipoContratoService.create(payload);

    peticion.subscribe({
      next: (res) => {
        this.guardando = false;
        if (res.success) {
          this.toastService.success(
            this.tipoEditando ? 'Tipo de contrato actualizado' : 'Tipo de contrato creado',
            `"${res.data.nombre}" se guardó correctamente.`
          );
          this.cerrarModal();
          this.cargar();
        }
      },
      error: (err) => {
        this.guardando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo guardar el tipo de contrato.'));
      },
    });
  }

  eliminar(tipo: TipoContrato): void {
    this.confirmService.confirmarEliminar(`el tipo de contrato "${tipo.nombre}"`, () => {
      this.tipoContratoService.delete(tipo.id).subscribe({
        next: () => {
          this.toastService.success('Eliminado', `"${tipo.nombre}" fue eliminado.`);
          this.cargar();
        },
        error: (err) => {
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo eliminar. Si ya está en uso, desactívalo en vez de borrarlo.'));
        },
      });
    });
  }
}
