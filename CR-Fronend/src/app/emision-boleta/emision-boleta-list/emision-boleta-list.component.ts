import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

export interface EmpleadoParaBoleta {
  id: string;
  // Datos de cabecera (cuadro AZUL - solo lectura, vendrán del backend)
  nombresApellidos: string;
  dni: string;
  categoria: string;
  cargo: string;
  area: string;
  fechaIngreso: string;
  diasTrabajados: number;
  diasNoTrabajados: number;
  entidadFinanciera: string;
  sppSnp: string;
  cuspp: string;
  numeroCuenta: string;
  fechaCese: string;
  // UI
  nivel: string;
  estado: 'Activo' | 'Vacaciones';
}

export interface FormularioBoleta {
  // INGRESOS (cuadro ROJO - editable)
  remuneracionBasica: number | null;
  bonificacionCargo: number | null;
  asignacionFamiliar: number | null;
  vacacionesTruncas: number | null;
  gratificacionesFiestas: number | null;
  bonifExtraordTemporal: number | null;
  otrosConceptosSubsidio: number | null;
  compensacionTiempoServicios: number | null;
  bonificacion: number | null;

  // DESCUENTOS (cuadro ROJO - editable)
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

  // APORTACIONES (cuadro ROJO - editable)
  essalud9: number | null;
  sctr: number | null;

  // Adelanto (pequeño cuadro)
  adelanto: number | null;

  // Datos de emisión
  ciudad: string;
  fechaEmision: string;
  mes: string;
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
  empleados: EmpleadoParaBoleta[] = [];
  searchTerm = '';

  // Modal state
  showModal = false;
  empleadoSeleccionado: EmpleadoParaBoleta | null = null;
  formulario!: FormularioBoleta;

