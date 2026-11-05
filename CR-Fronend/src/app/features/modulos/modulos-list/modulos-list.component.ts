import { Component, OnInit, inject } from '@angular/core';

import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { forkJoin, of, switchMap } from 'rxjs';

import {
  ModuloService,
  ModuloPadreService,
  RolService,
  ToastService,
  ConfirmService,
} from '../../../core/services';
import { ModuloAdmin, ModuloPadreAdmin, ModuloPayload, Rol } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { CLAVES_ICONO } from '../../../shared/icons/icon-map';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { etiquetaRol } from '../../../shared/constants';
import { CeldaTablaDirective } from '../../../shared/components/data-table/celda-tabla.directive';
import { FiltrosComponent } from '../../../shared/components/filtros/filtros.component';
import { CampoFiltro, ValoresFiltro } from '../../../shared/components/filtros/filtros.models';

/**
 * Módulos = los ÍTEMS de la barra lateral (solo Admin).
 *
 * Acá se junta todo el sistema de menús:
 *
 *   1. El módulo cuelga de un MÓDULO PADRE (el grupo donde saldrá).
 *   2. La RUTA es la de Angular a la que lleva el ítem ("/areas"). Si la
 *      pantalla todavía no existe en el front, el ítem no se muestra.
 *   3. Los ROLES marcados lo tienen: es su menú y su permiso real. En la tabla
 *      se dan y se quitan con un clic.
 *
 * Los roles NO son un campo del módulo: el backend los guarda aparte, en
 * POST /modulos/{id}/roles. Por eso guardar es en dos pasos — primero el
 * módulo, y con el id que devuelve, los roles.
 */
@Component({
  selector: 'app-modulos-list',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    PageHeaderComponent,
    DataTableComponent,
    FormModalComponent,
    IconComponent,
    CeldaTablaDirective,
    FiltrosComponent
],
  templateUrl: './modulos-list.component.html',
})
export class ModulosListComponent implements OnInit {
  /** 'rrhh' se lee "RRHH": el nombre crudo no se pinta en ningún sitio. */
  etiquetaRol = etiquetaRol;

  private fb = inject(FormBuilder);
  private moduloService = inject(ModuloService);
  private moduloPadreService = inject(ModuloPadreService);
  private rolService = inject(RolService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  modulos: ModuloAdmin[] = [];
  padres: ModuloPadreAdmin[] = [];
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
    return [{ icono: 'view_module', valor: this.total, etiqueta: 'Módulos', tono: 'brand' }];
  }
  guardando = false;
  modalVisible = false;
  moduloEditando: ModuloAdmin | null = null;

  /** Ids de los roles marcados en el formulario abierto. */
  rolesMarcados = new Set<string>();

  filtros: ValoresFiltro = {};
  camposFiltro: CampoFiltro[] = [
    { clave: 'modulo_padre_id', etiqueta: 'Grupo', tipo: 'opciones', vacio: 'Todos', opciones: [] },
  ];

  /** «módulo|rol» de los cambios que están viajando, para no repetirlos. */
  private guardandoEnFila = new Set<string>();

  iconos = CLAVES_ICONO;

