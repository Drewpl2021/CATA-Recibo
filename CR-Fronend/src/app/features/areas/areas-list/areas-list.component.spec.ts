import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { of } from 'rxjs';
import { AreasListComponent } from './areas-list.component';
import { AreaService, CargoService, ToastService, ConfirmService } from '../../../core/services';

/**
 * El buscador de cargos del modal de Áreas tenía el mismo bug que el de las
 * tablas (2026-09-28): solo se salía borrando letra por letra.
 */
describe('AreasListComponent — buscador de cargos del modal', () => {
  let fixture: ComponentFixture<AreasListComponent>;
  let componente: AreasListComponent;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [AreasListComponent],
      providers: [
        { provide: AreaService, useValue: { getPagina: () => of({ success: false }) } },
        { provide: CargoService, useValue: { getAll: () => of({ success: false }) } },
        { provide: ToastService, useValue: jasmine.createSpyObj('ToastService', ['error', 'success']) },
        { provide: ConfirmService, useValue: jasmine.createSpyObj('ConfirmService', ['confirmarEliminar']) },
      ],
    });
    fixture = TestBed.createComponent(AreasListComponent);
    componente = fixture.componentInstance;
    componente.cargos = [{ id: '1', nombre: 'Docente' } as any, { id: '2', nombre: 'Portería' } as any];
    componente.modalVisible = true;
    fixture.detectChanges();
  });

  const input = () => fixture.debugElement.query(By.css('.buscador-opciones')).nativeElement as HTMLInputElement;
  const botonLimpiar = () => fixture.debugElement.query(By.css('.buscador-opciones-clear'));

  const escribir = (texto: string) => {
    const campo = input();
    campo.value = texto;
    campo.dispatchEvent(new Event('input'));
    fixture.detectChanges();
  };

  it('sin nada escrito, no se ve el botón de limpiar', () => {
    expect(botonLimpiar()).toBeNull();
  });

  it('al escribir aparece el botón, y filtra la rejilla', () => {
    escribir('doc');
    expect(botonLimpiar()).not.toBeNull();
    expect(componente.cargosVisibles.map((c) => c.nombre)).toEqual(['Docente']);
  });

  it('el botón de limpiar borra la búsqueda y devuelve el foco', () => {
    escribir('doc');

    botonLimpiar().nativeElement.click();
    fixture.detectChanges();

    expect(componente.buscadorCargos).toBe('');
    expect(document.activeElement).toBe(input());
    expect(componente.cargosVisibles.length).toBe(2);
  });

  it('Escape con texto limpia sin quitar el foco; vacío, suelta el foco', () => {
    escribir('doc');
    input().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    fixture.detectChanges();
    expect(componente.buscadorCargos).toBe('');
    expect(document.activeElement).toBe(input());

    input().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    fixture.detectChanges();
    expect(document.activeElement).not.toBe(input());
  });
});