  mesesDisponibles = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre'
  ];

  ngOnInit(): void {
    this.formulario = this.getFormularioVacio();
    // Datos mock con estructura completa (simula lo que vendría del backend)
    this.empleados = [
      {
        id: 'EMP-001',
        nombresApellidos: 'Mamani Ticona Carlos Alberto',
        dni: '40123456',
        categoria: 'NOMBRADO',
        cargo: 'Docente',
        area: 'Secundaria',
        fechaIngreso: '01/03/2010',
        diasTrabajados: 30,
        diasNoTrabajados: 0,
        entidadFinanciera: 'Caja Arequipa',
        sppSnp: 'INTEGRA',
        cuspp: '40123456CAT10',
        numeroCuenta: '0004301302010001A001',
        fechaCese: '31/12/2026',
        nivel: 'Secundaria',
        estado: 'Activo'
      },
      {
        id: 'EMP-002',
        nombresApellidos: 'Quispe Flores Maria Elena',
        dni: '41234567',
        categoria: 'CONTRATADO',
        cargo: 'Directora',
        area: 'Colegio',
        fechaIngreso: '01/08/2015',
        diasTrabajados: 30,
        diasNoTrabajados: 0,
        entidadFinanciera: 'BCP',
        sppSnp: 'PRIMA',
        cuspp: '41234567PRIMA0',
        numeroCuenta: '191-1234567-0-85',
        fechaCese: '31/12/2026',
        nivel: 'Colegio',
        estado: 'Activo'
      },
      {
        id: 'EMP-003',
        nombresApellidos: 'Perez Gutierrez Juan Carlos',
        dni: '45678901',
        categoria: 'CONTRATADO',
        cargo: 'Auxiliar',
        area: 'Primaria',
        fechaIngreso: '01/01/2022',
        diasTrabajados: 28,
        diasNoTrabajados: 2,
        entidadFinanciera: 'Scotiabank',
        sppSnp: 'HABITAT',
        cuspp: '45678901HAB10',
        numeroCuenta: '006-12345678',
        fechaCese: '31/12/2026',
        nivel: 'Primaria',
        estado: 'Vacaciones'
      },
      {
        id: 'EMP-004',
        nombresApellidos: 'Condori Mamani Rosa Isabel',
        dni: '42345678',
        categoria: 'NOMBRADO',
        cargo: 'Docente',
        area: 'Inicial',
        fechaIngreso: '15/03/2008',
        diasTrabajados: 30,
        diasNoTrabajados: 0,
        entidadFinanciera: 'Interbank',
        sppSnp: 'ONP',
        cuspp: '',
        numeroCuenta: '200-3001234567-8',
        fechaCese: '31/12/2026',
        nivel: 'Inicial',
        estado: 'Activo'
      }
    ];
  }

  get filteredEmpleados(): EmpleadoParaBoleta[] {
    if (!this.searchTerm) return this.empleados;
    const lower = this.searchTerm.toLowerCase();
    return this.empleados.filter(e =>
      e.nombresApellidos.toLowerCase().includes(lower) ||
      e.cargo.toLowerCase().includes(lower) ||
      e.dni.includes(lower)
    );
  }

  abrirModal(empleado: EmpleadoParaBoleta): void {
    this.empleadoSeleccionado = empleado;
    this.formulario = this.getFormularioVacio();
    this.showModal = true;
    document.body.style.overflow = 'hidden';
  }

  cerrarModal(): void {
    this.showModal = false;
    this.empleadoSeleccionado = null;
    document.body.style.overflow = '';
  }

  // Totales calculados en tiempo real
  get totalIngresos(): number {
    const f = this.formulario;
    const values: (number | null)[] = [
      f.remuneracionBasica, f.bonificacionCargo, f.asignacionFamiliar,
      f.vacacionesTruncas, f.gratificacionesFiestas, f.bonifExtraordTemporal,
      f.otrosConceptosSubsidio, f.compensacionTiempoServicios, f.bonificacion
    ];
    return values.reduce((sum: number, v) => sum + (v ?? 0), 0);
  }

  get totalDescuentos(): number {
    const f = this.formulario;
    const values: (number | null)[] = [
      f.onp13, f.sppFondoPensiones, f.sppPrimaSeguro, f.sppComision,
      f.ir5taCategoria, f.descuentoAlimentacion, f.descuentoBazar,
      f.descuentoAutorizadoDiezmo, f.descuentoOtros, f.descuentoEscolaridad
    ];
    return values.reduce((sum: number, v) => sum + (v ?? 0), 0);
  }

  get totalAportaciones(): number {
    const f = this.formulario;
    const values: (number | null)[] = [f.essalud9, f.sctr];
    return values.reduce((sum: number, v) => sum + (v ?? 0), 0);
  }

  get totalNetoPagar(): number {
    return this.totalIngresos - this.totalDescuentos;
  }

  guardarBorrador(): void {
    console.log('Guardando borrador:', { empleado: this.empleadoSeleccionado?.id, formulario: this.formulario });
    alert(`✅ Borrador guardado para ${this.empleadoSeleccionado?.nombresApellidos}`);
  }

  emitirBoleta(): void {
    if (this.totalNetoPagar <= 0) {
      alert('⚠️ El Total Neto a Pagar debe ser mayor a 0 para emitir la boleta.');
      return;
    }
    console.log('Emitiendo boleta:', { empleado: this.empleadoSeleccionado?.id, formulario: this.formulario });
    alert(`🎉 Boleta de ${this.formulario.mes} ${this.formulario.anio} emitida correctamente para ${this.empleadoSeleccionado?.nombresApellidos}`);
    this.cerrarModal();
  }

  private getFormularioVacio(): FormularioBoleta {
    const now = new Date();
    const meses = [
      'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
      'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];
    return {
      remuneracionBasica: null, bonificacionCargo: null, asignacionFamiliar: null,
      vacacionesTruncas: null, gratificacionesFiestas: null, bonifExtraordTemporal: null,
      otrosConceptosSubsidio: null, compensacionTiempoServicios: null, bonificacion: null,
      onp13: null, sppFondoPensiones: null, sppPrimaSeguro: null, sppComision: null,
      ir5taCategoria: null, descuentoAlimentacion: null, descuentoBazar: null,
      descuentoAutorizadoDiezmo: null, descuentoOtros: null, descuentoEscolaridad: null,
      essalud9: null, sctr: null, adelanto: null,
      ciudad: 'Juliaca',
      fechaEmision: now.toLocaleDateString('es-PE'),
      mes: meses[now.getMonth()],
      anio: now.getFullYear()
    };
  }
}
