import { Component, OnInit } from '@angular/core';
import { CommonModule, Location } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { AreaService, Area } from '../../core/services/area.service';
import { CargoService, Cargo } from '../../core/services/cargo.service';
import { SedeService, Sede } from '../../core/services/sede.service';
import { PeriodoService, Periodo } from '../../core/services/periodo.service';
import { PaymentConceptService, PaymentConcept } from '../../core/services/payment-concept.service';
import { ToastService } from '../../core/services/toast.service';

export type TabCatalog = 'areas' | 'cargos' | 'sedes' | 'periodos' | 'conceptos';

@Component({
  selector: 'app-catalogos',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './catalogos.component.html',
  styleUrl: './catalogos.component.scss'
})
export class CatalogosComponent implements OnInit {
  activeTab: TabCatalog = 'areas';
  searchTerm = '';
  cargando = false;
  guardando = false;

  // Colecciones
  areas: Area[] = [];
  cargos: Cargo[] = [];
  sedes: Sede[] = [];
  periodos: Periodo[] = [];
  conceptos: PaymentConcept[] = [];

  // Modales
  showModal = false;
  modalModo: 'crear' | 'editar' = 'crear';
  selectedId: string | null = null;

  // Campos del formulario
  nombre = '';
  descripcion = '';
  direccion = '';
  telefono = '';
  sedeEstado = 'activo';
  fechaInicio = '';
  fechaFin = '';
  periodoActivo = true;
  tipoConcepto: 'ingreso' | 'descuento' | 'aportacion' = 'ingreso';
  conceptoActivo = true;

  // Confirmar eliminación
  showConfirmDelete = false;
  itemToDelete: { id: string; nombre: string; tipo: TabCatalog } | null = null;

  constructor(
    private router: Router,
    private route: ActivatedRoute,
    private location: Location,
    private areaService: AreaService,
    private cargoService: CargoService,
    private sedeService: SedeService,
    private periodoService: PeriodoService,
    private conceptoService: PaymentConceptService,
    private toastService: ToastService
  ) {}

  ngOnInit(): void {
    const url = this.router.url;
    if (url.includes('/cargos')) {
      this.activeTab = 'cargos';
    } else if (url.includes('/sedes')) {
      this.activeTab = 'sedes';
    } else if (url.includes('/periodos')) {
      this.activeTab = 'periodos';
    } else if (url.includes('/conceptos') || url.includes('/descuentos')) {
      this.activeTab = 'conceptos';
    } else {
      this.activeTab = 'areas';
    }

    this.cargarDatos(this.activeTab);
  }

  cambiarTab(tab: TabCatalog): void {
    this.activeTab = tab;
    this.searchTerm = '';
    this.location.replaceState(`/inicio/${tab}`);
    this.cargarDatos(tab);
  }

  cargarDatos(tab: TabCatalog): void {
    this.cargando = true;
    switch (tab) {
      case 'areas':
        this.areaService.getAreas().subscribe({
          next: (res) => {
            if (res.success) this.areas = res.data;
            this.cargando = false;
          },
          error: (err) => {
            console.error('Error cargando áreas', err);
            this.cargando = false;
          }
        });
        break;

      case 'cargos':
        this.cargoService.getCargos().subscribe({
          next: (res) => {
            if (res.success) this.cargos = res.data;
            this.cargando = false;
          },
          error: (err) => {
            console.error('Error cargando cargos', err);
            this.cargando = false;
          }
        });
        break;

      case 'sedes':
        this.sedeService.getSedes().subscribe({
          next: (res) => {
            if (res.success) this.sedes = res.data;
            this.cargando = false;
          },
          error: (err) => {
            console.error('Error cargando sedes', err);
            this.cargando = false;
          }
        });
        break;

      case 'periodos':
        this.periodoService.getPeriodos().subscribe({
          next: (res) => {
            if (res.success) this.periodos = res.data;
            this.cargando = false;
          },
          error: (err) => {
            console.error('Error cargando periodos', err);
            this.cargando = false;
          }
        });
        break;

      case 'conceptos':
        this.conceptoService.getConceptos().subscribe({
          next: (res) => {
            if (res.success) this.conceptos = res.data;
            this.cargando = false;
          },
          error: (err) => {
            console.error('Error cargando conceptos', err);
            this.cargando = false;
          }
        });
        break;
    }
  }

