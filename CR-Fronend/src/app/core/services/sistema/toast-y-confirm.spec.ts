import { TestBed } from '@angular/core/testing';
import { ConfirmRequest, ConfirmService } from './confirm.service';
import { ToastMessage, ToastService } from './toast.service';

describe('ToastService', () => {
  let servicio: ToastService;
  let recibidos: (ToastMessage | null)[];

  beforeEach(() => {
    jasmine.clock().install();
    TestBed.configureTestingModule({});
    servicio = TestBed.inject(ToastService);
    recibidos = [];
    servicio.toastState$.subscribe((t) => recibidos.push(t));
  });

  afterEach(() => jasmine.clock().uninstall());

  const ultimo = () => recibidos.filter((t) => t !== null).pop() as ToastMessage;

  it('success, info y warning llevan su tipo', () => {
    servicio.success('Listo');
    servicio.info('Dato');
    servicio.warning('Ojo');

    expect(recibidos.map((t) => t?.type)).toEqual(['success', 'info', 'warning']);
  });

  it('el aviso se retira solo pasado su tiempo', () => {
    servicio.success('Listo');
    expect(recibidos.length).toBe(1);

    jasmine.clock().tick(3600);

    expect(recibidos[recibidos.length - 1]).toBeNull();
  });

  it('los errores duran más que los éxitos', () => {
    servicio.success('a');
    const exito = ultimo().duration;
    servicio.error('b');

    expect(ultimo().duration!).toBeGreaterThan(exito!);
  });

  it('silenciar descarta los errores pero no los éxitos', () => {
    servicio.silenciarErrores(4000);

    servicio.error('no debe verse');
    servicio.success('sí se ve');

    expect(recibidos.map((t) => t?.title)).toEqual(['sí se ve']);
  });

  it('forzar muestra el error aun con silencio activo', () => {
    servicio.silenciarErrores(4000);

    servicio.error('Tu sesión expiró', '', true);

    expect(ultimo().title).toBe('Tu sesión expiró');
  });

  describe('resultadoMasivo', () => {
    const base = { exito: 'Boletas generadas', nada: 'No se generó ninguna boleta', cosas: 'boleta(s)', motivo: 'no tienen planilla' };

    it('todo bien es un éxito', () => {
      servicio.resultadoMasivo({ ...base, hechas: 5, omitidas: 0 });
      expect(ultimo().type).toBe('success');
    });

    it('una parte es una advertencia y dice cuántas', () => {
      servicio.resultadoMasivo({ ...base, hechas: 3, omitidas: 2 });

      expect(ultimo().type).toBe('warning');
      expect(ultimo().message).toContain('3 de 5');
    });

    it('cero hechas es un ERROR, no un éxito verde', () => {
      servicio.resultadoMasivo({ ...base, hechas: 0, omitidas: 4 });

      expect(ultimo().type).toBe('error');
      expect(ultimo().title).toBe(base.nada);
    });

    it('sin nada que procesar avisa que no había nada', () => {
      servicio.resultadoMasivo({ ...base, hechas: 0, omitidas: 0 });
      expect(ultimo().type).toBe('warning');
    });
  });
});

describe('ConfirmService', () => {
  let servicio: ConfirmService;
  let pedido: ConfirmRequest | null;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    servicio = TestBed.inject(ConfirmService);
    pedido = null;
    servicio.request$.subscribe((r) => (pedido = r));
  });

  it('resuelve true al aceptar', async () => {
    const promesa = servicio.confirmar({ titulo: 'T', mensaje: 'M' });

    pedido!.resolve(true);

    expect(await promesa).toBeTrue();
  });

  it('resuelve false al cancelar y cierra el diálogo', async () => {
    const promesa = servicio.confirmar({ titulo: 'T', mensaje: 'M' });

    pedido!.resolve(false);

    expect(await promesa).toBeFalse();
    expect(pedido).toBeNull();
  });

  it('confirmarEliminar solo ejecuta la acción si se acepta', async () => {
    const accion = jasmine.createSpy('accion');

    servicio.confirmarEliminar('el empleado', accion);
    expect(pedido!.options.variante).toBe('danger');
    pedido!.resolve(false);
    await Promise.resolve();
    expect(accion).not.toHaveBeenCalled();

    servicio.confirmarEliminar('el empleado', accion);
    pedido!.resolve(true);
    await Promise.resolve();
    expect(accion).toHaveBeenCalledTimes(1);
  });
});
