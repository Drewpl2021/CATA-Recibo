import { inject, Component, OnInit } from '@angular/core';
import { ProgresoService } from '../../../core/services/sistema/progreso.service';
import { EstadoListadoService } from '../../../core/services/sistema/estado-listado.service';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AjustesService, AreaService, AuthService, CargoService, PaymentConceptService, PlanillaCorridaService, SedeService, TipoContratoService, ValorLegal } from '../../../core/services';
import { PaymentConcept } from '../../../core/models';
import { EmpleadoService } from '../../../core/services';
import { Empleado } from '../../../core/models';
import { BoletaService } from '../../../core/services';
import { PlanillaService } from '../../../core/services';
import { PayrollDetalleService } from '../../../core/services';
import { Planilla } from '../../../core/models';
import { ToastService } from '../../../core/services';
import { ConfirmService } from '../../../core/services';
import { Observable, of, map, switchMap, forkJoin, EMPTY, expand } from 'rxjs';
import { PistaDirective } from '../../../shared/directives/pista.directive';
import { AlCuerpoDirective } from '../../../shared/directives/al-cuerpo.directive';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { RouterLink } from '@angular/router';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FiltrosComponent } from '../../../shared/components/filtros/filtros.component';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CampoFiltro, ValoresFiltro } from '../../../shared/components/filtros/filtros.models';
import { MESES_OPCIONES } from '../../../shared/constants';
import { diasHabilesDelMes, formatoDia, guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { SubirFirmadasComponent } from '../subir-firmadas/subir-firmadas.component';
import { ResultadoFirmarAqui, ResumenFirmaDigital } from '../../../core/services/planilla/boleta.service';

export interface FormularioBoleta {
  remuneracionBasica: number | null;
  bonificacionCargo: number | null;
  asignacionFamiliar: number | null;
  vacacionesTruncas: number | null;
  gratificacionesFiestas: number | null;
  bonifExtraordTemporal: number | null;
  otrosConceptosSubsidio: number | null;
  compensacionTiempoServicios: number | null;
  bonificacion: number | null;
  onp13: number | null;
  sppFondoPensiones: number | null;
  sppPrimaSeguro: number | null;
  sppComision: number | null;
  ir5taCategoria: number | null;
  descuentoAlimentacion: number | null;
  descuentoBazar: number | null;
  descuentoAutorizadoDiezmo: number | null;
  descuentoOtros: number | null;
  descuentoEscolaridad: number | null;
  essalud9: number | null;
  sctr: number | null;
  adelanto: number | null;
  /** Donde se firma: la misma que imprime el PDF (boleta.blade.php). */
  ciudad: string;
  fechaEmision: string;
  mes: number;
  anio: number;
}

@Component({
  selector: 'app-emision-boleta-list',
  standalone: true,
  imports: [IconComponent, CommonModule, FormsModule, PistaDirective, AlCuerpoDirective, PageHeaderComponent, DataTableComponent, FiltrosComponent, FormModalComponent, SubirFirmadasComponent, RouterLink],
  templateUrl: './emision-boleta-list.component.html',
  styleUrl: './emision-boleta-list.component.scss'
})
export class EmisionBoletaListComponent implements OnInit {
  /** El modal de avance de los procesos largos (ver ProgresoService). */
  private progreso = inject(ProgresoService);

  private estadoListados = inject(EstadoListadoService);

  empleados: Empleado[] = [];
  cargandoEmpleados = false;

  /** El corte y el buscador los hace el backend; acá solo se pinta. */
  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  busqueda = '';
  totalEmpleados = 0;

  /**
   * De los trabajadores QUE SE ESTÁN VIENDO, cuáles ya tienen planilla de
   * este mes. Se pregunta solo por los ids de la página.
   */
  empleadosEditados = new Set<string>();

  /**
   * De los trabajadores QUE SE ESTÁN VIENDO, a quiénes ya se les emitió la
   * boleta de este mes —uno por uno o en la emisión masiva, da lo mismo—.
   *
   * Es lo que apaga "Editar": una vez que la boleta salió, cambiar la
   * planilla por detrás la deja mintiendo sobre lo que el trabajador ya
   * recibió (y pudo haber firmado). Si hace falta corregirla, se anula la
   * boleta primero desde Documentos, no se pisa por acá.
   */
  empleadosConBoletaEmitida = new Set<string>();
  /** La boleta del mes de cada trabajador (con su firma digital, si va por ahí). */
  boletaDeEmpleado = new Map<string, NonNullable<Planilla['documento_boleta']>>();

  /**
   * De los trabajadores QUE SE ESTÁN VIENDO, en qué planilla está su
   * planilla de este mes: "Planilla TIC", o "Sin agrupar" si no está en
   * ninguna. Quien no tiene planilla del mes no aparece aquí.
   */
  planillaDeEmpleado = new Map<string, string>();

  /**
   * Cuantas planillas hay en todo el mes. Es distinto de empleadosEditados:
   * ese conjunto es de la página, y este número decide si el mes está sin
   * empezar (y toca enseñar el aviso de "generar la planilla del mes").
   */
  planillasDelMes = 0;

  /**
   * Cuántos trabajadores hay en total, sin contar lo que filtre la tabla.
   *
   * Hace falta aparte porque las cifras de arriba miden el AVANCE del mes
   * ("18 armadas, 4 sin armar") y eso no puede depender del filtro: al
   * pedir "a quién le falta" la lista queda en 4 y "sin armar" habría
   * salido 0, que es justo lo contrario de lo que pasa.
   */
  totalDelColegio = 0;

  /**
   * Si ya llegó la cuenta de las planillas del mes.
   *
   * El aviso de "este mes está sin empezar" no puede salir antes: la lista de
   * trabajadores y el conteo llegan por separado, y en ese hueco de medio
   * segundo planillasDelMes todavía vale 0 y el aviso parpadeaba en meses que
   * sí tenían boletas.
   */
  conteoListo = false;

  columnas: ColumnaTabla<Empleado>[] = [
    {
      campo: 'nombre', header: 'Nombres y apellidos', ancho: '24%',
      formatear: (_v, e) => `${e.nombre} ${e.apellido}`,
    },
    { campo: 'dni', header: 'DNI', ancho: '10%' },
    { campo: 'cargo.nombre', header: 'Cargo', ancho: '17%' },
    { campo: 'area.nombre', header: 'Área', ancho: '17%' },
    {
      // De qué planilla sale su boleta de este mes. La boleta no se agrupa
      // aparte: es de su planilla, y la planilla ya sabe en qué grupo está.
      campo: 'id', header: 'Planilla', ancho: '17%', romperTexto: true,
      formatear: (_v, e) => this.planillaDeEmpleado.get(e.id) ?? '—',
    },
    {
      // Se llamaba "Boleta del mes" y enseñaba si tenía PLANILLA. No es lo
      // mismo: la boleta es el papel que sale después, y decir que la de
      // alguien está "sin armar" cuando nadie le armó su planilla hacía
      // creer que el sistema ya le había hecho una.
      // Si la boleta de este mes ya se emitió. Decir "Armada" repetía lo que
      // ya dice la columna Planilla: sin planilla no hay boleta que emitir.
      // Con firma digital del colegio dice además en qué va: por firmar,
      // a medias, entregada, recibida.
      campo: 'id', header: 'Estado', tipo: 'badge', ancho: '15%',
      formatear: (_v, e) => this.estadoBoleta(e).texto,
      badgeSeveridad: (_v, e) => this.estadoBoleta(e).tono,
    },
  ];

  /**
   * Las cifras de arriba: cuánta gente hay y cuántas boletas van armadas de
   * ese mes. Las dos salen del backend contando TODO, no la página.
   */
  get hayFiltros(): boolean {
    // La vista de arriba (con/sin planilla) no cuenta como filtro: si
    // contara, la cifra diría siempre "En la lista".
    return Object.keys(this.filtros).some((clave) => clave !== 'planilla');
  }

  accionesFila: AccionPersonalizada<Empleado>[] = [
    {
      id: 'editar', titulo: 'Revisar y editar los conceptos de su boleta', icono: 'edit', etiqueta: 'Editar',
      visible: (e) => !this.empleadosConBoletaEmitida.has(e.id),
    },
    {
      // Con la boleta ya emitida (una por una o en masa) no se edita
      // directo: quedaría mintiendo sobre lo que el trabajador ya recibió.
      // Pero a veces sí hace falta —un aumento de último momento, un dato
      // que salió mal—, así que no queda cerrado del todo: pide de nuevo la
      // clave de quien lo abre.
      id: 'desbloquear', titulo: 'Ya se emitió su boleta de este mes. Pon tu clave para poder corregirla.',
      icono: 'lock', etiqueta: 'Bloqueado', severidad: 'warning',
      // Con la firma digital del colegio ya puesta no hay corrección posible:
      // el PDF está sellado. Ahí el camino es Anular.
      visible: (e) => this.empleadosConBoletaEmitida.has(e.id) && !this.tieneFirmaDelColegio(e),
    },
    {
      id: 'anular', titulo: 'Anular esta boleta para corregirla y volver a emitirla (el trabajador todavía no la abrió)',
      icono: 'trash', etiqueta: 'Anular', severidad: 'danger',
      visible: (e) => {
        const b = this.boletaDeEmpleado.get(e.id);
        return !!b?.firma_colegio && b.estado_firma === 'pendiente';
      },
    },
  ];

  /** En qué va su boleta del mes. */
  estadoBoleta(e: Empleado): { texto: string; tono: 'success' | 'info' | 'warning' | 'secondary' } {
    const b = this.boletaDeEmpleado.get(e.id);
    if (!b) return { texto: 'No emitida', tono: 'warning' };
    if (b.firma_colegio === 'pendiente') return { texto: 'Por firmar (colegio)', tono: 'info' };
    if (b.firma_colegio === 'parcial') return { texto: `Firmada ${(b.firmas_colegio ?? []).length} de ${this.resumenFirma?.requeridas ?? 2}`, tono: 'info' };
    if (b.estado_firma === 'firmado') return { texto: b.firma_colegio ? 'Recibida' : 'Firmada', tono: 'success' };
    if (b.firma_colegio === 'completa') return { texto: b.estado_firma === 'visto' ? 'Entregada · abierta' : 'Entregada', tono: 'success' };
    return { texto: 'Emitida', tono: 'success' };
  }

  tieneFirmaDelColegio(e: Empleado): boolean {
    const f = this.boletaDeEmpleado.get(e.id)?.firma_colegio;
    return f === 'parcial' || f === 'completa';
  }

  alAccionar(evento: { accion: string; fila: Empleado }): void {
    if (evento.accion === 'anular') {
      this.anularBoleta(evento.fila);
      return;
    }
    this.abrirModal(evento.fila);
  }

  // ── Firma digital del colegio (ReFirma) ──

  resumenFirma: ResumenFirmaDigital | null = null;
  modalSubirFirmadas = false;
  /** El modal con los pasos de la firma digital del mes. */
  modalFirma = false;

  /** Cuántas esperan la firma del colegio (sin firmar y a medias). */
  get porFirmar(): number {
    const r = this.resumenFirma;
    return r ? r.por_firmar + r.a_medias : 0;
  }

  /** Del modal de pasos al de subir: uno cierra y el otro abre. */
  abrirSubirFirmadas(): void {
    this.modalFirma = false;
    this.modalSubirFirmadas = true;
  }

  emitirDesdeFirma(): void {
    this.modalFirma = false;
    this.emitirTodasLasBoletas();
  }

  // ── «Firmar aquí», con el certificado de quien mira ──

  modalFirmarAqui = false;
  claveFirmarAqui = '';
  firmandoAqui = false;
  /** Cómo va la pasada: cuántas lleva de cuántas. */
  avanceFirma: { hechas: number; total: number } | null = null;
  /** Se pidió detener: termina la tanda en curso y no pide más. */
  private detenerFirma = false;
  /** Solo si alguna falló o se detuvo: el detalle se queda en el modal. */
  resultadoFirmarAqui: (Pick<ResultadoFirmarAqui, 'firmadas' | 'entregadas' | 'a_medias' | 'errores'> & { quedan: number; detenida: boolean }) | null = null;

  get porcentajeFirma(): number {
    const a = this.avanceFirma;
    return a && a.total ? Math.min(100, Math.round((a.hechas / a.total) * 100)) : 0;
  }

  get puedeFirmarAqui(): boolean {
    const c = this.resumenFirma?.mi_certificado;
    return !!c && !c.vencido && c.por_firmar > 0;
  }

  get pistaFirmarAqui(): string {
    const c = this.resumenFirma?.mi_certificado;
    if (!c) return 'Primero pon tu certificado en Ajustes → Boletas.';
    if (c.vencido) return 'Tu certificado venció: renuévalo con la entidad y ponlo de nuevo en Ajustes → Boletas.';
    if (!c.por_firmar) return 'No hay boletas de este mes esperando tu firma.';
    return `Firma de una vez las ${c.por_firmar} boleta(s) que esperan tu firma, con tu certificado. Te pide su clave.`;
  }

  abrirFirmarAqui(): void {
    if (!this.puedeFirmarAqui) return;
    this.modalFirma = false;
    this.claveFirmarAqui = '';
    this.resultadoFirmarAqui = null;
    this.modalFirmarAqui = true;
  }

  /** Al cerrar se olvida la clave. Mientras firma no se cierra: primero «Detener». */
  cerrarFirmarAqui(): void {
    if (this.firmandoAqui) return;
    this.modalFirmarAqui = false;
    this.claveFirmarAqui = '';
    this.resultadoFirmarAqui = null;
  }

  /**
   * Firma por tandas: pide una, y con su «siguiente» la que sigue, hasta que
   * no quede ninguna (o se pida detener). Con 94 o con mil boletas, ningún
   * pedido es largo y la barra avanza de verdad.
   */
  firmarAqui(): void {
    if (!this.claveFirmarAqui || this.firmandoAqui) return;
    const periodo = `${this.nombreMes(this.mesGlobal * 1)} ${this.anioGlobal}`;
    const mes = Number(this.mesGlobal);
    const anio = Number(this.anioGlobal);
    const clave = this.claveFirmarAqui;
    const tanda = (desde: string | null) => this.boletaService.firmarAqui(mes, anio, clave, desde);

    const suma = { firmadas: 0, entregadas: 0, a_medias: 0, errores: [] as ResultadoFirmarAqui['errores'] };
    let quedan = 0;
    this.firmandoAqui = true;
    this.detenerFirma = false;
    this.avanceFirma = { hechas: 0, total: 0 };

    const terminar = (falla?: unknown) => {
      this.firmandoAqui = false;
      this.claveFirmarAqui = '';
      this.avanceFirma = null;
      this.cargarEmpleados();
      this.cargarResumenFirma();

      // Falló antes de firmar nada (la clave, por ejemplo): solo el aviso.
      if (falla && !suma.firmadas && !suma.errores.length) {
        this.toastService.error('No se firmó', mensajeErrorApi(falla, 'No se pudieron firmar las boletas.'));
        return;
      }
      const detenida = this.detenerFirma || !!falla;
      if (suma.errores.length || detenida) {
        this.resultadoFirmarAqui = { ...suma, quedan, detenida };
        if (falla) this.toastService.error('Se cortó la firma', mensajeErrorApi(falla, 'Se perdió la conexión con el servidor.'));
        return;
      }
      this.modalFirmarAqui = false;
      this.toastService.success(
        `${suma.firmadas} boleta${suma.firmadas === 1 ? '' : 's'} firmada${suma.firmadas === 1 ? '' : 's'}`,
        suma.a_medias
          ? `De ${periodo}: ${suma.entregadas} ya le llegaron al trabajador y ${suma.a_medias} esperan la otra firma.`
          : `De ${periodo}: ya le llegaron a cada trabajador.`
      );
    };

    tanda(null)
      .pipe(expand((res) => (res.data.siguiente && !this.detenerFirma ? tanda(res.data.siguiente) : EMPTY)))
      .subscribe({
        next: (res) => {
          const d = res.data;
          if (this.avanceFirma && !this.avanceFirma.total) this.avanceFirma.total = d.total;
          if (this.avanceFirma) this.avanceFirma.hechas += d.procesadas;
          suma.firmadas += d.firmadas;
          suma.entregadas += d.entregadas;
          suma.a_medias += d.a_medias;
          suma.errores.push(...d.errores);
          quedan = d.total - d.procesadas;
        },
        error: (err) => terminar(err),
        complete: () => terminar(),
      });
  }

  /** Termina la tanda en curso y no pide más: lo firmado queda firmado. */
  detenerFirmarAqui(): void {
    this.detenerFirma = true;
  }
  descargandoParaFirmar = false;
  descargandoConstancia = false;

  cargarResumenFirma(): void {
    this.boletaService.resumenFirmaDigital(Number(this.mesGlobal), Number(this.anioGlobal)).subscribe({
      next: (res) => { if (res.success) this.resumenFirma = res.data; },
      // Si falla, el panel no sale: lo demás de la pantalla sigue igual.
      error: () => (this.resumenFirma = null),
    });
  }

  descargarParaFirmar(): void {
    this.descargandoParaFirmar = true;
    const periodo = `${this.nombreMes(this.mesGlobal * 1)} ${this.anioGlobal}`;

    this.boletaService
      .bajarEmitidasEnZip({ mes: this.mesGlobal, anio: this.anioGlobal, para_firmar: true, search: this.busqueda || undefined, ...this.filtros })
      .subscribe({
        next: (cantidad) => {
          this.descargandoParaFirmar = false;
          this.toastService.success(`Bajando ${cantidad} ${cantidad === 1 ? 'boleta' : 'boletas'} para firmar`,
            `Mira la barra de descargas del navegador. Descomprime «Boletas para firmar ${periodo}», fírmalas en ReFirma y súbelas con «Subir firmadas».`);
        },
        error: (err) => {
          this.descargandoParaFirmar = false;
          this.toastService.error('No se descargó', mensajeErrorApi(err, 'No se pudieron juntar las boletas.'));
        },
      });
  }

  descargarConstancia(): void {
    this.descargandoConstancia = true;
    const periodo = `${this.nombreMes(this.mesGlobal * 1)} ${this.anioGlobal}`;
    this.boletaService.constanciaDeEntrega(Number(this.mesGlobal), Number(this.anioGlobal)).subscribe({
      next: (blob) => {
        guardarArchivo(blob, `Constancia de entrega - ${periodo}.xlsx`);
        this.descargandoConstancia = false;
      },
      error: () => {
        this.descargandoConstancia = false;
        this.toastService.error('No se descargó', 'No se pudo armar la constancia de entrega.');
      },
    });
  }

  /** Se guardaron firmadas: la tabla y el panel cambian. */
  alGuardarFirmadas(): void {
    this.cargarEmpleados();
  }

  anularBoleta(e: Empleado): void {
    const boleta = this.boletaDeEmpleado.get(e.id);
    if (!boleta) return;
    this.confirmService.confirmar({
      titulo: 'Anular la boleta',
      mensaje: `Se borra la boleta de ${e.apellido} ${e.nombre} de ${this.nombreMes(this.mesGlobal * 1)}, con sus firmas. `
        + 'Después corriges su planilla, la vuelves a emitir y se firma de nuevo. El trabajador todavía no la abrió.',
      aceptarTexto: 'Sí, anular',
      variante: 'danger',
    }).then((ok) => {
      if (!ok) return;
      this.boletaService.anular(boleta.id).subscribe({
        next: (res) => {
          this.toastService.success('Boleta anulada', res.message ?? 'Ya puedes corregirla y volver a emitirla.');
          this.cargarEmpleados();
        },
        error: (err) => this.toastService.error('No se anuló', mensajeErrorApi(err, 'Inténtalo de nuevo.')),
      });
    });
  }

  // ── Desbloquear una boleta ya emitida ──
  private authService = inject(AuthService);
  showPasswordModal = false;
  empleadoADesbloquear: Empleado | null = null;
  passwordDesbloqueo = '';
  passwordErrorMsg = '';
  verificandoPassword = false;

  // Modal state
  showModal = false;
  empleadoSeleccionado: Empleado | null = null;
  formulario!: FormularioBoleta;
  planillaActual: Planilla | null = null;
  private _formularioOriginal: string = '';

  /**
   * Líneas de la planilla que NO tienen un campo fijo acá —un concepto
   * nuevo agregado desde Planillas o Importar Conceptos, que esta pantalla
   * no "conoce" por nombre—. Antes se perdían de vista: la línea seguía
   * guardada, pero esta pantalla la ignoraba en silencio. Se muestran aparte,
   * de solo lectura (se editan desde donde se agregaron), para que RR.HH.
   * vea SIEMPRE todo lo que tiene la planilla, no solo los ~20 de siempre.
   */
  conceptosExtra: { id: string; nombre: string; monto: number; tipo: string }[] = [];

  /** Días de lunes a viernes del mes que se está armando. */
  get diasDelMes(): number {
    return diasHabilesDelMes(Number(this.mesGlobal), Number(this.anioGlobal));
  }

  /**
   * El detalle escrito en las dos bolsas "Otros Conceptos" (por ejemplo
   * «Cobro de corbata»): se muestra en su fila, como en la boleta impresa.
   */
  detalleOtrosIngresos = '';
  detalleOtrosDescuentos = '';

  // ── Agregar otro concepto desde la vista previa ──
  private conceptoService = inject(PaymentConceptService);
  /** Los que se pueden agregar a mano: todo el catálogo menos los de ley. */
  catalogoParaAgregar: PaymentConcept[] = [];
  mostrarAgregarConcepto = false;
  guardandoConcepto = false;
  nuevoConcepto: { id: string; monto: number | null; detalle: string } = { id: '', monto: null, detalle: '' };
  /** true: se abrió con la clave, sobre una boleta ya emitida. Sin botón de emitir. */
  modoCorrigiendo = false;

  generandoPDF = false;
  generandoMasivo = false;

  // Global Period State
  mesGlobal: number = new Date().getMonth() + 1;
  anioGlobal: number = new Date().getFullYear();
  aniosDisponibles: number[] = [];

  // Mass emission modal
  private confirmService = inject(ConfirmService);
  private areaService = inject(AreaService);
  private cargoService = inject(CargoService);
  private sedeService = inject(SedeService);
  private tipoContratoService = inject(TipoContratoService);
  private corridaService = inject(PlanillaCorridaService);

  mesesDisponibles = MESES_OPCIONES.map((m) => ({ num: m.value, nombre: m.label }));

  /*
   * Los filtros de la tabla.
   *
   * Acá la pregunta mientras se emite es siempre la misma: "¿a quién le
   * falta?". Con 150 trabajadores y la mitad ya emitida, eso se buscaba
   * fila por fila mirando la columna de estado.
   *
   * Los de boleta y planilla son del MES que se está armando, así que
   * viajan con el mes y el año de arriba.
   */
  /**
   * Se abre con los que YA tienen su planilla del mes.
   *
   * Son los únicos que pueden tener boleta: la boleta sale de la planilla.
   * Con la lista completa, un colegio de 100 trabajadores enseñaba diez
   * páginas de gente sin nada que emitir.
   */
  filtros: ValoresFiltro = { planilla: 'con' };

  /** «Planilla del mes» del embudo: 'con', 'sin' o '' (todos). */
  get vista(): string {
    return this.filtros['planilla'] ?? '';
  }

  /** A cuántos les falta la planilla del mes. */
  get sinPlanilla(): number {
    return Math.max(this.totalDelColegio - this.planillasDelMes, 0);
  }

  /** Lo que dice la tabla cuando no hay filas, según lo que se esté viendo. */
  get mensajeTabla(): string {
    if (this.vista === 'con') {
      return 'Todavía no hay ninguna planilla armada de este mes. Mira a quiénes les falta y ármalas.';
    }
    if (this.vista === 'sin') {
      return 'No le falta la planilla a nadie: están todas armadas.';
    }

    return 'No se encontraron trabajadores.';
  }

  camposFiltro: CampoFiltro[] = [
    {
      // Arranca en «Con planilla»: la boleta sale de la planilla.
      clave: 'planilla', etiqueta: 'Planilla del mes', tipo: 'opciones', vacio: 'Todos',
      opciones: [
        { valor: 'con', etiqueta: 'Con planilla' },
        { valor: 'sin', etiqueta: 'Les falta la planilla' },
      ],
    },
    {
      clave: 'boleta', etiqueta: 'Boleta del mes', tipo: 'opciones', vacio: 'No importa',
      opciones: [
        { valor: 'sin', etiqueta: 'Le falta' },
        { valor: 'sin_firmar', etiqueta: 'Emitida, sin firmar' },
        { valor: 'por_firmar_colegio', etiqueta: 'Por firmar (colegio)' },
        { valor: 'con', etiqueta: 'Ya emitida' },
      ],
    },
    // Las opciones son las planillas del mes y año de arriba: cambian
    // cuando cambia el periodo (ver cargarPlanillasDelMes).
    { clave: 'corrida_id', etiqueta: 'Planilla', tipo: 'opciones', vacio: 'Todas', opciones: [] },
    { clave: 'sede_id', etiqueta: 'Sede', tipo: 'opciones', vacio: 'Todas', opciones: [] },
    { clave: 'area_id', etiqueta: 'Área', tipo: 'opciones', vacio: 'Todas', opciones: [] },
    { clave: 'cargo_id', etiqueta: 'Cargo', tipo: 'opciones', vacio: 'Todos', opciones: [] },
    { clave: 'tipo_contrato_id', etiqueta: 'Tipo de contrato', tipo: 'opciones', vacio: 'Todos', opciones: [] },
    {
      clave: 'sistema_pensiones', etiqueta: 'Pensión', tipo: 'opciones', vacio: 'Todas',
      opciones: [
        { valor: 'ONP', etiqueta: 'ONP' },
        { valor: 'AFP', etiqueta: 'AFP' },
        { valor: 'ninguno', etiqueta: 'No aporta' },
      ],
    },
    {
      clave: 'sin_sueldo', etiqueta: 'Sin sueldo puesto', tipo: 'si-no',
      ayuda: 'A quien no tiene sueldo no se le puede armar la boleta.',
    },
  ];

  alFiltrar(): void {
    this.pagina = 0;
    this.cargarEmpleados();
  }

  private catalogosListos = false;

  /**
   * Las planillas de ESTE mes y año, para el filtro «Planilla».
   *
   * Una planilla agrupada es de un solo mes: la de septiembre no tiene a
   * nadie en octubre. Por eso no se cargan todas, sino las del periodo de
   * arriba, y se vuelven a pedir cada vez que se cambia.
   */
  private cargarPlanillasDelMes(): void {
    const mes = Number(this.mesGlobal);
    const anio = Number(this.anioGlobal);
    const campo = this.camposFiltro.find((c) => c.clave === 'corrida_id');
    if (!campo) return;

    campo.ayuda = `Las de ${this.nombreMes(mes)} ${anio}.`;

    this.corridaService.getAll({ mes, anio }).subscribe({
      next: (res) => {
        // Si mientras llegaba ya se cambió de mes, esta respuesta es vieja.
        if (!res.success || mes !== Number(this.mesGlobal) || anio !== Number(this.anioGlobal)) return;

        campo.opciones = [
          ...res.data
            .map((c) => ({ valor: c.id, etiqueta: c.nombre }))
            .sort((a, b) => a.etiqueta.localeCompare(b.etiqueta, 'es')),
          // Las planillas del mes que no están en ningún grupo.
          { valor: 'sin_agrupar', etiqueta: 'Sin agrupar' },
        ];
      },
      error: () => this.toastService.error('Filtros', 'No se pudieron cargar las planillas del mes.'),
    });
  }

  /** Sedes, áreas y cargos: solo si se abre el panel. */
  cargarCatalogos(): void {
    if (this.catalogosListos) return;
    this.catalogosListos = true;
    this.cargarPlanillasDelMes();

    forkJoin({
      sedes: this.sedeService.getAll(),
      areas: this.areaService.getAll(),
      cargos: this.cargoService.getAll(),
      tiposContrato: this.tipoContratoService.getAll(),
    }).subscribe({
      next: ({ sedes, areas, cargos, tiposContrato }) => {
        this.ponerOpciones('sede_id', sedes.data);
        this.ponerOpciones('area_id', areas.data);
        this.ponerOpciones('cargo_id', cargos.data);
        this.ponerOpciones('tipo_contrato_id', tiposContrato.data);
      },
      error: () => {
        this.catalogosListos = false;
        this.toastService.error('Filtros', 'No se pudieron cargar las sedes, áreas y cargos.');
      },
    });
  }

  private ponerOpciones(clave: string, lista: { id: string; nombre: string }[]): void {
    const campo = this.camposFiltro.find((c) => c.clave === clave);
    if (!campo) return;

    campo.opciones = (lista ?? [])
      .map((x) => ({ valor: x.id, etiqueta: x.nombre }))
      .sort((a, b) => a.etiqueta.localeCompare(b.etiqueta, 'es'));
  }

  constructor(
    private empleadoService: EmpleadoService,
    private boletaService: BoletaService,
    private planillaService: PlanillaService,
    private detalleService: PayrollDetalleService,
    private toastService: ToastService
  ) {}

  ngOnInit(): void {
    const currentYear = new Date().getFullYear();
    for (let i = currentYear - 5; i <= currentYear + 5; i++) {
      this.aniosDisponibles.push(i);
    }
    this.formulario = this.getFormularioVacio();
    const recordado = this.estadoListados.leer<Record<string, unknown>>('emision-boletas');
    if (recordado) Object.assign(this, recordado);
    // Con filtros recordados, sus opciones hacen falta ya para mostrar los nombres.
    if (Object.keys(this.filtros ?? {}).length) this.cargarCatalogos();
    this.cargarEmpleados();

    // Una fila basta: lo que interesa es el total que manda el backend.
    this.empleadoService.getPagina({ page: 0, size: 1 }).subscribe({
      next: (res) => { if (res.success) this.totalDelColegio = res.data.totalElements; },
      error: () => {},
    });
  }

  cargarEmpleados(): void {
    // Para devolvérselo al volver (ver EstadoListadoService).
    this.estadoListados.guardar('emision-boletas', { busqueda: this.busqueda, pagina: this.pagina, filtros: this.filtros, mesGlobal: this.mesGlobal, anioGlobal: this.anioGlobal });
    this.cargandoEmpleados = true;
    this.empleadoService
      .getPagina({
        page: this.pagina,
        size: this.TAMANO_PAGINA,
        search: this.busqueda || undefined,
        // El mes que se está armando: los filtros de boleta y planilla son
        // de ESE periodo, no del trabajador.
        mes: this.mesGlobal,
        anio: this.anioGlobal,
        ...this.filtros,
      })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.empleados = res.data.content;
            this.totalEmpleados = res.data.totalElements;
            this.cargarEstadoBoletas();
            return;
          }
          this.cargandoEmpleados = false;
        },
        error: (err) => {
          this.toastService.error('Error', err?.error?.message || 'No se pudo cargar la lista de trabajadores.');
          this.cargandoEmpleados = false;
        },
      });
  }

  irAPagina(pagina: number): void {
    this.pagina = pagina;
    this.cargarEmpleados();
  }

  buscar(termino: string): void {
    this.busqueda = termino;
    this.pagina = 0;
    this.cargarEmpleados();
  }

  onGlobalPeriodChange(): void {
    // La planilla elegida era del mes anterior: en este no tiene a nadie,
    // y dejarla puesta enseñaba una tabla vacía sin explicación.
    if (this.filtros['corrida_id']) {
      const { corrida_id: _, ...resto } = this.filtros;
      this.filtros = resto;
    }

    // Las opciones del filtro solo se cargan si ya se abrió el panel; si no,
    // se cargarán al abrirlo, ya con el mes nuevo.
    if (this.catalogosListos) {
      const campo = this.camposFiltro.find((c) => c.clave === 'corrida_id');
      if (campo) campo.opciones = [];
      this.cargarPlanillasDelMes();
    }

    this.pagina = 0;
    this.cargarEmpleados();
  }

  /**
   * Cuáles de los trabajadores en pantalla ya tienen planilla del mes.
   *
   * Se pregunta por los ids de la página (?empleado_ids=), no por todo el
   * colegio: antes esto se traía las 150 planillas del mes para marcar diez
   * filas. Y aparte, un conteo suelto de cuántas hay en total, que es lo que
   * decide si el mes está sin empezar.
   */
  cargarEstadoBoletas(): void {
    const ids = this.empleados.map((e) => e.id);
    if (ids.length === 0) {
      this.empleadosEditados.clear();
      this.planillaDeEmpleado.clear();
      this.cargandoEmpleados = false;
      return;
    }

    this.planillaService
      .getPagina({
        mes: this.mesGlobal,
        anio: this.anioGlobal,
        empleado_ids: ids.join(','),
        page: 0,
        size: ids.length,
      })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.empleadosEditados = new Set(res.data.content.map((p) => p.empleado_id));
            this.empleadosConBoletaEmitida = new Set(
              res.data.content.filter((p) => p.documento_boleta).map((p) => p.empleado_id)
            );
            this.boletaDeEmpleado = new Map(
              res.data.content.filter((p) => p.documento_boleta).map((p) => [p.empleado_id, p.documento_boleta!] as const)
            );
            // El backend ya manda cada planilla con su grupo: no hace
            // falta otra consulta para saber de cuál viene.
            this.planillaDeEmpleado = new Map(
              res.data.content.map((p) => [p.empleado_id, p.corrida?.nombre ?? 'Sin agrupar'] as [string, string])
            );
          }
          this.cargandoEmpleados = false;
        },
        error: () => {
          // Si falla, las filas salen como "sin armar": se sigue pudiendo editar.
          this.empleadosEditados.clear();
          this.empleadosConBoletaEmitida.clear();
          this.boletaDeEmpleado.clear();
          this.planillaDeEmpleado.clear();
          this.cargandoEmpleados = false;
        },
      });

    this.cargarResumenFirma();

    this.conteoListo = false;
    this.planillaService
      .getPagina({ mes: this.mesGlobal, anio: this.anioGlobal, page: 0, size: 1 })
      .subscribe({
        next: (res) => {
          if (res.success) this.planillasDelMes = res.data.totalElements;
          this.conteoListo = true;
        },
        error: () => {
          this.planillasDelMes = 0;
          this.conteoListo = true;
        },
      });
  }

  nombreMes(num: number): string {
    return this.mesesDisponibles.find(m => m.num === num)?.nombre || '';
  }

  /**
   * Punto de entrada único de la tabla, sea cual sea el botón que se pulsó
   * ("Editar" o el candado "Bloqueado"): si la fila está bloqueada pide la
   * clave primero; si no, abre el formulario directo.
   */
  abrirModal(empleado: Empleado): void {
    if (this.empleadosConBoletaEmitida.has(empleado.id)) {
      this.abrirDesbloqueo(empleado);
      return;
    }
    this.abrirFormularioEdicion(empleado, false);
  }

  /**
   * Con `corrigiendo` en true (se llegó con la clave, boleta ya emitida) el
   * formulario abre igual, pero sin el botón de "Emitir Boleta Oficial": ese
   * es el acto de primera vez —avisa al trabajador, queda de evidencia—, y
   * repetirlo por una corrección le mandaría un segundo aviso de algo que ya
   * tenía. Ahí solo se guardan los montos y se refresca el PDF.
   */
  private abrirFormularioEdicion(empleado: Empleado, corrigiendo: boolean): void {
    this.empleadoSeleccionado = empleado;
    this.modoCorrigiendo = corrigiendo;
    this.formulario = this.getFormularioVacio();
    this.showModal = true;
    document.body.style.overflow = 'hidden';

    // Cargar la planilla del empleado para el periodo GLOBAL seleccionado
    this.cargarPlanillaDelEmpleado(empleado.id, this.mesGlobal, this.anioGlobal, empleado);
  }

  // ── Desbloquear una boleta ya emitida ──

  private abrirDesbloqueo(empleado: Empleado): void {
    this.empleadoADesbloquear = empleado;
    this.passwordDesbloqueo = '';
    this.passwordErrorMsg = '';
    this.showPasswordModal = true;
    document.body.style.overflow = 'hidden';
  }

  cerrarPasswordModal(): void {
    this.showPasswordModal = false;
    this.empleadoADesbloquear = null;
    this.passwordDesbloqueo = '';
    this.passwordErrorMsg = '';
    this.verificandoPassword = false;
    document.body.style.overflow = '';
  }

  /**
   * Confirma la clave de quien está en la sesión (RR.HH./Admin, no la del
   * trabajador) y, si es correcta, recién ahí abre el formulario. Un aumento
   * de último momento o un dato que salió mal sí se puede corregir, pero
   * queda a nombre de quien puso su propia clave para hacerlo.
   */
  confirmarDesbloqueo(): void {
    if (!this.empleadoADesbloquear) return;
    if (!this.passwordDesbloqueo) {
      this.passwordErrorMsg = 'Escribe tu contraseña para continuar.';
      return;
    }

    this.verificandoPassword = true;
    this.passwordErrorMsg = '';

    this.authService.verificarPassword(this.passwordDesbloqueo).subscribe({
      next: (res) => {
        this.verificandoPassword = false;
        if (res.success) {
          const empleado = this.empleadoADesbloquear!;
          this.cerrarPasswordModal();
          this.abrirFormularioEdicion(empleado, true);
          return;
        }
        this.passwordErrorMsg = res.message || 'No se pudo verificar la contraseña.';
      },
      error: (err) => {
        this.verificandoPassword = false;
        this.passwordErrorMsg = mensajeErrorApi(err, 'Contraseña incorrecta o el servidor no respondió.');
      },
    });
  }

  /**
   * Los montos de ley del año de la boleta (UIT, asignación familiar, % de
   * pensión y EsSalud), los mismos con que el backend arma la planilla.
   * Antes esta pantalla tenía su propia copia de las tasas, y bastaba que
   * cambiaran en un lado para que la vista previa no cuadrara con la boleta.
   */
  ley: ValorLegal | null = null;

  /** "ONP 13%" / "ESSALUD 9%" con la tasa del año, como la boleta impresa. */
  etiquetaTasa(nombre: string, tasa: number | undefined, porDefecto: number): string {
    return `${nombre} ${Number(tasa ?? porDefecto)}%`;
  }
  private leyPara: number | null = null;
  private ajustesService = inject(AjustesService);

  cargarPlanillaDelEmpleado(empleadoId: string, mes: number, anio: number, empleado: Empleado): void {
    // Primero los montos de ese año; después, la planilla.
    if (this.leyPara !== Number(anio)) {
      const seguir = () => {
        this.leyPara = Number(anio);
        this.cargarPlanillaDelEmpleado(empleadoId, mes, anio, empleado);
      };
      this.ajustesService.valoresLegalesDelAnio(Number(anio)).subscribe({
        next: (res) => {
          this.ley = res.success ? res.data : null;
          seguir();
        },
        error: () => {
          this.ley = null;
          seguir();
        },
      });
      return;
    }

    this.planillaService.listar({ empleado_id: empleadoId, mes, anio }).subscribe({
      next: (res) => {
        if (res.success && res.data.length > 0) {
          this.planillaActual = res.data[0];

          // El básico sale de la planilla; el resto, de sus líneas de concepto.
          // Laravel manda los decimales como string ("2500.00") — se convierten aqui.
          this.formulario.remuneracionBasica = this.planillaActual.sueldo_base != null ? Number(this.planillaActual.sueldo_base) : null;

          this.cargarConceptosEnFormulario(this.planillaActual.id!);
        } else {
          this.planillaActual = null;
          // Si no existe, cargar el sueldo_base inicial del empleado
          this.formulario.remuneracionBasica = empleado.sueldo_base ?? null;
          this.formulario.bonificacion = null;
          this.formulario.descuentoOtros = null;
          // La de su ficha: cada planilla nueva la trae sola.
          this.formulario.bonificacionCargo = Number(empleado.bonificacion_cargo) || null;
          this.formulario.vacacionesTruncas = null;
          this.formulario.bonifExtraordTemporal = null;
          this.formulario.otrosConceptosSubsidio = null;
          this.formulario.compensacionTiempoServicios = null;
          this.formulario.ir5taCategoria = null;
          this.formulario.descuentoAlimentacion = null;
          this.formulario.descuentoBazar = null;
          this.formulario.descuentoAutorizadoDiezmo = null;
          this.formulario.descuentoEscolaridad = null;
          this.formulario.adelanto = null;
          this.conceptosExtra = [];
    this.detalleOtrosIngresos = '';
    this.detalleOtrosDescuentos = '';
    this.mostrarAgregarConcepto = false;
          // Sin planilla todavía: la Renta de 5ta se calcula con el sueldo de su ficha.
          this.cargarRenta5ta();
        }

        // Recalcular montos dinámicos/previsionales
        this.recalcularMontosDinamicos();
        this._formularioOriginal = JSON.stringify(this.formulario);
      },
      error: (err) => {
        console.error('Error cargando planilla del empleado', err);
        // Fallback simple
        this.formulario.remuneracionBasica = empleado.sueldo_base ?? null;
        this.recalcularMontosDinamicos();
        this._formularioOriginal = JSON.stringify(this.formulario);
      }
    });
  }

  onPeriodoChange(): void {
    if (this.empleadoSeleccionado) {
      this.cargarPlanillaDelEmpleado(
        this.empleadoSeleccionado.id,
        this.formulario.mes,
        this.formulario.anio,
        this.empleadoSeleccionado
      );
    }
  }

  recalcularMontosDinamicos(): void {
    if (!this.empleadoSeleccionado) return;
    // Sin los montos del año no se inventan: se dejan los que ya tenía.
    const ley = this.ley;
    if (!ley) return;

    const sueldo = this.formulario.remuneracionBasica ?? 0;
    const mes = this.formulario.mes;

    // Asignación Familiar (10% de la RMV de ese año) si tiene hijos
    this.formulario.asignacionFamiliar = this.empleadoSeleccionado.tiene_hijos ? ley.asignacion_familiar : 0.00;

    // La misma base que el backend: sueldo + asignación + bonificación por
    // cargo + vacaciones truncas. Sobre esto van la pensión y EsSalud.
    const base = sueldo + (this.formulario.asignacionFamiliar ?? 0)
      + Number(this.formulario.bonificacionCargo ?? 0) + Number(this.formulario.vacacionesTruncas ?? 0);
    // Con todos sus decimales, como el backend y el Excel: se redondea solo el total.
    const porciento = (tasa: number) => Number((base * (tasa / 100)).toFixed(6));

    // Gratificación de julio y diciembre
    this.formulario.gratificacionesFiestas = [7, 12].includes(Number(mes)) ? sueldo : 0.00;

    // Aportes de Pensión (ONP / AFP). Lo que no le toca va en 0 y no en
    // blanco: como en la boleta, se ve todo aunque sea cero.
    if (this.empleadoSeleccionado.sistema_pensiones === 'ONP') {
      this.formulario.onp13 = porciento(ley.onp);
      this.formulario.sppFondoPensiones = 0;
      this.formulario.sppPrimaSeguro = 0;
      this.formulario.sppComision = 0;
    } else if (this.empleadoSeleccionado.sistema_pensiones === 'AFP') {
      this.formulario.onp13 = 0;
      this.formulario.sppFondoPensiones = porciento(ley.aporte_afp);
      // Con 65 años cumplidos antes de este mes ya no paga la prima, igual que en el backend.
      this.formulario.sppPrimaSeguro = this.tiene65AlEmpezarElMes(this.empleadoSeleccionado.fecha_nacimiento, Number(mes), Number(this.formulario.anio))
        ? 0
        : porciento(ley.prima_seguro_afp);

      // "Mixta" no paga comisión en planilla, igual que en el backend.
      const comisiones: Record<string, number> = {
        Habitat: ley.comision_habitat,
        Integra: ley.comision_integra,
        Prima: ley.comision_prima,
        Profuturo: ley.comision_profuturo,
      };
      const tasaComision = this.empleadoSeleccionado.tipo_comision_afp === 'mixta'
        ? 0
        : comisiones[this.empleadoSeleccionado.afp ?? ''] ?? 0;
      this.formulario.sppComision = porciento(tasaComision);
    } else {
      this.formulario.onp13 = 0;
      this.formulario.sppFondoPensiones = 0;
      this.formulario.sppPrimaSeguro = 0;
      this.formulario.sppComision = 0;
    }

    // EsSalud nunca sobre menos que el sueldo mínimo (RMV), igual que en el backend.
    this.formulario.essalud9 = base > 0
      ? Number((Math.max(base, ley.rmv) * (ley.essalud / 100)).toFixed(6))
      : 0;
  }

  /** Si ya cumplió 65 años el día 1 de ese mes (desde ahí no paga la prima de la AFP). */
  private tiene65AlEmpezarElMes(nacimiento: string | null | undefined, mes: number, anio: number): boolean {
    if (!nacimiento) return false;
    const [a, m, d] = String(nacimiento).slice(0, 10).split('-').map(Number);
    return new Date(a + 65, m - 1, d) <= new Date(anio, mes - 1, 1);
  }

  cerrarModal(): void {
    // Cerrar no marca nada. La columna dice si el trabajador YA TIENE su
    // planilla del mes guardada, y tocar el formulario sin guardar no la
    // crea: antes bastaba con abrir y cambiar un número para que la fila
    // dijera "editado" aunque en la base de datos no hubiera nada.
    this.showModal = false;
    this.empleadoSeleccionado = null;
    this.planillaActual = null;
    this.modoCorrigiendo = false;
    this.conceptosExtra = [];
    this.detalleOtrosIngresos = '';
    this.detalleOtrosDescuentos = '';
    this.mostrarAgregarConcepto = false;
    document.body.style.overflow = '';
  }

  /** Las líneas de conceptosExtra que le tocan a cada columna de la pantalla. */
  get conceptosExtraIngreso() {
    return this.conceptosExtra.filter((c) => c.tipo === 'bonificacion');
  }
  get conceptosExtraDescuento() {
    return this.conceptosExtra.filter((c) => c.tipo === 'descuento');
  }
  get conceptosExtraAportacion() {
    return this.conceptosExtra.filter((c) => c.tipo === 'aportacion');
  }
  get conceptosExtraAdelanto() {
    return this.conceptosExtra.filter((c) => c.tipo === 'adelanto');
  }

  private sumaExtra(lineas: { monto: number }[]): number {
    return lineas.reduce((sum, c) => sum + (c.monto || 0), 0);
  }

  get totalIngresos(): number {
    const f = this.formulario;
    const fijos = [
      f.remuneracionBasica, f.bonificacionCargo, f.asignacionFamiliar,
      f.vacacionesTruncas, f.gratificacionesFiestas, f.bonifExtraordTemporal,
      f.otrosConceptosSubsidio, f.compensacionTiempoServicios, f.bonificacion
    ].reduce((sum: number, v) => sum + (v ? Number(v) : 0), 0);
    return fijos + this.sumaExtra(this.conceptosExtraIngreso);
  }

  get totalDescuentos(): number {
    const f = this.formulario;
    const fijos = [
      f.onp13, f.sppFondoPensiones, f.sppPrimaSeguro, f.sppComision,
      f.ir5taCategoria, f.descuentoAlimentacion, f.descuentoBazar,
      f.descuentoAutorizadoDiezmo, f.descuentoOtros, f.descuentoEscolaridad,
      f.adelanto
    ].reduce((sum: number, v) => sum + (v ? Number(v) : 0), 0);
    // El adelanto "extra" también resta del neto, igual que el campo fijo de Adelanto.
    return fijos + this.sumaExtra(this.conceptosExtraDescuento) + this.sumaExtra(this.conceptosExtraAdelanto);
  }

  get totalAportaciones(): number {
    const fijos = [(this.formulario.essalud9), (this.formulario.sctr)]
      .reduce((sum: number, v) => sum + (v ? Number(v) : 0), 0);
    return fijos + this.sumaExtra(this.conceptosExtraAportacion);
  }

  get totalNetoPagar(): number {
    return this.totalIngresos - this.totalDescuentos;
  }

  /** Qué campo de la pantalla corresponde a cada concepto del catálogo. */
  private readonly CAMPO_POR_CONCEPTO: Record<string, keyof FormularioBoleta> = {
    'Bonificación por Cargo': 'bonificacionCargo',
    'Asignación Familiar': 'asignacionFamiliar',
    'Vacaciones Truncas': 'vacacionesTruncas',
    'Gratificaciones Fiestas Patrias - Ley 29351 y 30334': 'gratificacionesFiestas',
    'Bonif. Extraord. Temporal - Ley 29351 y 30334': 'bonifExtraordTemporal',
    'Otros Conceptos (Ingresos)': 'otrosConceptosSubsidio',
    'Compensación por Tiempo de Servicios': 'compensacionTiempoServicios',
    'Bonificaciones': 'bonificacion',
    'ONP 13%': 'onp13',
    'SPP. Fondo Pensiones': 'sppFondoPensiones',
    'SPP. Prima de Seguro': 'sppPrimaSeguro',
    'SPP. Comisión': 'sppComision',
    'I.R. 5ta Categoría': 'ir5taCategoria',
    'Descuento Serv. Alimentación': 'descuentoAlimentacion',
    'Descuento Serv. Bazar': 'descuentoBazar',
    'Descuento Autorizado - Diezmo': 'descuentoAutorizadoDiezmo',
    'Otros Conceptos (Descuentos)': 'descuentoOtros',
    'Descuento - Pago de Escolaridad Mensual': 'descuentoEscolaridad',
    'ESSALUD 9%': 'essalud9',
    'SCTR': 'sctr',
    'Adelanto de Sueldo': 'adelanto',
  };

  /**
   * Rellena el formulario con las LÍNEAS de la planilla, no con las columnas.
   *
   * Antes el diezmo, la alimentación y los demás se leían de dos columnas que
   * los traían sumados —y que desde el cambio a conceptos valen siempre 0—,
   * así que al reabrir el cuadro los campos salían vacíos y parecía que lo
   * guardado se había perdido.
   *
   * Se piden con un tope alto porque el endpoint viene paginado: con el
   * tamaño por defecto se quedarían fuera las últimas líneas de una planilla
   * cargada de conceptos.
   */
  private cargarConceptosEnFormulario(planillaId: string): void {
    this.conceptosExtra = [];
    this.detalleOtrosIngresos = '';
    this.detalleOtrosDescuentos = '';
    this.mostrarAgregarConcepto = false;
    this.detalleService.paginaDePlanilla(planillaId, 0, 200).subscribe({
      next: (res) => {
        if (!res.success) return;

        for (const linea of res.data.content) {
          const nombre = linea.payment_concept?.nombre ?? '';
          const campo = this.CAMPO_POR_CONCEPTO[nombre];
          if (campo) {
            (this.formulario[campo] as number | null) = Number(linea.monto_calculado);
            if (nombre === 'Otros Conceptos (Ingresos)') this.detalleOtrosIngresos = linea.descripcion ?? '';
            if (nombre === 'Otros Conceptos (Descuentos)') this.detalleOtrosDescuentos = linea.descripcion ?? '';
          } else if (linea.payment_concept) {
            // Un concepto que esta pantalla no tiene como campo fijo: se
            // muestra debajo de "Otros Conceptos", con su detalle, como sale
            // en la boleta impresa.
            this.conceptosExtra.push({
              id: linea.id,
              nombre: linea.descripcion ? `${nombre}: ${linea.descripcion}` : nombre,
              monto: Number(linea.monto_calculado),
              tipo: linea.payment_concept.tipo,
            });
          }
        }

        // Lo que no tenga línea se queda en blanco, que es lo que significa.
        this._formularioOriginal = JSON.stringify(this.formulario);
        // Después de las líneas guardadas, la Renta de 5ta al día: si fuera
        // antes, la línea guardada (quizá vieja) la pisaría al llegar.
        this.cargarRenta5ta();
      },
      error: () => {
        this.toastService.error('Aviso', 'No se pudieron cargar los conceptos ya guardados de esta planilla.');
      },
    });
  }

  /**
   * Un monto con sus decimales completos (44.89485) mostrado como en la
   * boleta (44.89). El total de la vista previa sigue sumando el completo.
   */
  dosDecimales(valor: number | null | undefined): number | null {
    if (valor === null || valor === undefined) return null;
    // Con notación "e2" y no "* 100": 239.085 * 100 da 23908.4999… y
    // redondearía a 239.08, cuando la boleta (y el Excel) dicen 239.09.
    return Number(Math.round(Number(`${Number(valor)}e2`)) + 'e-2');
  }

  // ── Agregar o quitar otro concepto desde la vista previa ──

  /** El formulario para agregar: el catálogo se pide la primera vez. */
  abrirAgregarConcepto(): void {
    if (!this.planillaActual?.id) {
      this.toastService.warning('Primero guarda', 'Presiona «Guardar Borrador» para crear su planilla del mes; después ya puedes agregarle conceptos.');
      return;
    }
    this.nuevoConcepto = { id: '', monto: null, detalle: '' };
    this.mostrarAgregarConcepto = true;
    if (this.catalogoParaAgregar.length) return;
    this.conceptoService.getAll().subscribe({
      next: (res) => {
        if (!res.success) return;
        this.catalogoParaAgregar = res.data
          .filter((c) => !c.de_ley && !c.calculo_especial && c.nombre !== 'Remuneración Básica')
          .sort((a, b) => a.tipo.localeCompare(b.tipo) || a.nombre.localeCompare(b.nombre, 'es'));
      },
    });
  }

  /** Etiqueta del tipo, para el desplegable. */
  tipoLegible(tipo: string): string {
    return ({ bonificacion: 'Ingreso', descuento: 'Descuento', aportacion: 'Aporte', adelanto: 'Adelanto' } as Record<string, string>)[tipo] ?? tipo;
  }

  guardarNuevoConcepto(): void {
    const planillaId = this.planillaActual?.id;
    const monto = Number(this.nuevoConcepto.monto);
    if (!planillaId || !this.nuevoConcepto.id || !(monto > 0)) {
      this.toastService.warning('Falta un dato', 'Elige el concepto y escribe un monto mayor que cero.');
      return;
    }
    this.guardandoConcepto = true;
    this.detalleService.crear({
      planilla_id: planillaId,
      payment_concept_id: this.nuevoConcepto.id,
      monto_calculado: monto,
      descripcion: this.nuevoConcepto.detalle.trim() || null,
    }).subscribe({
      next: () => {
        this.guardandoConcepto = false;
        this.mostrarAgregarConcepto = false;
        this.toastService.success('Concepto agregado', 'Ya está en su planilla del mes y saldrá en su boleta.');
        this.cargarConceptosEnFormulario(planillaId);
      },
      error: (err) => {
        this.guardandoConcepto = false;
        this.toastService.error('No se agregó', err?.error?.message || 'Revisa el concepto y el monto.');
      },
    });
  }

  quitarConceptoExtra(c: { id: string; nombre: string }): void {
    const planillaId = this.planillaActual?.id;
    if (!planillaId) return;
    this.detalleService.delete(c.id).subscribe({
      next: () => {
        this.toastService.success('Concepto quitado', `«${c.nombre}» ya no está en su planilla.`);
        this.cargarConceptosEnFormulario(planillaId);
      },
      error: (err) => this.toastService.error('No se quitó', err?.error?.message || 'No se pudo quitar el concepto.'),
    });
  }

  // ── Renta de 5ta en la vista previa ──

  calculandoRenta = false;
  private esperaRenta: ReturnType<typeof setTimeout> | null = null;

  /**
   * La Renta de 5ta de este trabajador en este mes, calculada por el backend
   * con el mismo motor que la boleta (sin guardar nada). Antes la casilla
   * decía "Automático en PDF" y el monto recién se veía al imprimir; tampoco
   * entraba en el total de descuentos ni en el neto de la vista previa.
   */
  cargarRenta5ta(): void {
    const empleado = this.empleadoSeleccionado;
    if (!empleado) return;

    this.calculandoRenta = true;
    this.planillaService.rentaQuinta({
      empleado_id: empleado.id,
      mes: Number(this.formulario.mes),
      anio: Number(this.formulario.anio),
      sueldo: this.formulario.remuneracionBasica,
      bonificacion_cargo: this.formulario.bonificacionCargo,
    }).subscribe({
      next: (res) => {
        // Si mientras llegaba se cerró o se cambió de trabajador, ya no vale.
        if (res.success && this.empleadoSeleccionado?.id === empleado.id) {
          this.formulario.ir5taCategoria = Number(res.data.monto);
        }
        this.calculandoRenta = false;
      },
      error: () => {
        this.calculandoRenta = false;
      },
    });
  }

  /** Al escribir el sueldo: se recalcula cuando deja de teclear, no en cada tecla. */
  programarRenta5ta(): void {
    if (this.esperaRenta) clearTimeout(this.esperaRenta);
    this.esperaRenta = setTimeout(() => this.cargarRenta5ta(), 450);
  }

  /**
   * Guarda lo escrito COMO CONCEPTOS, cada uno con su nombre.
   *
   * Antes sumaba los doce campos en dos números —total de bonificaciones y
   * total de descuentos— y los guardaba en dos columnas de la planilla. Se
   * perdía justo lo que importa: cuál era el diezmo, cuál la alimentación,
   * cuál el adelanto. En la boleta salían dos cifras sin explicación.
   *
   * Ahora cada campo viaja con el nombre de su concepto del catálogo y el
   * backend sincroniza las líneas de una vez. Un campo vacío borra su línea,
   * que es lo que se espera al dejarlo en blanco.
   *
   * Los calculados (pensión, EsSalud, Renta de 5ta, Asignación Familiar) NO
   * se mandan: los pone el motor según la ficha, y el backend además los
   * rechaza si alguien lo intenta.
   */
  guardarPlanillaEnServidor(): Observable<{ success: boolean; data: Planilla }> {
    const f = this.formulario;

    const conceptos: { nombre: string; monto: number | null }[] = [
      { nombre: 'Bonificación por Cargo', monto: f.bonificacionCargo },
      { nombre: 'Vacaciones Truncas', monto: f.vacacionesTruncas },
      { nombre: 'Gratificaciones Fiestas Patrias - Ley 29351 y 30334', monto: f.gratificacionesFiestas },
      { nombre: 'Bonif. Extraord. Temporal - Ley 29351 y 30334', monto: f.bonifExtraordTemporal },
      { nombre: 'Otros Conceptos (Ingresos)', monto: f.otrosConceptosSubsidio },
      { nombre: 'Compensación por Tiempo de Servicios', monto: f.compensacionTiempoServicios },
      { nombre: 'Bonificaciones', monto: f.bonificacion },
      { nombre: 'Descuento Serv. Alimentación', monto: f.descuentoAlimentacion },
      { nombre: 'Descuento Serv. Bazar', monto: f.descuentoBazar },
      { nombre: 'Descuento Autorizado - Diezmo', monto: f.descuentoAutorizadoDiezmo },
      { nombre: 'Otros Conceptos (Descuentos)', monto: f.descuentoOtros },
      { nombre: 'Descuento - Pago de Escolaridad Mensual', monto: f.descuentoEscolaridad },
      { nombre: 'SCTR', monto: f.sctr },
      { nombre: 'Adelanto de Sueldo', monto: f.adelanto },
    ];

    /*
     * Primero tiene que existir la planilla; sus conceptos van después.
     *
     * Al crearla NO se manda el sueldo base: sale de la ficha del trabajador
     * y el backend lo prorratea si entró a mitad de mes. Mandarlo desde acá
     * era escribir un número que el servidor ya ignoraba.
     */
    const planilla$ = this.planillaActual?.id
      ? of({ success: true, data: this.planillaActual } as { success: boolean; data: Planilla })
      : this.planillaService.crear({
          empleado_id: this.empleadoSeleccionado!.id,
          mes: Number(f.mes),
          anio: Number(f.anio),
        } as Partial<Planilla>);

    return planilla$.pipe(
      switchMap((res) => {
        const id = res.data?.id;
        if (!id) return of(res);

        return this.planillaService
          .sincronizarConceptos(id, conceptos)
          .pipe(map((sync) => ({ success: sync.success, data: sync.data.planilla })));
      })
    );
  }

  guardarBorrador(): void {
    if (!this.empleadoSeleccionado) return;

    this.guardarPlanillaEnServidor().subscribe({
      next: (res) => {
        if (res.success) {
          this.planillaActual = res.data;
          this._formularioOriginal = JSON.stringify(this.formulario);
          this.empleadosEditados.add(this.empleadoSeleccionado!.id);
          // Al guardar, el backend rehízo la Renta de 5ta con los bonos nuevos.
          this.cargarRenta5ta();
          this.toastService.success('Borrador Guardado', `Se guardó la planilla para ${this.empleadoSeleccionado?.nombre} ${this.empleadoSeleccionado?.apellido} en la base de datos.`);
        }
      },
      error: (err) => {
        console.error('Error guardando planilla', err);
        const msg = err?.error?.message || 'No se pudo guardar los datos de la planilla en el servidor.';
        this.toastService.error('Error al guardar', msg);
      }
    });
  }

  emitirBoleta(): void {
    if (!this.empleadoSeleccionado) return;

    this.generandoPDF = true;
    const { mes, anio } = this.formulario;

    // Primero guardamos en la BD para asegurarnos de que el PDF tenga los datos correctos
    this.guardarPlanillaEnServidor().subscribe({
      next: (res) => {
        if (res.success) {
          this.planillaActual = res.data;
          this.empleadosEditados.add(this.empleadoSeleccionado!.id);
          this.empleadosConBoletaEmitida.add(this.empleadoSeleccionado!.id);

          // Ahora generamos y descargamos el PDF
          this.boletaService.generarBoletaEmpleado(this.empleadoSeleccionado!.id, mes, anio).subscribe({
            next: (blob) => {
              const url = window.URL.createObjectURL(blob);
              const a = document.createElement('a');
              a.href = url;
              a.download = `boleta_${this.empleadoSeleccionado!.dni}_${mes}_${anio}.pdf`;
              a.click();
              window.URL.revokeObjectURL(url);
              this.generandoPDF = false;
              this.cerrarModal();
            },
            error: (err) => {
              console.error('Error generando boleta', err);
              const msg = err?.error?.message || `No existe planilla para el mes ${this.nombreMes(mes)} ${anio}.`;
              this.toastService.error('Error al generar', msg);
              this.generandoPDF = false;
            }
          });
        }
      },
      error: (err) => {
        console.error('Error al registrar planilla antes de emitir', err);
        const msg = err?.error?.message || 'No se pudo registrar la planilla en la base de datos.';
        this.toastService.error('Error de registro', msg);
        this.generandoPDF = false;
      }
    });
  }

  descargandoZip = false;

  /**
   * Baja en un .zip las boletas YA EMITIDAS del mes, con lo que haya en el
   * buscador y los filtros: si arriba se eligió una sede, bajan solo esas.
   * Las que todavía no se emiten no van (el servidor lo avisa si no hay
   * ninguna).
   */
  descargarEmitidasEnZip(): void {
    this.descargandoZip = true;
    const periodo = `${this.nombreMes(this.mesGlobal * 1)} ${this.anioGlobal}`;

    this.boletaService
      .bajarEmitidasEnZip({ mes: this.mesGlobal, anio: this.anioGlobal, search: this.busqueda || undefined, ...this.filtros })
      .subscribe({
        next: (cantidad) => {
          this.descargandoZip = false;
          this.toastService.success(`Bajando ${cantidad} ${cantidad === 1 ? 'boleta' : 'boletas'}`,
            `Las emitidas de ${periodo}, en un .zip. Mira la barra de descargas del navegador.`);
        },
        error: (err) => {
          this.descargandoZip = false;
          this.toastService.error('No se descargó', mensajeErrorApi(err, 'No se pudieron juntar las boletas.'));
        },
      });
  }

  emitirTodasLasBoletas(): void {
    // Contra el total del colegio y no contra la lista: emitir masivamente
    // va por todo el mes, así que un filtro puesto no puede bloquearlo.
    if (this.totalDelColegio === 0) {
      this.toastService.warning('Aviso', 'No hay trabajadores en la lista para emitir boletas.');
      return;
    }
    // El diálogo de confirmación compartido, como en el resto del sistema:
    // este tenía uno propio hecho a mano, con estilos sueltos.
    this.confirmService
      .confirmar({
        titulo: 'Emitir todas las boletas',
        mensaje: `Se emitirán las boletas de ${this.nombreMes(Number(this.mesGlobal))} ${this.anioGlobal} para todo el personal que tenga planilla ese mes.`,
        aceptarTexto: 'Sí, emitir todas',
        variante: 'default',
      })
      .then((aceptado) => { if (aceptado) this.confirmarEmisionMasiva(); });
  }

  confirmarEmisionMasiva(): void {
    this.generandoMasivo = true;
    this.progreso.seguir('Emitiendo boletas', this.boletaService.generarMasivo(this.mesGlobal, this.anioGlobal)).subscribe({
      next: (res) => {
        this.toastService.resultadoMasivo({
          hechas: res.generadas ?? 0,
          omitidas: res.omitidas ?? 0,
          exito: 'Boletas emitidas',
          nada: 'No se emitió ninguna boleta',
          cosas: 'boleta(s)',
          motivo: 'esos empleados no tienen planilla de ese mes, o ya tenían su boleta',
        });
        this.generandoMasivo = false;
        // Recargar para que las filas y el aviso del mes queden al día.
        this.cargarEstadoBoletas();
      },
      error: (err) => {
        console.error('Error generando masivo', err);
        this.toastService.error('Error', 'Hubo un problema al generar las boletas masivamente.');
        this.generandoMasivo = false;
      }
    });
  }

  private getFormularioVacio(): FormularioBoleta {
    const now = new Date();
    return {
      remuneracionBasica: null, bonificacionCargo: null, asignacionFamiliar: null,
      vacacionesTruncas: null, gratificacionesFiestas: null, bonifExtraordTemporal: null,
      otrosConceptosSubsidio: null, compensacionTiempoServicios: null, bonificacion: null,
      onp13: null, sppFondoPensiones: null, sppPrimaSeguro: null, sppComision: null,
      ir5taCategoria: null, descuentoAlimentacion: null, descuentoBazar: null,
      descuentoAutorizadoDiezmo: null, descuentoOtros: null, descuentoEscolaridad: null,
      essalud9: null, sctr: null, adelanto: null,
      ciudad: 'Juliaca',
      fechaEmision: formatoDia(now),
      mes: this.mesGlobal,
      anio: this.anioGlobal
    };
  }
}
