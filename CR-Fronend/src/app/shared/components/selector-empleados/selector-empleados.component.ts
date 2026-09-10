import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Area, Cargo, Empleado, Sede } from '../../../core/models';

/** A quiénes alcanza una operación masiva. */
export type AlcanceGrupo = 'todos' | 'elegidos';

/**
 * Buscar y elegir trabajadores.
 *
 * Lo usan la creación de una planilla, el "agregar trabajadores" y la
 * aplicación de un concepto a un grupo, que necesitaban lo mismo.
 *
 * Antes volcaba la plantilla entera como una lista de casillas, y los
 * filtros de área/cargo/sede no filtraban nada: solo habilitaban un botón
 * para marcar de golpe a los que coincidían. Con 150 trabajadores esa lista
 * es imposible de recorrer, y unos filtros que parecen filtros pero no
 * filtran son peores que no tenerlos.
 *
 * Ahora hay un buscador por nombre o DNI y los tres filtros acotan la lista
 * de verdad: lo que se ve es lo que hay, y se marca de ahí.
 *
 * Lo marcado NO se pierde al cambiar el filtro: se puede buscar "Mamani",
 * marcarlo, buscar "Quispe" y marcarlo también. Por eso el contador dice
 * cuántos van en total, y se avisa si alguno de ellos quedó fuera de vista.
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

  busqueda = '';
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

  // ────────── La lista que se ve ──────────

  /**
   * Los que pasan el buscador y los tres filtros.
   *
   * El buscador mira nombre, apellido y DNI, y no le importa el orden:
   * "mamani carlos" encuentra a "Carlos Enrique Mamani Flores".
   */
  get visibles(): Empleado[] {
    const palabras = this.busqueda.trim().toLowerCase().split(/\s+/).filter(Boolean);

    return this.empleados.filter((e) => {
      if (this.filtroArea && e.area_id !== this.filtroArea) return false;
      if (this.filtroCargo && e.cargo_id !== this.filtroCargo) return false;
      if (this.filtroSede && e.sede_id !== this.filtroSede) return false;
      if (!palabras.length) return true;

      const texto = `${e.nombre ?? ''} ${e.apellido ?? ''} ${e.dni ?? ''}`.toLowerCase();
      return palabras.every((p) => texto.includes(p));
    });
  }

  get hayFiltros(): boolean {
    return !!(this.busqueda.trim() || this.filtroArea || this.filtroCargo || this.filtroSede);
  }

  limpiarFiltros(): void {
    this.busqueda = '';
    this.filtroArea = '';
    this.filtroCargo = '';
    this.filtroSede = '';
  }

  // ────────── Lo marcado ──────────

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

  /** ¿Están marcados TODOS los que se ven ahora? */
  get todosVisiblesMarcados(): boolean {
    const lista = this.visibles;
    return lista.length > 0 && lista.every((e) => this.marcados.has(e.id));
  }

  /**
   * Marca o desmarca de golpe a los que se están viendo. Es lo que hace útil
   * el filtro: "TIC" → marcar los tres → buscar otra cosa → marcar más.
   */
  alternarVisibles(): void {
    const lista = this.visibles;
    if (this.todosVisiblesMarcados) {
      lista.forEach((e) => this.marcados.delete(e.id));
    } else {
      lista.forEach((e) => this.marcados.add(e.id));
    }
    this.emitir();
  }

  limpiarSeleccion(): void {
    this.marcados.clear();
    this.emitir();
  }

  get cuantosMarcados(): number {
    return this.marcados.size;
  }

  /**
   * Los marcados que el filtro de ahora no deja ver.
   *
   * Se avisa para que nadie crea que perdió una selección: la operación va a
   * alcanzarlos igual aunque no estén en pantalla.
   */
  get marcadosOcultos(): number {
    const visibles = new Set(this.visibles.map((e) => e.id));
    return [...this.marcados].filter((id) => !visibles.has(id)).length;
  }

  /** Para el trackBy de la lista: sin esto Angular la repinta entera al teclear. */
  porId(_indice: number, empleado: Empleado): string {
    return empleado.id;
  }

  private emitir(): void {
    this.seleccionChange.emit([...this.marcados]);
  }
}
