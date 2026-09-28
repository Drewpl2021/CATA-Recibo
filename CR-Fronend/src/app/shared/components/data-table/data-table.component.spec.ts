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
