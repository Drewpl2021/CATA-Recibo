import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { ConfirmDialogComponent } from './confirm-dialog.component';
import { ConfirmService } from '../../../core/services';

/**
 * El bug real (2026-09-28): "Sacar de esta planilla" y "Recalcular sueldo"
 * no pasaban `variante`, y como el tacho rojo salía con TODO lo que no
 * dijera explícitamente 'default', ambas acciones —que no borran nada—
 * salían con el ícono y el botón de eliminar. Se dio vuelta la regla: el
 * tacho ahora es solo para quien pide 'danger' a propósito; olvidarse de
 * la variante sale seguro.
 */
describe('ConfirmDialogComponent — coherencia del ícono con la acción', () => {
  let fixture: ComponentFixture<ConfirmDialogComponent>;
  let confirmService: ConfirmService;

  beforeEach(() => {
    TestBed.configureTestingModule({ imports: [ConfirmDialogComponent] });
    fixture = TestBed.createComponent(ConfirmDialogComponent);
    confirmService = TestBed.inject(ConfirmService);
    fixture.detectChanges();
  });

  const abrir = (variante?: 'danger' | 'default') => {
    confirmService.confirmar({ titulo: 'T', mensaje: 'M', variante });
    fixture.detectChanges();
  };

  const tacho = () => fixture.debugElement.query(By.css('.modal-icon-danger'));
  const visto = () => fixture.debugElement.query(By.css('.modal-icon-brand'));
  const botonAceptar = () => fixture.debugElement.query(By.css('.modal-actions button:last-child')).nativeElement as HTMLElement;

  it('sin variante (el caso que se olvida): el visto azul, botón normal — nunca el tacho', () => {
    abrir(undefined);

    expect(tacho()).toBeNull();
    expect(visto()).not.toBeNull();
    expect(botonAceptar().className).toContain('btn-primary');
    expect(botonAceptar().className).not.toContain('btn-danger-modal');
  });

  it("variante: 'default' explícito: igual que no ponerla", () => {
    abrir('default');

    expect(tacho()).toBeNull();
    expect(visto()).not.toBeNull();
    expect(botonAceptar().className).toContain('btn-primary');
  });

  it("variante: 'danger': el tacho y el botón rojo, solo cuando se pide a propósito", () => {
    abrir('danger');

    expect(tacho()).not.toBeNull();
    expect(visto()).toBeNull();
    expect(botonAceptar().className).toContain('btn-danger-modal');
  });
});
