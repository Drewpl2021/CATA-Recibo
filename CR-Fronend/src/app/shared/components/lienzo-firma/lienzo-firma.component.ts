import { AfterViewInit, Component, ElementRef, EventEmitter, HostListener, Input, Output, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';

/** Un trazo: los puntos que se dibujaron sin levantar el dedo. */
type Trazo = { x: number; y: number }[];

/** Márgen blanco alrededor del trazo al recortar, en píxeles. */
const MARGEN = 12;

/** Ancho máximo de la imagen guardada: de sobra para la boleta, y pesa poco. */
const ANCHO_MAXIMO = 600;

/** Menos que esto no es una firma, es un toque sin querer. */
const TRAZO_MINIMO = 40;

/**
 * Un recuadro para firmar con el dedo o con el mouse.
 *
 * Guarda los trazos como puntos, no como píxeles: así se puede volver a
 * dibujar cuando cambia el tamaño de la pantalla (al girar el celular el
 * lienzo se borraba entero), y al exportar se recorta justo al trazo, sin el
 * espacio vacío de alrededor.
 *
 * La imagen sale en PNG con fondo transparente, para que en la boleta se vea
 * como tinta y no como un papel pegado encima.
 *
 * El componente no guarda nada por su cuenta: la pantalla que lo usa le pide
 * la imagen con exportarPng() cuando la persona confirma.
 */
@Component({
  selector: 'app-lienzo-firma',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './lienzo-firma.component.html',
})
export class LienzoFirmaComponent implements AfterViewInit {
  @ViewChild('lienzo') private lienzoRef!: ElementRef<HTMLCanvasElement>;

  /** Lo que va debajo de la línea, como en la boleta: el nombre de quien firma. */
  @Input() pie = '';

  /** Avisa a la pantalla si ya hay algo dibujado, para habilitar el botón. */
  @Output() hayTrazoChange = new EventEmitter<boolean>();

  hayTrazo = false;

  private trazos: Trazo[] = [];
  private trazoActual: Trazo | null = null;

  ngAfterViewInit(): void {
    this.ajustarTamano();
  }

  // ── Dibujar ────────────────────────────────────────────────────

  empezar(evento: PointerEvent): void {
    evento.preventDefault();
    this.lienzoRef.nativeElement.setPointerCapture(evento.pointerId);
    this.trazoActual = [this.punto(evento)];
    this.trazos.push(this.trazoActual);
  }

  mover(evento: PointerEvent): void {
    if (!this.trazoActual) return;
    evento.preventDefault();
    this.trazoActual.push(this.punto(evento));
    this.pintar();
  }

  terminar(): void {
    if (!this.trazoActual) return;
    this.trazoActual = null;
    this.avisarSiHayTrazo();
  }

  borrar(): void {
    this.trazos = [];
    this.trazoActual = null;
    this.pintar();
    this.avisarSiHayTrazo();
  }

  /** Al girar el celular el lienzo cambia de ancho: se vuelve a dibujar. */
  @HostListener('window:resize')
  ajustarTamano(): void {
    const lienzo = this.lienzoRef?.nativeElement;
    if (!lienzo) return;

    const escala = window.devicePixelRatio || 1;
    lienzo.width = lienzo.clientWidth * escala;
    lienzo.height = lienzo.clientHeight * escala;
    this.pintar();
  }

  // ── Guardar ────────────────────────────────────────────────────

  /**
   * La firma recortada, en PNG con fondo transparente.
   * null si no hay nada que valga la pena guardar.
   */
  async exportarPng(): Promise<Blob | null> {
    const limites = this.limitesDelTrazo();
    if (!limites) return null;

    const ancho = limites.derecha - limites.izquierda + MARGEN * 2;
    const alto = limites.abajo - limites.arriba + MARGEN * 2;
    const escala = Math.min(1, ANCHO_MAXIMO / ancho);

    const recorte = document.createElement('canvas');
    recorte.width = Math.round(ancho * escala);
    recorte.height = Math.round(alto * escala);

    const pincel = recorte.getContext('2d');
    if (!pincel) return null;

    pincel.scale(escala, escala);
    pincel.translate(MARGEN - limites.izquierda, MARGEN - limites.arriba);
    this.dibujarTrazos(pincel);

    return new Promise((resolver) => recorte.toBlob((blob) => resolver(blob), 'image/png'));
  }

  // ── Por dentro ─────────────────────────────────────────────────

  private punto(evento: PointerEvent): { x: number; y: number } {
    const caja = this.lienzoRef.nativeElement.getBoundingClientRect();
    return { x: evento.clientX - caja.left, y: evento.clientY - caja.top };
  }

  private pintar(): void {
    const lienzo = this.lienzoRef.nativeElement;
    const pincel = lienzo.getContext('2d');
    if (!pincel) return;

    const escala = window.devicePixelRatio || 1;
    pincel.setTransform(escala, 0, 0, escala, 0, 0);
    pincel.clearRect(0, 0, lienzo.width, lienzo.height);
    this.dibujarTrazos(pincel);
  }

  /** Curvas entre punto y punto: a rectas, la firma sale temblorosa. */
  private dibujarTrazos(pincel: CanvasRenderingContext2D): void {
    pincel.lineWidth = 2.4;
    pincel.lineCap = 'round';
    pincel.lineJoin = 'round';
    pincel.strokeStyle = this.tinta();

    for (const trazo of this.trazos) {
      if (trazo.length === 1) {
        // Un punto solo (una tilde, un punto de la i) también se pinta.
        pincel.beginPath();
        pincel.arc(trazo[0].x, trazo[0].y, pincel.lineWidth / 2, 0, Math.PI * 2);
        pincel.fillStyle = pincel.strokeStyle;
        pincel.fill();
        continue;
      }

      pincel.beginPath();
      pincel.moveTo(trazo[0].x, trazo[0].y);
      for (let i = 1; i < trazo.length - 1; i++) {
        const medioX = (trazo[i].x + trazo[i + 1].x) / 2;
        const medioY = (trazo[i].y + trazo[i + 1].y) / 2;
        pincel.quadraticCurveTo(trazo[i].x, trazo[i].y, medioX, medioY);
      }
      pincel.lineTo(trazo[trazo.length - 1].x, trazo[trazo.length - 1].y);
      pincel.stroke();
    }
  }

  /** El color de la tinta sale del token --tinta, como todo color del sistema. */
  private tinta(): string {
    return getComputedStyle(document.documentElement).getPropertyValue('--tinta').trim() || '#16213F';
  }

  /** Hasta dónde llega la tinta; null si no hay casi nada dibujado. */
  private limitesDelTrazo(): { izquierda: number; derecha: number; arriba: number; abajo: number } | null {
    const puntos = this.trazos.flat();
    if (!puntos.length) return null;

    const xs = puntos.map((p) => p.x);
    const ys = puntos.map((p) => p.y);
    const limites = {
      izquierda: Math.min(...xs),
      derecha: Math.max(...xs),
      arriba: Math.min(...ys),
      abajo: Math.max(...ys),
    };

    const tamano = (limites.derecha - limites.izquierda) + (limites.abajo - limites.arriba);
    return tamano >= TRAZO_MINIMO ? limites : null;
  }

  private avisarSiHayTrazo(): void {
    const hay = this.trazos.some((t) => t.length > 0);
    if (hay !== this.hayTrazo) {
      this.hayTrazo = hay;
      this.hayTrazoChange.emit(hay);
    }
  }
}
