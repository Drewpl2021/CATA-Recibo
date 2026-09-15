import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { ConfirmService, ImportacionEmpleadosService, ToastService } from '../../../core/services';
import {
  AvisoImportacion,
  CambioFicha,
  CampoFicha,
  ColumnaEmpleadoReconocida,
  FilaEmpleadoImportada,
  FilaImportada,
  PayloadImportacionEmpleados,
  ResultadoImportacionEmpleados,
  ResultadoReconocerEmpleados,
  VistaPreviaEmpleados,
} from '../../../core/models';
import { guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import { dniEnNombreDeArchivo, leerHojaExcel } from '../../../core/utils/lectura-excel';
import { CeldaTablaDirective } from '../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { SelectorArchivoComponent } from '../../../shared/components/selector-archivo/selector-archivo.component';
import { WizardComponent } from '../../../shared/components/wizard/wizard.component';
import { PasoWizard } from '../../../shared/components/wizard/wizard.models';

/** Los mismos topes que el backend. */
const MAX_FILAS = 2000;
const MAX_COLUMNAS = 60;

interface ColumnaEditable extends ColumnaEmpleadoReconocida {
  /** El primer valor de la columna, para reconocerla de un vistazo. */
  ejemplo: string;
}

interface ResultadoCv {
  archivo: string;
  dni: string;
  bien: boolean;
  mensaje: string;
}

/**
 * Importar empleados desde un Excel, con sus hojas de vida en lote.
 *
 * Lo más cómodo para RR.HH.: descargar la lista de empleados, corregirla o
 * agregarle filas en Excel, y volver a subirla.
 *
 *   DNI nuevo       se le da de alta, como en Nuevo Empleado
 *   DNI existente   se le cambian SOLO las celdas con algo escrito
 *
 * Las hojas de vida no caben en una celda: se eligen aparte, cada archivo
 * nombrado con el DNI de su trabajador, y se suben después de aplicar.
 *
 * Mismo asistente y mismos componentes que la importación de conceptos: el
 * wizard, el selector de archivos y la tabla compartida.
 */
@Component({
  selector: 'app-importar-empleados',
  standalone: true,
  imports: [
    CommonModule, FormsModule, ReactiveFormsModule,
    PageHeaderComponent, WizardComponent, IconComponent,
    DataTableComponent, CeldaTablaDirective, SelectorArchivoComponent,
  ],
  templateUrl: './importar-empleados.component.html',
})
export class ImportarEmpleadosComponent {
  private fb = inject(FormBuilder);
  private router = inject(Router);
  private importacion = inject(ImportacionEmpleadosService);
  private toast = inject(ToastService);
  private confirm = inject(ConfirmService);

  readonly extensionesCv = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

  readonly pasos: PasoWizard[] = [
    { id: 'archivos', titulo: 'Subir archivos', icono: 'description', campos: ['leido'] },
    { id: 'columnas', titulo: 'Reconocer columnas', icono: 'layers', campos: ['revisado'] },
    { id: 'revision', titulo: 'Revisar cambios', icono: 'check_circle', campos: ['aplicado'] },
    { id: 'listo', titulo: 'Listo', icono: 'people' },
  ];
  paso = 0;

  /** Lo que le dice al wizard qué pasos están terminados. */
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
  cvs: File[] = [];
  descargandoModelo = false;

  // ── Paso 2 ─────────────────────────────────────────────
  columnas: ColumnaEditable[] = [];
  campos: CampoFicha[] = [];
  revisando = false;

  readonly columnasDeColumnas: ColumnaTabla<ColumnaEditable>[] = [
    { campo: 'titulo', header: 'Columna del Excel', ancho: '22%', formatear: (v) => v || '(sin título)' },
    { campo: 'ejemplo', header: 'Ejemplo', ancho: '18%', formatear: (v) => v || '—' },
    { campo: 'estado', header: 'Lo que entendió el sistema', ancho: '32%' },
    { campo: 'campo', header: 'Dato de la ficha' },
  ];

  // ── Paso 3 ─────────────────────────────────────────────
  vista: VistaPreviaEmpleados | null = null;
  aplicando = false;

  readonly columnasDeRevision: ColumnaTabla<FilaEmpleadoImportada>[] = [
    { campo: 'nombre', header: 'Trabajador', ancho: '26%' },
    { campo: 'modo', header: 'Qué pasa', ancho: '14%' },
    { campo: 'cambios', header: 'Datos' },
    { campo: 'cv', header: 'Hoja de vida', ancho: '14%' },
  ];

  // ── Paso 4 ─────────────────────────────────────────────
  resultado: ResultadoImportacionEmpleados | null = null;
  subiendoCvs = false;
  resultadosCv: ResultadoCv[] = [];

  // ═══ Paso 1: los archivos ══════════════════════════════════════

  alElegirExcel(archivos: File[]): void {
    this.archivosExcel = archivos;
    if (archivos[0]) void this.leer(archivos[0]);
  }

  private async leer(archivo: File): Promise<void> {
    this.reiniciarDesde(0);
    this.leyendo = true;

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
        // No salta solo al paso siguiente: todavía puede querer elegir las
        // hojas de vida en esta misma pantalla.
        this.avance.patchValue({ leido: true });
      },
      error: (err) => {
        this.leyendo = false;
        this.toast.error('No se pudo reconocer', mensajeErrorApi(err, 'Intenta de nuevo en un momento.'));
      },
    });
  }

  alCambiarCvs(archivos: File[]): void {
    this.cvs = archivos;
    if (this.vista || this.resultado) this.reiniciarDesde(1);
  }

  /** Las hojas de vida con el DNI que se lee en su nombre. */
  get hojasDeVida(): { archivo: File; dni: string | null }[] {
    return this.cvs.map((archivo) => ({ archivo, dni: dniEnNombreDeArchivo(archivo.name) }));
  }

  get cvsSinDni(): string[] {
    return this.hojasDeVida.filter((h) => !h.dni).map((h) => h.archivo.name);
  }

  private get dnisConCv(): string[] {
    return [...new Set(this.hojasDeVida.map((h) => h.dni).filter((d): d is string => !!d))];
  }

  continuar(): void {
    if (this.avance.value.leido) this.paso = 1;
  }

  // ═══ Paso 2: las columnas ══════════════════════════════════════

  private cargarColumnas(res: ResultadoReconocerEmpleados): void {
    this.campos = res.campos;
    this.columnas = res.columnas.map((c) => ({ ...c, ejemplo: this.ejemploDe(c.indice) }));
  }

  private ejemploDe(indice: number): string {
    const fila = this.filas.find((f) => f.celdas[indice] !== null && f.celdas[indice] !== undefined);
    const valor = fila ? String(fila.celdas[indice]) : '';
    return valor.length > 30 ? valor.slice(0, 29) + '…' : valor;
  }

  etiqueta(c: ColumnaEditable): string {
    if (!c.campo) return c.estado === 'sin_titulo' ? 'Sin título' : 'Se ignora';
    return c.estado === 'reconocida' ? 'Reconocida' : 'Elegida a mano';
  }

  severidad(c: ColumnaEditable): string {
    if (!c.campo) return 'secondary';
    return c.estado === 'reconocida' ? 'success' : 'info';
  }

  /** Lo que impide revisar: sin DNI no se sabe de quién es cada fila. */
  get pendientes(): string[] {
    const faltan: string[] = [];
    const conCampo = this.columnas.filter((c) => c.campo);

    if (!conCampo.some((c) => c.campo === 'dni')) faltan.push('Marca cuál columna es el DNI.');
    if (conCampo.length < 2) faltan.push('Asigna al menos una columna además del DNI.');

    const vistos = new Set<string>();
    for (const c of conCampo) {
      if (vistos.has(c.campo!)) {
        faltan.push(`Dos columnas son «${this.tituloDeCampo(c.campo!)}»: deja una sola.`);
      }
      vistos.add(c.campo!);
    }

    return faltan;
  }

  /** Los datos que pide un alta y no tienen columna: con ellos solo se actualiza. */
  get obligatoriosSinColumna(): string[] {
    const asignados = new Set(this.columnas.map((c) => c.campo));
    return this.campos.filter((f) => f.obligatorio && f.campo !== 'dni' && !asignados.has(f.campo)).map((f) => f.titulo);
  }

  get cuantasReconocidas(): number {
    return this.columnas.filter((c) => c.campo).length;
  }

  private tituloDeCampo(campo: string): string {
    return this.campos.find((f) => f.campo === campo)?.titulo ?? campo;
  }

  alCambiarColumna(): void {
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

  private payload(): PayloadImportacionEmpleados {
    return {
      archivo: this.nombreArchivo.slice(0, 150),
      columnas: this.columnas.map((c) => ({ indice: c.indice, titulo: c.titulo, campo: c.campo || null })),
      filas: this.filas,
      cvs: this.dnisConCv,
    };
  }

  // ═══ Paso 3: la revisión ═══════════════════════════════════════

  /** Las hojas de vida que sí se van a subir: con DNI y de alguien que existe o se da de alta. */
  get cvsParaSubir(): { archivo: File; dni: string }[] {
    const sinTrabajador = new Set(this.vista?.resumen.cvs_sin_trabajador ?? []);
    return this.hojasDeVida
      .filter((h): h is { archivo: File; dni: string } => !!h.dni && !sinTrabajador.has(h.dni));
  }

  get sePuedeAplicar(): boolean {
    return !!this.vista
      && this.vista.resumen.errores === 0
      && (this.vista.filas.length > 0 || this.cvsParaSubir.length > 0)
      && !this.aplicando;
  }

  get textoAplicar(): string {
    if (this.aplicando) return 'Aplicando…';
    const filas = this.vista?.filas.length ?? 0;
    if (filas) return `Aplicar ${filas} cambio(s)`;
    return `Subir ${this.cvsParaSubir.length} hoja(s) de vida`;
  }

  textoCambio(c: CambioFicha): string {
    return c.antes === null ? c.despues : `${c.antes} → ${c.despues}`;
  }

  /** Dónde está el problema, en palabras de quien mira el Excel. */
  ubicacion(aviso: AvisoImportacion): string {
    const partes = [aviso.fila ? `Fila ${aviso.fila}` : '', aviso.dni ? `DNI ${aviso.dni}` : '', aviso.columna ?? ''].filter((p) => p);
    return partes.length ? partes.join(', ') : 'Todo el archivo';
  }

  aplicar(): void {
    const vista = this.vista;
    if (!vista || !this.sePuedeAplicar) return;

    const r = vista.resumen;
    const partes = [
      r.altas ? `dar de alta a ${r.altas} trabajador(es)` : '',
      r.actualizaciones ? `actualizar a ${r.actualizaciones}` : '',
      this.cvsParaSubir.length ? `subir ${this.cvsParaSubir.length} hoja(s) de vida` : '',
    ].filter((p) => p);

    this.confirm
      .confirmar({
        titulo: 'Aplicar la importación',
        mensaje: `Vas a ${partes.join(', ')}.`,
        aceptarTexto: 'Sí, aplicar',
        variante: 'default',
      })
      .then((aceptado) => {
        if (!aceptado) return;

        if (!vista.filas.length) {
          this.resultado = { resumen: vista.resumen, mensaje: 'Los datos ya estaban al día.' };
          void this.terminar();
          return;
        }

        this.aplicando = true;
        this.importacion.aplicar(this.payload()).subscribe({
          next: (res) => {
            this.aplicando = false;
            if (!res.success) return;
            this.resultado = res.data;
            void this.terminar();
          },
          error: (err) => {
            this.aplicando = false;
            if (err?.status === 422 && err?.error?.data?.resumen) {
              this.vista = err.error.data as VistaPreviaEmpleados;
            }
            this.toast.error('No se aplicó nada', mensajeErrorApi(err, 'Intenta de nuevo en un momento.'));
          },
        });
      });
  }

  /**
   * Pasa al último paso y sube las hojas de vida de a una: un archivo que
   * falle no tumba a los demás, y se puede decir cuál fue.
   */
  private async terminar(): Promise<void> {
    this.avance.patchValue({ aplicado: true });
    this.paso = 3;
    this.resultadosCv = [];

    const pendientes = this.cvsParaSubir;
    if (!pendientes.length) {
      this.toast.success('Importación aplicada', this.resultado?.mensaje ?? '');
      return;
    }

    this.subiendoCvs = true;
    for (const { archivo, dni } of pendientes) {
      try {
        const res = await firstValueFrom(this.importacion.subirHojaDeVida(dni, archivo));
        this.resultadosCv.push({ archivo: archivo.name, dni, bien: true, mensaje: `De ${res.data.nombre}` });
      } catch (err) {
        this.resultadosCv.push({ archivo: archivo.name, dni, bien: false, mensaje: mensajeErrorApi(err, 'No se pudo subir.') });
      }
    }
    this.subiendoCvs = false;

    const mal = this.cvsConProblema.length;
    if (mal) {
      this.toast.warning('Importación aplicada', `${mal} hoja(s) de vida no se pudieron subir: revisa el detalle.`);
    } else {
      this.toast.success('Importación aplicada', `${this.resultado?.mensaje ?? ''} Hojas de vida subidas: ${this.resultadosCv.length}.`);
    }
  }

  get cvsSubidos(): number {
    return this.resultadosCv.filter((r) => r.bien).length;
  }

  get cvsConProblema(): ResultadoCv[] {
    return this.resultadosCv.filter((r) => !r.bien);
  }

  // ═══ Navegación ════════════════════════════════════════════════

  /** El Excel modelo, con las áreas, cargos y sedes que hay hoy en el sistema. */
  descargarModelo(): void {
    this.descargandoModelo = true;
    this.importacion.descargarModelo().subscribe({
      next: (blob) => {
        guardarArchivo(blob, 'Modelo de empleados.xlsx');
        this.descargandoModelo = false;
        this.toast.success('Modelo descargado', 'Llénalo en Excel y súbelo aquí. La hoja «Instrucciones» explica cada columna.');
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
    this.cvs = [];
    this.nombreArchivo = '';
    this.filas = [];
    this.titulos = [];
    this.paso = 0;
  }

  verEmpleados(): void {
    this.router.navigate(['/inicio/empleados']);
  }

  private reiniciarDesde(paso: number): void {
    if (paso <= 0) {
      this.columnas = [];
      this.campos = [];
      this.avance.patchValue({ leido: false });
    }
    if (paso <= 1) {
      this.vista = null;
      this.avance.patchValue({ revisado: false });
    }
    this.resultado = null;
    this.resultadosCv = [];
    this.avance.patchValue({ aplicado: false });
  }
}
