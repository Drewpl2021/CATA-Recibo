import { Component, EventEmitter, Input, OnInit, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../../core/services';
import { mensajeErrorApi } from '../../../../core/utils';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { SelectorArchivoComponent } from '../../../../shared/components/selector-archivo/selector-archivo.component';
import { ImagenPortal, PuntoFocal, UsoDeImagen } from '../../portal-web.models';
import { PortalWebService } from '../../portal-web.service';

/**
 * Elegir la imagen de algo del portal: la foto de una sede, el afiche de un
 * banner, el recorte de un estudiante.
 *
 *   <app-selector-imagen uso="sede" etiqueta="Foto de la sede"
 *     [(imagen)]="foto"></app-selector-imagen>
 *   <app-selector-imagen uso="banner" etiqueta="Afiche"
 *     [(imagen)]="afiche" [(foco)]="puntoFocal"></app-selector-imagen>
 *
 * Pide el texto alternativo ANTES de subir (o marcarla como decorativa): el
 * contrato no deja una imagen sin decidirlo. La sube al momento —el backend
 * la endereza, le quita los metadatos y la comprime— y la muestra en un marco
 * con la proporción con que la recorta el portal, para ver qué va a quedar.
 * En los banners se toca la vista previa para elegir el punto que no se
 * recorta, o se escribe en %.
 *
 * Emite la imagen nueva; el formulario que la hospeda la guarda con lo suyo.
 * Si al final no se guarda, la imagen queda suelta y la limpieza la borra.
 *
 * Reutiliza el selector de archivos del sistema y sus clases; los pocos
 * estilos en línea son los que dependen de los datos (la proporción del
 * marco y el punto focal).
 */
@Component({
  selector: 'app-selector-imagen',
  standalone: true,
  imports: [CommonModule, FormsModule, IconComponent, SelectorArchivoComponent],
  templateUrl: './selector-imagen.component.html',
})
export class SelectorImagenComponent implements OnInit {
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  /** Clave del uso (UsosDeImagen del backend): 'sede', 'banner', 'recorte'… */
  @Input({ required: true }) uso = '';
  @Input() etiqueta = 'Imagen';
  @Input() obligatoria = false;

  @Input() imagen: ImagenPortal | null = null;
  @Output() imagenChange = new EventEmitter<ImagenPortal | null>();

  /** Solo en los usos con foco (banners). null = centro. */
  @Input() foco: PuntoFocal | null = null;
  @Output() focoChange = new EventEmitter<PuntoFocal | null>();

  /** Un id único para enlazar etiquetas y campos cuando hay varios en la misma pantalla. */
  readonly id = 'img-' + Math.random().toString(36).slice(2, 8);

  especificacion: UsoDeImagen | null = null;
  archivos: File[] = [];
  alt = '';
  decorativa = false;
  subiendo = false;
  avisos: string[] = [];
  /** Mostrando el formulario de subida aunque ya haya imagen (al reemplazarla). */
  reemplazando = false;
  editandoAlt = false;
  altEditado = '';
  decorativaEditada = false;
  intentoSubir = false;

  ngOnInit(): void {
    this.portalWebService.getUsosDeImagen().subscribe({
      next: (usos) => (this.especificacion = usos[this.uso] ?? null),
      error: () => (this.especificacion = null),
    });
  }

  get mostrarSubida(): boolean {
    return !this.imagen || this.reemplazando;
  }

  /** «aspect-ratio» del marco: la proporción con que la recorta el portal. */
  get proporcion(): string | null {
    const p = this.especificacion?.proporcion;
    return p ? `${p[0]} / ${p[1]}` : null;
  }

  /**
   * La dirección para la vista previa del panel. Una imagen subida se pide
   * por su ruta en este mismo servidor (/api/portal/medios/…): el panel y la
   * ruta de medios viven juntos, así que funciona aunque la dirección pública
   * (la que recibe el portal, siempre https) apunte a otro nombre. Una imagen
   * de fuera, tal cual.
   */
  get urlParaVer(): string {
    const url = this.imagen?.url ?? '';
    const ruta = '/api/portal/medios/';
    const i = url.indexOf(ruta);
    return i >= 0 ? url.slice(i) : url;
  }

  get posicion(): string {
    const f = this.foco ?? { x: 50, y: 50 };
    return `${f.x}% ${f.y}%`;
  }

  get altFalta(): boolean {
    return !this.decorativa && !this.alt.trim();
  }

  get peso(): string {
    const b = this.imagen?.bytes;
    return b ? (b < 1024 * 1024 ? `${Math.round(b / 1024)} KB` : `${(b / 1024 / 1024).toFixed(1)} MB`) : '';
  }

  subir(): void {
    this.intentoSubir = true;
    const archivo = this.archivos[0];
    if (!archivo || this.altFalta) return;

    this.subiendo = true;
    this.portalWebService.subirImagen(archivo, this.uso, this.alt.trim(), this.decorativa).subscribe({
      next: (res) => {
        this.subiendo = false;
        this.avisos = res.data.avisos;
        this.archivos = [];
        this.alt = '';
        this.decorativa = false;
        this.intentoSubir = false;
        this.reemplazando = false;
        this.imagen = res.data.imagen;
        this.imagenChange.emit(this.imagen);
        // Una imagen nueva vuelve al centro; el punto elegido era de la anterior.
        if (this.especificacion?.foco) this.cambiarFoco(null);
      },
      error: (err: HttpErrorResponse) => {
        this.subiendo = false;
        this.toastService.error('No se subió la imagen', mensajeErrorApi(err, 'No se pudo subir la imagen.'));
      },
    });
  }

  quitar(): void {
    this.imagen = null;
    this.avisos = [];
    this.imagenChange.emit(null);
    if (this.especificacion?.foco) this.cambiarFoco(null);
  }

  // ───────── Texto alternativo de la que ya está ─────────

  empezarEdicionAlt(): void {
    this.altEditado = this.imagen?.alt ?? '';
    this.decorativaEditada = this.imagen?.alt === '';
    this.editandoAlt = true;
  }

  guardarAlt(): void {
    if (!this.imagen || (!this.decorativaEditada && !this.altEditado.trim())) return;

    this.portalWebService.editarAltImagen(this.imagen.id, this.altEditado.trim(), this.decorativaEditada).subscribe({
      next: (res) => {
        this.imagen = res.data.imagen;
        this.imagenChange.emit(this.imagen);
        this.editandoAlt = false;
        this.toastService.success('Texto alternativo guardado', 'El portal lo usará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => this.toastService.error('No se guardó', mensajeErrorApi(err, 'No se pudo guardar el texto alternativo.')),
    });
  }

  // ───────── Punto focal (banners) ─────────

  /** Un toque en la vista previa elige el punto que queda a la vista. */
  elegirFoco(evento: MouseEvent): void {
    if (!this.especificacion?.foco) return;
    const marco = (evento.currentTarget as HTMLElement).getBoundingClientRect();
    const x = Math.round(((evento.clientX - marco.left) / marco.width) * 100);
    const y = Math.round(((evento.clientY - marco.top) / marco.height) * 100);
    this.cambiarFoco({ x: this.acotar(x), y: this.acotar(y) });
  }

  /** Lo mismo escrito en %, para quien usa el teclado. */
  cambiarFocoEn(eje: 'x' | 'y', valor: number | string): void {
    const actual = this.foco ?? { x: 50, y: 50 };
    this.cambiarFoco({ ...actual, [eje]: this.acotar(Number(valor)) });
  }

  centrar(): void {
    this.cambiarFoco(null);
  }

  private cambiarFoco(foco: PuntoFocal | null): void {
    this.foco = foco;
    this.focoChange.emit(foco);
  }

  private acotar(n: number): number {
    return Number.isFinite(n) ? Math.min(100, Math.max(0, Math.round(n))) : 50;
  }
}
