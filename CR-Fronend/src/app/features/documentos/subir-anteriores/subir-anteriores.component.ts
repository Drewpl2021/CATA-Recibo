import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { ConfirmService, DocumentosAnterioresService, ToastService } from '../../../core/services';
import {
  ArchivoAnteriorPayload,
  EstadoArchivoAnterior,
  FilaArchivoAnterior,
  TipoDocumentoAnterior,
  VistaDocumentosAnteriores,
} from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { identificarDocumento } from '../../../core/utils/identificar-documento';
import { huellaDeArchivo, textoDePdf } from '../../../core/utils/lectura-pdf';
import { MESES_OPCIONES, nombreMes } from '../../../shared/constants';
import { CeldaTablaDirective } from '../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { SelectorArchivoComponent } from '../../../shared/components/selector-archivo/selector-archivo.component';
import { WizardComponent } from '../../../shared/components/wizard/wizard.component';
import { PasoWizard } from '../../../shared/components/wizard/wizard.models';

/** El mismo tope que el backend. */
const MAX_ARCHIVOS = 1000;

/** Cuántos archivos se suben a la vez: más rápido que de a uno, sin ahogar al servidor. */
const SUBIDAS_A_LA_VEZ = 3;

interface ArchivoLeido {
  archivo: File;
  /** El tipo que se reconoció por dentro o por el nombre; null si no se supo. */
  tipoLeido: TipoDocumentoAnterior | null;
  /** Lo encontrado, más lo que RR.HH. corrige en la revisión. */
  datos: ArchivoAnteriorPayload;
}

interface ResultadoSubida {
  nombre: string;
  bien: boolean;
  mensaje: string;
}

type FiltroRevision = '' | 'lista' | 'por_revisar' | 'omitida';

/**
 * Subir boletas y contratos de antes del sistema, cada uno al expediente de
 * su trabajador.
 *
 * RR.HH. tiene años de PDFs sacados del Excel de la planilla. Los elige todos
 * de una vez y el navegador lee cada uno por dentro: la boleta del colegio
 * dice "DNI: 42558107" y "Del 01/03/2026 al 31/03/2026". Si no lo encuentra
 * (un escaneo, un Word), mira el nombre del archivo. Antes de guardar nada se
 * muestra qué se entendió de cada archivo, y lo dudoso se decide a mano.
 *
 * Los archivos no salen de la computadora hasta el último paso: para revisar
 * solo viaja lo que se leyó (DNI, tipo, mes).
 */
@Component({
  selector: 'app-subir-anteriores',
  standalone: true,
  imports: [
    CommonModule, FormsModule, ReactiveFormsModule,
    PageHeaderComponent, WizardComponent, IconComponent,
    DataTableComponent, CeldaTablaDirective, SelectorArchivoComponent,
  ],
  templateUrl: './subir-anteriores.component.html',
})
export class SubirAnterioresComponent {
  private fb = inject(FormBuilder);
  private router = inject(Router);
  private anteriores = inject(DocumentosAnterioresService);
  private toast = inject(ToastService);
  private confirm = inject(ConfirmService);

  readonly extensiones = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
  readonly meses = MESES_OPCIONES;
  readonly tipos: { value: TipoDocumentoAnterior; label: string }[] = [
    { value: 'boleta_anterior', label: 'Boleta' },
    { value: 'contrato_anterior', label: 'Contrato' },
  ];

  readonly pasos: PasoWizard[] = [
    { id: 'archivos', titulo: 'Elegir archivos', icono: 'description', campos: ['leido'] },
    { id: 'revision', titulo: 'Revisar', icono: 'check_circle', campos: ['subido'] },
    { id: 'listo', titulo: 'Listo', icono: 'folder' },
  ];
  paso = 0;

  avance = this.fb.group({
    leido: [false, Validators.requiredTrue],
    subido: [false, Validators.requiredTrue],
  });

  // ── Paso 1 ─────────────────────────────────────────────
  archivos: File[] = [];
  tipoPorDefecto: TipoDocumentoAnterior | '' = '';
  leyendo = false;
  leidosHastaAhora = 0;
  private leidos: ArchivoLeido[] = [];
  /** Si elige otros archivos a mitad de la lectura, la lectura vieja se abandona. */
  private lectura = 0;

  // ── Paso 2 ─────────────────────────────────────────────
  vista: VistaDocumentosAnteriores | null = null;
  revisando = false;
  hayCambios = false;
  filtro: FiltroRevision = '';
  /** Las filas del filtro elegido. Un arreglo guardado y no un getter: la tabla se redibujaría a cada rato. */
  filas: FilaArchivoAnterior[] = [];

