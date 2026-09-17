import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { ConfirmService, DocumentoService, ExpedienteService, ToastService } from '../../../core/services';
import { Contrato, ContratoDelExpediente, Documento, Expediente } from '../../../core/models';
import {
  TIPOS_DOCUMENTO_SUBIBLES,
  diasHasta,
  esDocumentoAnterior,
  esPdf,
  estadoFirmaLegible,
  fechaDeDia,
  fechaLegible,
  formatoDocumento,
  guardarArchivo,
  mensajeErrorApi,
  nombreArchivoDocumento,
  nombreDocumento,
  severidadFirma,
} from '../../../core/utils';
import { ESTADO_CONTRATO_OPCIONES, TIPO_CONTRATO_CONTRATO_OPCIONES, nombreMes } from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { SelectorArchivoComponent } from '../../../shared/components/selector-archivo/selector-archivo.component';
import { VisorDocumentoComponent } from '../../../shared/components/visor-documento/visor-documento.component';

/**
 * El expediente de un trabajador, ordenado como lo ordena RR.HH. en su
 * archivador:
 *
 *   Hoja de vida   la vigente, y las que reemplazó
 *   Contratos      del más reciente al más antiguo, cada uno con los
 *                  documentos que se le adjuntaron (el contrato firmado)
 *   Boletas        mes a mes, con si la revisó, la descargó y la firmó
 *   Otros          CTS, comprobantes, lo que se subió sin contrato
 *
 * Subir, ver, descargar y quitar van por los caminos de siempre
 * (DocumentoService); esta pantalla solo los junta alrededor de la persona.
 */
