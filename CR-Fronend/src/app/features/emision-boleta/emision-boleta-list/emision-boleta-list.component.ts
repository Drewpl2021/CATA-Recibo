import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { EmpleadoService } from '../../../core/services';
import { Empleado } from '../../../core/models';
import { BoletaService } from '../../../core/services';
import { PlanillaService } from '../../../core/services';
import { Planilla } from '../../../core/models';
import { ToastService } from '../../../core/services';
import { Observable } from 'rxjs';
import { PistaDirective } from '../../../shared/directives/pista.directive';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';

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
  ciudad: string;
  fechaEmision: string;
  mes: number;
  anio: number;
}

@Component({
  selector: 'app-emision-boleta-list',
  standalone: true,
  imports: [CommonModule, FormsModule, PistaDirective, PageHeaderComponent, DataTableComponent],
  templateUrl: './emision-boleta-list.component.html',
  styleUrl: './emision-boleta-list.component.scss'
})
export class EmisionBoletaListComponent implements OnInit {
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
   * Cuantas planillas hay en todo el mes. Es distinto de empleadosEditados:
   * ese conjunto es de la página, y este número decide si el mes está sin
   * empezar (y toca enseñar el aviso de "generar la planilla del mes").
   */
  planillasDelMes = 0;

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
      campo: 'nombre', header: 'Nombres y apellidos', ancho: '28%',
      formatear: (_v, e) => `${e.nombre} ${e.apellido}`,
    },
    { campo: 'dni', header: 'DNI', ancho: '12%' },
    { campo: 'cargo.nombre', header: 'Cargo', ancho: '20%' },
    { campo: 'area.nombre', header: 'Área', ancho: '20%' },
    {
      campo: 'id', header: 'Boleta del mes', tipo: 'badge', ancho: '15%',
      formatear: (_v, e) => (this.empleadosEditados.has(e.id) ? 'Armada' : 'Sin armar'),
      badgeSeveridad: (_v, e) => (this.empleadosEditados.has(e.id) ? 'success' : 'warning'),
    },
  ];

  /**
   * Las cifras de arriba: cuánta gente hay y cuántas boletas van armadas de
   * ese mes. Las dos salen del backend contando TODO, no la página.
   */
  get cifras(): CifraCabecera[] {
    return [
      { icono: 'people', valor: this.totalEmpleados, etiqueta: 'Trabajadores', tono: 'brand' },
      { icono: 'receipt', valor: this.planillasDelMes, etiqueta: 'Boletas armadas', tono: 'success' },
      {
        icono: 'clock',
        valor: Math.max(this.totalEmpleados - this.planillasDelMes, 0),
        etiqueta: 'Sin armar',
        tono: 'warning',
      },
    ];
  }

  accionesFila: AccionPersonalizada<Empleado>[] = [
    { id: 'editar', titulo: 'Revisar y editar los conceptos de su boleta', icono: 'money', etiqueta: 'Editar' },
  ];

  // Modal state
  showModal = false;
  empleadoSeleccionado: Empleado | null = null;
  formulario!: FormularioBoleta;
  planillaActual: Planilla | null = null;
  private _formularioOriginal: string = '';

  generandoPDF = false;
  generandoMasivo = false;

  // Global Period State
  mesGlobal: number = new Date().getMonth() + 1;
  anioGlobal: number = new Date().getFullYear();
  aniosDisponibles: number[] = [];

  // Mass emission modal
  showConfirmMasivo = false;

  mesesDisponibles = [
    { num: 1, nombre: 'Enero' }, { num: 2, nombre: 'Febrero' },
    { num: 3, nombre: 'Marzo' }, { num: 4, nombre: 'Abril' },
    { num: 5, nombre: 'Mayo' }, { num: 6, nombre: 'Junio' },
    { num: 7, nombre: 'Julio' }, { num: 8, nombre: 'Agosto' },
    { num: 9, nombre: 'Setiembre' }, { num: 10, nombre: 'Octubre' },
    { num: 11, nombre: 'Noviembre' }, { num: 12, nombre: 'Diciembre' }
  ];

  constructor(
    private empleadoService: EmpleadoService,
    private boletaService: BoletaService,
    private planillaService: PlanillaService,
    private toastService: ToastService
  ) {}

  ngOnInit(): void {
    const currentYear = new Date().getFullYear();
    for (let i = currentYear - 5; i <= currentYear + 5; i++) {
      this.aniosDisponibles.push(i);
    }
    this.formulario = this.getFormularioVacio();
    this.cargarEmpleados();
  }

  cargarEmpleados(): void {
    this.cargandoEmpleados = true;
    this.empleadoService
      .getPagina({ page: this.pagina, size: this.TAMANO_PAGINA, search: this.busqueda || undefined })
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
          }
          this.cargandoEmpleados = false;
        },
        error: () => {
          // Si falla, las filas salen como "sin armar": se sigue pudiendo editar.
          this.empleadosEditados.clear();
          this.cargandoEmpleados = false;
        },
      });

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

  abrirModal(empleado: Empleado): void {
    this.empleadoSeleccionado = empleado;
    this.formulario = this.getFormularioVacio();
    this.showModal = true;
    document.body.style.overflow = 'hidden';
    
    // Cargar la planilla del empleado para el periodo GLOBAL seleccionado
    this.cargarPlanillaDelEmpleado(empleado.id, this.mesGlobal, this.anioGlobal, empleado);
  }

  cargarPlanillaDelEmpleado(empleadoId: string, mes: number, anio: number, empleado: Empleado): void {
    this.planillaService.listar({ empleado_id: empleadoId, mes, anio }).subscribe({
      next: (res) => {
        if (res.success && res.data.length > 0) {
          this.planillaActual = res.data[0];
          // Rellenar formulario con los montos guardados
          // Laravel manda los decimales como string ("2500.00") — se convierten aqui.
          this.formulario.remuneracionBasica = this.planillaActual.sueldo_base != null ? Number(this.planillaActual.sueldo_base) : null;
          this.formulario.bonificacion = this.planillaActual.bonificaciones != null ? Number(this.planillaActual.bonificaciones) : null;
          this.formulario.descuentoOtros = this.planillaActual.descuentos != null ? Number(this.planillaActual.descuentos) : null;

          // Limpiar otros campos específicos
          this.formulario.bonificacionCargo = null;
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
        } else {
          this.planillaActual = null;
          // Si no existe, cargar el sueldo_base inicial del empleado
          this.formulario.remuneracionBasica = empleado.sueldo_base ?? null;
          this.formulario.bonificacion = null;
          this.formulario.descuentoOtros = null;
          this.formulario.bonificacionCargo = null;
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
    const sueldo = this.formulario.remuneracionBasica ?? 0;
    const mes = this.formulario.mes;

    // Asignación Familiar S/ 113.00 si tiene hijos
    this.formulario.asignacionFamiliar = this.empleadoSeleccionado.tiene_hijos ? 113.00 : 0.00;
    
    // Gratificación de julio y diciembre
    this.formulario.gratificacionesFiestas = [7, 12].includes(Number(mes)) ? sueldo : 0.00;

    // Aportes de Pensión (ONP / AFP)
    if (this.empleadoSeleccionado.sistema_pensiones === 'ONP') {
      this.formulario.onp13 = Number((sueldo * 0.13).toFixed(2));
      this.formulario.sppFondoPensiones = null;
      this.formulario.sppPrimaSeguro = null;
      this.formulario.sppComision = null;
    } else if (this.empleadoSeleccionado.sistema_pensiones === 'AFP') {
      this.formulario.onp13 = null;
      this.formulario.sppFondoPensiones = Number((sueldo * 0.10).toFixed(2));
      this.formulario.sppPrimaSeguro = Number((sueldo * 0.0137).toFixed(2));
      
      const afp = this.empleadoSeleccionado.afp;
      const tasaComision = afp === 'Habitat' ? 0.0147 :
                           afp === 'Integra' ? 0.0155 :
                           afp === 'Prima' ? 0.0160 :
                           afp === 'Profuturo' ? 0.0169 : 0;
      this.formulario.sppComision = Number((sueldo * tasaComision).toFixed(2));
    } else {
      this.formulario.onp13 = null;
      this.formulario.sppFondoPensiones = null;
      this.formulario.sppPrimaSeguro = null;
      this.formulario.sppComision = null;
    }

    // Essalud (9%)
    this.formulario.essalud9 = Number((sueldo * 0.09).toFixed(2));
  }

  cerrarModal(): void {
    // Cerrar no marca nada. La columna dice si el trabajador YA TIENE su
    // planilla del mes guardada, y tocar el formulario sin guardar no la
    // crea: antes bastaba con abrir y cambiar un número para que la fila
    // dijera "editado" aunque en la base de datos no hubiera nada.
    this.showModal = false;
    this.empleadoSeleccionado = null;
    this.planillaActual = null;
    document.body.style.overflow = '';
  }

  get totalIngresos(): number {
    const f = this.formulario;
    return [
      f.remuneracionBasica, f.bonificacionCargo, f.asignacionFamiliar,
      f.vacacionesTruncas, f.gratificacionesFiestas, f.bonifExtraordTemporal,
      f.otrosConceptosSubsidio, f.compensacionTiempoServicios, f.bonificacion
    ].reduce((sum: number, v) => sum + (v ? Number(v) : 0), 0);
  }

  get totalDescuentos(): number {
    const f = this.formulario;
    return [
      f.onp13, f.sppFondoPensiones, f.sppPrimaSeguro, f.sppComision,
      f.ir5taCategoria, f.descuentoAlimentacion, f.descuentoBazar,
      f.descuentoAutorizadoDiezmo, f.descuentoOtros, f.descuentoEscolaridad,
      f.adelanto
    ].reduce((sum: number, v) => sum + (v ? Number(v) : 0), 0);
  }

  get totalAportaciones(): number {
    return [(this.formulario.essalud9), (this.formulario.sctr)]
      .reduce((sum: number, v) => sum + (v ? Number(v) : 0), 0);
  }

  get totalNetoPagar(): number {
    return this.totalIngresos - this.totalDescuentos;
  }

  guardarPlanillaEnServidor(): Observable<{ success: boolean; data: Planilla }> {
    const total_bonificaciones = [
      this.formulario.bonificacionCargo,
      this.formulario.vacacionesTruncas,
      this.formulario.bonifExtraordTemporal,
      this.formulario.otrosConceptosSubsidio,
      this.formulario.compensacionTiempoServicios,
      this.formulario.bonificacion
    ].reduce((sum: number, v) => sum + (v ? Number(v) : 0), 0);

    const total_descuentos = [
      this.formulario.descuentoAlimentacion,
      this.formulario.descuentoBazar,
      this.formulario.descuentoAutorizadoDiezmo,
      this.formulario.descuentoOtros,
      this.formulario.descuentoEscolaridad,
      this.formulario.adelanto
    ].reduce((sum: number, v) => sum + (v ? Number(v) : 0), 0);

    const body: Planilla = {
      empleado_id: this.empleadoSeleccionado!.id,
      mes: Number(this.formulario.mes),
      anio: Number(this.formulario.anio),
      sueldo_base: this.formulario.remuneracionBasica ?? 0,
      bonificaciones: total_bonificaciones,
      descuentos: total_descuentos
    };

    if (this.planillaActual && this.planillaActual.id) {
      return this.planillaService.update(this.planillaActual.id, body);
    } else {
      return this.planillaService.crear(body);
    }
  }

  guardarBorrador(): void {
    if (!this.empleadoSeleccionado) return;

    this.guardarPlanillaEnServidor().subscribe({
      next: (res) => {
        if (res.success) {
          this.planillaActual = res.data;
          this._formularioOriginal = JSON.stringify(this.formulario);
          this.empleadosEditados.add(this.empleadoSeleccionado!.id);
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

  aplicarBonosMasivos(): void {
    // Solo un botón dummy por ahora para la demo
    this.toastService.info(
      'Función en desarrollo',
      'La asignación masiva de Gratificación y CTS se implementará en la próxima versión del sistema.'
    );
  }

  emitirTodasLasBoletas(): void {
    if (this.totalEmpleados === 0) {
      this.toastService.warning('Aviso', 'No hay trabajadores en la lista para emitir boletas.');
      return;
    }
    this.showConfirmMasivo = true;
  }

  cancelarEmisionMasiva(): void {
    this.showConfirmMasivo = false;
  }

  confirmarEmisionMasiva(): void {
    this.showConfirmMasivo = false;
    this.generandoMasivo = true;
    this.boletaService.generarMasivo(this.mesGlobal, this.anioGlobal).subscribe({
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
      ciudad: 'CATA',
      fechaEmision: now.toLocaleDateString('es-PE'),
      mes: this.mesGlobal,
      anio: this.anioGlobal
    };
  }
}
