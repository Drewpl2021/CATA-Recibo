import { Component, OnInit, inject } from '@angular/core';

import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { forkJoin } from 'rxjs';

import {
  UsuarioService,
  RolService,
  ModuloService,
  EmpleadoService,
  AuthService,
  ToastService,
  ConfirmService,
} from '../../../core/services';
import { Empleado, ModuloAdmin, Rol, Usuario } from '../../../core/models';
import { fechaLegible, mensajeErrorApi } from '../../../core/utils';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { etiquetaRol } from '../../../shared/constants';

/**
 * Cuentas de acceso al sistema (solo Admin).
 *
 * Acá NO se crean usuarios: nacen al dar de alta a un empleado. Desde esta
 * pantalla se administran los que ya existen: su nombre, su correo, el ROL que
 * decide a qué entra, y a qué EMPLEADO está vinculada la cuenta (ese vínculo es
 * el que permite ver sus propias boletas).
 *
 * El rol y el estado se cambian en la misma fila, si quien mira es
 * Administrador. Dar de baja no borra: desactiva la cuenta y le cierra las
 * sesiones abiertas, y se puede volver a activar.
 */
import { AvatarComponent } from '../../../shared/components/avatar/avatar.component';
import { CeldaTablaDirective } from '../../../shared/components/data-table/celda-tabla.directive';
import { FotoDeCuenta } from '../../../shared/components/avatar/avatar.component';
import { FiltrosComponent } from '../../../shared/components/filtros/filtros.component';
import { CampoFiltro, ValoresFiltro } from '../../../shared/components/filtros/filtros.models';
@Component({
  selector: 'app-usuarios-list',
  standalone: true,
  imports: [CeldaTablaDirective, AvatarComponent, FiltrosComponent,
    ReactiveFormsModule,
    PageHeaderComponent,
    DataTableComponent,
    FormModalComponent
],
  templateUrl: './usuarios-list.component.html',
})
export class UsuariosListComponent implements OnInit {
  readonly etiquetaRol = etiquetaRol;

