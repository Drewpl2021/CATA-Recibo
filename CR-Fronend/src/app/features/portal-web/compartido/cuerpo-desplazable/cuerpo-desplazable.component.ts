import { AfterViewInit, Component, ElementRef, NgZone, OnDestroy, inject } from '@angular/core';

/** Lo que se considera «un campo con problema» al buscar el primer error. */
const CON_PROBLEMA = '.is-invalid, [aria-invalid="true"], .form-error';

/**
 * El cuerpo de un modal del panel Portal Web, con su propio scroll, para que
 * la cabecera y el pie del modal («Cancelar» y «Guardar») queden siempre a
 * la vista aunque el contenido sea largo.
 *
 * El modal compartido (app-form-modal) se desplaza entero cuando su
 * contenido pasa del 90 % de la pantalla, y con él se va su pie. Este
 * componente no lo toca: mide la cabecera y el pie del modal que lo contiene
 * y limita su propia altura a lo que queda, así el modal nunca necesita
 * desplazarse. Vuelve a medir cuando el modal se abre y cuando algo cambia
 * de tamaño (un título en dos líneas, la ventana más chica).
 *
 *   <app-form-modal …>
 *     <pw-cuerpo-desplazable> …el formulario… </pw-cuerpo-desplazable>
 *   </app-form-modal>
 */
@Component({
  selector: 'pw-cuerpo-desplazable',
  standalone: true,
  template: `<div class="pw-cuerpo"><ng-content></ng-content></div>`,
  styles: [
    `
      :host {
        display: block;
        /* Anula el relleno del cuerpo del modal (24 px): el scroll llega a los bordes y el relleno va adentro. */
        margin: -24px;
      }

      .pw-cuerpo {
        /* Respaldo mientras no se pudo medir: 90 % de la pantalla menos una cabecera y un pie de una línea. */
        max-height: var(--pw-alto-cuerpo, calc(90vh - 150px));
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 24px;
      }
    `,
  ],
})
export class CuerpoDesplazableComponent implements AfterViewInit, OnDestroy {
  private anfitrion = inject(ElementRef<HTMLElement>);
  private zona = inject(NgZone);
  private observador: ResizeObserver | null = null;
  /** La cabecera y el pie que se están observando (cambian si el modal se vuelve a abrir). */
  private observados: Element[] = [];
  private alCambiarVentana = () => this.medir();

  private get host(): HTMLElement {
    return this.anfitrion.nativeElement;
  }

  ngAfterViewInit(): void {
    // Fuera de Angular: medir no cambia nada de la vista, no hace falta detectar cambios.
    this.zona.runOutsideAngular(() => {
      if (typeof ResizeObserver !== 'undefined') {
        // El modal solo dibuja su contenido al abrirse: el cuerpo aparece (y cambia de tamaño) recién ahí.
        this.observador = new ResizeObserver(() => this.medir());
        this.observador.observe(this.host);
      }
      window.addEventListener('resize', this.alCambiarVentana);
      this.medir();
    });
  }

  ngOnDestroy(): void {
    this.observador?.disconnect();
    window.removeEventListener('resize', this.alCambiarVentana);
  }

  /**
   * Lleva la vista al primer campo con problema (y le da el foco), para que
   * al fallar un guardado nadie tenga que buscar qué falta. Se llama después
   * de marcar los errores: espera a que la plantilla los muestre (hasta un
   * segundo; si no aparece ninguno, no hace nada).
   */
  irAlPrimerError(): void {
    const limite = performance.now() + 1000;
    const buscar = () => {
      const problema = this.host.querySelector<HTMLElement>(CON_PROBLEMA);
      if (problema) this.llevarA(problema);
      else if (performance.now() < limite) requestAnimationFrame(buscar);
    };
    requestAnimationFrame(buscar);
  }

  private llevarA(problema: HTMLElement): void {
    const quieto = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    // El foco va al campo. Si el problema es un mensaje de su bloque, al primer campo vacío y
    // visible del bloque (lo que más probablemente falta); si no hay, al primero visible.
    let campo: HTMLElement | undefined = problema;
    if (!problema.matches('input, textarea, select')) {
      const bloque = problema.closest('section, .form-group') ?? problema.parentElement;
      // «Sube el afiche…»: el mensaje va justo después de su selector de imagen. Si ese selector no
      // tiene archivo elegido, el foco va a elegirlo (el campo es «solo-lectores», pero recibe el foco).
      const archivos = Array.from(bloque?.querySelectorAll<HTMLInputElement>('input[type="file"]') ?? [])
        .filter((c) => !c.disabled && c.compareDocumentPosition(problema) & Node.DOCUMENT_POSITION_FOLLOWING);
      const archivo = archivos[archivos.length - 1];
      if (archivo && !archivo.files?.length) {
        campo = archivo;
      } else {
        const campos = Array.from(bloque?.querySelectorAll<HTMLInputElement>('input, textarea, select') ?? [])
          .filter((c) => c.offsetParent !== null && !c.disabled && c.type !== 'file' && c.type !== 'hidden');
        campo = campos.find((c) => !c.value.trim()) ?? campos[0];
      }
    }

    // A la vista queda el campo a corregir (o el mensaje, si no hay campo). El de archivos, por su zona.
    const aLaVista = campo?.matches('input[type="file"]') ? campo.closest('label') ?? campo : campo;
    (aLaVista ?? problema).scrollIntoView({ block: 'center', behavior: quieto ? 'auto' : 'smooth' });
    campo?.focus({ preventScroll: true });
  }

  /** El alto que puede ocupar el cuerpo: el máximo del modal menos su cabecera y su pie. */
  private medir(): void {
    const modal = this.host.closest('.modal-container') as HTMLElement | null;
    if (!modal) return;

    const cabecera = modal.querySelector<HTMLElement>('.modal-header');
    const pie = modal.querySelector<HTMLElement>('.modal-footer');
    this.observar([cabecera, pie].filter((e): e is HTMLElement => !!e));

    const maximo = parseFloat(getComputedStyle(modal).maxHeight);
    if (!Number.isFinite(maximo)) return;

    const alto = Math.max(160, Math.floor(maximo - (cabecera?.offsetHeight ?? 0) - (pie?.offsetHeight ?? 0)));
    this.host.style.setProperty('--pw-alto-cuerpo', `${alto}px`);
  }

  /** Observa la cabecera y el pie del modal actual (un título que pasa a dos líneas cambia el alto disponible). */
  private observar(elementos: Element[]): void {
    if (!this.observador || elementos.every((e) => this.observados.includes(e))) return;
    this.observados.forEach((e) => this.observador!.unobserve(e));
    elementos.forEach((e) => this.observador!.observe(e));
    this.observados = elementos;
  }
}
