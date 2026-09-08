import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { forkJoin } from 'rxjs';

import {
  UsuarioService,
  RolService,
  EmpleadoService,
  AuthService,
  ToastService,
  ConfirmService,
} from '../../../core/services';
import { Empleado, Rol, Usuario } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

/**
 * Cuentas de acceso al sistema (solo Admin).
 *
 * Acá NO se crean usuarios: nacen cuando alguien se registra o cuando se da
 * de alta un empleado. Desde esta pantalla se administran los que ya existen:
 * su nombre, su correo, el ROL que decide a qué entra, y a qué EMPLEADO está
 * vinculada la cuenta (ese vínculo es el que permite ver sus propias boletas).
 *
 * Dar de baja no borra: desactiva la cuenta y le cierra las sesiones abiertas,
 * y se puede volver a activar.
 */
@Component({
  selector: 'app-usuarios-list',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent,
  ],
  templateUrl: './usuarios-list.component.html',
})
export class UsuariosListComponent implements OnInit {
  private fb = inject(FormBuilder);
  private usuarioService = inject(UsuarioService);
  private rolService = inject(RolService);
  private empleadoService = inject(EmpleadoService);
  private authService = inject(AuthService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  usuarios: Usuario[] = [];
  roles: Rol[] = [];
  empleados: Empleado[] = [];

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
    { campo: 'name', header: 'Nombre', ancho: '22%' },
    { campo: 'email', header: 'Correo', ancho: '26%', romperTexto: true },
    {
      campo: 'rol',
      header: 'Rol',
      ancho: '14%',
      tipo: 'badge',
      formatear: (valor) => this.nombreRol(valor),
      badgeSeveridad: (valor) => (this.nombreRol(valor) === 'admin' ? 'warning' : 'info'),
    },
    {
      campo: 'empleado_id',
      header: 'Empleado vinculado',
      formatear: (_v, fila) => this.nombreEmpleadoDe(fila),
    },
    {
      campo: 'estado_registro',
      header: 'Estado',
      ancho: '12%',
      tipo: 'badge',
      formatear: (valor) => (valor === 'inactivo' ? 'Inactivo' : 'Activo'),
      badgeSeveridad: (valor) => (valor === 'inactivo' ? 'secondary' : 'success'),
    },
  ];

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
    /** Reactivar solo aparece en las cuentas dadas de baja. */
    {
      id: 'reactivar',
      titulo: 'Volver a activar esta cuenta',
      icono: 'shield',
      severidad: 'success',
      visible: (u) => u.estado_registro === 'inactivo',
    },
  ];

  /** La contraseña temporal recién puesta, que se enseña UNA vez. */
  modalPassword = false;
  usuarioRestablecido: Usuario | null = null;
  passwordTemporal = '';
  passwordEsDni = false;
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

  /** El backend manda `rol` a veces como objeto y a veces como texto. */
  nombreRol(rol: unknown): string {
    if (!rol) return '—';
    return typeof rol === 'string' ? rol : ((rol as Rol).nombre ?? '—');
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
      empleados: this.empleadoService.getAll(),
    }).subscribe({
      next: ({ roles, empleados }) => {
        if (roles.success) this.roles = roles.data;
        if (empleados.success) this.empleados = empleados.data;
      },
      error: (err) => {
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los roles y empleados.'));
      },
    });
  }

  cargar(): void {
    this.cargando = true;
    this.usuarioService
      .getPagina({ page: this.pagina, size: this.TAMANO_PAGINA, search: this.busqueda || undefined })
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
    // rol_id no siempre viene suelto; si falta, se deduce del rol incluido.
    const rolId = usuario.rol_id
      ?? this.roles.find((r) => r.nombre === this.nombreRol(usuario.rol))?.id
      ?? '';
    this.form.patchValue({
      name: usuario.name,
      email: usuario.email,
      rol_id: rolId,
      empleado_id: usuario.empleado_id ?? '',
    });
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

  reactivar(usuario: Usuario): void {
    this.usuarioService.update(usuario.id, { estado_registro: 'activo' }).subscribe({
      next: () => {
        this.toastService.success('Cuenta reactivada', `"${usuario.name}" ya puede volver a entrar.`);
        this.cargar();
      },
      error: (err) => {
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo reactivar la cuenta.'));
      },
    });
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
          `A "${usuario.name}" se le pondrá su DNI como contraseña, se le cerrarán ` +
          `las sesiones abiertas y el sistema le pedirá cambiarla al entrar. ¿Seguimos?`,
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
            if (res.success) {
              this.usuarioRestablecido = usuario;
              this.passwordTemporal = res.data.password_temporal;
              this.passwordEsDni = res.data.es_dni;
              this.modalPassword = true;
            }
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
    if (evento.accion === 'reactivar') this.reactivar(evento.fila);
    if (evento.accion === 'restablecer') this.restablecer(evento.fila);
  }
}