  private fb = inject(FormBuilder);
  private usuarioService = inject(UsuarioService);
  private rolService = inject(RolService);
  private moduloService = inject(ModuloService);
  private empleadoService = inject(EmpleadoService);
  private authService = inject(AuthService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  usuarios: Usuario[] = [];
  roles: Rol[] = [];
  empleados: Empleado[] = [];
  private modulos: ModuloAdmin[] = [];

  /** Cambiar rol, ficha y estado es solo del Administrador; el servidor lo exige igual. */
  readonly esAdministrador = this.authService.esSuperadmin();

  filtros: ValoresFiltro = {};
  camposFiltro: CampoFiltro[] = [
    { clave: 'rol_id', etiqueta: 'Rol', tipo: 'opciones', vacio: 'Todos', opciones: [] },
    ...(this.esAdministrador ? [{
      clave: 'estado_registro', etiqueta: 'Estado', tipo: 'opciones' as const, vacio: 'Todos',
      opciones: [{ valor: 'activo', etiqueta: 'Activos' }, { valor: 'inactivo', etiqueta: 'De baja' }],
    }] : []),
  ];

  /** Lo que tendrá con el rol elegido en el formulario. */
  modulosDelRolElegido: string[] = [];
  /** Cuentas cuya fila está guardando un cambio. */
  private filasOcupadas = new Set<number>();

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
      { icono: 'check_circle', valor: this.activos, etiqueta: 'Activos', tono: 'success' },
      { icono: 'pause_circle', valor: this.inactivos, etiqueta: 'De baja', tono: 'muted' },
    ];
  }
  guardando = false;
  modalVisible = false;
  usuarioEditando: Usuario | null = null;

  columnas: ColumnaTabla<Usuario>[] = [
    // El correo va debajo del nombre: en su propia columna se partía a media palabra.
    { campo: 'name', header: 'Nombre y correo', ancho: '30%' },
    {
      campo: 'rol',
      header: 'Rol',
      ancho: '16%',
    },
    {
      campo: 'empleado_id',
      header: 'Empleado vinculado',
      ancho: '16%',
      formatear: (_v, fila) => this.nombreEmpleadoDe(fila),
    },
    {
      campo: 'estado_registro',
      header: 'Estado',
      ancho: '12%',
    },
    {
      // La columna que antes era una hoja de Excel con las firmas: quién
      // aceptó los términos y quién no. Ya no hay que perseguir a nadie
      // con un papel para saberlo.
      campo: 'terminos_estado',
      header: 'Términos',
      ancho: '10%',
      tipo: 'badge',
      formatear: (valor, fila) => this.etiquetaTerminos(valor, fila),
      badgeSeveridad: (valor) => {
        if (valor === 'firmado') return 'success';
        return valor === 'desactualizado' ? 'warning' : 'secondary';
      },
    },
  ];

  /**
   * "Firmado" a secas no dice nada: lo que prueba una aceptación es cuándo
   * fue, así que la fecha va en la misma celda.
   */
  private etiquetaTerminos(valor: unknown, fila: Usuario): string {
    if (valor === 'firmado') {
      return fila.terminos_firmados_en
        ? `Firmado ${fechaLegible(fila.terminos_firmados_en)}`
        : 'Firmado';
    }

    return valor === 'desactualizado' ? 'Versión anterior' : 'Pendiente';
  }

  accionesExtra: AccionPersonalizada<Usuario>[] = [
    {
      // Para el docente que se quedó fuera y no puede usar el correo.
      id: 'restablecer',
      titulo: 'Restablecer su contraseña',
      icono: 'lock',
      severidad: 'warning',
      // En la cuenta propia no: cerraría la sesión con la que se trabaja.
      visible: (u) => !this.esMiCuenta(u) && u.estado_registro !== 'inactivo',
    },
  ];

  /** La contraseña temporal recién puesta, que se enseña UNA vez. */
  modalPassword = false;
  usuarioRestablecido: Usuario | null = null;
  passwordTemporal = '';
  restableciendo = false;

  form = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(255)]],
    email: ['', [Validators.required, Validators.email]],
    rol_id: ['', [Validators.required]],
    empleado_id: [''],
  });

  ngOnInit(): void {
    this.cargar();
    this.cargarCatalogos();
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  /**
   * El nombre INTERNO del rol ('rrhh'), que es con el que se compara.
   *
   * El backend manda `rol` a veces como objeto y a veces como texto. Para
   * pintarlo se usa etiquetaRol(), que devuelve "RRHH"; esto es para la
   * lógica, no para la pantalla.
   */
  nombreRol(rol: unknown): string {
    if (!rol) return '';
    const nombre = typeof rol === 'string' ? rol : ((rol as Rol).nombre ?? '');
    return nombre.trim().toLowerCase();
  }

  nombreEmpleadoDe(usuario: Usuario): string {
    if (!usuario.empleado_id) return 'Sin vincular';
    const e = this.empleados.find((x) => x.id === usuario.empleado_id);
    return e ? `${e.nombre} ${e.apellido}`.trim() : 'Empleado no encontrado';
  }

  /** La cuenta con la que se está trabajando ahora mismo. */
  esMiCuenta(usuario: Usuario): boolean {
    return this.authService.getUser()?.id === usuario.id;
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

  /**
   * Roles y empleados llenan los desplegables del formulario: se piden UNA
   * vez y completos, porque a un <select> no se le pagina. La lista de
   * usuarios sí viene por páginas.
   */
  private cargarCatalogos(): void {
    forkJoin({
      roles: this.rolService.getAll(),
      empleados: this.empleadoService.paraSelector(),
    }).subscribe({
      next: ({ roles, empleados }) => {
        if (roles.success) {
          this.roles = roles.data;
          this.camposFiltro = this.camposFiltro.map((c) => c.clave === 'rol_id'
            ? { ...c, opciones: roles.data.map((r) => ({ valor: r.id, etiqueta: etiquetaRol(r) })) }
            : c);
        }
        if (empleados.success) this.empleados = empleados.data;
      },
      error: (err) => {
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los roles y empleados.'));
      },
    });

    // Para enseñar qué tendrá cada rol. Sin permiso para leerlos, simplemente no se enseña.
    if (this.authService.puede('/roles', '/modulos', '/modulos-padre')) {
      this.moduloService.getAll().subscribe({
        next: (res) => { if (res.success) this.modulos = res.data; },
        error: () => {},
      });
    }
  }

  alFiltrar(): void {
    this.pagina = 0;
    this.cargar();
  }

  rolIdDe(usuario: Usuario): string {
    return usuario.rol_id ?? this.roles.find((r) => r.nombre === this.nombreRol(usuario.rol))?.id ?? '';
  }

  estaOcupada(usuario: Usuario): boolean {
    return this.filasOcupadas.has(usuario.id);
  }

  /** Los módulos de ese rol, para decir antes de guardar qué tendrá la persona. */
  private modulosDeRol(rolId: string): string[] {
    return this.modulos.filter((m) => m.roles?.some((r) => r.id === rolId)).map((m) => m.nombre);
  }

  alElegirRolEnFormulario(): void {
    this.modulosDelRolElegido = this.modulosDeRol(this.form.get('rol_id')?.value ?? '');
  }

  /** Cambia el rol desde la fila. Dar permisos pide confirmar; si no, vuelve como estaba. */
  cambiarRolEnFila(usuario: Usuario, control: HTMLSelectElement): void {
    const anterior = this.rolIdDe(usuario);
    const rol = this.roles.find((r) => r.id === control.value);
    if (!rol || rol.id === anterior) return;

    const cuantos = this.modulosDeRol(rol.id).length;
    this.confirmService
      .confirmar({
        titulo: 'Cambiar el rol',
        mensaje: rol.es_superadmin
          ? `"${usuario.name}" será Administrador y podrá entrar a todo el sistema. ¿Seguimos?`
          : `"${usuario.name}" pasará a ${etiquetaRol(rol)}, con ${cuantos} módulo(s). ¿Seguimos?`,
        aceptarTexto: 'Sí, cambiar',
        variante: 'default',
      })
      .then((aceptado) => {
        if (!aceptado) {
          control.value = anterior;
          return;
        }
        this.filasOcupadas.add(usuario.id);
        this.usuarioService.update(usuario.id, { rol_id: rol.id }).subscribe({
          next: () => {
            this.filasOcupadas.delete(usuario.id);
            usuario.rol_id = rol.id;
            usuario.rol = rol;
            this.toastService.success('Rol cambiado', `"${usuario.name}" ahora es ${etiquetaRol(rol)}.`);
          },
          error: (err) => {
            this.filasOcupadas.delete(usuario.id);
            control.value = anterior;
            this.toastService.error('No se cambió', mensajeErrorApi(err, 'No se pudo cambiar el rol.'));
          },
        });
      });
  }

  /** Enciende o apaga la cuenta desde la fila. */
  cambiarEstadoEnFila(usuario: Usuario, control: HTMLInputElement): void {
    const activar = control.checked;
    const volver = () => { control.checked = !activar; };

    if (activar) {
      this.filasOcupadas.add(usuario.id);
      this.usuarioService.update(usuario.id, { estado_registro: 'activo' }).subscribe({
        next: () => {
          this.filasOcupadas.delete(usuario.id);
          this.toastService.success('Cuenta activada', `"${usuario.name}" ya puede volver a entrar.`);
          this.cargar();
        },
        error: (err) => {
          this.filasOcupadas.delete(usuario.id);
          volver();
          this.toastService.error('No se activó', mensajeErrorApi(err, 'No se pudo activar la cuenta.'));
        },
      });
      return;
    }

    this.confirmService
      .confirmar({
        titulo: 'Desactivar la cuenta',
        mensaje: `"${usuario.name}" no podrá entrar y se le cerrarán las sesiones abiertas. Podrás volver a activarla. ¿Seguimos?`,
        aceptarTexto: 'Sí, desactivar',
        variante: 'danger',
      })
      .then((aceptado) => {
        if (!aceptado) {
          volver();
          return;
        }
        this.filasOcupadas.add(usuario.id);
        this.usuarioService.delete(usuario.id).subscribe({
          next: () => {
            this.filasOcupadas.delete(usuario.id);
            this.toastService.success('Cuenta desactivada', `"${usuario.name}" ya no puede entrar al sistema.`);
            this.cargar();
          },
          error: (err) => {
            this.filasOcupadas.delete(usuario.id);
            volver();
            this.toastService.error('No se desactivó', mensajeErrorApi(err, 'No se pudo desactivar la cuenta.'));
          },
        });
      });
  }

  cargar(): void {
    this.cargando = true;
    this.usuarioService
      .getPagina({ page: this.pagina, size: this.TAMANO_PAGINA, search: this.busqueda || undefined, ...this.filtros })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.usuarios = res.data.content;
            this.total = res.data.totalElements;
            this.activos = res.data.activos ?? 0;
            this.inactivos = res.data.inactivos ?? 0;
          }
          this.cargando = false;
        },
        error: (err) => {
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los usuarios.'));
          this.cargando = false;
        },
      });
  }

  editar(usuario: Usuario): void {
    this.usuarioEditando = usuario;
    this.form.patchValue({
      name: usuario.name,
      email: usuario.email,
      // rol_id no siempre viene suelto; si falta, se deduce del rol incluido.
      rol_id: this.rolIdDe(usuario),
      empleado_id: usuario.empleado_id ?? '',
    });
    // Solo el Administrador los cambia: así no se ofrece lo que el servidor rechazaría.
    const controles = [this.form.get('rol_id')!, this.form.get('empleado_id')!];
    controles.forEach((c) => (this.esAdministrador ? c.enable() : c.disable()));
    this.alElegirRolEnFormulario();
    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.usuarioEditando = null;
    this.form.reset();
  }

  guardar(): void {
    if (this.form.invalid || !this.usuarioEditando) {
      this.form.markAllAsTouched();
      return;
    }

    const crudo = this.form.getRawValue();
    this.guardando = true;

    this.usuarioService
      .update(this.usuarioEditando.id, {
        name: crudo.name!,
        email: crudo.email!,
        rol_id: crudo.rol_id!,
        // "Sin vincular" viaja como null: el backend valida `nullable|uuid`.
        empleado_id: crudo.empleado_id || null,
      })
      .subscribe({
        next: (res) => {
          this.guardando = false;
          if (res.success) {
            this.toastService.success('Usuario actualizado', `"${res.data.name}" se guardó correctamente.`);
            this.cerrarModal();
            this.cargar();
          }
        },
        error: (err) => {
          this.guardando = false;
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo guardar el usuario.'));
        },
      });
  }

  desactivar(usuario: Usuario): void {
    if (this.esMiCuenta(usuario)) {
      this.toastService.error('No permitido', 'No puedes desactivar tu propia cuenta.');
      return;
    }

    this.confirmService.confirmarEliminar(
      `la cuenta de "${usuario.name}". Se le cerrarán las sesiones abiertas y no podrá entrar, pero podrás reactivarla`,
      () => {
        this.usuarioService.delete(usuario.id).subscribe({
          next: () => {
            this.toastService.success('Cuenta desactivada', `"${usuario.name}" ya no puede entrar al sistema.`);
            this.cargar();
          },
          error: (err) => {
            this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo desactivar la cuenta.'));
          },
        });
      }
    );
  }

  /**
   * Le repone la contraseña a alguien que se quedó fuera.
   *
   * Se avisa antes de lo que va a pasar: se le cierran las sesiones y tendrá
   * que cambiarla al entrar. La temporal se muestra una sola vez, así que la
   * pantalla se queda con ella hasta que RR.HH. la cierre.
   */
  restablecer(usuario: Usuario): void {
    this.confirmService
      .confirmar({
        titulo: 'Restablecer contraseña',
        mensaje:
          `A "${usuario.name}" le enviaremos a su correo un enlace para crear una contraseña nueva ` +
          `(si no tiene correo, se generará una clave temporal para entregársela en persona). ` +
          `La que tiene ahora deja de servir y se cierran sus sesiones. ¿Seguimos?`,
        aceptarTexto: 'Sí, restablecer',
        // No se borra nada: el tacho rojo daría a entender otra cosa.
        variante: 'default',
      })
      .then((aceptado) => {
        if (!aceptado) return;

        this.restableciendo = true;
        this.usuarioService.restablecerPassword(usuario.id).subscribe({
          next: (res) => {
            this.restableciendo = false;
            if (!res.success) return;
            if (res.data.por_correo) {
              this.toastService.success('Acceso enviado', res.data.message);
              return;
            }
            this.usuarioRestablecido = usuario;
            this.passwordTemporal = res.data.password_temporal ?? '';
            this.modalPassword = true;
          },
          error: (err) => {
            this.restableciendo = false;
            this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo restablecer la contraseña.'));
          },
        });
      });
  }

  cerrarPasswordTemporal(): void {
    this.modalPassword = false;
    this.usuarioRestablecido = null;
    this.passwordTemporal = '';
  }

  alAccionar(evento: { accion: string; fila: Usuario }): void {
    if (evento.accion === 'restablecer') this.restablecer(evento.fila);
  }

  /** La foto de su cuenta para app-avatar (la lista de usuarios trae la versión en «foto»). */
  fotoDe(u: Usuario): FotoDeCuenta | null {
    const version = (u as unknown as { foto?: string | null }).foto;
    return version ? { usuario_id: u.id, version } : null;
  }
}