  readonly chips: { valor: FiltroRevision; etiqueta: string; cuenta: (v: VistaDocumentosAnteriores) => number }[] = [
    { valor: '', etiqueta: 'Todos', cuenta: (v) => v.resumen.archivos },
    { valor: 'lista', etiqueta: 'Se suben', cuenta: (v) => v.resumen.listas },
    { valor: 'por_revisar', etiqueta: 'Por revisar', cuenta: (v) => v.resumen.por_revisar },
    { valor: 'omitida', etiqueta: 'Ya estaban', cuenta: (v) => v.resumen.omitidas },
  ];

  readonly columnas: ColumnaTabla<FilaArchivoAnterior>[] = [
    { campo: 'nombre', header: 'Archivo', ancho: '17%' },
    { campo: 'empleado', header: 'De quién es', ancho: '23%' },
    { campo: 'tipo', header: 'Qué es', ancho: '14%' },
    { campo: 'mes', header: 'Periodo', ancho: '24%' },
    { campo: 'estado', header: 'Qué pasa' },
  ];

  // ── Paso 3 ─────────────────────────────────────────────
  subiendo = false;
  resultados: ResultadoSubida[] = [];
  porSubir = 0;

  // ═══ Paso 1: elegir y leer ═════════════════════════════════════

  alElegir(archivos: File[]): void {
    this.archivos = archivos;
    this.reiniciar();

    if (!archivos.length) return;
    if (archivos.length > MAX_ARCHIVOS) {
      this.toast.warning('Demasiados archivos', `Elegiste ${archivos.length} y el máximo es ${MAX_ARCHIVOS}: súbelos en dos tandas.`);
      return;
    }
    void this.leer();
  }

  /** "Todos son boletas": rellena solo lo que no se reconoció, lo leído manda. */
  alCambiarTipoPorDefecto(): void {
    if (!this.leidos.length) return;
    for (const l of this.leidos) l.datos.tipo = l.tipoLeido ?? (this.tipoPorDefecto || null);
    if (this.vista) this.revisar();
  }

  private async leer(): Promise<void> {
    const esta = ++this.lectura;
    this.leyendo = true;
    this.leidosHastaAhora = 0;

    const leidos: ArchivoLeido[] = [];
    for (const [indice, archivo] of this.archivos.entries()) {
      const [texto, huella] = await Promise.all([this.textoDe(archivo), huellaDeArchivo(archivo).catch(() => null)]);
      if (esta !== this.lectura) return;

      const encontrado = identificarDocumento(texto, archivo.name);
      leidos.push({
        archivo,
        tipoLeido: encontrado.tipo,
        datos: {
          indice,
          nombre: archivo.name.slice(0, 255),
          huella,
          tipo: encontrado.tipo ?? (this.tipoPorDefecto || null),
          dnis: encontrado.dnis.slice(0, 20),
          dni_nombre: encontrado.dniNombre,
          dni_elegido: null,
          mes: encontrado.mes,
          anio: encontrado.anio,
        },
      });
      this.leidosHastaAhora = indice + 1;
    }

    this.leidos = leidos;
    this.leyendo = false;
    this.revisar();
  }

  /** Un Word o una imagen no se leen por dentro: para ellos queda el nombre del archivo. */
  private async textoDe(archivo: File): Promise<string> {
    if (!archivo.name.toLowerCase().endsWith('.pdf')) return '';
    try {
      return await textoDePdf(archivo);
    } catch {
      return '';
    }
  }

  get textoLeyendo(): string {
    return `Leyendo ${this.leidosHastaAhora} de ${this.archivos.length} archivo(s)…`;
  }

  // ═══ Paso 2: revisar ═══════════════════════════════════════════

  revisar(): void {
    if (!this.leidos.length || this.revisando) return;

    this.revisando = true;
    this.anteriores.previsualizar(this.leidos.map((l) => l.datos)).subscribe({
      next: (res) => {
        this.revisando = false;
        if (!res.success) return;
        this.vista = res.data;
        this.hayCambios = false;
        this.aplicarFiltro();
        this.avance.patchValue({ leido: true, subido: false });
        this.paso = 1;
      },
      error: (err) => {
        this.revisando = false;
        this.toast.error('No se pudo revisar', mensajeErrorApi(err, 'Intenta de nuevo en un momento.'));
      },
    });
  }

  filtrar(valor: FiltroRevision): void {
    this.filtro = valor;
    this.aplicarFiltro();
  }

  private aplicarFiltro(): void {
    const todas = this.vista?.filas ?? [];
    this.filas = this.filtro === 'por_revisar'
      ? todas.filter((f) => f.estado === 'error' || f.estado === 'elegir')
      : this.filtro ? todas.filter((f) => f.estado === this.filtro) : todas;
  }

  /** Lo que se corrige en la tabla va a los datos del archivo, no a la fila que devolvió el servidor. */
  datos(fila: FilaArchivoAnterior): ArchivoAnteriorPayload {
    return this.leidos[fila.indice].datos;
  }

