import { Component, OnInit, inject } from '@angular/core';

import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { GlobalWorkerOptions } from 'pdfjs-dist';
import { AuthService, BoletaService, MisDocumentosService, ToastService } from '../../../core/services';
import { Documento } from '../../../core/models';
import { guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import { VisorPdfComponent } from '../../../shared/components/visor-pdf/visor-pdf.component';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { CeldaTablaDirective } from '../../../shared/components/data-table/celda-tabla.directive';
import { nombreMes } from '../../../shared/constants';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { AlCuerpoDirective } from '../../../shared/directives/al-cuerpo.directive';
import { PistaDirective } from '../../../shared/directives/pista.directive';
import { DocumentoService } from '../../../core/services/planilla/documento.service';

/** Una boleta u hoja en la lista de «Descargar varias». */
interface OpcionJuntar {
  id: string;
  titulo: string;
  detalle: string;
  /** Ya tiene su conformidad: se puede bajar. */
  sePuede: boolean;
  marcada: boolean;
}

/**
 * Una hoja de CTS o de gratificación: le llega aquí, junto a sus boletas,
 * cuando el colegio ya la firmó digitalmente. La puede leer antes de dar su
 * conformidad (como un contrato) y bajarla después.
 */
export interface HojaRow {
  id: string;
  /** «Hoja de CTS», «Hoja de gratificación». */
  documento: string;
  /** «Depósito de noviembre 2026», «Fiestas Patrias (julio 2026)». */
  periodo: string;
  avisoEnviado: string | null;
  revisado: string | null;
  descargado: string | null;
  descargas: number;
  firmado: string | null;
  correo: string;
}

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
  /** Boleta de un año anterior armada para registro: ya se firmó a mano. */
  enPapel: boolean;
  /**
   * Viene firmada digitalmente por el colegio (ReFirma): él no la firma, da
   * su conformidad, y eso no toca el PDF.
   */
  firmadaPorElColegio: boolean;
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
  imports: [IconComponent, FormsModule, VisorPdfComponent, PageHeaderComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent, AlCuerpoDirective, PistaDirective],
  templateUrl: './mis-boletas.component.html',
  styleUrl: './mis-boletas.component.scss'
})
export class MisBoletasComponent implements OnInit {
  private authService = inject(AuthService);
  private boletaService = inject(BoletaService);
  private misDocumentosService = inject(MisDocumentosService);
  private documentoService = inject(DocumentoService);
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

  // Modal de firma (la boleta o una hoja)
  showSignModal = false;
  signPassword = '';
  boletaAFirmar: BoletaRow | null = null;
  hojaAFirmar: HojaRow | null = null;
  /** «tu boleta de octubre 2026», «tu hoja de CTS del depósito de noviembre 2026». */
  queFirma = '';
  signErrorMsg = '';
  isSigning = false;
  private firmaPendienteId: string | null = null;

  /** Las cifras de la cabecera: del año entero, no de la página. */
  get cifras(): CifraCabecera[] {
    return [
      { icono: 'receipt_long', valor: this.total, etiqueta: 'Boletas del año', tono: 'brand' },
      { icono: 'signature', valor: this.firmados, etiqueta: 'Con conformidad', tono: 'success' },
      { icono: 'clock', valor: this.pendientes, etiqueta: 'Sin conformidad', tono: 'warning' },
    ];
  }

  /**
   * Las columnas del seguimiento van en el orden en que pasan las cosas
   * —avisar, revisar, descargar, firmar—, para que la fila se lea de
   * izquierda a derecha como la historia de esa boleta.
   */
  columnas: ColumnaTabla<BoletaRow>[] = [
    { campo: 'mes', header: 'Mes', ancho: '12%' },
    { campo: 'montoTotal', header: 'Neto a pagar', tipo: 'moneda', ancho: '14%' },
    {
      campo: 'avisoEnviado', header: 'Aviso enviado', tipo: 'hito', ancho: '20%',
      // El correo va DENTRO del hito: es a dónde se mandó ese aviso, no el
      // que tenga hoy la cuenta.
      hitoDetalle: (b) => b.correo,
    },
    { campo: 'revisado', header: 'Revisado', tipo: 'hito', ancho: '17%' },
    {
      campo: 'descargado', header: 'Descargado', tipo: 'hito', ancho: '18%',
      hitoDetalle: (b) => (b.descargas > 1 ? `${b.descargas} descargas` : null),
    },
    { campo: 'firmado', header: 'Conformidad', tipo: 'hito', ancho: '17%' },
  ];

