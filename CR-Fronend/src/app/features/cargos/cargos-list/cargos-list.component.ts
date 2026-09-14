import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';

import { AreaService, CargoService, ToastService, ConfirmService } from '../../../core/services';
import { Area, Cargo, CargoPayload } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import {
  ESTADO_CATALOGO_OPCIONES,
  ESTADO_CATALOGO_POR_DEFECTO,
  columnaEstado,
} from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-cargos-list',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, FormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent,
  ],
  templateUrl: './cargos-list.component.html',
})
export class CargosListComponent implements OnInit {
  private fb = inject(FormBuilder);
  private cargoService = inject(CargoService);
  private areaService = inject(AreaService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  cargos: Cargo[] = [];
  cargando = false;

  /** Filas por página; el backend corta y cuenta, acá solo se pinta. */
  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  busqueda = '';
  /** Cuántos hay en total, según el backend — no el largo de la página. */
  total = 0;
  /** Los conteos que manda el backend junto a la página. */
  activos = 0;
  inactivos = 0;

  /** Lo que pinta la cabecera: total, en uso y dados de baja. */
  get cifras(): CifraCabecera[] {
    return [
      { icono: 'layers', valor: this.total, etiqueta: 'Total', tono: 'brand' },
      { icono: 'check_circle', valor: this.activos, etiqueta: 'Activos', tono: 'success' },
      { icono: 'pause_circle', valor: this.inactivos, etiqueta: 'De baja', tono: 'muted' },
    ];
  }
  guardando = false;
  modalVisible = false;
  cargoEditando: Cargo | null = null;

  /** Las áreas del colegio, para marcar dónde vale cada cargo. */
  areas: Area[] = [];

  /** Lo marcado en el modal. Vacío = el cargo vale en todas las áreas. */
  areasElegidas = new Set<string>();

  /** Filtra la rejilla del modal. Con quince áreas ya cuesta encontrar una. */
  buscadorAreas = '';

  /** Las áreas que pasan el buscador. Lo marcado NO se pierde al filtrar. */
  get areasVisibles(): Area[] {
    const termino = this.buscadorAreas.trim().toLowerCase();

    return termino
      ? this.areas.filter((a) => a.nombre.toLowerCase().includes(termino))
      : this.areas;
  }

  /**
   * Marcadas que el filtro de ahora no deja ver.
   *
   * Se avisa para que nadie crea que al buscar perdió lo que ya había
   * marcado: sigue contando y se guardará igual.
   */
  get marcadasOcultas(): number {
    const visibles = new Set(this.areasVisibles.map((a) => a.id));
    return [...this.areasElegidas].filter((id) => !visibles.has(id)).length;
  }

  columnas: ColumnaTabla<Cargo>[] = [
    { campo: 'nombre', header: 'Nombre', ancho: '24%' },
    { campo: 'descripcion', header: 'Descripción' },
    {
      // Sin áreas no es un vacío: significa que vale en todas.
      campo: 'areas', header: 'Áreas donde vale', ancho: '26%', romperTexto: true,
      formatear: (_v, fila) => this.areasDelCargo(fila),
    },
    columnaEstado<Cargo>(),
  ];

  /** Mismo catálogo activo/inactivo que Sedes. */
  estadoOpciones = ESTADO_CATALOGO_OPCIONES;

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(100)]],
    descripcion: ['', [Validators.maxLength(255)]],
    estado: [ESTADO_CATALOGO_POR_DEFECTO],
  });

  ngOnInit(): void {
    this.cargar();

    // A un desplegable de quince no se le pagina.
    this.areaService.getAll().subscribe({
      next: (res) => { if (res.success) this.areas = res.data; },
      error: () => this.toastService.error('Aviso', 'No se pudieron cargar las áreas.'),
    });
  }

  /** "Biblioteca, TIC" o, si no está acotado, "Todas las áreas". */
  areasDelCargo(cargo: Cargo): string {
    return cargo.areas?.length
      ? cargo.areas.map((a) => a.nombre).join(', ')
      : 'Todas las áreas';
  }

  estaMarcada(areaId: string): boolean {
    return this.areasElegidas.has(areaId);
  }

  alternarArea(areaId: string): void {
    if (this.areasElegidas.has(areaId)) {
      this.areasElegidas.delete(areaId);
    } else {
      this.areasElegidas.add(areaId);
    }
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  /** El usuario pidió otra página. */
  irAPagina(pagina: number): void {
    this.pagina = pagina;
    this.cargar();
  }

  /** Búsqueda contra el backend; llega ya con el retardo aplicado. */
  buscar(termino: string): void {
    this.busqueda = termino;
    this.pagina = 0;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.cargoService
      .getPagina({ page: this.pagina, size: this.TAMANO_PAGINA, search: this.busqueda || undefined })
      .subscribe({
      next: (res) => {
        if (res.success) {
          this.cargos = res.data.content;
          this.total = res.data.totalElements;
          this.activos = res.data.activos ?? 0;
          this.inactivos = res.data.inactivos ?? 0;
        }
        this.cargando = false;
      },
      error: (err) => {
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los cargos.'));
        this.cargando = false;
      },
    });
  }

  nuevo(): void {
    this.buscadorAreas = '';
    this.areasElegidas.clear();
    this.cargoEditando = null;
    this.form.reset({ estado: ESTADO_CATALOGO_POR_DEFECTO });
    this.modalVisible = true;
  }

  editar(cargo: Cargo): void {
    this.cargoEditando = cargo;
    this.buscadorAreas = '';
    this.areasElegidas = new Set((cargo.areas ?? []).map((a) => a.id));
    this.form.patchValue({
      nombre: cargo.nombre,
      descripcion: cargo.descripcion ?? '',
      estado: cargo.estado ?? ESTADO_CATALOGO_POR_DEFECTO,
    });
    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.cargoEditando = null;
    this.form.reset({ estado: ESTADO_CATALOGO_POR_DEFECTO });
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const payload: CargoPayload = {
      ...(this.form.getRawValue() as CargoPayload),
      // Siempre va, aunque esté vacío: así se puede quitar la última área y
      // devolver el cargo a "vale en todas".
      area_ids: [...this.areasElegidas],
    };
    this.guardando = true;

    const peticion = this.cargoEditando
      ? this.cargoService.update(this.cargoEditando.id, payload)
      : this.cargoService.create(payload);

    peticion.subscribe({
      next: (res) => {
        this.guardando = false;
        if (res.success) {
          this.toastService.success(
            this.cargoEditando ? 'Cargo actualizado' : 'Cargo creado',
            `"${res.data.nombre}" se guardó correctamente.`
          );
          this.cerrarModal();
          this.cargar();
        }
      },
      error: (err) => {
        this.guardando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo guardar el cargo.'));
      },
    });
  }

  eliminar(cargo: Cargo): void {
    this.confirmService.confirmarEliminar(`el cargo "${cargo.nombre}"`, () => {
      this.cargoService.delete(cargo.id).subscribe({
        next: () => {
          this.toastService.success('Eliminado', `El cargo "${cargo.nombre}" fue eliminado.`);
          this.cargar();
        },
        error: (err) => {
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo eliminar el cargo.'));
        },
      });
    });
  }
}
