import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { VacacionService, ToastService, ConfirmService, AuthService } from '../../../core/services';
import { SaldoVacaciones, Vacacion } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { ESTADO_VACACION_SEVERIDAD } from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

/**
 * Mis Vacaciones — lo que ve cada trabajador de las suyas.
 *
 * Manda su propio empleado_id a propósito. A un docente el backend le recorta
 * el listado al empleado del token pase lo que pase, pero a quien está en
 * RR.HH. le devuelve las de TODO el colegio — y en esta pantalla eso está
 * mal: acá se ven las de uno. Mandando el id, RR.HH. ve las suyas igual que
 * cualquiera.
 *
 * Lo primero que se ve son los días que le quedan, porque es la pregunta que
 * trae a esta pantalla; pedir viene después.
 */
@Component({
  selector: 'app-mis-vacaciones',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent,
  ],
  templateUrl: './mis-vacaciones.component.html',
})
export class MisVacacionesComponent implements OnInit {
  private fb = inject(FormBuilder);
  private vacacionService = inject(VacacionService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);
  private authService = inject(AuthService);

  /** El empleado de la sesión. Una cuenta sin empleado no tiene vacaciones. */
  private readonly miEmpleadoId = this.authService.getEmpleadoId();

  vacaciones: Vacacion[] = [];
  saldo: SaldoVacaciones | null = null;
  cargando = false;

  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  total = 0;

  modalVisible = false;
  guardando = false;

  get cifras(): CifraCabecera[] {
    return [
      { icono: 'beach', valor: this.saldo?.diasDisponibles ?? 0, etiqueta: 'Días libres', tono: 'success' },
      { icono: 'clock', valor: this.saldo?.diasUsados ?? 0, etiqueta: 'Ya pedidos', tono: 'warning' },
      { icono: 'layers', valor: this.saldo?.diasGanados ?? 0, etiqueta: 'Del año', tono: 'brand' },
    ];
  }

  /** Todavía no cumple su año de servicio: le toca la parte proporcional. */
  get esProporcional(): boolean {
    return !!this.saldo && this.saldo.mesesTrabajados < 12;
  }

  columnas: ColumnaTabla<Vacacion>[] = [
    { campo: 'fecha_inicio', header: 'Desde', tipo: 'fecha', ancho: '15%' },
    { campo: 'fecha_fin', header: 'Hasta', tipo: 'fecha', ancho: '15%' },
    { campo: 'dias_solicitados', header: 'Días', ancho: '8%', formatear: (v) => `${v ?? 0}` },
    {
      campo: 'estado',
      header: 'Estado',
      ancho: '13%',
      tipo: 'badge',
      formatear: (valor) => this.etiquetaEstado(valor),
      badgeSeveridad: (valor) => ESTADO_VACACION_SEVERIDAD[valor] ?? 'secondary',
    },
    { campo: 'motivo', header: 'Motivo', formatear: (v) => v || '—' },
    {
      campo: 'observacion',
      header: 'Respuesta de RR.HH.',
      formatear: (valor, fila) => this.respuesta(valor, fila),
    },
  ];

  form = this.fb.group({
    fecha_inicio: ['', [Validators.required]],
    fecha_fin: ['', [Validators.required]],
    motivo: [''],
  });

  /** Una cuenta de sistema, sin ficha de empleado, no puede pedir vacaciones. */
  get sinEmpleado(): boolean {
    return !this.miEmpleadoId;
  }

  ngOnInit(): void {
    if (this.sinEmpleado) return;
    this.cargar();
    this.cargarSaldo();
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  etiquetaEstado(estado: string): string {
    if (estado === 'aprobado') return 'Aprobada';
    if (estado === 'rechazado') return 'Rechazada';
    return 'Pendiente';
  }

  /** Lo que contestó RR.HH.: el motivo del rechazo, o quién la aprobó. */
  respuesta(valor: string | null | undefined, fila: Vacacion): string {
    if (valor) return valor;
    if (fila.estado === 'aprobado') return `Aprobada por ${fila.aprobado_por ?? 'Recursos Humanos'}`;
    if (fila.estado === 'rechazado') return 'Rechazada, sin motivo indicado';
    return 'Esperando respuesta';
  }

  /** Días de la solicitud que se está escribiendo, contando los dos extremos. */
  get diasDelFormulario(): number {
    const { fecha_inicio, fecha_fin } = this.form.getRawValue();
    if (!fecha_inicio || !fecha_fin) return 0;

    const inicio = new Date(fecha_inicio + 'T00:00:00');
    const fin = new Date(fecha_fin + 'T00:00:00');
    if (fin < inicio) return 0;

    return Math.round((fin.getTime() - inicio.getTime()) / 86400000) + 1;
  }

  get seExcedeDelSaldo(): boolean {
    return !!this.saldo && this.diasDelFormulario > this.saldo.diasDisponibles;
  }

  irAPagina(pagina: number): void {
    this.pagina = pagina;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.vacacionService
      .getPagina({ page: this.pagina, size: this.TAMANO_PAGINA, empleado_id: this.miEmpleadoId! })
      .subscribe({
      next: (res) => {
        if (res.success) {
          this.vacaciones = res.data.content;
          this.total = res.data.totalElements;
        }
        this.cargando = false;
      },
      error: (err) => {
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar tus vacaciones.'));
        this.cargando = false;
      },
    });
  }

  private cargarSaldo(): void {
    this.vacacionService.saldo({ empleado_id: this.miEmpleadoId! }).subscribe({
      next: (res) => { if (res.success) this.saldo = res.data; },
      error: (err) => {
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar tus días disponibles.'));
      },
    });
  }

  pedir(): void {
    this.form.reset({ fecha_inicio: '', fecha_fin: '', motivo: '' });
    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.form.reset();
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const crudo = this.form.getRawValue();
    this.guardando = true;

    this.vacacionService
      .crear({
        fecha_inicio: crudo.fecha_inicio!,
        fecha_fin: crudo.fecha_fin!,
        motivo: crudo.motivo || null,
      })
      .subscribe({
        next: (res) => {
          this.guardando = false;
          if (res.success) {
            this.toastService.success(
              'Solicitud enviada',
              `Recursos Humanos tiene que aprobarla. Te quedan ${res.dias_restantes} días.`
            );
            this.cerrarModal();
            this.cargar();
            this.cargarSaldo();
          }
        },
        error: (err) => {
          this.guardando = false;
          this.toastService.error('No se pudo pedir', mensajeErrorApi(err, 'Revisa las fechas e intenta de nuevo.'));
        },
      });
  }

  /** Arrepentirse: solo mientras nadie la haya resuelto. */
  anular(v: Vacacion): void {
    if (v.estado !== 'pendiente') {
      this.toastService.warning(
        'Ya está resuelta',
        'Esta solicitud ya la respondió Recursos Humanos. Habla con ellos para cambiarla.'
      );
      return;
    }

    this.confirmService.confirmarEliminar('esta solicitud de vacaciones', () => {
      this.vacacionService.delete(v.id).subscribe({
        next: () => {
          this.toastService.success('Solicitud retirada', 'Los días vuelven a tu saldo.');
          this.cargar();
          this.cargarSaldo();
        },
        error: (err) => {
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo retirar la solicitud.'));
        },
      });
    });
  }
}
