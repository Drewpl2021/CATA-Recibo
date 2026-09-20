import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { GlobalWorkerOptions } from 'pdfjs-dist';
import { AuthService, BoletaService, MisDocumentosService, ToastService } from '../../../core/services';
import { Documento } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { VisorPdfComponent } from '../../../shared/components/visor-pdf/visor-pdf.component';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { nombreMes } from '../../../shared/constants';

/**
 * Una fila de la tabla: la boleta ya masticada para pintarla.
 *
 * Los cuatro hitos son el rastro de la boleta —cuándo se avisó, cuándo la
 * revisó, cuándo se la bajó, cuándo la firmó— y los guarda el backend en
 * cada paso. Antes esta interfaz ya los declaraba, pero llegaban siempre en
 * null porque nadie los anotaba: se veía una tabla con cuatro columnas
 * vacías para siempre.
 */
export interface BoletaRow {
  id: string;
  entidad: string;
  tipoDocumento: string;
  numeroDocumento: string;
  fechaEmision: string;
  mes: string;
  mesNum: number;
  montoTotal: number;
  anio: number;
  avisoEnviado: string | null;
  revisado: string | null;
  descargado: string | null;
  descargas: number;
  firmado: string | null;
  correo: string;
  celular: string;
}

/**
 * Mis Boletas: las boletas del trabajador de la sesión, año por año.
 *
 * El año NO se filtra en el navegador: se le pide al backend
 * (?tipo=boleta&anio=), que además corta la página y cuenta cuántas van
 * firmadas. Antes esta pantalla se traía todos los documentos de todos los
 * años del trabajador y se quedaba con los doce del año elegido.
 */
@Component({
  selector: 'app-mis-boletas',
  standalone: true,
  imports: [CommonModule, FormsModule, VisorPdfComponent, PageHeaderComponent, DataTableComponent, FormModalComponent],
  templateUrl: './mis-boletas.component.html',
  styleUrl: './mis-boletas.component.scss'
})
export class MisBoletasComponent implements OnInit {
  private authService = inject(AuthService);
  private boletaService = inject(BoletaService);
  private misDocumentosService = inject(MisDocumentosService);
  private toastService = inject(ToastService);
  private route = inject(ActivatedRoute);

  anios: number[] = [];
  anioElegido = new Date().getFullYear();
  boletas: BoletaRow[] = [];
  cargando = false;
  errorMsg = '';
  userName = '';

  /** Doce filas: un año entero cabe en una página. */
  readonly TAMANO_PAGINA = 12;
  pagina = 0;
  total = 0;
  pendientes = 0;
  firmados = 0;

  // Visor de PDF
  showPdfModal = false;
  pdfUrl: string | null = null;
  pdfBoletaName = '';
  private currentPdfBlob: Blob | null = null;

  // Modal de firma
  showSignModal = false;
  signPassword = '';
  boletaAFirmar: BoletaRow | null = null;
  signErrorMsg = '';
  isSigning = false;
  private firmaPendienteId: string | null = null;

  /** Las cifras de la cabecera: del año entero, no de la página. */
  get cifras(): CifraCabecera[] {
    return [
      { icono: 'receipt_long', valor: this.total, etiqueta: 'Boletas del año', tono: 'brand' },
      { icono: 'signature', valor: this.firmados, etiqueta: 'Firmadas', tono: 'success' },
      { icono: 'clock', valor: this.pendientes, etiqueta: 'Por firmar', tono: 'warning' },
    ];
  }

  /**
   * Las columnas del seguimiento van en el orden en que pasan las cosas
   * —avisar, revisar, descargar, firmar—, para que la fila se lea de
   * izquierda a derecha como la historia de esa boleta.
   */
  columnas: ColumnaTabla<BoletaRow>[] = [
    { campo: 'entidad', header: 'Entidad', ancho: '9%' },
    { campo: 'mes', header: 'Mes', ancho: '9%' },
    { campo: 'montoTotal', header: 'Neto a pagar', tipo: 'moneda', ancho: '11%' },
    {
      campo: 'avisoEnviado', header: 'Aviso enviado', tipo: 'hito', ancho: '17%',
      // El correo va DENTRO del hito: es a dónde se mandó ese aviso, no el
      // que tenga hoy la cuenta.
      hitoDetalle: (b) => b.correo,
    },
    { campo: 'revisado', header: 'Revisado', tipo: 'hito', ancho: '13%' },
    {
      campo: 'descargado', header: 'Descargado', tipo: 'hito', ancho: '15%',
      hitoDetalle: (b) => (b.descargas > 1 ? `${b.descargas} descargas` : null),
    },
    { campo: 'firmado', header: 'Firmado', tipo: 'hito', ancho: '13%' },
    { campo: 'celular', header: 'Celular', ancho: '10%' },
  ];

