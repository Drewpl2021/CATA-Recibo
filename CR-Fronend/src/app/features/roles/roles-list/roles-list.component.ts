import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { RolService, ToastService, ConfirmService } from '../../../core/services';
import { Rol } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { esRolDelSistema, etiquetaRol } from '../../../shared/constants';

/**
 * Roles del sistema (solo Admin).
 *
 * El rol es la pieza de la que cuelga todo lo demás: un usuario tiene un
 * rol, y ese rol decide qué módulos ve en la barra lateral (pantalla de
 * Módulos) y a qué endpoints llega (middleware `rol:` del backend).
 *
 * Borrar un rol es DEFINITIVO — no tiene baja lógica como los módulos — y
 * el backend lo rechaza si todavía hay usuarios usándolo.
 */
@Component({
  selector: 'app-roles-list',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent,
  ],
  templateUrl: './roles-list.component.html',
})
export class RolesListComponent implements OnInit {
  private fb = inject(FormBuilder);
  private rolService = inject(RolService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  roles: Rol[] = [];
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
    return [{ icono: 'shield', valor: this.total, etiqueta: 'Roles', tono: 'brand' }];
  }
  guardando = false;
  modalVisible = false;
  rolEditando: Rol | null = null;

  columnas: ColumnaTabla<Rol>[] = [
    // Se pinta la etiqueta, no el nombre guardado: la tabla dice "RRHH"
    // aunque en la base ponga "rrhh", igual que en el resto de la app.
    { campo: 'nombre', header: 'Nombre', ancho: '24%', formatear: (v) => etiquetaRol(v) },
    { campo: 'descripcion', header: 'Descripción' },
    {
      campo: 'nombre',
      header: 'Tipo',
      ancho: '16%',
      tipo: 'badge',
      formatear: (v) => (esRolDelSistema(v) ? 'Del sistema' : 'Creado acá'),
      badgeSeveridad: (v) => (esRolDelSistema(v) ? 'info' : 'secondary'),
    },
  ];

  /** Al rol del sistema que se está editando no se le toca el nombre. */
  get editandoRolDelSistema(): boolean {
    return !!this.rolEditando && esRolDelSistema(this.rolEditando.nombre);
  }

  /** Cómo se lee el rol que se está editando: "RRHH", no "rrhh". */
  get etiquetaRolEditando(): string {
    return this.rolEditando ? etiquetaRol(this.rolEditando) : '';
  }

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(45)]],
    descripcion: ['', [Validators.maxLength(255)]],
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
    this.rolService
      .getPagina({ page: this.pagina, size: this.TAMANO_PAGINA, search: this.busqueda || undefined })
      .subscribe({
      next: (res) => {
        if (res.success) {
          this.roles = res.data.content;
          this.total = res.data.totalElements;
        }
        this.cargando = false;
      },
      error: (err) => {
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los roles.'));
        this.cargando = false;
      },
    });
  }

  nuevo(): void {
    this.rolEditando = null;
    this.form.reset();
    this.form.get('nombre')!.enable();
    this.modalVisible = true;
  }

  editar(rol: Rol): void {
    this.rolEditando = rol;
    this.form.patchValue({ nombre: rol.nombre, descripcion: rol.descripcion ?? '' });

    // El nombre de admin/rrhh/empleado es la llave con la que el backend
    // decide permisos, no una etiqueta: se enseña pero no se deja tocar.
    // El backend lo rechaza igual; esto es para que no se intente.
    const campoNombre = this.form.get('nombre')!;
    if (esRolDelSistema(rol.nombre)) {
      campoNombre.disable();
    } else {
      campoNombre.enable();
    }

    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.rolEditando = null;
    this.form.reset();
    this.form.get('nombre')!.enable();
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const payload = this.form.getRawValue() as Partial<Rol>;
    this.guardando = true;

    const peticion = this.rolEditando
      ? this.rolService.update(this.rolEditando.id, payload)
      : this.rolService.create(payload);

    peticion.subscribe({
      next: (res) => {
        this.guardando = false;
        if (res.success) {
          this.toastService.success(
            this.rolEditando ? 'Rol actualizado' : 'Rol creado',
            `"${res.data.nombre}" se guardó correctamente.`
          );
          this.cerrarModal();
          this.cargar();
        }
      },
      error: (err) => {
        this.guardando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo guardar el rol.'));
      },
    });
  }

  eliminar(rol: Rol): void {
    // Se avisa antes de preguntar. El backend lo rechaza igual, pero hacer
    // confirmar un borrado que no va a ocurrir es hacerle perder el tiempo.
    if (esRolDelSistema(rol.nombre)) {
      this.toastService.warning(
        'Es un rol del sistema',
        `"${etiquetaRol(rol)}" no se puede eliminar: los permisos de todas las pantallas dependen de él.`
      );
      return;
    }

    this.confirmService.confirmarEliminar(
      `el rol "${etiquetaRol(rol)}". Los usuarios que lo tengan asignado se quedarán sin permisos`,
      () => {
        this.rolService.delete(rol.id).subscribe({
          next: () => {
            this.toastService.success('Eliminado', `El rol "${etiquetaRol(rol)}" fue eliminado.`);
            this.cargar();
          },
          error: (err) => {
            this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo eliminar el rol.'));
          },
        });
      }
    );
  }
}