  // Getters para filtrado
  get filteredAreas(): Area[] {
    if (!this.searchTerm) return this.areas;
    const q = this.searchTerm.toLowerCase();
    return this.areas.filter(a => a.nombre.toLowerCase().includes(q) || (a.descripcion && a.descripcion.toLowerCase().includes(q)));
  }

  get filteredCargos(): Cargo[] {
    if (!this.searchTerm) return this.cargos;
    const q = this.searchTerm.toLowerCase();
    return this.cargos.filter(c => c.nombre.toLowerCase().includes(q) || (c.descripcion && c.descripcion.toLowerCase().includes(q)));
  }

  get filteredSedes(): Sede[] {
    if (!this.searchTerm) return this.sedes;
    const q = this.searchTerm.toLowerCase();
    return this.sedes.filter(s => s.nombre.toLowerCase().includes(q) || (s.direccion && s.direccion.toLowerCase().includes(q)));
  }

  get filteredPeriodos(): Periodo[] {
    if (!this.searchTerm) return this.periodos;
    const q = this.searchTerm.toLowerCase();
    return this.periodos.filter(p => p.nombre.toLowerCase().includes(q));
  }

  get filteredConceptos(): PaymentConcept[] {
    if (!this.searchTerm) return this.conceptos;
    const q = this.searchTerm.toLowerCase();
    return this.conceptos.filter(c => c.nombre.toLowerCase().includes(q) || c.tipo.toLowerCase().includes(q));
  }

  // Modales
  abrirModalCrear(): void {
    this.modalModo = 'crear';
    this.selectedId = null;
    this.resetForm();
    this.showModal = true;
  }

  abrirModalEditar(item: any): void {
    this.modalModo = 'editar';
    this.selectedId = item.id;
    this.resetForm();

    this.nombre = item.nombre || '';
    this.descripcion = item.descripcion || '';

    if (this.activeTab === 'sedes') {
      this.direccion = item.direccion || '';
      this.telefono = item.telefono || '';
      this.sedeEstado = item.estado || 'activo';
    } else if (this.activeTab === 'periodos') {
      this.fechaInicio = item.fecha_inicio ? item.fecha_inicio.split('T')[0] : '';
      this.fechaFin = item.fecha_fin ? item.fecha_fin.split('T')[0] : '';
      this.periodoActivo = !!item.activo;
    } else if (this.activeTab === 'conceptos') {
      this.tipoConcepto = item.tipo || 'ingreso';
      this.conceptoActivo = !!item.activo;
    }

    this.showModal = true;
  }

  cerrarModal(): void {
    this.showModal = false;
    this.resetForm();
  }

  resetForm(): void {
    this.nombre = '';
    this.descripcion = '';
    this.direccion = '';
    this.telefono = '';
    this.sedeEstado = 'activo';
    this.fechaInicio = '';
    this.fechaFin = '';
    this.periodoActivo = true;
    this.tipoConcepto = 'ingreso';
    this.conceptoActivo = true;
  }