  acciones: AccionPersonalizada<BoletaRow>[] = [
    {
      // Abrirla se puede siempre: firmar algo que no se ha podido leer no
      // prueba nada. Bajársela, ya firmada. Al abrirla queda anotado que la
      // revisó, que antes había que marcarlo a mano y nadie lo hacía.
      id: 'ver', titulo: 'Abrir tu boleta en PDF', icono: 'receipt_long',
    },
    {
      id: 'firmar', titulo: 'Firmar esta boleta', icono: 'signature', severidad: 'success',
      visible: (b) => !b.firmado,
    },
  ];

  ngOnInit(): void {
    // El motor de pdf.js sale de nuestro propio servidor, no de un CDN.
    GlobalWorkerOptions.workerSrc = 'assets/pdf/pdf.worker.min.mjs';

    const actual = new Date().getFullYear();
    for (let a = actual; a >= actual - 5; a--) this.anios.push(a);

    const user = this.authService.getUser();
    this.userName = user?.name ? user.name.split(' ')[0] : 'Usuario';

    // La campanita manda acá con ?firmar=<id> para abrir la firma de una
    // boleta concreta sin que el trabajador tenga que buscarla.
    this.route.queryParams.subscribe((params) => {
      if (params['firmar']) {
        this.firmaPendienteId = params['firmar'];
        this.abrirFirmaPendiente();
      }
    });

    this.cargar();
  }

  alCambiarAnio(): void {
    this.pagina = 0;
    this.cargar();
  }

  irAPagina(pagina: number): void {
    this.pagina = pagina;
    this.cargar();
  }

