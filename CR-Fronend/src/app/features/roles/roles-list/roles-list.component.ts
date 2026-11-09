import { Component, OnInit, inject } from '@angular/core';

import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { forkJoin } from 'rxjs';

import { ModuloService, RolService, ToastService, ConfirmService } from '../../../core/services';
import { ModuloAdmin, Rol } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { PanelLateralComponent } from '../../../shared/components/panel-lateral/panel-lateral.component';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { esRolDelSistema, etiquetaRol } from '../../../shared/constants';
import { IconComponent } from '../../../shared/components/icon/icon.component';

/** Un módulo en el panel de permisos, con su interruptor. */
interface PermisoDeModulo {
  modulo: ModuloAdmin;
  activo: boolean;
  guardando: boolean;
  /** No se puede quitar: el Administrador lo necesita para corregir los permisos. */
  fijo: boolean;
}

interface GrupoDePermisos {
  id: string;
  nombre: string;
  permisos: PermisoDeModulo[];
}

/**
 * Roles del sistema (solo Admin).
 *
 * El rol es la pieza de la que cuelga todo lo demás: un usuario tiene un
 * rol, y los módulos de ese rol son a la vez su menú y sus permisos reales. Se
 * dan y se quitan aquí con un interruptor, y cada cambio se guarda al momento.
 *
 * Borrar un rol es DEFINITIVO — no tiene baja lógica como los módulos — y
 * el backend lo rechaza si todavía hay usuarios usándolo.
 */
@Component({
  selector: 'app-roles-list',
  standalone: true,
  imports: [IconComponent, ReactiveFormsModule, PageHeaderComponent, DataTableComponent, FormModalComponent, PanelLateralComponent],
  templateUrl: './roles-list.component.html',
})
export class RolesListComponent implements OnInit {
  private fb = inject(FormBuilder);
  private rolService = inject(RolService);
  private moduloService = inject(ModuloService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  roles: Rol[] = [];
  cargando = false;

  /** Todos los módulos, con los roles que tiene cada uno. */
  private modulos: ModuloAdmin[] = [];

  readonly accionesExtra: AccionPersonalizada<Rol>[] = [
    { id: 'permisos', titulo: 'Elegir sus módulos y permisos', icono: 'view_module' },
  ];

  panelAbierto = false;
  rolDelPanel: Rol | null = null;
  gruposDePermisos: GrupoDePermisos[] = [];
  cuentaDelPanel = 0;
  etiquetaDelPanel = '';

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
    { campo: 'id', header: 'Módulos', ancho: '10%', formatear: (_v, fila) => (fila.es_superadmin ? 'Todos' : String(this.cuantosModulos(fila))) },
    { campo: 'usuarios_count', header: 'Cuentas activas', ancho: '12%', formatear: (v) => String(v ?? 0) },
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
    this.cargarModulos();
  }

  private cargarModulos(): void {
    this.moduloService.getAll().subscribe({
      next: (res) => {
        if (!res.success) return;
        this.modulos = res.data;
        // Un arreglo nuevo para que la tabla vuelva a pintar la cuenta de módulos.
        this.roles = [...this.roles];
      },
      error: (err) => this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los módulos.')),
    });
  }

  private cuantosModulos(rol: Rol): number {
    return this.modulos.filter((m) => m.roles?.some((r) => r.id === rol.id)).length;
  }

  alAccionar(evento: { accion: string; fila: Rol }): void {
    if (evento.accion === 'permisos') this.abrirPermisos(evento.fila);
  }

  abrirPermisos(rol: Rol): void {
    this.rolDelPanel = rol;
    this.etiquetaDelPanel = etiquetaRol(rol);
    const grupos = new Map<string, GrupoDePermisos>();

    [...this.modulos]
      .sort((a, b) => (a.modulo_padre?.orden ?? 0) - (b.modulo_padre?.orden ?? 0) || a.orden - b.orden)
      .forEach((modulo) => {
        const grupo = grupos.get(modulo.modulo_padre_id)
          ?? { id: modulo.modulo_padre_id, nombre: modulo.modulo_padre?.nombre ?? 'Sin grupo', permisos: [] };
        grupos.set(modulo.modulo_padre_id, grupo);
        grupo.permisos.push({
          modulo,
          activo: !!modulo.roles?.some((r) => r.id === rol.id),
          guardando: false,
          fijo: !!rol.es_superadmin && ['/roles', '/modulos'].includes(modulo.ruta ?? ''),
        });
      });

    this.gruposDePermisos = [...grupos.values()];
    this.actualizarCuentaDelPanel();
    this.panelAbierto = true;
  }

  private actualizarCuentaDelPanel(): void {
    this.cuentaDelPanel = this.gruposDePermisos.reduce((suma, g) => suma + g.permisos.filter((p) => p.activo).length, 0);
  }

  /** Se ve al instante; si el servidor lo rechaza, vuelve como estaba y dice por qué. */
  cambiarPermiso(permiso: PermisoDeModulo, activo: boolean): void {
    const rol = this.rolDelPanel;
    if (!rol || permiso.guardando || permiso.fijo) return;

    permiso.activo = activo;
    permiso.guardando = true;
    this.actualizarCuentaDelPanel();

    this.rolService.cambiarModulo(rol.id, permiso.modulo.id, activo).subscribe({
      next: () => {
        permiso.guardando = false;
        this.anotarEnElModulo(permiso.modulo, rol, activo);
      },
      error: (err) => {
        permiso.guardando = false;
        permiso.activo = !activo;
        this.actualizarCuentaDelPanel();
        this.toastService.error('No se guardó', mensajeErrorApi(err, 'No se pudo cambiar el permiso.'));
      },
    });
  }

  /** Marca o desmarca todo el grupo, sin tocar lo que ya está así ni lo fijo. */
  cambiarGrupo(grupo: GrupoDePermisos, activo: boolean): void {
    const rol = this.rolDelPanel;
    if (!rol) return;
    const pendientes = grupo.permisos.filter((p) => p.activo !== activo && !p.guardando && !p.fijo);
    if (!pendientes.length) return;

    pendientes.forEach((p) => { p.activo = activo; p.guardando = true; });
    this.actualizarCuentaDelPanel();

    forkJoin(pendientes.map((p) => this.rolService.cambiarModulo(rol.id, p.modulo.id, activo))).subscribe({
      next: () => {
        pendientes.forEach((p) => { p.guardando = false; this.anotarEnElModulo(p.modulo, rol, activo); });
      },
      error: (err) => {
        this.toastService.error('Revisa el grupo', mensajeErrorApi(err, 'No se pudieron cambiar todos los permisos.'));
        // Alguno pudo guardarse: lo cierto es lo que diga el servidor.
        this.moduloService.getAll().subscribe((res) => {
          if (res.success) this.modulos = res.data;
          this.abrirPermisos(rol);
          this.roles = [...this.roles];
        });
      },
    });
  }

  private anotarEnElModulo(modulo: ModuloAdmin, rol: Rol, activo: boolean): void {
    const otros = (modulo.roles ?? []).filter((r) => r.id !== rol.id);
    modulo.roles = activo ? [...otros, rol] : otros;
    this.roles = [...this.roles];
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
          const esNuevo = !this.rolEditando;
          this.cerrarModal();
          this.cargar();
          // Un rol sin módulos no sirve de nada: lo siguiente es elegirlos.
          if (esNuevo) this.abrirPermisos(res.data);
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