  acciones: AccionPersonalizada<BoletaRow>[] = [
    {
      // Se ve y se descarga DESPUÉS de firmarla con la contraseña: así lo
      // pidió el colegio. Antes de firmar solo está el botón de firmar (el
      // neto ya se ve en la tabla). Al abrirla queda anotado que la revisó.
      id: 'ver', titulo: 'Ver y descargar tu boleta', icono: 'receipt_long',
      visible: (b) => !!b.firmado || b.enPapel,
    },
    {
      // Él no firma la boleta (la firma digitalmente el colegio): da su
      // conformidad con su contraseña, y su boleta no cambia.
      id: 'firmar', titulo: 'Dar tu conformidad: confirma que recibiste esta boleta', icono: 'check_circle',
      etiqueta: 'Dar conformidad', severidad: 'success',
      visible: (b) => !b.firmado && !b.enPapel,
    },
  ];

  // ── Bajar varias juntas: .zip o un solo PDF ──
  modalJuntar = false;
  juntando = false;
  formatoJuntar: 'zip' | 'pdf' = 'zip';
  /** Lo que se ofrece para juntar; se arma al abrir, no en un getter. */
  opcionesJuntar: OpcionJuntar[] = [];
  cuantasMarcadas = 0;

  abrirJuntar(): void {
    this.opcionesJuntar = [
      ...this.boletas.map((b) => ({
        id: b.id, titulo: `Boleta de ${b.mes}`, detalle: `${b.anio}`,
        sePuede: !!b.firmado || b.enPapel, marcada: !!b.firmado || b.enPapel,
      })),
      ...this.hojas.map((h) => ({
        id: h.id, titulo: h.documento, detalle: h.periodo, sePuede: !!h.firmado, marcada: !!h.firmado,
      })),
    ];
    this.formatoJuntar = 'zip';
    this.contarMarcadas();
    this.modalJuntar = true;
  }

  contarMarcadas(): void {
    this.cuantasMarcadas = this.opcionesJuntar.filter((o) => o.sePuede && o.marcada).length;
  }

  marcarTodasJuntar(marcar: boolean): void {
    this.opcionesJuntar.forEach((o) => (o.marcada = o.sePuede && marcar));
    this.contarMarcadas();
  }

  juntar(): void {
    const ids = this.opcionesJuntar.filter((o) => o.sePuede && o.marcada).map((o) => o.id);
    if (!ids.length || this.juntando) return;
    this.juntando = true;
    this.misDocumentosService.juntar(ids, this.formatoJuntar).subscribe({
      next: (res) => {
        this.juntando = false;
        this.modalJuntar = false;
        const nombre = `Mis documentos ${this.anioElegido}.${this.formatoJuntar}`;
        guardarArchivo(res.body!, nombre);
        const fuera = Number(res.headers.get('X-No-Incluidas') ?? 0);
        this.toastService.success(
          'Descarga lista',
          fuera
            ? `${ids.length - fuera} documento(s) en ${nombre}. ${fuera} no se pudieron incluir${this.formatoJuntar === 'zip' ? ': el motivo va en NO_INCLUIDAS.txt' : ''}.`
            : `${ids.length} documento(s) en ${nombre}.`,
        );
        this.cargar();
      },
      error: async (err) => {
        this.juntando = false;
        // El error llega como Blob (se pidió un archivo): se lee para mostrar su mensaje.
        let mensaje = 'No se pudieron bajar. Inténtalo de nuevo.';
        try {
          if (err?.error instanceof Blob) mensaje = JSON.parse(await err.error.text())?.message ?? mensaje;
        } catch {
          /* se queda el mensaje de siempre */
        }
        this.toastService.error('No se descargó', mensaje);
      },
    });
  }

  // ── Sus hojas de CTS y de gratificación del año ──
  hojas: HojaRow[] = [];
  cargandoHojas = false;

