import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { SolicitudService, ToastService } from '../../../core/services';
import type { PreviaSolicitud, Solicitud } from '../../../core/services';
import { guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import { AvatarComponent } from '../../../shared/components/avatar/avatar.component';
import { CeldaTablaDirective } from '../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FiltrosComponent } from '../../../shared/components/filtros/filtros.component';
import { CampoFiltro, ValoresFiltro } from '../../../shared/components/filtros/filtros.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { PistaDirective } from '../../../shared/directives/pista.directive';
import {
  ESTADO_SOLICITUD_SEVERIDAD,
  ESTADO_SOLICITUD_TEXTO,
  TIPOS_SOLICITUD,
  cuandoSolicitud,
} from '../../../shared/constants';

/**
 * Solicitudes: la bandeja de RR.HH. con lo que pidió el personal.
 *
 * Al abrir una se ve, ANTES de aprobar, lo que va a pasar en la planilla y
 * la CTS (lo calcula el servidor: EfectosDeSolicitud::previa). Lo ya pagado
 * no se toca, y la pantalla lo dice en vez de esconderlo.
 */
@Component({
  selector: 'app-solicitudes-list',
  standalone: true,
  imports: [FormsModule, PageHeaderComponent, DataTableComponent, CeldaTablaDirective, AvatarComponent, FiltrosComponent, FormModalComponent, IconComponent, PistaDirective],
  templateUrl: './solicitudes-list.component.html',
})
export class SolicitudesListComponent implements OnInit {
  private readonly servicio = inject(SolicitudService);
  private readonly toast = inject(ToastService);
  private readonly route = inject(ActivatedRoute);

  solicitudes: Solicitud[] = [];
  cargando = false;
  cifras: CifraCabecera[] = [];

  filtros: ValoresFiltro = { estado: 'pendiente' };
  readonly camposFiltro: CampoFiltro[] = [
    {
      clave: 'estado', etiqueta: 'Estado', tipo: 'opciones', vacio: 'Todas',
      opciones: Object.entries(ESTADO_SOLICITUD_TEXTO).map(([valor, etiqueta]) => ({ valor, etiqueta })),
    },
    {
      clave: 'tipo', etiqueta: 'Tipo', tipo: 'opciones', vacio: 'Todos',
      opciones: TIPOS_SOLICITUD.map((t) => ({ valor: t.valor, etiqueta: t.titulo })),
    },
  ];

  readonly columnas: ColumnaTabla<Solicitud>[] = [
    { campo: 'empleado.nombre', header: 'Trabajador', ancho: '24%' },
    { campo: 'tipo_texto', header: 'Solicitud', ancho: '16%' },
    { campo: 'fecha_inicio', header: 'Cuándo', ancho: '22%', formatear: (_v, f) => this.cuando(f) },
    { campo: 'pedida', header: 'Pedida', tipo: 'fecha-hora', ancho: '13%' },
    {
      campo: 'estado', header: 'Estado', tipo: 'badge', ancho: '11%',
      formatear: (v) => ESTADO_SOLICITUD_TEXTO[v] ?? v,
      badgeSeveridad: (v) => ESTADO_SOLICITUD_SEVERIDAD[v] ?? 'secondary',
    },
  ];

  readonly acciones: AccionPersonalizada<Solicitud>[] = [
    { id: 'revisar', titulo: 'Revisar la solicitud', icono: 'eye', etiqueta: 'Revisar' },
  ];

  // ── Revisar una ──
  abierta: Solicitud | null = null;
  previa: PreviaSolicitud | null = null;
  cargandoPrevia = false;
  conGoce: boolean | null = null;
  restarFaltas = false;
  respuesta = '';
  enviando = false;

  ngOnInit(): void {
    const estado = this.route.snapshot.queryParamMap.get('estado');
    if (estado !== null) this.filtros = estado ? { estado } : {};
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.servicio.todas(this.filtros).subscribe({
      next: (res) => {
        this.cargando = false;
        if (!res.success) return;
        this.solicitudes = res.data.filas;
        this.cifras = [
          { icono: 'clock', valor: res.data.pendientes, etiqueta: 'Esperan respuesta', tono: 'warning' },
          { icono: 'layers', valor: res.data.filas.length, etiqueta: 'En la lista', tono: 'brand' },
        ];
      },
      error: (err) => {
        this.cargando = false;
        this.toast.error('No se cargó', mensajeErrorApi(err, 'No se pudieron leer las solicitudes.'));
      },
    });
  }

  cuando(f: Solicitud): string {
    if (f.tipo === 'cambio_cuenta' && f.datos) {
      return `${f.datos.destino === 'cts' ? 'Cuenta CTS' : 'Cuenta de sueldo'}: ${f.datos.entidad} ${f.datos.numero}`;
    }
    return cuandoSolicitud(f);
  }

  alAccionar(e: { accion: string; fila: Solicitud }): void {
    if (e.accion === 'revisar') this.abrir(e.fila);
  }

  abrir(s: Solicitud): void {
    this.abierta = s;
    this.previa = null;
    this.conGoce = s.con_goce;
    this.restarFaltas = false;
    this.respuesta = '';
    this.cargarPrevia();
  }

  cerrar(): void {
    this.abierta = null;
    this.previa = null;
  }

  /** Lo que pasaría al aprobar; con o sin goce cambia la respuesta. */
  cargarPrevia(): void {
    if (!this.abierta) return;
    this.cargandoPrevia = true;
    const goce = this.abierta.tipo === 'permiso' || this.abierta.tipo === 'licencia' ? this.conGoce : null;
    this.servicio.previa(this.abierta.id, goce).subscribe({
      next: (res) => {
        this.cargandoPrevia = false;
        if (res.success) {
          this.previa = res.data;
          this.restarFaltas = res.data.faltas_cts.some((f) => f.se_puede);
        }
      },
      error: (err) => {
        this.cargandoPrevia = false;
        this.toast.error('No se cargó', mensajeErrorApi(err, 'No se pudo revisar la solicitud.'));
      },
    });
  }

  verSustento(): void {
    const s = this.abierta;
    if (!s) return;
    this.servicio.sustento(s.id).subscribe({
      next: (blob) => {
        guardarArchivo(blob, `Sustento - ${s.empleado?.nombre ?? ''} - ${s.tipo_texto}.${this.extension(blob.type)}`);
        // Abrirlo queda anotado: con eso se habilita aprobar un cambio de cuenta.
        if (this.previa && !this.previa.sustento_visto) this.previa = { ...this.previa, sustento_visto: true };
      },
      error: (err) => this.toast.error('No se abrió', mensajeErrorApi(err, 'No se pudo abrir el sustento.')),
    });
  }

  private extension(tipo: string): string {
    return tipo === 'application/pdf' ? 'pdf' : tipo === 'image/png' ? 'png' : 'jpg';
  }

  aprobar(): void {
    const s = this.abierta;
    if (!s || this.enviando) return;
    this.enviando = true;
    this.servicio.aprobar(s.id, {
      con_goce: s.tipo === 'permiso' || s.tipo === 'licencia' ? this.conGoce : null,
      restar_faltas: this.restarFaltas,
      respuesta: this.respuesta.trim() || null,
    }).subscribe({
      next: () => {
        this.enviando = false;
        this.toast.success('Solicitud aprobada', `${s.tipo_texto} de ${s.empleado?.nombre}. Le llegó el aviso.`);
        this.cerrar();
        this.cargar();
      },
      error: (err) => {
        this.enviando = false;
        this.toast.error('No se aprobó', mensajeErrorApi(err, 'Inténtalo de nuevo.'));
      },
    });
  }

  rechazar(): void {
    this.resolver('rechazar');
  }

  anular(): void {
    this.resolver('anular');
  }

  private resolver(como: 'rechazar' | 'anular'): void {
    const s = this.abierta;
    if (!s || this.enviando) return;
    if (this.respuesta.trim().length < 5) {
      this.toast.warning('Falta el motivo', `Escribe por qué la ${como === 'rechazar' ? 'rechazas' : 'anulas'}: le llega al trabajador.`);
      return;
    }
    this.enviando = true;
    const pedido = como === 'rechazar' ? this.servicio.rechazar(s.id, this.respuesta.trim()) : this.servicio.anular(s.id, this.respuesta.trim());
    pedido.subscribe({
      next: (res) => {
        this.enviando = false;
        const avisos = (res.data as { avisos?: string[] }).avisos ?? [];
        this.toast.success(como === 'rechazar' ? 'Solicitud rechazada' : 'Solicitud anulada',
          avisos.length ? avisos.join(' ') : `Le llegó el aviso a ${s.empleado?.nombre}.`);
        this.cerrar();
        this.cargar();
      },
      error: (err) => {
        this.enviando = false;
        this.toast.error('No se guardó', mensajeErrorApi(err, 'Inténtalo de nuevo.'));
      },
    });
  }
}
