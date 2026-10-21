import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { ConfirmService, DocumentoService, ExpedienteService, ToastService } from '../../../core/services';
import { Contrato, ContratoDelExpediente, Documento, Expediente } from '../../../core/models';
import {
  TIPOS_DOCUMENTO_SUBIBLES,
  antiguedad,
  diasHasta,
  esDocumentoAnterior,
  firmaResuelta,
  sePuedeVer,
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
import { ESTADO_CONTRATO_OPCIONES, MOTIVO_FIN_CONTRATO_OPCIONES, nombreMes } from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { PanelLateralComponent } from '../../../shared/components/panel-lateral/panel-lateral.component';
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
type SeccionExpediente = 'documentos' | 'contratos' | 'boletas' | 'otros';

/** Un paso del recorrido de una boleta, en el panel lateral. */
interface PasoBoleta {
  titulo: string;
  fecha: string | null | undefined;
  detalle?: string;
}

@Component({
  selector: 'app-expediente',
  standalone: true,
  imports: [
    CommonModule, FormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent, IconComponent,
    SelectorArchivoComponent, VisorDocumentoComponent, PanelLateralComponent,
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

  // ── Secciones ──
  //
  // Una a la vez, en pestañas. Antes eran cuatro bloques uno debajo del otro
  // con un índice de enlaces <a href="#…">: con el enrutador esos enlaces
  // mandaban al inicio, y con años de boletas la página no terminaba nunca.
  readonly pestanas: { id: SeccionExpediente; titulo: string; icono: string }[] = [
    { id: 'documentos', titulo: 'Sus documentos', icono: 'person' },
    { id: 'contratos', titulo: 'Contratos', icono: 'badge' },
    { id: 'boletas', titulo: 'Boletas', icono: 'receipt' },
    { id: 'otros', titulo: 'Otros documentos', icono: 'folder' },
  ];
  seccion: SeccionExpediente = 'documentos';

  /** El año de boletas a la vista; null = todas. */
  anioBoletas: number | null = null;

  /** Lo que está abierto en el panel lateral. */
  contratoAbierto: ContratoDelExpediente | null = null;
  boletaAbierta: Documento | null = null;
  /** El recorrido de la boleta abierta, armado una vez al abrirla. */
  rastroAbierto: PasoBoleta[] = [];

  /*
   * Listas derivadas del expediente, GUARDADAS y no calculadas con un getter.
   *
   * Un getter que filtra devuelve un arreglo nuevo en cada ciclo de Angular:
   * la tabla lo recibía como `datos` distintos, volvía a la página 1 y
   * redibujaba todas las filas con sus íconos, aunque nada hubiera cambiado.
   * Se recalculan solo en `recalcular()`: al llegar el expediente y al
   * cambiar de año.
   */
  otrosPersonales: Documento[] = [];
  aniosBoletas: number[] = [];
  boletasVisibles: Documento[] = [];
  contratosPasados: ContratoDelExpediente[] = [];
  /** Cuántas boletas tiene cada año, para el chip. */
  boletasPorAnio = new Map<number, number>();

  // ── Subir un documento ──
  modalSubir = false;
  tipoASubir = 'hoja_de_vida';
  contratoASubir = '';
  archivos: File[] = [];
  subiendo = false;

  readonly tiposSubibles = TIPOS_DOCUMENTO_SUBIBLES;

  /** "Mayo 2026, Junio 2026": los meses que esperan su boleta. */
  get mesesSinEmitir(): string {
    return (this.expediente?.boletas_sin_emitir ?? [])
      .map((p) => `${nombreMes(p.mes)} ${p.anio}`)
      .join(', ');
  }

  /** Lleva a emitir las boletas del mes que falta (el más reciente). */
  irAEmitir(): void {
    this.router.navigate(['/inicio/emision-boleta']);
  }

  readonly nombreDocumento = nombreDocumento;
  readonly sePuedeVer = sePuedeVer;
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
    { id: 'detalle', titulo: 'Ver su recorrido', icono: 'eye' },
    { id: 'ver', titulo: 'Ver la boleta', icono: 'description', visible: (d) => sePuedeVer(d) },
    { id: 'descargar', titulo: 'Descargar la boleta', icono: 'folder_open' },
    // Solo la subida a mano se quita: la que generó el sistema es la constancia del pago.
    { id: 'quitar', titulo: 'Quitar del expediente', icono: 'remove_circle', severidad: 'danger', visible: (d) => esDocumentoAnterior(d) },
  ];

  readonly columnasOtros: ColumnaTabla<Documento>[] = [
    { campo: 'tipo', header: 'Documento', ancho: '45%', formatear: (_v, d) => nombreDocumento(d) },
    { campo: 'created_at', header: 'Subido el', tipo: 'fecha', ancho: '20%' },
  ];

  readonly accionesOtros: AccionPersonalizada<Documento>[] = [
    { id: 'ver', titulo: 'Ver el documento', icono: 'description', visible: (d) => sePuedeVer(d) },
    { id: 'descargar', titulo: 'Descargar el documento', icono: 'folder_open' },
    { id: 'quitar', titulo: 'Quitar del expediente', icono: 'remove_circle', severidad: 'danger' },
  ];

  ngOnInit(): void {
    this.empleadoId = this.route.snapshot.paramMap.get('empleadoId') ?? '';
    const pedida = this.route.snapshot.queryParamMap.get('seccion');
    if (this.pestanas.some((p) => p.id === pedida)) this.seccion = pedida as SeccionExpediente;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.expedientes.obtener(this.empleadoId).subscribe({
      next: (res) => {
        this.expediente = res.data;
        this.cargando = false;
        this.recalcular();
        this.refrescarPaneles();
      },
      error: (err) => {
        this.cargando = false;
        this.toast.error('No se pudo abrir el expediente', mensajeErrorApi(err, 'Vuelve a la lista e intenta de nuevo.'));
      },
    });
  }

  // ── Secciones ──

  elegirSeccion(id: SeccionExpediente): void {
    this.seccion = id;
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { seccion: id },
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
  }

  cuenta(id: SeccionExpediente): number {
    const e = this.expediente;
    if (!e) return 0;
    switch (id) {
      case 'documentos': return (e.hoja_de_vida ? 1 : 0) + this.otrosPersonales.length;
      case 'contratos': return e.contratos.length + e.contratos_anteriores.length;
      case 'boletas': return e.boletas.length;
      case 'otros': return e.otros.length;
    }
  }

  // ── Boletas por año ──

  /** De qué año es: el de su planilla, el que se le puso al subirla o el de subida. */
  anioDe(doc: Documento): number {
    return doc.planilla?.anio ?? doc.periodo_anio ?? Number(String(doc.created_at ?? '').slice(0, 4));
  }

  /** Arma las listas derivadas. Solo al llegar el expediente. */
  private recalcular(): void {
    const e = this.expediente;
    const vigente = e?.hoja_de_vida?.id;
    // Sus papeles, menos la hoja de vida vigente, que va en su propia fila.
    this.otrosPersonales = (e?.personales ?? []).filter((d) => d.id !== vigente);
    // Los contratos que ya no rigen; el vigente va aparte, arriba.
    this.contratosPasados = (e?.contratos ?? []).filter((c) => c.estado !== 'vigente');

    this.boletasPorAnio = new Map();
    for (const b of e?.boletas ?? []) {
      const a = this.anioDe(b);
      if (a) this.boletasPorAnio.set(a, (this.boletasPorAnio.get(a) ?? 0) + 1);
    }
    this.aniosBoletas = [...this.boletasPorAnio.keys()].sort((a, b) => b - a);

    // El año más reciente, salvo que el elegido siga existiendo.
    if (this.anioBoletas === null || !this.aniosBoletas.includes(this.anioBoletas)) {
      this.anioBoletas = this.aniosBoletas[0] ?? null;
    }
    this.filtrarBoletas();
  }

  elegirAnio(anio: number | null): void {
    if (anio === this.anioBoletas) return;
    this.anioBoletas = anio;
    this.filtrarBoletas();
  }

  private filtrarBoletas(): void {
    const todas = this.expediente?.boletas ?? [];
    this.boletasVisibles = this.anioBoletas === null ? todas : todas.filter((b) => this.anioDe(b) === this.anioBoletas);
  }

  // ── Panel lateral ──

  abrirContrato(c: ContratoDelExpediente): void {
    this.contratoAbierto = c;
  }

  abrirBoleta(doc: Documento): void {
    this.boletaAbierta = doc;
    this.rastroAbierto = this.rastro(doc);
  }

  cerrarBoleta(): void {
    this.boletaAbierta = null;
    this.rastroAbierto = [];
  }

  /**
   * Al recargar el expediente (después de subir o quitar algo) el panel
   * abierto debe mostrar lo nuevo, no la copia vieja que tenía.
   */
  private refrescarPaneles(): void {
    const e = this.expediente;
    if (this.contratoAbierto) {
      this.contratoAbierto = e?.contratos.find((c) => c.id === this.contratoAbierto!.id) ?? null;
    }
    if (this.boletaAbierta) {
      const nueva = e?.boletas.find((b) => b.id === this.boletaAbierta!.id);
      if (nueva) this.abrirBoleta(nueva); else this.cerrarBoleta();
    }
  }

  mesDeBoleta(d: Documento): string {
    if (d.planilla) return `${nombreMes(d.planilla.mes)} ${d.planilla.anio}`;
    return d.periodo_anio ? `${nombreMes(d.periodo_mes)} ${d.periodo_anio}` : fechaLegible(d.created_at);
  }

  estadoFirma(d: Documento): string {
    return estadoFirmaLegible(d);
  }

  esAnterior(d: Documento): boolean {
    return esDocumentoAnterior(d);
  }

  fechaDia(valor: string | null | undefined): string {
    return fechaDeDia(valor);
  }

  /**
   * El recorrido de la boleta, paso a paso, para contestar con una fecha
   * cuando alguien dice «a mí nunca me llegó». Una boleta anterior al
   * sistema no tiene recorrido: solo cuándo se subió.
   */
  private rastro(d: Documento): PasoBoleta[] {
    if (esDocumentoAnterior(d)) {
      return [{ titulo: 'Subida al expediente', fecha: d.created_at, detalle: 'Archivo anterior al sistema' }];
    }
    const pasos: PasoBoleta[] = [
      { titulo: 'Emitida', fecha: d.created_at },
    ];
    if (d.firma_colegio) {
      const ultima = (d.firmas_colegio ?? []).filter((f) => f.fecha).slice(-1)[0];
      pasos.push({
        titulo: 'Firmada por el colegio',
        fecha: d.firma_colegio === 'completa' ? (ultima?.fecha ?? null) : null,
        detalle: ultima?.nombre ?? (d.firma_colegio === 'parcial' ? 'Falta una firma' : undefined),
      });
    }
    pasos.push(
      { titulo: 'Aviso enviado', fecha: d.fecha_aviso, detalle: d.aviso_correo ?? undefined },
      { titulo: 'La revisó', fecha: d.fecha_visto },
      { titulo: 'La descargó', fecha: d.fecha_descarga, detalle: d.descargas ? `${d.descargas} vez(ces)` : undefined },
      { titulo: 'Dio su conformidad', fecha: d.fecha_firma, detalle: d.codigo_firma ?? undefined },
    );
    return pasos;
  }

  // ── Quién es ──

  get nombreCompleto(): string {
    const e = this.expediente?.empleado;
    return e ? `${e.nombre} ${e.apellido}` : '';
  }

  /**
   * Mientras carga nomás: una vez abierto el expediente, quién es se
   * cuenta en la ficha de abajo —con foto, cargo, sede y antigüedad—,
   * así que repetirlo acá en chiquito sería decir lo mismo dos veces.
   */
  get subtitulo(): string {
    return this.expediente ? '' : (this.cargando ? 'Abriendo el expediente…' : '');
  }

  get iniciales(): string {
    const e = this.expediente?.empleado;
    return e ? `${e.nombre.charAt(0)}${e.apellido.charAt(0)}`.toUpperCase() : '';
  }

  /** "Cargo en Área, sede Tal." */
  get puesto(): string {
    const e = this.expediente?.empleado;
    if (!e) return '';
    const partes = [
      [e.cargo?.nombre, e.area?.nombre ? `en ${e.area.nombre}` : ''].filter(Boolean).join(' '),
      e.sede?.nombre ? `sede ${e.sede.nombre}` : '',
    ];
    return partes.filter(Boolean).join(', ');
  }

  /**
   * "3 años y 4 meses": cuánto lleva en el colegio, contado desde su fecha
   * de ingreso. Es el dato que faltaba —no está en ningún otro lado del
   * expediente— y es justo el que junta dos cosas que antes vivían
   * separadas: la ficha del empleado (de dónde sale fecha_ingreso) y el
   * expediente de documentos.
   */
  get antiguedadTexto(): string {
    const e = this.expediente?.empleado;
    if (!e?.fecha_ingreso) return '';
    const texto = antiguedad(e.fecha_ingreso, e.estado === 'inactivo' ? e.fecha_cese : null);
    return texto ? `${texto} en el colegio` : '';
  }

  get contratoVigente(): ContratoDelExpediente | null {
    return this.expediente?.contratos.find((c) => c.estado === 'vigente') ?? null;
  }

  /** Solo las que generó el sistema: una boleta anterior no se firma aquí. */
  get boletasFirmadas(): number {
    return this.expediente?.boletas.filter((b) => b.tipo === 'boleta' && firmaResuelta(b)).length ?? 0;
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

  estadoContrato(valor: string): string {
    return ESTADO_CONTRATO_OPCIONES.find((o) => o.value === valor)?.label ?? valor;
  }

  motivoFin(valor: string): string {
    return MOTIVO_FIN_CONTRATO_OPCIONES.find((o) => o.value === valor)?.label ?? valor;
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
    return `${contrato.tipo_contrato?.nombre ?? 'Contrato'}, desde el ${fechaDeDia(contrato.fecha_inicio)}`;
  }

  fecha(valor: string | null | undefined): string {
    return fechaLegible(valor);
  }

  // ── Ver, descargar y quitar ──

  alAccionar(evento: { accion: string; fila: Documento }): void {
    if (evento.accion === 'detalle') this.abrirBoleta(evento.fila);
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
