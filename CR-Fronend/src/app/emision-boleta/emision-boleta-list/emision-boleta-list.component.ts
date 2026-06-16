import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { EmpleadoService, Empleado } from '../../core/services/empleado.service';
import { BoletasService } from '../../core/services/boletas.service';
import { ToastService } from '../../core/services/toast.service';

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
    this._formularioOriginal = JSON.stringify(this.formulario);
    this.showModal = true;
    document.body.style.overflow = 'hidden';
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
    document.body.style.overflow = '';
  }

  get totalIngresos(): number {
    const f = this.formulario;
    return [f.remuneracionBasica, f.bonificacionCargo, f.asignacionFamiliar,
      f.vacacionesTruncas, f.gratificacionesFiestas, f.bonifExtraordTemporal,
      f.otrosConceptosSubsidio, f.compensacionTiempoServicios, f.bonificacion
    ].reduce((sum: number, v) => sum + (v ?? 0), 0);
  }

  get totalDescuentos(): number {
    const f = this.formulario;
    return [f.onp13, f.sppFondoPensiones, f.sppPrimaSeguro, f.sppComision,
      f.ir5taCategoria, f.descuentoAlimentacion, f.descuentoBazar,
      f.descuentoAutorizadoDiezmo, f.descuentoOtros, f.descuentoEscolaridad
    ].reduce((sum: number, v) => sum + (v ?? 0), 0);
  }

  get totalAportaciones(): number {
    return [(this.formulario.essalud9), (this.formulario.sctr)]
      .reduce((sum: number, v) => sum + (v ?? 0), 0);
  }

  get totalNetoPagar(): number {
    return this.totalIngresos - this.totalDescuentos;
  }

  guardarBorrador(): void {
    if (this.empleadoSeleccionado) {
      this.empleadosEditados.add(this.empleadoSeleccionado.id);
    }
    this.toastService.success('Borrador Guardado', `Se guardó el borrador para ${this.empleadoSeleccionado?.nombre} ${this.empleadoSeleccionado?.apellido}`);
  }

  emitirBoleta(): void {
    if (!this.empleadoSeleccionado) return;

    this.generandoPDF = true;
    const { mes, anio } = this.formulario;

    this.boletasService.generarBoletaAdmin(this.empleadoSeleccionado.id, mes, anio).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `boleta_${this.empleadoSeleccionado!.dni}_${mes}_${anio}.pdf`;
        a.click();
        window.URL.revokeObjectURL(url);
        this.empleadosEditados.add(this.empleadoSeleccionado!.id);
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