  marcarCambio(): void {
    this.hayCambios = true;
  }

  etiqueta(estado: EstadoArchivoAnterior): string {
    return { lista: 'Se sube', omitida: 'Ya estaba', elegir: 'Elige de quién es', error: 'Por revisar' }[estado];
  }

  severidad(estado: EstadoArchivoAnterior): string {
    return { lista: 'success', omitida: 'secondary', elegir: 'warning', error: 'danger' }[estado];
  }

  origen(fila: FilaArchivoAnterior): string {
    if (fila.origen === 'pdf') return 'leído en el PDF';
    if (fila.origen === 'nombre') return 'por el nombre del archivo';
    return fila.origen === 'elegido' ? 'elegido a mano' : '';
  }

  nombreTipo(tipo: TipoDocumentoAnterior | null): string {
    return this.tipos.find((t) => t.value === tipo)?.label ?? '—';
  }

  periodo(fila: FilaArchivoAnterior): string {
    if (!fila.anio) return '—';
    return fila.mes ? `${nombreMes(fila.mes)} ${fila.anio}` : String(fila.anio);
  }

  get listas(): FilaArchivoAnterior[] {
    return this.vista?.filas.filter((f) => f.estado === 'lista') ?? [];
  }

  get trabajadoresConArchivos(): number {
    return new Set(this.listas.map((f) => f.empleado?.id)).size;
  }

  get sePuedeSubir(): boolean {
    return !!this.vista && this.vista.resumen.listas > 0 && !this.hayCambios && !this.revisando && !this.subiendo;
  }

  // ═══ Paso 3: subir ═════════════════════════════════════════════

  subir(): void {
    if (!this.sePuedeSubir || !this.vista) return;

    const listas = this.listas;
    const quedan = this.vista.resumen.por_revisar;
    this.confirm
      .confirmar({
        titulo: 'Subir a los expedientes',
        mensaje: `Vas a subir ${listas.length} archivo(s) a los expedientes de ${this.trabajadoresConArchivos} trabajador(es).`
          + (quedan ? ` Los ${quedan} por revisar se quedan sin subir.` : ''),
        aceptarTexto: 'Sí, subir',
        variante: 'default',
      })
      .then((aceptado) => {
        if (aceptado) void this.subirTodos(listas);
      });
  }

  /** De a pocos a la vez: un archivo que falle no tumba a los demás, y se puede decir cuál fue. */
  private async subirTodos(listas: FilaArchivoAnterior[]): Promise<void> {
    this.avance.patchValue({ subido: true });
    this.paso = 2;
    this.subiendo = true;
    this.resultados = [];
    this.porSubir = listas.length;

    const cola = [...listas];
    const trabajar = async (): Promise<void> => {
      for (let fila = cola.shift(); fila; fila = cola.shift()) {
        try {
          const res = await firstValueFrom(this.anteriores.subir(
            { dni: fila.empleado!.dni, tipo: fila.tipo!, mes: fila.mes, anio: fila.anio },
            this.leidos[fila.indice].archivo
          ));
          this.resultados.push({ nombre: fila.nombre, bien: true, mensaje: `Al expediente de ${res.data.nombre}` });
        } catch (err) {
          this.resultados.push({ nombre: fila.nombre, bien: false, mensaje: mensajeErrorApi(err, 'No se pudo subir.') });
        }
      }
    };
    await Promise.all(Array.from({ length: SUBIDAS_A_LA_VEZ }, () => trabajar()));
    this.subiendo = false;

    const mal = this.conProblema.length;
    if (mal) {
      this.toast.warning('Subida terminada', `${mal} archivo(s) no se pudieron subir: revisa el detalle.`);
    } else {
      this.toast.success('Archivos subidos', `${this.subidos} archivo(s) quedaron en los expedientes.`);
    }
  }

  get subidos(): number {
    return this.resultados.filter((r) => r.bien).length;
  }

  get conProblema(): ResultadoSubida[] {
    return this.resultados.filter((r) => !r.bien);
  }

  // ═══ Navegación ════════════════════════════════════════════════

  otraTanda(): void {
    this.archivos = [];
    this.reiniciar();
  }

  verDocumentos(): void {
    this.router.navigate(['/inicio/documentos']);
  }

  importarEmpleados(): void {
    this.router.navigate(['/inicio/empleados/importar']);
  }

  private reiniciar(): void {
    this.lectura++;
    this.leyendo = false;
    this.leidos = [];
    this.vista = null;
    this.filas = [];
    this.filtro = '';
    this.hayCambios = false;
    this.resultados = [];
    this.avance.patchValue({ leido: false, subido: false });
    this.paso = 0;
  }
}
