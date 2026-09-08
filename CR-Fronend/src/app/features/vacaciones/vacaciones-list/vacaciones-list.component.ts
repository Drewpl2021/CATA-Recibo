import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';

import {
  VacacionService,
  EmpleadoService,
  ToastService,
  ConfirmService,
} from '../../../core/services';
import { Empleado, SaldoVacaciones, Vacacion } from '../../../core/models';
import { fechaLegible, mensajeErrorApi } from '../../../core/utils';
import { ESTADO_VACACION_SEVERIDAD } from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

/** Un chip del filtro por estado. */
interface ChipEstado {
  value: '' | 'pendiente' | 'aprobado' | 'rechazado';
  label: string;
  icono: string;
}

/**
 * Vacaciones de todo el personal — la bandeja de RR.HH.
 *
 * Acá se resuelven las solicitudes: aprobar o rechazar. Las fechas y los días
 * no se tocan al resolver; si están mal, se rechaza explicando por qué y el
 * trabajador vuelve a pedir. Eso es a propósito: cambiar en silencio las
 * fechas de una solicitud sería aprobar algo distinto de lo que se pidió.
 *
 * RR.HH. también puede registrar la solicitud de alguien que la trajo en
 * papel, eligiendo al empleado.
 */
@Component({
  selector: 'app-vacaciones-list',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, FormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent, IconComponent,
  ],
  templateUrl: './vacaciones-list.component.html',
})
export class VacacionesListComponent implements OnInit {
  private fb = inject(FormBuilder);
  private vacacionService = inject(VacacionService);
  private empleadoService = inject(EmpleadoService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  vacaciones: Vacacion[] = [];
  empleados: Empleado[] = [];
  cargando = false;

  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  busqueda = '';
  filtroEstado: ChipEstado['value'] = '';

  total = 0;
  pendientes = 0;
  aprobadas = 0;
  rechazadas = 0;

  /** Solicitar para otro. */
  modalVisible = false;
  guardando = false;
  /** Los días que le quedan al empleado elegido, para avisar antes de guardar. */
  saldoElegido: SaldoVacaciones | null = null;

  /** Rechazar pide el motivo. */
  modalRechazo = false;
  rechazando: Vacacion | null = null;
  motivoRechazo = '';

  chipsEstado: ChipEstado[] = [
    { value: '', label: 'Todas', icono: 'layers' },
    { value: 'pendiente', label: 'Pendientes', icono: 'clock' },
    { value: 'aprobado', label: 'Aprobadas', icono: 'check_circle' },
    { value: 'rechazado', label: 'Rechazadas', icono: 'remove_circle' },
  ];

  get cifras(): CifraCabecera[] {
    return [
      { icono: 'layers', valor: this.total, etiqueta: 'Solicitudes', tono: 'brand' },
      { icono: 'clock', valor: this.pendientes, etiqueta: 'Pendientes', tono: 'warning' },
      { icono: 'check_circle', valor: this.aprobadas, etiqueta: 'Aprobadas', tono: 'success' },
      { icono: 'remove_circle', valor: this.rechazadas, etiqueta: 'Rechazadas', tono: 'muted' },
    ];
  }

  columnas: ColumnaTabla<Vacacion>[] = [
    {
      campo: 'empleado_id',
      header: 'Empleado',
      ancho: '24%',
      formatear: (_v, fila) => this.nombreEmpleado(fila),
    },
    { campo: 'fecha_inicio', header: 'Desde', tipo: 'fecha', ancho: '13%' },
    { campo: 'fecha_fin', header: 'Hasta', tipo: 'fecha', ancho: '13%' },
    {
      campo: 'dias_solicitados',
      header: 'Días',
      ancho: '8%',
      formatear: (valor) => `${valor ?? 0}`,
    },
    {
      campo: 'estado',
      header: 'Estado',
      ancho: '12%',
      tipo: 'badge',
      formatear: (valor) => this.etiquetaEstado(valor),
      badgeSeveridad: (valor) => ESTADO_VACACION_SEVERIDAD[valor] ?? 'secondary',
    },
    {
      campo: 'aprobado_por',
      header: 'Resuelto por',
      formatear: (valor, fila) => this.resueltoPor(valor, fila),
    },
  ];

  accionesExtra: AccionPersonalizada<Vacacion>[] = [
    {
      id: 'aprobar',
      titulo: 'Aprobar esta solicitud',
      icono: 'check_circle',
      severidad: 'success',
      visible: (v) => v.estado !== 'aprobado',
    },
    {
      id: 'rechazar',
      titulo: 'Rechazar esta solicitud',
      icono: 'remove_circle',
      severidad: 'danger',
      visible: (v) => v.estado !== 'rechazado',
    },
  ];

  form = this.fb.group({
    empleado_id: ['', [Validators.required]],
    fecha_inicio: ['', [Validators.required]],
    fecha_fin: ['', [Validators.required]],
    motivo: [''],
  });

  ngOnInit(): void {
    this.cargar();
    this.cargarEmpleados();
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  nombreEmpleado(v: Vacacion): string {
    if (v.empleado) return `${v.empleado.nombre} ${v.empleado.apellido}`.trim();
    const e = this.empleados.find((x) => x.id === v.empleado_id);
    return e ? `${e.nombre} ${e.apellido}`.trim() : '—';
  }

  etiquetaEstado(estado: string): string {
    if (estado === 'aprobado') return 'Aprobada';
    if (estado === 'rechazado') return 'Rechazada';
    return 'Pendiente';
  }

  /** Quién resolvió y cuándo; si sigue pendiente, no hay nada que decir. */
  resueltoPor(valor: string | null | undefined, fila: Vacacion): string {
    if (fila.estado === 'pendiente' || !valor) return 'Sin resolver';
    return fila.aprobado_at ? `${valor} · ${fechaLegible(fila.aprobado_at)}` : valor;
  }

  /**
   * Los días que ocupa la solicitud que se está escribiendo.
   *
   * Se cuentan los dos extremos, igual que en el backend: del lunes al lunes
   * son 8 días de vacaciones, no 7.
   */
  get diasDelFormulario(): number {
    const { fecha_inicio, fecha_fin } = this.form.getRawValue();
    if (!fecha_inicio || !fecha_fin) return 0;

    const inicio = new Date(fecha_inicio + 'T00:00:00');
    const fin = new Date(fecha_fin + 'T00:00:00');
    if (fin < inicio) return 0;

    return Math.round((fin.getTime() - inicio.getTime()) / 86400000) + 1;
  }

  get seExcedeDelSaldo(): boolean {
    return !!this.saldoElegido && this.diasDelFormulario > this.saldoElegido.diasDisponibles;
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

  filtrarPorEstado(estado: ChipEstado['value']): void {
    this.filtroEstado = estado;
    this.pagina = 0;
    this.cargar();
  }

  get mensajeVacio(): string {
    if (this.busqueda) return 'Ningún trabajador coincide con lo que buscas.';
    if (this.filtroEstado === 'pendiente') return 'No hay solicitudes esperando respuesta.';
    if (this.filtroEstado) return `No hay solicitudes ${this.filtroEstado === 'aprobado' ? 'aprobadas' : 'rechazadas'}.`;
    return 'Todavía nadie ha pedido vacaciones.';
  }

  cargar(): void {
    this.cargando = true;
    this.vacacionService
      .getPagina({
        page: this.pagina,
        size: this.TAMANO_PAGINA,
        search: this.busqueda || undefined,
        estado: this.filtroEstado || undefined,
      })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.vacaciones = res.data.content;
            this.total = res.data.totalElements;
            this.pendientes = res.data.pendientes ?? 0;
            this.aprobadas = res.data.aprobadas ?? 0;
            this.rechazadas = res.data.rechazadas ?? 0;
          }
          this.cargando = false;
        },
        error: (err) => {
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar las vacaciones.'));
          this.cargando = false;
        },
      });
  }

  /** Para el desplegable del formulario: la lista completa, sin paginar. */
  private cargarEmpleados(): void {
    this.empleadoService.getAll().subscribe({
      next: (res) => { if (res.success) this.empleados = res.data; },
      error: (err) => {
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la lista de empleados.'));
      },
    });
  }

  // ── Registrar una solicitud ────────────────────────────────

  nueva(): void {
    this.form.reset({ empleado_id: '', fecha_inicio: '', fecha_fin: '', motivo: '' });
    this.saldoElegido = null;
    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.saldoElegido = null;
    this.form.reset();
  }

  /** Al elegir empleado se traen sus días, para no llenar el resto en vano. */
  alElegirEmpleado(): void {
    const id = this.form.getRawValue().empleado_id;
    this.saldoElegido = null;
    if (!id) return;

    this.vacacionService.saldo({ empleado_id: id }).subscribe({
      next: (res) => { if (res.success) this.saldoElegido = res.data; },
      error: () => { /* si falla, el backend igual valida al guardar */ },
    });
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
        empleado_id: crudo.empleado_id!,
        fecha_inicio: crudo.fecha_inicio!,
        fecha_fin: crudo.fecha_fin!,
        motivo: crudo.motivo || null,
      })
      .subscribe({
        next: (res) => {
          this.guardando = false;
          if (res.success) {
            this.toastService.success(
              'Solicitud registrada',
              `Quedó pendiente de aprobación. Le restan ${res.dias_restantes} días este año.`
            );
            this.cerrarModal();
            this.cargar();
          }
        },
        error: (err) => {
          this.guardando = false;
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo registrar la solicitud.'));
        },
      });
  }

  // ── Resolver ───────────────────────────────────────────────

  aprobar(v: Vacacion): void {
    this.confirmService
      .confirmar({
        titulo: 'Aprobar vacaciones',
        mensaje:
          `Vas a aprobar ${v.dias_solicitados} día(s) de ${this.nombreEmpleado(v)}, ` +
          `del ${fechaLegible(v.fecha_inicio)} al ${fechaLegible(v.fecha_fin)}.`,
        aceptarTexto: 'Sí, aprobar',
        // Aprobar no destruye nada: sin esto el diálogo salía con el tacho
        // rojo de borrar.
        variante: 'default',
      })
      .then((aceptado) => {
        if (!aceptado) return;

        this.vacacionService.resolver(v.id, 'aprobado').subscribe({
          next: () => {
            this.toastService.success('Aprobada', `Las vacaciones de ${this.nombreEmpleado(v)} quedaron aprobadas.`);
            this.cargar();
          },
          error: (err) => {
            this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo aprobar la solicitud.'));
          },
        });
      });
  }

  abrirRechazo(v: Vacacion): void {
    this.rechazando = v;
    this.motivoRechazo = '';
    this.modalRechazo = true;
  }

  cerrarRechazo(): void {
    this.modalRechazo = false;
    this.rechazando = null;
    this.motivoRechazo = '';
  }

  confirmarRechazo(): void {
    if (!this.rechazando) return;

    const v = this.rechazando;
    this.guardando = true;

    this.vacacionService.resolver(v.id, 'rechazado', this.motivoRechazo || null).subscribe({
      next: () => {
        this.guardando = false;
        this.toastService.success('Rechazada', `${this.nombreEmpleado(v)} verá el motivo en Mis Vacaciones.`);
        this.cerrarRechazo();
        this.cargar();
      },
      error: (err) => {
        this.guardando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo rechazar la solicitud.'));
      },
    });
  }

  eliminar(v: Vacacion): void {
    this.confirmService.confirmarEliminar(
      `la solicitud de ${this.nombreEmpleado(v)}`,
      () => {
        this.vacacionService.delete(v.id).subscribe({
          next: () => {
            this.toastService.success('Eliminada', 'La solicitud fue dada de baja.');
            this.cargar();
          },
          error: (err) => {
            this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo eliminar la solicitud.'));
          },
        });
      }
    );
  }

  alAccionar(evento: { accion: string; fila: Vacacion }): void {
    if (evento.accion === 'aprobar') this.aprobar(evento.fila);
    if (evento.accion === 'rechazar') this.abrirRechazo(evento.fila);
  }
}
