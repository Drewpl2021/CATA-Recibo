import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { of } from 'rxjs';
import { CargosListComponent } from './cargos-list.component';
import { AreaService, CargoService, ToastService, ConfirmService } from '../../../core/services';

/** Espejo de areas-list.component.spec.ts: mismo bug, mismo arreglo. */
describe('CargosListComponent — buscador de áreas del modal', () => {
  let fixture: ComponentFixture<CargosListComponent>;
  let componente: CargosListComponent;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [CargosListComponent],
      providers: [
        { provide: CargoService, useValue: { getPagina: () => of({ success: false }) } },
        { provide: AreaService, useValue: { getAll: () => of({ success: false }) } },
        { provide: ToastService, useValue: jasmine.createSpyObj('ToastService', ['error', 'success']) },
        { provide: ConfirmService, useValue: jasmine.createSpyObj('ConfirmService', ['confirmarEliminar']) },
      ],
    });
    fixture = TestBed.createComponent(CargosListComponent);
    componente = fixture.componentInstance;
    componente.areas = [{ id: '1', nombre: 'Primaria' } as any, { id: '2', nombre: 'Secundaria' } as any];
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
    escribir('prim');
    expect(botonLimpiar()).not.toBeNull();
    expect(componente.areasVisibles.map((a) => a.nombre)).toEqual(['Primaria']);
  });

  it('el botón de limpiar borra la búsqueda y devuelve el foco', () => {
    escribir('prim');

    botonLimpiar().nativeElement.click();
    fixture.detectChanges();

    expect(componente.buscadorAreas).toBe('');
    expect(document.activeElement).toBe(input());
    expect(componente.areasVisibles.length).toBe(2);
  });

  it('Escape con texto limpia sin quitar el foco; vacío, suelta el foco', () => {
    escribir('prim');
    input().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    fixture.detectChanges();
    expect(componente.buscadorAreas).toBe('');
    expect(document.activeElement).toBe(input());

    input().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    fixture.detectChanges();
    expect(document.activeElement).not.toBe(input());
  });
});
