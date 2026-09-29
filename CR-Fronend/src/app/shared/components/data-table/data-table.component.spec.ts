import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { DataTableComponent } from './data-table.component';
import { ColumnaTabla } from './data-table.models';

interface Fila {
  nombre: string;
}

describe('DataTableComponent — buscador', () => {
  let fixture: ComponentFixture<DataTableComponent<Fila>>;
  let componente: DataTableComponent<Fila>;

  const columnas: ColumnaTabla<Fila>[] = [{ campo: 'nombre', header: 'Nombre' }];

  beforeEach(() => {
    TestBed.configureTestingModule({ imports: [DataTableComponent] });
    fixture = TestBed.createComponent<DataTableComponent<Fila>>(DataTableComponent);
    componente = fixture.componentInstance;
    componente.columnas = columnas;
    componente.datos = [{ nombre: 'Ana' }, { nombre: 'Karina' }, { nombre: 'Luis' }];
    componente.camposBusqueda = ['nombre'];
    fixture.detectChanges();
  });

  const input = () => fixture.debugElement.query(By.css('.data-table-search input')).nativeElement as HTMLInputElement;
  const botonLimpiar = () => fixture.debugElement.query(By.css('.data-table-search-clear'));

  const escribir = (texto: string) => {
    const campo = input();
    campo.value = texto;
    campo.dispatchEvent(new Event('input'));
    fixture.detectChanges();
  };

  it('sin nada escrito, no se ve el botón de limpiar', () => {
    expect(botonLimpiar()).toBeNull();
  });

  it('al escribir aparece el botón de limpiar', () => {
    escribir('k');
    expect(botonLimpiar()).not.toBeNull();
  });

  it('el botón de limpiar borra la búsqueda y le devuelve el foco al campo', () => {
    escribir('k');

    botonLimpiar().nativeElement.click();
    fixture.detectChanges();

    expect(componente.busqueda).toBe('');
    expect(botonLimpiar()).toBeNull();
    expect(document.activeElement).toBe(input());
  });

  it('Escape con texto lo borra sin quitar el foco', () => {
    escribir('k');
    input().focus();

    input().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    fixture.detectChanges();

    expect(componente.busqueda).toBe('');
    expect(document.activeElement).toBe(input());
  });

  it('Escape con el campo ya vacío le quita el foco (un segundo Escape sale del todo)', () => {
    input().focus();
    expect(document.activeElement).toBe(input());

    input().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    fixture.detectChanges();

    expect(document.activeElement).not.toBe(input());
  });

  it('limpiar la búsqueda vuelve a mostrar todas las filas', () => {
    escribir('kar');
    expect(componente.filaFiltradas.length).toBe(1);

    componente.limpiarBusqueda(input());
    fixture.detectChanges();

    expect(componente.busqueda).toBe('');
    expect(componente.filaFiltradas.length).toBe(3);
  });
});

describe('DataTableComponent — el esqueleto solo en la carga de verdad', () => {
  let fixture: ComponentFixture<DataTableComponent<Fila>>;
  let componente: DataTableComponent<Fila>;

  const columnas: ColumnaTabla<Fila>[] = [{ campo: 'nombre', header: 'Nombre' }];

  beforeEach(() => {
    TestBed.configureTestingModule({ imports: [DataTableComponent] });
    fixture = TestBed.createComponent<DataTableComponent<Fila>>(DataTableComponent);
    componente = fixture.componentInstance;
    componente.columnas = columnas;
  });

  const tabla = () => fixture.debugElement.query(By.css('table.data-table')).nativeElement as HTMLElement;

  it('primera carga, sin filas todavía: se ve el esqueleto', () => {
    componente.datos = [];
    componente.cargando = true;
    fixture.detectChanges();

    expect(componente.filasEsqueleto.length).toBeGreaterThan(0);
    expect(tabla().classList.contains('data-table--refrescando')).toBeFalse();
  });

  /**
   * El bug real (2026-09-29): recargar la tabla DESPUÉS de aplicar un
   * concepto a un grupo dibujaba el esqueleto encima de las filas que ya
   * estaban — un parpadeo raro, porque las filas viejas seguían ahí abajo
   * hasta que llegaba la respuesta. Ahora, con datos ya puestos, no hay
   * esqueleto: la tabla solo se atenúa un poco.
   */
  it('recargar con filas ya puestas: nada de esqueleto, solo se atenúa', () => {
    componente.datos = [{ nombre: 'Ana' }, { nombre: 'Karina' }];
    componente.paginacionServidor = true;
    fixture.detectChanges();

    componente.cargando = true;
    fixture.detectChanges();

    expect(componente.filasEsqueleto.length).toBe(0);
    expect(componente.filaPagina.length).toBe(2);
    expect(tabla().classList.contains('data-table--refrescando')).toBeTrue();
  });

  it('cuando termina de cargar, se quita el atenuado', () => {
    componente.datos = [{ nombre: 'Ana' }];
    componente.paginacionServidor = true;
    componente.cargando = true;
    fixture.detectChanges();

    componente.cargando = false;
    fixture.detectChanges();

    expect(tabla().classList.contains('data-table--refrescando')).toBeFalse();
  });
});
