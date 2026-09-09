import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { GlobalWorkerOptions } from 'pdfjs-dist';
import { AuthService, BoletaService, MisDocumentosService, ToastService } from '../../../core/services';
import { Documento } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { VisorPdfComponent } from '../../../shared/components/visor-pdf/visor-pdf.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';

const MESES: Record<number, string> = {
  1: 'Enero', 2: 'Febrero', 3: 'Marzo', 4: 'Abril',
  5: 'Mayo', 6: 'Junio', 7: 'Julio', 8: 'Agosto',
  9: 'Setiembre', 10: 'Octubre', 11: 'Noviembre', 12: 'Diciembre'
};

/** Una fila de la tabla: la boleta ya masticada para pintarla. */
export interface BoletaRow {
  id: string;
  tipoDocumento: string;
  numeroDocumento: string;
  fechaEmision: string;
  mes: string;
  mesNum: number;
  montoTotal: number;
  anio: number;
  firmado: { fecha: string } | null;
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
  imports: [CommonModule, FormsModule, VisorPdfComponent, PageHeaderComponent, DataTableComponent],
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

  columnas: ColumnaTabla<BoletaRow>[] = [
    { campo: 'numeroDocumento', header: 'N.° de documento', ancho: '18%' },
    { campo: 'mes', header: 'Mes', ancho: '14%' },
    { campo: 'fechaEmision', header: 'Emisión', ancho: '14%' },
    { campo: 'montoTotal', header: 'Neto a pagar', tipo: 'moneda', ancho: '16%' },
    {
      campo: 'firmado', header: 'Estado', tipo: 'badge', ancho: '22%',
      formatear: (_v, fila) => (fila.firmado ? `Firmada el ${fila.firmado.fecha}` : 'Pendiente de firma'),
      badgeSeveridad: (_v, fila) => (fila.firmado ? 'success' : 'warning'),
    },
  ];

  acciones: AccionPersonalizada<BoletaRow>[] = [
    {
      id: 'ver', titulo: 'Abrir y descargar tu boleta en PDF', icono: 'receipt_long',
      // El PDF definitivo es el de la boleta ya firmada.
      visible: (b) => !!b.firmado,
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
      tipoDocumento: 'Boleta de pago',
      numeroDocumento: `BP-${anio}-${String(mes).padStart(2, '0')}`,
      fechaEmision: d.created_at ? this.formatFecha(d.created_at).split(' ')[0] : '',
      mes: MESES[mes] ?? `Mes ${mes}`,
      mesNum: mes,
      montoTotal: Number((d.planilla as any)?.total ?? 0),
      anio,
      firmado: d.estado_firma === 'firmado'
        ? { fecha: this.formatFecha(d.fecha_firma ?? d.created_at ?? '') }
        : null,
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

    this.boletaService.descargarMiBoleta(boleta.mesNum, String(boleta.anio)).subscribe({
      next: (blob) => {
        this.cargando = false;
        this.currentPdfBlob = blob;
        this.pdfUrl = URL.createObjectURL(blob);
        this.pdfBoletaName = `Boleta de ${boleta.mes} ${boleta.anio}`;
        this.showPdfModal = true;
      },
      error: () => {
        this.cargando = false;
        this.errorMsg = `No se pudo abrir la boleta de ${boleta.mes} ${boleta.anio}.`;
      },
    });
  }

  closePdfModal(): void {
    this.showPdfModal = false;
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
      this.toastService.info('Ya firmada', `Firmaste esta boleta el ${boleta.firmado.fecha}.`);
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