  columnas: ColumnaTabla<ModuloAdmin>[] = [
    { campo: 'nombre', header: 'Módulo', ancho: '17%' },
    { campo: 'modulo_padre.nombre', header: 'Grupo', ancho: '14%' },
    // Después de Grupo y no al principio: es el orden DENTRO de ese grupo
    // (la posición en el menú), así que se lee mejor pegado al grupo al
    // que pertenece. Se llama "Orden" y no "#" porque la numeración de
    // fila (app-data-table la trae por defecto) ya usa esa cabecera, y
    // dos columnas de "#" una al lado de la otra no había forma de
    // distinguirlas.
    { campo: 'orden', header: 'Orden', ancho: '7%' },
    { campo: 'ruta', header: 'Ruta', ancho: '13%' },
    {
      campo: 'roles',
      header: 'Roles que lo tienen',
      ancho: '32%',
    },
  ];

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(255)]],
    modulo_padre_id: ['', [Validators.required]],
    ruta: ['', [Validators.maxLength(255)]],
    icono: ['circle'],
    orden: [0, [Validators.required, Validators.min(0)]],
  });

  ngOnInit(): void {
    this.cargar();
    this.cargarCatalogos();
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  get iconoElegido(): string {
    return this.form.get('icono')?.value ?? 'circle';
  }

  /** Módulos, grupos y roles se piden juntos: el formulario necesita los tres. */
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

  /**
   * Los catálogos que llenan los desplegables del formulario (grupos y
   * roles) se piden UNA vez y completos: a un <select> no se le pagina.
   * La lista de módulos, en cambio, viene por páginas.
   */
  private cargarCatalogos(): void {
    forkJoin({
      padres: this.moduloPadreService.getAll(),
      roles: this.rolService.getAll(),
    }).subscribe({
      next: ({ padres, roles }) => {
        if (padres.success) {
          this.padres = padres.data;
          this.camposFiltro = [{
            ...this.camposFiltro[0],
            opciones: padres.data.map((p) => ({ valor: p.id, etiqueta: p.nombre })),
          }];
        }
        if (roles.success) this.roles = roles.data;
      },
      error: (err) => {
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los grupos y roles.'));
      },
    });
  }

  cargar(): void {
    this.cargando = true;
    this.moduloService
      .getPagina({ page: this.pagina, size: this.TAMANO_PAGINA, search: this.busqueda || undefined, ...this.filtros })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.modulos = res.data.content;
            this.total = res.data.totalElements;
          }
          this.cargando = false;
        },
        error: (err) => {
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los módulos.'));
          this.cargando = false;
        },
      });
  }

  alFiltrar(): void {
    this.pagina = 0;
    this.cargar();
  }

  tieneElRol(modulo: ModuloAdmin, rol: Rol): boolean {
    return !!modulo.roles?.some((r) => r.id === rol.id);
  }

  /** Se ve al instante; si el servidor lo rechaza, vuelve como estaba y dice por qué. */
  alternarRolEnFila(modulo: ModuloAdmin, rol: Rol): void {
    const clave = `${modulo.id}|${rol.id}`;
    if (this.guardandoEnFila.has(clave)) return;

    const antes = modulo.roles ?? [];
    const activo = !this.tieneElRol(modulo, rol);
    modulo.roles = activo ? [...antes, rol] : antes.filter((r) => r.id !== rol.id);
    this.guardandoEnFila.add(clave);

    this.rolService.cambiarModulo(rol.id, modulo.id, activo).subscribe({
      next: () => this.guardandoEnFila.delete(clave),
      error: (err) => {
        this.guardandoEnFila.delete(clave);
        modulo.roles = antes;
        this.toastService.error('No se guardó', mensajeErrorApi(err, 'No se pudo cambiar el permiso.'));
      },
    });
  }

  alternarRol(rolId: string): void {
    if (this.rolesMarcados.has(rolId)) {
      this.rolesMarcados.delete(rolId);
    } else {
      this.rolesMarcados.add(rolId);
    }
  }

  tieneRol(rolId: string): boolean {
    return this.rolesMarcados.has(rolId);
  }

  nuevo(): void {
    this.moduloEditando = null;
    this.rolesMarcados.clear();
    const siguiente = this.modulos.reduce((max, m) => Math.max(max, m.orden ?? 0), 0) + 1;
    this.form.reset({
      icono: 'circle',
      orden: siguiente,
      modulo_padre_id: this.padres[0]?.id ?? '',
    });
    this.modalVisible = true;
  }

  editar(modulo: ModuloAdmin): void {
    this.moduloEditando = modulo;
    this.rolesMarcados = new Set((modulo.roles ?? []).map((r) => r.id));
    this.form.patchValue({
      nombre: modulo.nombre,
      modulo_padre_id: modulo.modulo_padre_id,
      ruta: modulo.ruta ?? '',
      icono: modulo.icono ?? 'circle',
      orden: modulo.orden ?? 0,
    });
    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.moduloEditando = null;
    this.rolesMarcados.clear();
    this.form.reset({ icono: 'circle', orden: 0, modulo_padre_id: '' });
  }

  /**
   * Guardar son dos llamadas encadenadas: el módulo primero (para tener su
   * id) y después sus roles, que el backend guarda en su propio endpoint.
   */
  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const payload = this.form.getRawValue() as ModuloPayload;
    this.guardando = true;

    const guardarModulo = this.moduloEditando
      ? this.moduloService.update(this.moduloEditando.id, payload)
      : this.moduloService.create(payload);

    guardarModulo
      .pipe(
        switchMap((res) => {
          if (!res.success) return of(res);
          const ids = [...this.rolesMarcados];
          // Sin roles marcados en un módulo nuevo no hay nada que sincronizar
          // (el backend exige un array con al menos un elemento).
          if (!ids.length && !this.moduloEditando) return of(res);
          return this.moduloService.asignarRoles(res.data.id, ids);
        })
      )
      .subscribe({
        next: () => {
          this.guardando = false;
          this.toastService.success(
            this.moduloEditando ? 'Módulo actualizado' : 'Módulo creado',
            this.rolesMarcados.size
              ? `Lo tienen ${this.rolesMarcados.size} rol(es). Lo verán en su menú al recargar la página.`
              : 'Guardado. Todavía no lo ve ningún rol: márcalos para que aparezca en su menú.'
          );
          this.cerrarModal();
          this.cargar();
        },
        error: (err) => {
          this.guardando = false;
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo guardar el módulo.'));
        },
      });
  }

  eliminar(modulo: ModuloAdmin): void {
    this.confirmService.confirmarEliminar(
      `el módulo "${modulo.nombre}". Dejará de verse en la barra lateral de todos los roles`,
      () => {
        this.moduloService.delete(modulo.id).subscribe({
          next: () => {
            this.toastService.success('Eliminado', `El módulo "${modulo.nombre}" fue eliminado.`);
            this.cargar();
          },
          error: (err) => {
            this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo eliminar el módulo.'));
          },
        });
      }
    );
  }
}
