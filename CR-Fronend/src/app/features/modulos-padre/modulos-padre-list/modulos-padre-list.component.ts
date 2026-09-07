import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { ModuloPadreService, ToastService, ConfirmService } from '../../../core/services';
import { ModuloPadreAdmin, ModuloPadrePayload } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { CLAVES_ICONO } from '../../../shared/icons/icon-map';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';

/**
 * Módulos padre = los GRUPOS de la barra lateral (solo Admin).
 *
 * No llevan ruta ni roles: son solo el encabezado que agrupa ítems. Quién
 * ve el grupo se decide en los módulos que cuelgan de él; si a un usuario
 * no le toca ninguno, el grupo entero no le aparece.
 *
 * El `orden` es el que manda en la barra lateral: menor número, más arriba.
 */
@Component({
  selector: 'app-modulos-padre-list',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent, IconComponent,
  ],
  templateUrl: './modulos-padre-list.component.html',
})
export class ModulosPadreListComponent implements OnInit {
  private fb = inject(FormBuilder);
  private moduloPadreService = inject(ModuloPadreService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  padres: ModuloPadreAdmin[] = [];
  cargando = false;

  /** Filas por página; el backend corta y cuenta, acá solo se pinta. */
  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  busqueda = '';
  /** Cuántos hay en total, según el backend — no el largo de la página. */
  total = 0;

  /**
   * Una sola cifra: esta tabla no maneja activos e inactivos. El listado ya
   * excluye lo dado de baja, así que un contador de inactivos diría 0 siempre.
   */
  get cifras(): CifraCabecera[] {
    return [{ icono: 'folder', valor: this.total, etiqueta: 'Grupos', tono: 'brand' }];
  }
  guardando = false;
  modalVisible = false;
  padreEditando: ModuloPadreAdmin | null = null;

  /** Las claves del catálogo de íconos, para el selector del formulario. */
  iconos = CLAVES_ICONO;

  columnas: ColumnaTabla<ModuloPadreAdmin>[] = [
    { campo: 'orden', header: 'Orden', ancho: '10%' },
    { campo: 'nombre', header: 'Grupo', ancho: '35%' },
    { campo: 'icono', header: 'Ícono', tipo: 'icono' },
  ];

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(100)]],
    icono: ['folder'],
    orden: [0, [Validators.required, Validators.min(0)]],
  });

  ngOnInit(): void {
    this.cargar();
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  /** Ícono elegido ahora mismo, para la vista previa del formulario. */
  get iconoElegido(): string {
    return this.form.get('icono')?.value ?? 'folder';
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
    this.moduloPadreService
      .getPagina({ page: this.pagina, size: this.TAMANO_PAGINA, search: this.busqueda || undefined })
      .subscribe({
      next: (res) => {
        if (res.success) {
          this.padres = res.data.content;
          this.total = res.data.totalElements;
        }
        this.cargando = false;
      },
      error: (err) => {
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los grupos.'));
        this.cargando = false;
      },
    });
  }

  nuevo(): void {
    this.padreEditando = null;
    // El siguiente en la lista, para no tener que pensar el número.
    const siguiente = this.padres.reduce((max, p) => Math.max(max, p.orden ?? 0), 0) + 1;
    this.form.reset({ icono: 'folder', orden: siguiente });
    this.modalVisible = true;
  }

  editar(padre: ModuloPadreAdmin): void {
    this.padreEditando = padre;
    this.form.patchValue({
      nombre: padre.nombre,
      icono: padre.icono ?? 'folder',
      orden: padre.orden ?? 0,
    });
    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.padreEditando = null;
    this.form.reset({ icono: 'folder', orden: 0 });
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const payload = this.form.getRawValue() as ModuloPadrePayload;
    this.guardando = true;

    const peticion = this.padreEditando
      ? this.moduloPadreService.update(this.padreEditando.id, payload)
      : this.moduloPadreService.create(payload);

    peticion.subscribe({
      next: (res) => {
        this.guardando = false;
        if (res.success) {
          this.toastService.success(
            this.padreEditando ? 'Grupo actualizado' : 'Grupo creado',
            `"${res.data.nombre}" se guardó correctamente.`
          );
          this.cerrarModal();
          this.cargar();
        }
      },
      error: (err) => {
        this.guardando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo guardar el grupo.'));
      },
    });
  }

  eliminar(padre: ModuloPadreAdmin): void {
    this.confirmService.confirmarEliminar(
      `el grupo "${padre.nombre}" y todos los módulos que cuelgan de él`,
      () => {
        this.moduloPadreService.delete(padre.id).subscribe({
          next: () => {
            this.toastService.success('Eliminado', `El grupo "${padre.nombre}" fue eliminado.`);
            this.cargar();
          },
          error: (err) => {
            this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo eliminar el grupo.'));
          },
        });
      }
    );
  }
}