  cargar(): void {
    const user = this.authService.getUser();
    if (!user?.empleado_id) {
      this.errorMsg = 'Esta cuenta no tiene un trabajador vinculado, así que no hay boletas que mostrar.';
      return;
    }

    this.cargando = true;
    this.errorMsg = '';
    this.misDocumentosService
      .paginar({ tipo: 'boleta', anio: this.anioElegido, page: this.pagina, size: this.TAMANO_PAGINA })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.boletas = res.data.content.map((d) => this.aFila(d));
            this.total = res.data.totalElements;
            this.pendientes = res.data.pendientes ?? 0;
            this.firmados = res.data.firmados ?? 0;
          }
          this.cargando = false;
          this.abrirFirmaPendiente();
        },
        error: () => {
          this.cargando = false;
          this.errorMsg = 'No se pudieron cargar tus boletas. Revisa tu conexión e inténtalo de nuevo.';
        },
      });
  }

  private aFila(d: Documento): BoletaRow {
    const mes = d.planilla?.mes ?? 0;
    const anio = d.planilla?.anio ?? 0;
    return {
      id: d.id,
      // La sede a la que pertenece el trabajador. Si su ficha no la tiene,
      // se deja en blanco antes que inventar un nombre.
      entidad: d.empleado?.sede?.nombre ?? '—',
      tipoDocumento: 'Boleta de pago',
      numeroDocumento: `BP-${anio}-${String(mes).padStart(2, '0')}`,
      fechaEmision: d.created_at ? this.formatFecha(d.created_at).split(' ')[0] : '',
      mes: nombreMes(mes),
      mesNum: mes,
      montoTotal: Number((d.planilla as any)?.total ?? 0),
      anio,
      avisoEnviado: d.fecha_aviso ?? null,
      revisado: d.fecha_visto ?? null,
      descargado: d.fecha_descarga ?? null,
      descargas: d.descargas ?? 0,
      // El hito de la firma es la fecha; el estado_firma manda por si acaso
      // hubiera una firma vieja sin fecha guardada.
      firmado: d.estado_firma === 'firmado' ? (d.fecha_firma ?? d.created_at ?? null) : null,
      // El correo del aviso va congelado en el documento; si esa boleta es
      // de antes de que se anotara, se cae al de la cuenta.
      correo: d.aviso_correo ?? d.empleado?.usuario?.email ?? '—',
      celular: d.empleado?.telefono ?? '—',
    };
  }

  /** Si veníamos de la campanita, abrir la firma de esa boleta. */
  private abrirFirmaPendiente(): void {
    if (!this.firmaPendienteId || this.boletas.length === 0) return;
    const boleta = this.boletas.find((b) => b.id === this.firmaPendienteId);
    this.firmaPendienteId = null;
    if (boleta && !boleta.firmado) this.firmarBoleta(boleta);
  }

  alAccionar(evento: { accion: string; fila: BoletaRow }): void {
    if (evento.accion === 'ver') this.verBoleta(evento.fila);
    if (evento.accion === 'firmar') this.firmarBoleta(evento.fila);
  }

  verBoleta(boleta: BoletaRow): void {
    this.cargando = true;
    this.errorMsg = '';

    this.boletaService.descargarMiBoleta(boleta.mesNum, String(boleta.anio), true).subscribe({
      next: (blob) => {
        this.cargando = false;
        this.currentPdfBlob = blob;
        this.pdfUrl = URL.createObjectURL(blob);
        this.pdfBoletaName = `Boleta de ${boleta.mes} ${boleta.anio}`;
        this.boletaAbierta = boleta;
        this.showPdfModal = true;
      },
      error: () => {
        this.cargando = false;
        this.errorMsg = `No se pudo abrir la boleta de ${boleta.mes} ${boleta.anio}.`;
      },
    });
  }

  /** La boleta que está abierta en el visor: de ella depende si se baja o no. */
  boletaAbierta: BoletaRow | null = null;

  closePdfModal(): void {
    this.showPdfModal = false;
    this.boletaAbierta = null;
    // Abrirla dejó anotado que la revisó: la tabla tiene que enterarse.
    this.cargar();
    if (this.pdfUrl) URL.revokeObjectURL(this.pdfUrl);
    this.pdfUrl = null;
    this.currentPdfBlob = null;
  }

  descargarPdfDirecto(): void {
    if (!this.currentPdfBlob || !this.pdfUrl) return;
    const a = document.createElement('a');
    a.href = this.pdfUrl;
    a.download = `${this.pdfBoletaName.replace(/ /g, '_')}.pdf`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  firmarBoleta(boleta: BoletaRow): void {
    if (boleta.firmado) {
      this.toastService.info('Ya firmada', `Firmaste esta boleta el ${this.formatFecha(boleta.firmado)}.`);
      return;
    }
    this.boletaAFirmar = boleta;
    this.signPassword = '';
    this.signErrorMsg = '';
    this.showSignModal = true;
    document.body.style.overflow = 'hidden';
  }

  closeSignModal(): void {
    this.showSignModal = false;
    this.boletaAFirmar = null;
    this.signPassword = '';
    this.signErrorMsg = '';
    this.isSigning = false;
    document.body.style.overflow = '';
  }

  confirmarFirma(): void {
    if (!this.boletaAFirmar) return;
    if (!this.signPassword) {
      this.signErrorMsg = 'Ingresa tu contraseña para firmar.';
      return;
    }

    this.isSigning = true;
    this.signErrorMsg = '';

    this.misDocumentosService.firmar(this.boletaAFirmar.id, this.signPassword).subscribe({
      next: (res) => {
        this.isSigning = false;
        if (res.success) {
          this.toastService.success('Boleta firmada', `Tu boleta de ${this.boletaAFirmar!.mes} quedó firmada.`);
          this.closeSignModal();
          this.cargar();
          return;
        }
        this.signErrorMsg = res.message || 'No se pudo firmar la boleta.';
      },
      error: (err) => {
        this.isSigning = false;
        this.signErrorMsg = mensajeErrorApi(err, 'Contraseña incorrecta o el servidor no respondió.');
      },
    });
  }

  private formatFecha(isoDate: string): string {
    const d = new Date(isoDate);
    if (isNaN(d.getTime())) return '';
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${dd}/${mm}/${d.getFullYear()} ${hh}:${min}`;
  }
}
