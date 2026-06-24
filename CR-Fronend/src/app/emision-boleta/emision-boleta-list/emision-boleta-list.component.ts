import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { EmpleadoService, Empleado } from '../../core/services/empleado.service';
import { BoletasService } from '../../core/services/boletas.service';
import { PlanillaService, Planilla } from '../../core/services/planilla.service';
import { ToastService } from '../../core/services/toast.service';
import { Observable } from 'rxjs';

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
  imports: [CommonModule, FormsModule],
  templateUrl: './emision-boleta-list.component.html',
  styleUrl: './emision-boleta-list.component.scss'
})
export class EmisionBoletaListComponent implements OnInit {
  empleados: Empleado[] = [];
  searchTerm = '';
  cargandoEmpleados = false;

  // Tracking de emitidos
  empleadosEditados = new Set<string>();

  // Modal state
  showModal = false;
  empleadoSeleccionado: Empleado | null = null;
  formulario!: FormularioBoleta;
  planillaActual: Planilla | null = null;
  private _formularioOriginal: string = '';

  generandoPDF = false;
  generandoMasivo = false;

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
    private boletasService: BoletasService,
    private planillaService: PlanillaService,
    private toastService: ToastService
  ) {}

  ngOnInit(): void {
    this.formulario = this.getFormularioVacio();
    this.cargarEmpleados();
  }

  cargarEmpleados(): void {
    this.cargandoEmpleados = true;
    this.empleadoService.getEmpleados().subscribe({
      next: (res) => {
        if (res.success) this.empleados = res.data;
        this.cargandoEmpleados = false;
      },
      error: (err) => {
        console.error('Error cargando empleados', err);
        this.cargandoEmpleados = false;
      }
    });
  }

  get filteredEmpleados(): Empleado[] {
    if (!this.searchTerm) return this.empleados;
    const lower = this.searchTerm.toLowerCase();
    return this.empleados.filter(e => {
      const nombre = `${e.nombre} ${e.apellido}`.toLowerCase();
      const cargo = e.cargo?.nombre?.toLowerCase() || '';
      return nombre.includes(lower) || cargo.includes(lower) || e.dni.includes(lower);
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
    
    // Cargar la planilla del empleado para el periodo actual
    this.cargarPlanillaDelEmpleado(empleado.id, this.formulario.mes, this.formulario.anio, empleado);
  }

  cargarPlanillaDelEmpleado(empleadoId: string, mes: number, anio: number, empleado: Empleado): void {
    this.planillaService.getPlanillas({ empleado_id: empleadoId, mes, anio }).subscribe({
      next: (res) => {
        if (res.success && res.data.length > 0) {
          this.planillaActual = res.data[0];
          // Rellenar formulario con los montos guardados
          this.formulario.remuneracionBasica = this.planillaActual.sueldo_base;
          this.formulario.bonificacion = this.planillaActual.bonificaciones;
          this.formulario.descuentoOtros = this.planillaActual.descuentos;

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
    if (this.empleadoSeleccionado) {
      const actual = JSON.stringify(this.formulario);
      if (actual !== this._formularioOriginal) {
        this.empleadosEditados.add(this.empleadoSeleccionado.id);
      }
    }
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
      this.formulario.ir5taCategoria,
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
      return this.planillaService.actualizarPlanilla(this.planillaActual.id, body);
    } else {
      return this.planillaService.crearPlanilla(body);
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
          this.boletasService.generarBoletaAdmin(this.empleadoSeleccionado!.id, mes, anio).subscribe({
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

  emitirTodasLasBoletas(): void {
    if (this.filteredEmpleados.length === 0) {
      this.toastService.warning('Aviso', 'No hay empleados en la lista para emitir boletas.');
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
    const f = this.formulario;
    this.boletasService.generarMasivo(f.mes, f.anio).subscribe({
      next: (res) => {
        this.toastService.success('Proceso completado', `${res.message}<br/>Generadas: ${res.generadas}<br/>Omitidas (sin planilla): ${res.omitidas}`);
        this.generandoMasivo = false;
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
      mes: now.getMonth() + 1,
      anio: now.getFullYear()
    };
  }
}
