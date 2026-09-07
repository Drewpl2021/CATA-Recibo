import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Area, Cargo, Empleado, Sede } from '../../../core/models';

/** A quiénes alcanza una operación masiva. */
export type AlcanceGrupo = 'todos' | 'elegidos';

/**
 * Elegir a qué empleados alcanza una operación masiva.
 *
 * Lo usan la generación de planillas (Periodos) y la aplicación de un
 * concepto a un grupo (Conceptos de Pago), que necesitaban lo mismo.
 *
 * Ofrece dos modos: todo el personal activo, o una selección concreta. En
 * el segundo, los filtros por área, cargo y sede no ocultan a nadie: sirven
 * para MARCAR de golpe a los que cumplen, y luego se puede ajustar a mano.
 * Así el usuario siempre ve exactamente a quién le va a caer la operación.
 */
@Component({
  selector: 'app-selector-empleados',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './selector-empleados.component.html',
})
export class SelectorEmpleadosComponent implements OnChanges {
  @Input({ required: true }) empleados: Empleado[] = [];
  @Input() areas: Area[] = [];
  @Input() cargos: Cargo[] = [];
  @Input() sedes: Sede[] = [];

  /** Permite (o no) la opción "todo el personal activo". */
  @Input() permiteTodos = true;

  @Input() alcance: AlcanceGrupo = 'todos';
  @Output() alcanceChange = new EventEmitter<AlcanceGrupo>();

  /** Ids marcados. Solo cuenta cuando el alcance es 'elegidos'. */
  @Input() seleccion: string[] = [];
  @Output() seleccionChange = new EventEmitter<string[]>();

  filtroArea = '';
  filtroCargo = '';
  filtroSede = '';

  private marcados = new Set<string>();

  ngOnChanges(cambios: SimpleChanges): void {
    if (cambios['seleccion']) {
      this.marcados = new Set(this.seleccion ?? []);
    }
  }

  get esTodos(): boolean {
    return this.alcance === 'todos';
  }

  cambiarAlcance(valor: AlcanceGrupo): void {
    this.alcance = valor;
    this.alcanceChange.emit(valor);
  }

  estaMarcado(id: string): boolean {
    return this.marcados.has(id);
  }

  alternar(id: string): void {
    if (this.marcados.has(id)) {
      this.marcados.delete(id);
    } else {
      this.marcados.add(id);
    }
    this.emitir();
  }

  get todosMarcados(): boolean {
    return this.empleados.length > 0 && this.marcados.size === this.empleados.length;
  }

  alternarTodos(): void {
    if (this.todosMarcados) {
      this.marcados.clear();
    } else {
      this.empleados.forEach((e) => this.marcados.add(e.id));
    }
    this.emitir();
  }

  /** Los que cumplen los filtros de arriba; sin filtros, nadie. */
  private coincidenConFiltros(): Empleado[] {
    if (!this.filtroArea && !this.filtroCargo && !this.filtroSede) return [];
    return this.empleados.filter(
      (e) =>
        (!this.filtroArea || e.area_id === this.filtroArea) &&
        (!this.filtroCargo || e.cargo_id === this.filtroCargo) &&
        (!this.filtroSede || e.sede_id === this.filtroSede)
    );
  }

  get cuantosCoinciden(): number {
    return this.coincidenConFiltros().length;
  }

  get hayFiltros(): boolean {
    return !!(this.filtroArea || this.filtroCargo || this.filtroSede);
  }

  /** Marca a los que cumplen los filtros, sin desmarcar lo ya elegido. */
  marcarLosDelFiltro(): void {
    this.coincidenConFiltros().forEach((e) => this.marcados.add(e.id));
    this.emitir();
  }

  limpiarSeleccion(): void {
    this.marcados.clear();
    this.filtroArea = '';
    this.filtroCargo = '';
    this.filtroSede = '';
    this.emitir();
  }

  get cuantosMarcados(): number {
    return this.marcados.size;
  }

  private emitir(): void {
    this.seleccionChange.emit([...this.marcados]);
  }
}
