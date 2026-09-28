import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { SelectorEmpleadosComponent } from './selector-empleados.component';

describe('SelectorEmpleadosComponent — buscador de personas', () => {
  let fixture: ComponentFixture<SelectorEmpleadosComponent>;
  let componente: SelectorEmpleadosComponent;

  beforeEach(() => {
    TestBed.configureTestingModule({ imports: [SelectorEmpleadosComponent] });
    fixture = TestBed.createComponent(SelectorEmpleadosComponent);
    componente = fixture.componentInstance;
    componente.empleados = [
      { id: '1', nombre: 'Ana', apellido: 'Quispe', dni: '11111111' } as any,
      { id: '2', nombre: 'Karina', apellido: 'Mamani', dni: '22222222' } as any,
    ];
    componente.alcance = 'elegidos';
    componente.ngOnChanges({});
    fixture.detectChanges();
  });

  const input = () => fixture.debugElement.query(By.css('.buscador-personas__campo input')).nativeElement as HTMLInputElement;
  const botonLimpiar = () => fixture.debugElement.query(By.css('.buscador-personas__campo-clear'));

  const escribir = (texto: string) => {
    const campo = input();
    campo.value = texto;
    campo.dispatchEvent(new Event('input'));
    fixture.detectChanges();
  };

  it('sin nada escrito, no se ve el botón de limpiar', () => {
    expect(botonLimpiar()).toBeNull();
  });

  it('al escribir aparece el botón', () => {
    escribir('kar');
    expect(botonLimpiar()).not.toBeNull();
  });

  it('el botón de limpiar solo borra el texto: los filtros de área/cargo/sede no se tocan', () => {
    escribir('kar');
    componente.filtroArea = 'area-1';

    botonLimpiar().nativeElement.click();
    fixture.detectChanges();

    expect(componente.busqueda).toBe('');
    expect(componente.filtroArea).toBe('area-1');
    expect(document.activeElement).toBe(input());
  });

  it('Escape con texto limpia sin quitar el foco; vacío, suelta el foco', () => {
    escribir('kar');
    input().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    fixture.detectChanges();
    expect(componente.busqueda).toBe('');
    expect(document.activeElement).toBe(input());

    input().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    fixture.detectChanges();
    expect(document.activeElement).not.toBe(input());
  });
});
