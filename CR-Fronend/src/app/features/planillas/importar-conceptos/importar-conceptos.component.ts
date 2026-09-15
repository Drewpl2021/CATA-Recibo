import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { leerHojaExcel } from '../../../core/utils/lectura-excel';

import { ConfirmService, ImportacionConceptosService, ToastService } from '../../../core/services';
import {
  AccionColumna,
  AvisoImportacion,
  CambioImportado,
  ConceptoCandidato,
  EstadoColumna,
  FilaImportada,
  PayloadImportacion,
  ResultadoImportacion,
  ResultadoReconocer,
  TipoConcepto,
  TrabajadorImportado,
  VistaPreviaImportacion,
} from '../../../core/models';
import { guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import { nombreMes } from '../../../shared/constants';
import { CeldaTablaDirective } from '../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { SelectorArchivoComponent } from '../../../shared/components/selector-archivo/selector-archivo.component';
import { WizardComponent } from '../../../shared/components/wizard/wizard.component';
import { PasoWizard } from '../../../shared/components/wizard/wizard.models';

/** Los mismos topes que el backend: avisar antes de mandar es mejor que un 422. */
const MAX_FILAS = 3000;
const MAX_COLUMNAS = 80;

const ETIQUETA_ESTADO: Record<EstadoColumna, string> = {
  reconocida: 'Reconocida',
  dudosa: 'Elige cuál',
  nueva: 'No existe',
  protegida: 'La calcula el sistema',
  dni: 'DNI',
  detalle: 'Detalle',
  informativa: 'De referencia',
  sin_titulo: 'Sin título',
};

const SEVERIDAD_ESTADO: Record<EstadoColumna, 'success' | 'warning' | 'info' | 'secondary'> = {
  reconocida: 'success',
  dudosa: 'warning',
  nueva: 'info',
  protegida: 'secondary',
  dni: 'info',
  detalle: 'info',
  informativa: 'secondary',
  sin_titulo: 'secondary',
};

const GRUPO_TIPO: Record<TipoConcepto, string> = {
  bonificacion: 'Ingresos',
  descuento: 'Descuentos',
  adelanto: 'Adelantos',
  aportacion: 'Aportaciones del empleador',
};

const ETIQUETA_TIPO: Record<TipoConcepto, string> = {
  bonificacion: 'ingreso',
  descuento: 'descuento',
  adelanto: 'adelanto',
  aportacion: 'aportación',
};

/** Una columna tal como la va ajustando RR.HH. en el segundo paso. */
interface ColumnaEditable {
  indice: number;
  titulo: string;
  estado: EstadoColumna;
  motivo: string;
  candidatos: ConceptoCandidato[];
  /** El primer valor de la columna, para reconocerla de un vistazo. */
  ejemplo: string;
  /** Lo que calcula el sistema no se puede reasignar. */
  bloqueada: boolean;
  /** '' = todavía sin decidir. */
  accion: AccionColumna | '';
  conceptoId: string;
  nuevoNombre: string;
  nuevoTipo: TipoConcepto | '';
  deColumna: number | null;
}

/**
 * Importar conceptos de pago desde un Excel.
 *
 * RR.HH. sube su propia hoja —con sus títulos, sin plantilla— y carga el mes
 * entero de una vez, en vez de entrar planilla por planilla.
 *
 * Cuatro pasos, y cada uno bloquea al siguiente hasta estar hecho:
 *
 *   1. Subir el Excel      se lee aquí, en el navegador; el archivo no se sube
 *   2. Reconocer columnas  el sistema propone qué es cada una; RR.HH. confirma
 *   3. Revisar cambios     qué le pasa a cada planilla, sin guardar nada
 *   4. Listo               se guardó todo, o nada
 *
 * Todo con los componentes compartidos: el wizard, el selector de archivos y
 * la tabla, con celdas a medida para los desplegables. La primera versión se
 * había armado su propia tabla HTML, con otro aspecto.
 */
@Component({
  selector: 'app-importar-conceptos',
  standalone: true,
  imports: [
    CommonModule, FormsModule, ReactiveFormsModule,
    PageHeaderComponent, WizardComponent, IconComponent,
    DataTableComponent, CeldaTablaDirective, SelectorArchivoComponent,
  ],
  templateUrl: './importar-conceptos.component.html',
})
export class ImportarConceptosComponent implements OnInit {
  private fb = inject(FormBuilder);
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private importacion = inject(ImportacionConceptosService);
  private toast = inject(ToastService);
  private confirm = inject(ConfirmService);

  readonly meses = Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: nombreMes(i + 1) }));
  mes = new Date().getMonth() + 1;
  anio = new Date().getFullYear();

  readonly pasos: PasoWizard[] = [
    { id: 'archivo', titulo: 'Subir el Excel', icono: 'description', campos: ['leido'] },
    { id: 'columnas', titulo: 'Reconocer columnas', icono: 'layers', campos: ['revisado'] },
    { id: 'revision', titulo: 'Revisar cambios', icono: 'check_circle', campos: ['aplicado'] },
    { id: 'listo', titulo: 'Listo', icono: 'receipt' },
  ];
  paso = 0;

  /**
   * No es un formulario de verdad: es lo que le dice al wizard qué pasos
   * están terminados, para que no deje saltar hacia adelante sin hacerlos.
   */
  avance = this.fb.group({
    leido: [false, Validators.requiredTrue],
    revisado: [false, Validators.requiredTrue],
    aplicado: [false, Validators.requiredTrue],
  });

  // ── Paso 1 ─────────────────────────────────────────────
  archivosExcel: File[] = [];
  nombreArchivo = '';
  leyendo = false;
  filas: FilaImportada[] = [];
  private titulos: string[] = [];
  descargandoModelo = false;

  // ── Paso 2 ─────────────────────────────────────────────
  columnas: ColumnaEditable[] = [];
  grupos: { etiqueta: string; conceptos: ConceptoCandidato[] }[] = [];
  revisando = false;

  readonly columnasDeColumnas: ColumnaTabla<ColumnaEditable>[] = [
    { campo: 'titulo', header: 'Columna del Excel', ancho: '20%', formatear: (v) => v || '(sin título)' },
    { campo: 'ejemplo', header: 'Ejemplo', ancho: '14%', formatear: (v) => v || '—' },
    { campo: 'estado', header: 'Lo que entendió el sistema', ancho: '30%' },
    { campo: 'accion', header: 'Qué hacer con ella' },
  ];

  // ── Paso 3 ─────────────────────────────────────────────
  vista: VistaPreviaImportacion | null = null;
  aplicando = false;

  readonly columnasDeRevision: ColumnaTabla<TrabajadorImportado>[] = [
    { campo: 'nombre', header: 'Trabajador', ancho: '24%' },
    { campo: 'planilla', header: 'Planilla', ancho: '14%' },
    { campo: 'cambios', header: 'Qué cambia' },
    { campo: 'neto_despues', header: 'Neto a pagar', ancho: '14%' },
  ];

  // ── Paso 4 ─────────────────────────────────────────────
  resultado: ResultadoImportacion | null = null;

  ngOnInit(): void {
    // Llega con el mes que se estaba mirando en Planillas.
    const parametros = this.route.snapshot.queryParamMap;
    const mes = Number(parametros.get('mes'));
    const anio = Number(parametros.get('anio'));
    if (mes >= 1 && mes <= 12) this.mes = mes;
    if (anio >= 2000 && anio <= 2100) this.anio = anio;
  }

  get periodo(): string {
    return `${nombreMes(this.mes)} ${this.anio}`;
  }

  // ═══ Paso 1: leer el archivo ═══════════════════════════════════

  /** El selector ya validó extensión y peso. */
  alElegirExcel(archivos: File[]): void {
    this.archivosExcel = archivos;
    if (archivos[0]) void this.leer(archivos[0]);
  }

  private async leer(archivo: File): Promise<void> {
    this.reiniciarDesde(0);
    this.leyendo = true;

    // El lector compartido con la importación de empleados: busca la fila
    // de los títulos y deja cada celda lista para mandar.
    try {
      const hoja = await leerHojaExcel(archivo, MAX_COLUMNAS);
      this.nombreArchivo = archivo.name;
      this.titulos = hoja.titulos;
      this.filas = hoja.filas;
    } catch {
      this.leyendo = false;
      this.toast.error('No se pudo leer', 'El archivo está dañado o no es un Excel válido.');
      return;
    }

    if (!this.titulos.length || !this.filas.length) {
      this.leyendo = false;
      this.toast.warning('Sin datos', 'El Excel no tiene filas con datos debajo de los títulos.');
      return;
    }
    if (this.filas.length > MAX_FILAS) {
      this.leyendo = false;
      this.toast.warning('Demasiadas filas', `El Excel tiene ${this.filas.length} filas y el máximo es ${MAX_FILAS}: pártelo en dos.`);
      return;
    }

    this.importacion.reconocer(this.titulos).subscribe({
      next: (res) => {
        this.leyendo = false;
        if (!res.success) return;
        this.cargarColumnas(res.data);
        this.avance.patchValue({ leido: true });
        this.paso = 1;
      },
      error: (err) => {
        this.leyendo = false;
        this.toast.error('No se pudo reconocer', mensajeErrorApi(err, 'Intenta de nuevo en un momento.'));
      },
    });
  }

  // ═══ Paso 2: las columnas ══════════════════════════════════════

  private cargarColumnas(res: ResultadoReconocer): void {
    this.grupos = (['bonificacion', 'descuento', 'adelanto', 'aportacion'] as TipoConcepto[])
      .map((tipo) => ({ etiqueta: GRUPO_TIPO[tipo], conceptos: res.catalogo.filter((c) => c.tipo === tipo) }))
      .filter((g) => g.conceptos.length > 0);

    this.columnas = res.columnas.map((c) => ({
      indice: c.indice,
      titulo: c.titulo,
      estado: c.estado,
      motivo: c.motivo,
      candidatos: c.candidatos,
      ejemplo: this.ejemploDe(c.indice),
      bloqueada: c.estado === 'protegida',
      accion: c.accion === 'elegir' ? '' : c.accion,
      conceptoId: c.payment_concept_id ?? '',
      nuevoNombre: c.nuevo?.nombre ?? c.titulo,
      nuevoTipo: c.nuevo?.tipo ?? '',
      deColumna: c.de_columna,
    }));
  }

  private ejemploDe(indice: number): string {
    const fila = this.filas.find((f) => f.celdas[indice] !== null && f.celdas[indice] !== undefined);
    const valor = fila ? String(fila.celdas[indice]) : '';
    return valor.length > 30 ? valor.slice(0, 29) + '…' : valor;
  }

  /** Las columnas de montos, para decir de cuál es un detalle. */
  get columnasDeDinero(): ColumnaEditable[] {
    return this.columnas.filter((c) => c.accion === 'usar' || c.accion === 'crear');
  }

  /** Lo que falta decidir antes de poder revisar, dicho columna por columna. */
  get pendientes(): string[] {
    const faltan: string[] = [];
    const columnasDni = this.columnas.filter((c) => c.accion === 'dni').length;

    if (columnasDni === 0) faltan.push('Marca cuál columna tiene el DNI.');
    if (columnasDni > 1) faltan.push('Solo una columna puede ser la del DNI.');
    if (!this.columnasDeDinero.length) faltan.push('Asigna al menos una columna a un concepto de pago.');

    for (const c of this.columnas) {
      const nombre = `«${c.titulo || 'Columna ' + (c.indice + 1)}»`;
      if (c.accion === '') faltan.push(`${nombre}: elige qué es.`);
      if (c.accion === 'usar' && !c.conceptoId) faltan.push(`${nombre}: elige el concepto.`);
      if (c.accion === 'crear' && !c.nuevoNombre.trim()) faltan.push(`${nombre}: ponle nombre al concepto nuevo.`);
      if (c.accion === 'crear' && !c.nuevoTipo) faltan.push(`${nombre}: elige si suma o resta del neto.`);
      if (c.accion === 'detalle' && c.deColumna === null) faltan.push(`${nombre}: elige de qué columna es el detalle.`);
    }

    return faltan;
  }

  get cuantasReconocidas(): number {
    return this.columnas.filter((c) => c.estado === 'reconocida').length;
  }

  etiquetaEstado(estado: EstadoColumna): string {
    return ETIQUETA_ESTADO[estado];
  }

  severidadEstado(estado: EstadoColumna): string {
    return SEVERIDAD_ESTADO[estado];
  }

  /** Tocar una columna invalida la revisión: hay que volver a verla. */
  alCambiarColumna(): void {
    if (this.vista || this.resultado) this.reiniciarDesde(1);
  }

  alCambiarPeriodo(): void {
    if (this.vista || this.resultado) this.reiniciarDesde(1);
  }

  previsualizar(): void {
    if (this.pendientes.length || this.revisando) return;

    this.revisando = true;
    this.importacion.previsualizar(this.payload()).subscribe({
      next: (res) => {
        this.revisando = false;
        if (!res.success) return;
        this.vista = res.data;
        this.avance.patchValue({ revisado: true });
        this.paso = 2;
      },
      error: (err) => {
        this.revisando = false;
        this.toast.error('No se pudo revisar', mensajeErrorApi(err, 'Revisa el archivo e intenta de nuevo.'));
      },
    });
  }

  private payload(): PayloadImportacion {
    return {
      mes: this.mes,
      anio: this.anio,
      archivo: this.nombreArchivo.slice(0, 150),
      columnas: this.columnas.map((c) => ({
        indice: c.indice,
        titulo: c.titulo,
        accion: c.accion as AccionColumna,
        payment_concept_id: c.accion === 'usar' ? c.conceptoId : null,
        nuevo: c.accion === 'crear' ? { nombre: c.nuevoNombre.trim(), tipo: c.nuevoTipo as TipoConcepto } : null,
        de_columna: c.accion === 'detalle' ? c.deColumna : null,
      })),
      filas: this.filas,
    };
  }

  // ═══ Paso 3: la revisión ═══════════════════════════════════════

  get totalCambios(): number {
    const r = this.vista?.resumen;
    return r ? r.lineas_nuevas + r.lineas_cambiadas + r.lineas_quitadas : 0;
  }

  get sePuedeAplicar(): boolean {
    return !!this.vista && this.vista.resumen.errores === 0 && this.vista.resumen.trabajadores > 0 && !this.aplicando;
  }

  etiquetaTipo(tipo: TipoConcepto): string {
    return ETIQUETA_TIPO[tipo] ?? tipo;
  }

  /** "se agrega con S/ 120.00", "de S/ 80.00 a S/ 120.00", "se quita (tenía S/ 35.50)". */
  textoCambio(c: CambioImportado): string {
    if (c.accion === 'agregar') return `se agrega con ${this.soles(c.despues)}`;
    if (c.accion === 'quitar') return `se quita (tenía ${this.soles(c.antes)})`;
    return `de ${this.soles(c.antes)} a ${this.soles(c.despues)}`;
  }

  /** Dónde está el problema, en palabras de quien mira el Excel. */
  ubicacion(aviso: AvisoImportacion): string {
    const partes = [
      aviso.fila ? `Fila ${aviso.fila}` : '',
      aviso.dni ? `DNI ${aviso.dni}` : '',
      aviso.columna ?? '',
    ].filter((p) => p);
    return partes.length ? partes.join(', ') : 'Todo el archivo';
  }

  aplicar(): void {
    const vista = this.vista;
    if (!vista || !this.sePuedeAplicar) return;

    const r = vista.resumen;
    const nuevos = r.conceptos_nuevos.length
      ? ` Además se crearán ${r.conceptos_nuevos.length} concepto(s) nuevo(s) en el catálogo.`
      : '';

    this.confirm
      .confirmar({
        titulo: 'Aplicar la importación',
        mensaje: `Vas a cambiar ${this.totalCambios} concepto(s) en ${r.trabajadores} planilla(s) de ${r.periodo}.${nuevos}`,
        aceptarTexto: 'Sí, aplicar',
        variante: 'default',
      })
      .then((aceptado) => {
        if (!aceptado) return;

        this.aplicando = true;
        this.importacion.aplicar(this.payload()).subscribe({
          next: (res) => {
            this.aplicando = false;
            if (!res.success) return;
            this.resultado = res.data;
            this.avance.patchValue({ aplicado: true });
            this.paso = 3;
            this.toast.success('Importación aplicada', res.data.mensaje);
          },
          error: (err) => {
            this.aplicando = false;
            // Entre la revisión y la confirmación algo cambió (una planilla
            // que se cerró, por ejemplo): se enseña la revisión nueva.
            if (err?.status === 422 && err?.error?.data?.resumen) {
              this.vista = err.error.data as VistaPreviaImportacion;
            }
            this.toast.error('No se aplicó nada', mensajeErrorApi(err, 'Intenta de nuevo en un momento.'));
          },
        });
      });
  }

  // ═══ Paso 4 y navegación ═══════════════════════════════════════

  /** El Excel modelo del mes elegido: la gente con planilla y una columna por concepto. */
  descargarModelo(): void {
    const periodo = this.periodo;
    this.descargandoModelo = true;
    this.importacion.descargarModelo(this.mes, this.anio).subscribe({
      next: (blob) => {
        guardarArchivo(blob, `Modelo de conceptos ${periodo}.xlsx`);
        this.descargandoModelo = false;
        this.toast.success('Modelo descargado', 'Escribe los montos en Excel y súbelo aquí. La hoja «Instrucciones» explica cada columna.');
      },
      error: (err) => {
        this.descargandoModelo = false;
        this.toast.error('No se descargó', mensajeErrorApi(err, 'No se pudo preparar el modelo. Intenta de nuevo.'));
      },
    });
  }

  otroArchivo(): void {
    this.reiniciarDesde(0);
    this.archivosExcel = [];
    this.nombreArchivo = '';
    this.filas = [];
    this.titulos = [];
    this.paso = 0;
  }

  verPlanillas(): void {
    this.router.navigate(['/inicio/planillas'], { queryParams: { mes: this.mes, anio: this.anio } });
  }

  /** Deshace lo que dependía del paso indicado y de los siguientes. */
  private reiniciarDesde(paso: number): void {
    if (paso <= 0) {
      this.columnas = [];
      this.grupos = [];
      this.avance.patchValue({ leido: false });
    }
    if (paso <= 1) {
      this.vista = null;
      this.avance.patchValue({ revisado: false });
    }
    this.resultado = null;
    this.avance.patchValue({ aplicado: false });
  }

  private soles(monto: number | null): string {
    return `S/ ${(monto ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }
}