@Component({
  selector: 'app-expediente',
  standalone: true,
  imports: [
    CommonModule, FormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent, IconComponent,
    SelectorArchivoComponent, VisorDocumentoComponent,
  ],
  templateUrl: './expediente.component.html',
})
export class ExpedienteComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private expedientes = inject(ExpedienteService);
  private documentoService = inject(DocumentoService);
  private toast = inject(ToastService);
  private confirm = inject(ConfirmService);

  empleadoId = '';
  expediente: Expediente | null = null;
  cargando = true;

  /** El documento abierto en el visor. */
  docAbierto: Documento | null = null;

  // ── Subir un documento ──
  modalSubir = false;
  tipoASubir = 'hoja_de_vida';
  contratoASubir = '';
  archivos: File[] = [];
  subiendo = false;
  readonly tiposSubibles = TIPOS_DOCUMENTO_SUBIBLES;

  readonly nombreDocumento = nombreDocumento;
  readonly esPdf = esPdf;
  readonly formato = formatoDocumento;

  readonly columnasBoletas: ColumnaTabla<Documento>[] = [
    {
      campo: 'planilla', header: 'Mes', ancho: '20%',
      formatear: (_v, d) => {
        if (d.planilla) return `${nombreMes(d.planilla.mes)} ${d.planilla.anio}`;
        return d.periodo_anio ? `${nombreMes(d.periodo_mes)} ${d.periodo_anio}` : fechaLegible(d.created_at);
      },
    },
    {
      campo: 'estado_firma', header: 'Estado', tipo: 'badge', ancho: '14%',
      formatear: (_v, d) => estadoFirmaLegible(d),
      badgeSeveridad: (_v, d) => severidadFirma(d),
    },
    { campo: 'fecha_visto', header: 'La revisó', tipo: 'hito' },
    { campo: 'fecha_descarga', header: 'La descargó', tipo: 'hito' },
    { campo: 'fecha_firma', header: 'La firmó', tipo: 'hito' },
  ];

  readonly accionesBoletas: AccionPersonalizada<Documento>[] = [
    { id: 'ver', titulo: 'Ver la boleta', icono: 'description', visible: (d) => esPdf(d) },
    { id: 'descargar', titulo: 'Descargar la boleta', icono: 'folder_open' },
    // Solo la subida a mano se quita: la que generó el sistema es la constancia del pago.
    { id: 'quitar', titulo: 'Quitar del expediente', icono: 'remove_circle', severidad: 'danger', visible: (d) => esDocumentoAnterior(d) },
  ];

  readonly columnasOtros: ColumnaTabla<Documento>[] = [
    { campo: 'tipo', header: 'Documento', ancho: '45%', formatear: (_v, d) => nombreDocumento(d) },
    { campo: 'created_at', header: 'Subido el', tipo: 'fecha', ancho: '20%' },
  ];

  readonly accionesOtros: AccionPersonalizada<Documento>[] = [
    { id: 'ver', titulo: 'Ver el documento', icono: 'description', visible: (d) => esPdf(d) },
    { id: 'descargar', titulo: 'Descargar el documento', icono: 'folder_open' },
    { id: 'quitar', titulo: 'Quitar del expediente', icono: 'remove_circle', severidad: 'danger' },
  ];

  ngOnInit(): void {
    this.empleadoId = this.route.snapshot.paramMap.get('empleadoId') ?? '';
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.expedientes.obtener(this.empleadoId).subscribe({
      next: (res) => {
        this.expediente = res.data;
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toast.error('No se pudo abrir el expediente', mensajeErrorApi(err, 'Vuelve a la lista e intenta de nuevo.'));
      },
    });
  }

  // ── Quién es ──

  get nombreCompleto(): string {
    const e = this.expediente?.empleado;
    return e ? `${e.nombre} ${e.apellido}` : '';
  }

  /** "DNI 81577382, Administrativo en Administración, sede CATA." */
  get subtitulo(): string {
    const e = this.expediente?.empleado;
    if (!e) return this.cargando ? 'Abriendo el expediente…' : '';

    const puesto = [e.cargo?.nombre, e.area?.nombre ? `en ${e.area.nombre}` : ''].filter(Boolean).join(' ');
    const partes = [
      `DNI ${e.dni}`,
      puesto,
      e.sede?.nombre ? `sede ${e.sede.nombre}` : '',
      e.estado === 'inactivo' ? (e.fecha_cese ? `cesó el ${fechaDeDia(e.fecha_cese)}` : 'dado de baja') : '',
    ];
    return partes.filter(Boolean).join(', ') + '.';
  }

  get iniciales(): string {
    const e = this.expediente?.empleado;
    return e ? `${e.nombre.charAt(0)}${e.apellido.charAt(0)}`.toUpperCase() : '';
  }

  get contratoVigente(): ContratoDelExpediente | null {
    return this.expediente?.contratos.find((c) => c.estado === 'vigente') ?? null;
  }

  /** Solo las que generó el sistema: una boleta anterior no se firma aquí. */
  get boletasFirmadas(): number {
    return this.expediente?.boletas.filter((b) => b.tipo === 'boleta' && b.estado_firma === 'firmado').length ?? 0;
  }

  get boletasDelSistema(): number {
    return this.expediente?.boletas.filter((b) => b.tipo === 'boleta').length ?? 0;
  }

  /** Lo que se subió a mano: hoja de vida, contratos firmados y otros. */
  get documentosSubidos(): number {
    const e = this.expediente;
    if (!e) return 0;
    return (e.hoja_de_vida ? 1 : 0) + e.otros.length + e.contratos.reduce((n, c) => n + c.documentos.length, 0)
      + e.contratos_anteriores.length + e.boletas.filter((b) => esDocumentoAnterior(b)).length;
  }

  // ── Contratos ──

  tipoContrato(valor: string | null | undefined): string {
    return TIPO_CONTRATO_CONTRATO_OPCIONES.find((o) => o.value === valor)?.label ?? 'Contrato';
  }

  estadoContrato(valor: string): string {
    return ESTADO_CONTRATO_OPCIONES.find((o) => o.value === valor)?.label ?? valor;
  }

  severidadContrato(valor: string): string {
    if (valor === 'vigente') return 'success';
    return valor === 'renovado' ? 'info' : 'secondary';
  }

  vigencia(contrato: Contrato): string {
    const desde = fechaDeDia(contrato.fecha_inicio);
    return contrato.fecha_fin
      ? `Del ${desde} al ${fechaDeDia(contrato.fecha_fin)}`
      : `Desde el ${desde}, sin fecha de fin`;
  }

  avisoVencimiento(contrato: Contrato): string | null {
    if (contrato.estado !== 'vigente' || !contrato.fecha_fin || !this.expediente) return null;
    const dias = diasHasta(contrato.fecha_fin);
    if (dias < 0) return `Venció hace ${-dias} día(s)`;
    if (dias === 0) return 'Vence hoy';
    return dias <= this.expediente.dias_por_vencer ? `Vence en ${dias} día(s)` : null;
  }

  etiquetaContrato(contrato: Contrato): string {
    return `${this.tipoContrato(contrato.tipo_contrato)}, desde el ${fechaDeDia(contrato.fecha_inicio)}`;
  }

  fecha(valor: string | null | undefined): string {
    return fechaLegible(valor);
  }

  // ── Ver, descargar y quitar ──

  alAccionar(evento: { accion: string; fila: Documento }): void {
    if (evento.accion === 'ver') this.ver(evento.fila);
    if (evento.accion === 'descargar') this.descargar(evento.fila);
    if (evento.accion === 'quitar') this.quitar(evento.fila);
  }

  ver(doc: Documento): void {
    this.docAbierto = doc;
  }

  descargar(doc: Documento): void {
    this.documentoService.descargar(doc.id).subscribe({
      next: (blob) => guardarArchivo(blob, nombreArchivoDocumento(doc, this.nombreCompleto)),
      error: (err) => this.toast.error('No se pudo descargar', mensajeErrorApi(err, 'Intenta de nuevo.')),
    });
  }

  quitar(doc: Documento): void {
    this.confirm
      .confirmar({
        titulo: 'Quitar del expediente',
        mensaje: `¿Quitar «${nombreDocumento(doc)}» del expediente de ${this.nombreCompleto}? Deja de verse aquí, pero el archivo no se borra del servidor.`,
        aceptarTexto: 'Sí, quitar',
        variante: 'danger',
      })
      .then((aceptado) => {
        if (!aceptado) return;
        this.documentoService.delete(doc.id).subscribe({
          next: () => {
            this.toast.success('Documento quitado', `${nombreDocumento(doc)} ya no está en el expediente.`);
            this.cargar();
          },
          error: (err) => this.toast.error('No se pudo quitar', mensajeErrorApi(err, 'Intenta de nuevo.')),
        });
      });
  }

  // ── Subir ──

  abrirSubir(tipo = 'hoja_de_vida', contratoId = ''): void {
    this.tipoASubir = tipo;
    this.contratoASubir = contratoId;
    this.archivos = [];
    this.modalSubir = true;
  }

  cerrarSubir(): void {
    this.modalSubir = false;
    this.archivos = [];
  }

  subir(): void {
    const archivo = this.archivos[0];
    if (!archivo) {
      this.toast.warning('Falta el archivo', 'Elige el archivo que quieres subir.');
      return;
    }

    const tipo = this.tiposSubibles.find((t) => t.value === this.tipoASubir)?.label ?? 'El documento';
    this.subiendo = true;
    this.documentoService
      .subir({
        empleado_id: this.empleadoId,
        tipo: this.tipoASubir,
        archivo,
        contrato_id: this.tipoASubir === 'contrato' ? this.contratoASubir || null : null,
      })
      .subscribe({
        next: () => {
          this.subiendo = false;
          this.toast.success('Documento guardado', `${tipo} quedó en el expediente de ${this.nombreCompleto}.`);
          this.cerrarSubir();
          this.cargar();
        },
        error: (err) => {
          this.subiendo = false;
          this.toast.error('No se pudo subir', mensajeErrorApi(err, 'Revisa el archivo e intenta de nuevo.'));
        },
      });
  }

  // ── Navegación ──

  volver(): void {
    this.router.navigate(['/inicio/documentos']);
  }

  verFicha(): void {
    this.router.navigate(['/inicio/empleados/ver', this.empleadoId]);
  }

  irAContratos(): void {
    this.router.navigate(['/inicio/contratos']);
  }

  subirAnteriores(): void {
    this.router.navigate(['/inicio/documentos/subir-anteriores']);
  }
}
