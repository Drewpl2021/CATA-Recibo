import { Directive, ElementRef, HostListener, Input, OnDestroy, inject } from '@angular/core';

/**
 * El globito que explica qué hace un botón.
 *
 * Reemplaza al `title` del navegador, que en esta app no servía: sale en letra
 * diminuta, tarda casi un segundo en aparecer y en las tablas se pierde contra
 * el fondo. Quien entra a RR.HH. por primera vez veía una fila de íconos sin
 * saber cuál era cuál.
 *
 *   <button [appPista]="'Aprobar esta solicitud'" aria-label="Aprobar esta solicitud">
 *
 * El globo se cuelga del <body> y se posiciona en coordenadas de pantalla a
 * propósito: dentro de la tabla lo cortaría el `overflow` del contenedor, que
 * es justo donde más falta hace.
 *
 * El nombre accesible sigue siendo el aria-label del botón; este globo es solo
 * para los ojos, por eso va con aria-hidden.
 */
@Directive({
  selector: '[appPista]',
  standalone: true,
})
export class PistaDirective implements OnDestroy {
  /** El texto a mostrar. Vacío desactiva la pista. */
  @Input('appPista') texto = '';

  /** Por dónde sale. Si no cabe arriba, se voltea solo. */
  @Input() pistaLado: 'arriba' | 'abajo' = 'arriba';

  private readonly host = inject(ElementRef<HTMLElement>);

  private globo: HTMLElement | null = null;
  private temporizador: ReturnType<typeof setTimeout> | null = null;

  /** Cuánto espera antes de salir: lo justo para no parpadear al pasar de largo. */
  private readonly RETARDO = 120;
  /** Separación entre el botón y el globo. */
  private readonly SEPARACION = 10;
  /** Margen mínimo con el borde de la pantalla. */
  private readonly MARGEN = 8;

  @HostListener('mouseenter')
  @HostListener('focus')
  alEntrar(): void {
    if (!this.texto) return;
    this.cancelarEspera();
    this.temporizador = setTimeout(() => this.mostrar(), this.RETARDO);
  }

  // Al hacer clic también se va: si el botón abre un modal, el globo se
  // quedaría flotando encima de él.
  @HostListener('mouseleave')
  @HostListener('blur')
  @HostListener('click')
  alSalir(): void {
    this.ocultar();
  }

  // Con la página en movimiento el globo apuntaría a otro sitio.
  @HostListener('window:scroll')
  @HostListener('window:resize')
  alMoverse(): void {
    this.ocultar();
  }

  ngOnDestroy(): void {
    this.ocultar();
  }

  private mostrar(): void {
    // Si el globo sigue en pie, no se duplica. Si alguien lo sacó del DOM por
    // fuera, se suelta la referencia y se hace uno nuevo: sin esto la pista se
    // quedaba muda para siempre en ese botón.
    if (this.globo?.isConnected) return;
    this.globo = null;

    const globo = document.createElement('div');
    globo.className = 'pista';
    globo.setAttribute('role', 'tooltip');
    globo.setAttribute('aria-hidden', 'true');
    globo.textContent = this.texto;
    document.body.appendChild(globo);
    this.globo = globo;

    this.colocar(globo);

    // El fundido arranca en el siguiente cuadro, ya con la posición puesta:
    // si no, se ve venir desde la esquina.
    requestAnimationFrame(() => globo.classList.add('pista--visible'));
  }

  private colocar(globo: HTMLElement): void {
    const boton = this.host.nativeElement.getBoundingClientRect();
    const suyo = globo.getBoundingClientRect();

    const cabeArriba = boton.top - suyo.height - this.SEPARACION >= this.MARGEN;
    const arriba = this.pistaLado === 'arriba' ? cabeArriba : false;

    globo.classList.toggle('pista--abajo', !arriba);

    const y = arriba
      ? boton.top - suyo.height - this.SEPARACION
      : boton.bottom + this.SEPARACION;

    // Centrado sobre el botón, pero sin salirse de la pantalla.
    const centro = boton.left + boton.width / 2;
    const libre = window.innerWidth - suyo.width - this.MARGEN;
    const x = Math.max(this.MARGEN, Math.min(centro - suyo.width / 2, libre));

    globo.style.top = `${Math.round(y)}px`;
    globo.style.left = `${Math.round(x)}px`;

    // Dónde va la flechita: sigue al botón aunque el globo se haya corrido
    // contra el borde, para que se vea de quién habla.
    globo.style.setProperty('--pista-flecha', `${Math.round(centro - x)}px`);
  }

  private ocultar(): void {
    this.cancelarEspera();
    this.globo?.remove();
    this.globo = null;
  }

  private cancelarEspera(): void {
    if (this.temporizador) {
      clearTimeout(this.temporizador);
      this.temporizador = null;
    }
  }
}