  guardar(): void {
    if (!this.nombre.trim()) {
      this.toastService.warning('Campo requerido', 'El nombre es obligatorio.');
      return;
    }

    this.guardando = true;

    if (this.activeTab === 'areas') {
      const data = { nombre: this.nombre, descripcion: this.descripcion || null };
      const obs = this.modalModo === 'crear' 
        ? this.areaService.crearArea(data)
        : this.areaService.actualizarArea(this.selectedId!, data);

      obs.subscribe({
        next: () => {
          this.toastService.success('Éxito', `Área ${this.modalModo === 'crear' ? 'creada' : 'actualizada'} correctamente.`);
          this.guardando = false;
          this.cerrarModal();
          this.cargarDatos('areas');
        },
        error: (err) => {
          this.guardando = false;
          this.toastService.error('Error', err?.error?.message || 'Error al procesar área.');
        }
      });
    } else if (this.activeTab === 'cargos') {
      const data = { nombre: this.nombre, descripcion: this.descripcion || null };
      const obs = this.modalModo === 'crear' 
        ? this.cargoService.crearCargo(data)
        : this.cargoService.actualizarCargo(this.selectedId!, data);

      obs.subscribe({
        next: () => {
          this.toastService.success('Éxito', `Cargo ${this.modalModo === 'crear' ? 'creado' : 'actualizado'} correctamente.`);
          this.guardando = false;
          this.cerrarModal();
          this.cargarDatos('cargos');
        },
        error: (err) => {
          this.guardando = false;
          this.toastService.error('Error', err?.error?.message || 'Error al procesar cargo.');
        }
      });
    } else if (this.activeTab === 'sedes') {
      const data = { 
        nombre: this.nombre, 
        direccion: this.direccion || null, 
        telefono: this.telefono || null,
        estado: this.sedeEstado 
      };
      const obs = this.modalModo === 'crear' 
        ? this.sedeService.crearSede(data)
        : this.sedeService.actualizarSede(this.selectedId!, data);

      obs.subscribe({
        next: () => {
          this.toastService.success('Éxito', `Sede ${this.modalModo === 'crear' ? 'creada' : 'actualizada'} correctamente.`);
          this.guardando = false;
          this.cerrarModal();
          this.cargarDatos('sedes');
        },
        error: (err) => {
          this.guardando = false;
          this.toastService.error('Error', err?.error?.message || 'Error al procesar sede.');
        }
      });
    } else if (this.activeTab === 'periodos') {
      if (!this.fechaInicio || !this.fechaFin) {
        this.toastService.warning('Fechas requeridas', 'Las fechas de inicio y fin son obligatorias.');
        this.guardando = false;
        return;
      }
      const data = { 
        nombre: this.nombre, 
        fecha_inicio: this.fechaInicio, 
        fecha_fin: this.fechaFin, 
        activo: this.periodoActivo 
      };
      const obs = this.modalModo === 'crear' 
        ? this.periodoService.crearPeriodo(data)
        : this.periodoService.actualizarPeriodo(this.selectedId!, data);

      obs.subscribe({
        next: () => {
          this.toastService.success('Éxito', `Periodo ${this.modalModo === 'crear' ? 'creado' : 'actualizado'} correctamente.`);
          this.guardando = false;
          this.cerrarModal();
          this.cargarDatos('periodos');
        },
        error: (err) => {
          this.guardando = false;
          this.toastService.error('Error', err?.error?.message || 'Error al procesar periodo.');
        }
      });
    } else if (this.activeTab === 'conceptos') {
      const data = { 
        nombre: this.nombre, 
        tipo: this.tipoConcepto, 
        descripcion: this.descripcion || undefined, 
        activo: this.conceptoActivo 
      };
      const obs = this.modalModo === 'crear' 
        ? this.conceptoService.crearConcepto(data)
        : this.conceptoService.actualizarConcepto(this.selectedId!, data);

      obs.subscribe({
        next: () => {
          this.toastService.success('Éxito', `Concepto ${this.modalModo === 'crear' ? 'creado' : 'actualizado'} correctamente.`);
          this.guardando = false;
          this.cerrarModal();
          this.cargarDatos('conceptos');
        },
        error: (err) => {
          this.guardando = false;
          this.toastService.error('Error', err?.error?.message || 'Error al procesar concepto.');
        }
      });
    }
  }

  // Eliminar
  confirmarEliminar(item: any): void {
    this.itemToDelete = {
      id: item.id,
      nombre: item.nombre,
      tipo: this.activeTab
    };
    this.showConfirmDelete = true;
  }

  cancelarEliminar(): void {
    this.showConfirmDelete = false;
    this.itemToDelete = null;
  }

  ejecutarEliminar(): void {
    if (!this.itemToDelete) return;
    const { id, tipo } = this.itemToDelete;

    let obs;
    switch (tipo) {
      case 'areas': obs = this.areaService.eliminarArea(id); break;
      case 'cargos': obs = this.cargoService.eliminarCargo(id); break;
      case 'sedes': obs = this.sedeService.eliminarSede(id); break;
      case 'periodos': obs = this.periodoService.eliminarPeriodo(id); break;
      case 'conceptos': obs = this.conceptoService.eliminarConcepto(id); break;
    }

    obs.subscribe({
      next: () => {
        this.toastService.success('Eliminado', 'Registro eliminado correctamente.');
        this.cancelarEliminar();
        this.cargarDatos(tipo);
      },
      error: (err) => {
        console.error('Error al eliminar', err);
        this.toastService.error('Error', err?.error?.message || 'No se pudo eliminar el registro (puede tener dependencias vinculadas).');
        this.cancelarEliminar();
      }
    });
  }
}
