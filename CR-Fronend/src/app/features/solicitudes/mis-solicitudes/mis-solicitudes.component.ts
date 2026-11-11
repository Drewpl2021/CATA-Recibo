import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AuthService, ConfirmService, SolicitudService, ToastService } from '../../../core/services';
import type { Solicitud, TipoSolicitud } from '../../../core/services';
import { guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { SelectorArchivoComponent } from '../../../shared/components/selector-archivo/selector-archivo.component';
import {
  ESTADO_SOLICITUD_SEVERIDAD,
  ESTADO_SOLICITUD_TEXTO,
  TIPOS_SOLICITUD,
  cuandoSolicitud,
} from '../../../shared/constants';

/**
 * Mis Solicitudes: lo que el trabajador le pide a RR.HH. (permisos,
 * licencias, descansos médicos, faltas por justificar y cambio de cuenta) y
 * en qué va cada una.
 *
 * Lo que valida de verdad es el servidor (fechas, cruces con vacaciones,
 * sustento, contraseña); acá se ayuda a llenar bien y se explica qué pasa
 * con el sueldo y la CTS antes de pedir.
 */
@Component({
  selector: 'app-mis-solicitudes',
  standalone: true,
  imports: [FormsModule, PageHeaderComponent, DataTableComponent, FormModalComponent, IconComponent, SelectorArchivoComponent],
  templateUrl: './mis-solicitudes.component.html',
  styleUrl: './mis-solicitudes.component.scss',
})
export class MisSolicitudesComponent implements OnInit {
  private readonly servicio = inject(SolicitudService);
  private readonly toast = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  private readonly auth = inject(AuthService);

  readonly sinEmpleado = !this.auth.getEmpleadoId();
  readonly tipos = TIPOS_SOLICITUD;

  solicitudes: Solicitud[] = [];
  cargando = false;
  cifras: CifraCabecera[] = this.contar([]);

  readonly columnas: ColumnaTabla<Solicitud>[] = [
    { campo: 'tipo_texto', header: 'Solicitud', ancho: '18%' },
    { campo: 'fecha_inicio', header: 'Cuándo', ancho: '22%', formatear: (_v, f) => this.cuando(f) },
    { campo: 'motivo', header: 'Motivo', ancho: '22%' },
    {
      campo: 'estado', header: 'Estado', tipo: 'badge', ancho: '11%',
      formatear: (v) => ESTADO_SOLICITUD_TEXTO[v] ?? v,
      badgeSeveridad: (v) => ESTADO_SOLICITUD_SEVERIDAD[v] ?? 'secondary',
    },
    { campo: 'respuesta', header: 'Respuesta de RR.HH.', formatear: (v, f) => v || (f.estado === 'pendiente' ? 'Esperando respuesta' : '—') },
  ];

  readonly acciones: AccionPersonalizada<Solicitud>[] = [
    { id: 'sustento', titulo: 'Bajar el sustento que adjuntaste', icono: 'download', visible: (f) => f.tiene_sustento },
    { id: 'cancelar', titulo: 'Cancelar esta solicitud', icono: 'close', severidad: 'danger', visible: (f) => f.estado === 'pendiente' },
  ];

  // ── El formulario ──
  modal = false;
  enviando = false;
  tipo: TipoSolicitud = 'permiso';
  fechaInicio = '';
  fechaFin = '';
  horaInicio = '';
  horaFin = '';
  conGoce: boolean | null = null;
  motivo = '';
  sustento: File[] = [];
  destino: 'sueldo' | 'cts' = 'cts';
  entidad = '';
  numero = '';
  cci = '';
  password = '';
  /** El error que devolvió el servidor, campo por campo. */
  errores: Record<string, string> = {};

  ngOnInit(): void {
    if (!this.sinEmpleado) this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.servicio.mias().subscribe({
      next: (res) => {
        this.cargando = false;
        if (!res.success) return;
        this.solicitudes = res.data;
        this.cifras = this.contar(res.data);
      },
      error: (err) => {
        this.cargando = false;
        this.toast.error('No se cargó', mensajeErrorApi(err, 'No se pudieron leer tus solicitudes.'));
      },
    });
  }

  private contar(filas: Solicitud[]): CifraCabecera[] {
    const n = (e: string) => filas.filter((f) => f.estado === e).length;
    return [
      { icono: 'clock', valor: n('pendiente'), etiqueta: 'Esperando respuesta', tono: 'warning' },
      { icono: 'check_circle', valor: n('aprobada'), etiqueta: 'Aprobadas', tono: 'success' },
      { icono: 'circle_x', valor: n('rechazada'), etiqueta: 'Rechazadas', tono: 'muted' },
    ];
  }

  cuando(f: Solicitud): string {
    if (f.tipo === 'cambio_cuenta' && f.datos) {
      return `${f.datos.destino === 'cts' ? 'Cuenta CTS' : 'Cuenta de sueldo'}: ${f.datos.entidad} ${f.datos.numero}`;
    }
    return cuandoSolicitud(f);
  }

  alAccionar(e: { accion: string; fila: Solicitud }): void {
    if (e.accion === 'sustento') this.bajarSustento(e.fila);
    if (e.accion === 'cancelar') this.cancelar(e.fila);
  }

  private bajarSustento(f: Solicitud): void {
    this.servicio.miSustento(f.id).subscribe({
      next: (blob) => guardarArchivo(blob, f.sustento_nombre || 'sustento'),
      error: (err) => this.toast.error('No se bajó', mensajeErrorApi(err, 'No se pudo bajar el sustento.')),
    });
  }

  private cancelar(f: Solicitud): void {
    this.confirm
      .confirmar({
        titulo: 'Cancelar la solicitud',
        mensaje: `Se cancela tu ${f.tipo_texto.toLowerCase()}. Si la necesitas después, la vuelves a pedir.`,
        aceptarTexto: 'Sí, cancelarla',
        variante: 'danger',
      })
      .then((ok) => {
        if (!ok) return;
        this.servicio.cancelar(f.id).subscribe({
          next: () => {
            this.toast.success('Solicitud cancelada', f.tipo_texto);
            this.cargar();
          },
          error: (err) => this.toast.error('No se canceló', mensajeErrorApi(err, 'Inténtalo de nuevo.')),
        });
      });
  }

  // ── Pedir ──
  abrir(): void {
    this.tipo = 'permiso';
    this.fechaInicio = this.fechaFin = this.horaInicio = this.horaFin = '';
    this.conGoce = null;
    this.motivo = this.entidad = this.numero = this.cci = this.password = '';
    this.destino = 'cts';
    this.sustento = [];
    this.errores = {};
    this.modal = true;
  }

  cerrar(): void {
    this.modal = false;
    this.password = '';
  }

  elegirTipo(tipo: TipoSolicitud): void {
    this.tipo = tipo;
    this.errores = {};
    this.conGoce = null;
  }

  /** Lleva sustento obligatorio: el servidor lo exige igual; acá se avisa antes. */
  get sustentoObligatorio(): boolean {
    return this.tipo === 'descanso_medico' || this.tipo === 'justificar_falta' || this.tipo === 'cambio_cuenta'
      || (this.tipo === 'licencia' && this.conGoce === true);
  }

  get ayudaSustento(): string {
    switch (this.tipo) {
      case 'descanso_medico': return 'Tu certificado médico o CITT de EsSalud.';
      case 'justificar_falta': return 'Lo que respalda la falta: certificado, constancia, citación.';
      case 'cambio_cuenta': return 'La constancia o el voucher del banco donde se vea tu nombre y el número de cuenta.';
      case 'licencia': return this.conGoce ? 'Lo que respalda la licencia (partida de nacimiento, certificado, etc.).' : 'Opcional.';
      default: return 'Opcional: una cita, una citación.';
    }
  }

  /** Hoy en AAAA-MM-DD, para el tope de las fechas de una falta. */
  readonly hoy = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

  enviar(): void {
    if (this.enviando) return;
    this.errores = {};
    const f = new FormData();
    f.append('tipo', this.tipo);
    f.append('motivo', this.motivo.trim());

    if (this.tipo === 'cambio_cuenta') {
      f.append('destino', this.destino);
      f.append('entidad', this.entidad.trim());
      f.append('numero', this.numero.trim());
      if (this.destino === 'sueldo') f.append('cci', this.cci.trim());
      f.append('password', this.password);
    } else {
      f.append('fecha_inicio', this.fechaInicio);
      if (this.tipo === 'permiso') {
        f.append('hora_inicio', this.horaInicio);
        f.append('hora_fin', this.horaFin);
      } else {
        f.append('fecha_fin', this.fechaFin || this.fechaInicio);
      }
      if (this.tipo === 'permiso' || this.tipo === 'licencia') {
        if (this.conGoce === null) {
          this.errores = { con_goce: 'Indica si la pides con goce o sin goce de haber.' };
          return;
        }
        f.append('con_goce', this.conGoce ? '1' : '0');
      }
    }
    if (this.sustento[0]) f.append('sustento', this.sustento[0]);

    this.enviando = true;
    this.servicio.pedir(f).subscribe({
      next: (res) => {
        this.enviando = false;
        this.password = '';
        if (!res.success) return;
        this.toast.success('Solicitud enviada', 'Recursos Humanos la revisará. Te avisamos por la campanita cuando responda.');
        this.modal = false;
        this.cargar();
      },
      error: (err) => {
        this.enviando = false;
        this.password = '';
        const lista = err?.error?.errors as Record<string, string[]> | undefined;
        if (lista) {
          this.errores = Object.fromEntries(Object.entries(lista).map(([k, v]) => [k, v[0]]));
        }
        this.toast.error('No se envió', mensajeErrorApi(err, 'Revisa los datos e inténtalo de nuevo.'));
      },
    });
  }
}