  columnasHojas: ColumnaTabla<HojaRow>[] = [
    { campo: 'documento', header: 'Documento', ancho: '26%' },
    { campo: 'avisoEnviado', header: 'Aviso enviado', tipo: 'hito', ancho: '20%', hitoDetalle: (h) => h.correo },
    { campo: 'revisado', header: 'Revisado', tipo: 'hito', ancho: '17%' },
    {
      campo: 'descargado', header: 'Descargado', tipo: 'hito', ancho: '18%',
      hitoDetalle: (h) => (h.descargas > 1 ? `${h.descargas} descargas` : null),
    },
    { campo: 'firmado', header: 'Conformidad', tipo: 'hito', ancho: '17%' },
  ];

  accionesHojas: AccionPersonalizada<HojaRow>[] = [
    // La hoja se lee antes de dar la conformidad; se baja después.
    { id: 'ver', titulo: 'Ver tu hoja', icono: 'receipt_long' },
    {
      id: 'firmar', titulo: 'Dar tu conformidad: confirma que recibiste esta hoja', icono: 'check_circle',
      etiqueta: 'Dar conformidad', severidad: 'success',
      visible: (h) => !h.firmado,
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
    this.cargarHojas();
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

  /** Sus hojas de CTS y de gratificación del año elegido (llegan ya firmadas por el colegio). */
  private cargarHojas(): void {
    this.cargandoHojas = true;
    this.misDocumentosService.paginar({ tipo: 'cts,gratificacion,certificado_5ta', anio: this.anioElegido, page: 0, size: 20 }).subscribe({
      next: (res) => {
        this.cargandoHojas = false;
        if (res.success) this.hojas = res.data.content.map((d) => this.aHoja(d));
        this.abrirFirmaPendiente();
      },
      // Si fallan, la sección queda vacía: las boletas siguen igual.
      error: () => { this.cargandoHojas = false; },
    });
  }

  private aHoja(d: Documento): HojaRow {
    const mes = d.periodo_mes ?? 0;
    const anio = d.periodo_anio ?? 0;
    const esCts = d.tipo === 'cts';
    if (d.tipo === 'certificado_5ta') {
      return {
        id: d.id, documento: 'Certificado de retenciones de 5ta', periodo: `Año ${anio}`,
        avisoEnviado: d.fecha_aviso ?? null, revisado: d.fecha_visto ?? null, descargado: d.fecha_descarga ?? null,
        descargas: d.descargas ?? 0, firmado: d.estado_firma === 'firmado' ? (d.fecha_firma ?? d.created_at ?? null) : null,
        correo: d.aviso_correo ?? d.empleado?.usuario?.email ?? '—',
      };
    }
    const grati = mes === 7 ? `Fiestas Patrias (julio ${anio})` : mes === 12 ? `Navidad (diciembre ${anio})` : `trunca (${nombreMes(mes).toLowerCase()} ${anio})`;
    return {
      id: d.id,
      documento: esCts ? 'Hoja de CTS' : 'Hoja de gratificación',
      periodo: esCts ? `Depósito de ${nombreMes(mes).toLowerCase()} ${anio}` : grati,
      avisoEnviado: d.fecha_aviso ?? null,
      revisado: d.fecha_visto ?? null,
      descargado: d.fecha_descarga ?? null,
      descargas: d.descargas ?? 0,
      firmado: d.estado_firma === 'firmado' ? (d.fecha_firma ?? d.created_at ?? null) : null,
      correo: d.aviso_correo ?? d.empleado?.usuario?.email ?? '—',
    };
  }

  alAccionarHoja(evento: { accion: string; fila: HojaRow }): void {
    if (evento.accion === 'ver') this.verHoja(evento.fila);
    if (evento.accion === 'firmar') this.firmarHoja(evento.fila);
  }

  verHoja(hoja: HojaRow): void {
    this.documentoService.ver(hoja.id).subscribe({
      next: (blob) => {
        this.currentPdfBlob = blob;
        this.pdfUrl = URL.createObjectURL(blob);
        this.pdfBoletaName = `${hoja.documento} · ${hoja.periodo}`;
        this.boletaAbierta = null;
        this.hojaAbierta = hoja;
        this.showPdfModal = true;
      },
      error: (err) => this.toastService.error('No se abrió', mensajeErrorApi(err, 'No se pudo abrir tu hoja.')),
    });
  }

  /** La hoja que está abierta en el visor: se baja recién con su conformidad. */
  hojaAbierta: HojaRow | null = null;

  /** En el visor: se puede bajar lo que ya tiene su conformidad (o la boleta de registro). */
  get sePuedeBajar(): boolean {
    return !!(this.boletaAbierta?.firmado || this.boletaAbierta?.enPapel || this.hojaAbierta?.firmado);
  }

  firmarHoja(hoja: HojaRow): void {
    if (hoja.firmado) {
      this.toastService.info('Ya diste tu conformidad', `La diste el ${this.formatFecha(hoja.firmado)}.`);
      return;
    }
    this.boletaAFirmar = null;
    this.hojaAFirmar = hoja;
    this.queFirma = `tu ${hoja.documento.replace('Hoja', 'hoja')} (${hoja.periodo.charAt(0).toLowerCase() + hoja.periodo.slice(1)})`;
    this.signPassword = '';
    this.signErrorMsg = '';
    this.showSignModal = true;
    document.body.style.overflow = 'hidden';
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
      enPapel: d.estado_firma === 'en_papel',
      firmadaPorElColegio: d.firma_colegio === 'completa',
      // El correo del aviso va congelado en el documento; si esa boleta es
      // de antes de que se anotara, se cae al de la cuenta.
      correo: d.aviso_correo ?? d.empleado?.usuario?.email ?? '—',
      celular: d.empleado?.telefono ?? '—',
    };
  }

  /** Si veníamos de la campanita, abrir la firma de esa boleta. */
  private abrirFirmaPendiente(): void {
    if (!this.firmaPendienteId) return;
    const boleta = this.boletas.find((b) => b.id === this.firmaPendienteId);
    const hoja = this.hojas.find((h) => h.id === this.firmaPendienteId);
    if (!boleta && !hoja) return;
    this.firmaPendienteId = null;
    if (boleta && !boleta.firmado && !boleta.enPapel) this.firmarBoleta(boleta);
    if (hoja && !hoja.firmado) this.firmarHoja(hoja);
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
        this.hojaAbierta = null;
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
    this.hojaAbierta = null;
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
    if (boleta.enPapel) {
      this.toastService.info('Firmada en papel', 'Esta boleta es de registro: ya la firmaste a mano en su momento.');
      return;
    }
    this.boletaAFirmar = boleta;
    this.hojaAFirmar = null;
    this.queFirma = `tu boleta de ${boleta.mes} ${boleta.anio}`;
    this.signPassword = '';
    this.signErrorMsg = '';
    this.showSignModal = true;
    document.body.style.overflow = 'hidden';
  }

  closeSignModal(): void {
    this.showSignModal = false;
    this.boletaAFirmar = null;
    this.hojaAFirmar = null;
    this.signPassword = '';
    this.signErrorMsg = '';
    this.isSigning = false;
    document.body.style.overflow = '';
  }

  confirmarFirma(): void {
    const id = this.boletaAFirmar?.id ?? this.hojaAFirmar?.id;
    if (!id) return;
    if (!this.signPassword) {
      this.signErrorMsg = 'Ingresa tu contraseña para dar tu conformidad.';
      return;
    }

    this.isSigning = true;
    this.signErrorMsg = '';

    this.misDocumentosService.firmar(id, this.signPassword).subscribe({
      next: (res) => {
        this.isSigning = false;
        if (res.success && this.hojaAFirmar) {
          const hoja = this.hojaAFirmar;
          this.toastService.success('Conformidad registrada', `Diste tu conformidad a ${this.queFirma}. Ya puedes descargarla.`);
          this.closeSignModal();
          this.cargarHojas();
          this.verHoja({ ...hoja, firmado: new Date().toISOString() });
          return;
        }
        if (res.success) {
          const firmada = this.boletaAFirmar!;
          this.toastService.success('Conformidad registrada', `Diste tu conformidad a tu boleta de ${firmada.mes}. Ya puedes verla y descargarla.`);
          this.closeSignModal();
          // Recién firmada, se abre sola —con su botón de descargar—: es lo
          // que la persona quiere ver.
          this.verBoleta({ ...firmada, firmado: new Date().toISOString() });
          return;
        }
        this.signErrorMsg = res.message || 'No se pudo registrar tu conformidad.';
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
