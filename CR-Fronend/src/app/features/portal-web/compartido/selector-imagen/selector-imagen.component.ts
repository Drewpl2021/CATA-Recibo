import { Component, EventEmitter, Input, OnDestroy, OnInit, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse, HttpEventType } from '@angular/common/http';
import { Subscription } from 'rxjs';

import { ToastService } from '../../../../core/services';
import { mensajeErrorApi } from '../../../../core/utils';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { SelectorArchivoComponent } from '../../../../shared/components/selector-archivo/selector-archivo.component';
import { ImagenPortal, PuntoFocal, UsoDeImagen } from '../../portal-web.models';
import { PortalWebService } from '../../portal-web.service';

/** Por dónde va la subida: el archivo viajando, el backend preparándola, o ya lista. */
type EtapaDeSubida = 'subiendo' | 'optimizando' | 'lista';

/** «4,2 MB» o «380 KB». */
function peso(bytes: number): string {
  return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`;
}

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
 * Mientras sube, dice por dónde va: «Subiendo…» con la barra del sistema,
 * «Optimizando…» mientras el backend la prepara, y «Lista» con cuánto pesaba
 * y cuánto quedó. Si falla, la causa queda aquí mismo, con «Reintentar». Al
 * reemplazar una imagen se ven la de antes y la nueva lado a lado, y hasta
 * que se guarda el formulario se puede volver a la anterior.
 *
 * Emite la imagen nueva; el formulario que la hospeda la guarda con lo suyo.
 * Si al final no se guarda, la imagen queda suelta y la limpieza la borra.
 *
 * Reutiliza el selector de archivos y la barra de progreso del sistema; los
 * pocos estilos en línea son los que dependen de los datos (la proporción
 * del marco y el punto focal).
 */
@Component({
  selector: 'app-selector-imagen',
  standalone: true,
  imports: [CommonModule, FormsModule, IconComponent, SelectorArchivoComponent],
  templateUrl: './selector-imagen.component.html',
  styleUrls: ['../pw-estilos.scss'],
})
export class SelectorImagenComponent implements OnInit, OnDestroy {
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
  avisos: string[] = [];
  /** Mostrando el formulario de subida aunque ya haya imagen (al reemplazarla). */
  reemplazando = false;
  editandoAlt = false;
  altEditado = '';
  decorativaEditada = false;
  intentoSubir = false;

  // ───────── La subida ─────────

  etapa: EtapaDeSubida | null = null;
  /** Cuánto del archivo ya llegó, de 0 a 100. */
  porcentaje = 0;
  enviados = 0;
  total = 0;
  /** Lo que pesaba el archivo, para decir cuánto se achicó. */
  pesoOriginal = 0;
  /** Por qué no se subió, dicho para quien la sube. */
  error: string | null = null;
  private subida?: Subscription;

  // ───────── Reemplazar ─────────

  /** La que había antes de reemplazar: para compararlas y para volver a ella. */
  anterior: ImagenPortal | null = null;
  private focoAnterior: PuntoFocal | null = null;
  /** El archivo elegido, visto aquí mismo antes de subirlo. */
  vistaPrevia: string | null = null;

  ngOnInit(): void {
    this.portalWebService.getUsosDeImagen().subscribe({
      next: (usos) => (this.especificacion = usos[this.uso] ?? null),
      error: () => (this.especificacion = null),
    });
  }

  ngOnDestroy(): void {
    this.subida?.unsubscribe();
    this.soltarVistaPrevia();
  }

  get subiendo(): boolean {
    return this.etapa === 'subiendo' || this.etapa === 'optimizando';
  }

  get mostrarSubida(): boolean {
    return !this.imagen || this.reemplazando;
  }

  /** Antes y después, lado a lado: mientras se reemplaza, y después, hasta decidir. */
  get comparando(): boolean {
    return !!this.imagen && (this.reemplazando || !!this.anterior);
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
  ver(imagen: ImagenPortal | null): string {
    const url = imagen?.url ?? '';
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

  /** «1850 × 1233 px · 247 KB» (sin el peso si no se sabe, como en las de los ejemplos). */
  medidas(imagen: ImagenPortal | null): string {
    if (!imagen) return '';
    return [`${imagen.ancho} × ${imagen.alto} px`, imagen.bytes ? peso(imagen.bytes) : null].filter(Boolean).join(' · ');
  }

  /** «Pesaba 4,2 MB y quedó en 380 KB.» */
  get resultado(): string {
    const final = this.imagen?.bytes ?? 0;
    if (!this.pesoOriginal || !final) return '';
    return final < this.pesoOriginal
      ? `Pesaba ${peso(this.pesoOriginal)} y quedó en ${peso(final)}.`
      : `Pesa ${peso(final)}: ya venía liviana.`;
  }

  get textoDeAvance(): string {
    return `${peso(this.enviados)} de ${peso(this.total || this.pesoOriginal)}`;
  }

  /** El archivo elegido: se ve aquí mismo (al reemplazar, junto a la de antes) y se olvida el error anterior. */
  alElegirArchivo(archivos: File[]): void {
    this.archivos = archivos;
    this.error = null;
    this.soltarVistaPrevia();
    if (archivos[0]) this.vistaPrevia = URL.createObjectURL(archivos[0]);
  }

  private soltarVistaPrevia(): void {
    if (this.vistaPrevia) URL.revokeObjectURL(this.vistaPrevia);
    this.vistaPrevia = null;
  }

  subir(): void {
    this.intentoSubir = true;
    const archivo = this.archivos[0];
    if (!archivo || this.altFalta) return;

    this.error = null;
    this.etapa = 'subiendo';
    this.porcentaje = 0;
    this.enviados = 0;
    this.total = archivo.size;
    this.pesoOriginal = archivo.size;

    this.subida = this.portalWebService.subirImagen(archivo, this.uso, this.alt.trim(), this.decorativa).subscribe({
      next: (evento) => {
        if (evento.type === HttpEventType.UploadProgress) {
          this.enviados = evento.loaded;
          this.total = evento.total ?? this.total;
          this.porcentaje = this.total ? Math.min(100, Math.round((evento.loaded / this.total) * 100)) : 0;
          // Ya llegó entero: lo que falta es que el backend la prepare.
          if (this.total && evento.loaded >= this.total) this.etapa = 'optimizando';
        } else if (evento.type === HttpEventType.Response && evento.body) {
          this.alSubir(evento.body.data.imagen, evento.body.data.avisos);
        }
      },
      error: (err: HttpErrorResponse) => {
        this.etapa = null;
        this.error = this.causaDe(err);
      },
    });
  }

  private alSubir(imagen: ImagenPortal, avisos: string[]): void {
    this.etapa = 'lista';
    this.avisos = avisos;
    this.archivos = [];
    this.soltarVistaPrevia();
    this.alt = '';
    this.decorativa = false;
    this.intentoSubir = false;
    this.reemplazando = false;
    this.imagen = imagen;
    this.imagenChange.emit(imagen);
    // Una imagen nueva vuelve al centro; el punto elegido era de la anterior.
    if (this.especificacion?.foco) this.cambiarFoco(null);
  }

  /** Se deja de esperar: el archivo no se guarda. */
  cancelarSubida(): void {
    this.subida?.unsubscribe();
    this.etapa = null;
  }

  /**
   * La causa, dicha para quien la sube. El backend ya explica lo suyo (el
   * formato, el tamaño, que esté dañada); lo demás es la conexión o el
   * servidor.
   */
  private causaDe(err: HttpErrorResponse): string {
    if (err.status === 0) return 'No hubo conexión con el servidor. Revisa el internet y vuelve a intentarlo.';
    if (err.status === 413) return 'El archivo pesa más de lo que acepta el servidor. Achícalo (a menos de 5 MB, por ejemplo) y vuelve a subirlo.';
    if (err.status === 401 || err.status === 419) return 'La sesión terminó. Vuelve a entrar y sube la imagen otra vez.';

    const errores = err.error?.errors as Record<string, string[] | string> | undefined;
    const primero = errores ? Object.values(errores).flat()[0] : null;
    if (primero) return primero;

    if (err.status >= 500) return 'El servidor no pudo preparar la imagen. Vuelve a intentarlo; si se repite, prueba guardándola otra vez como JPEG.';
    return mensajeErrorApi(err, 'No se pudo subir la imagen.');
  }

  quitar(): void {
    this.imagen = null;
    this.avisos = [];
    this.etapa = null;
    this.anterior = null;
    this.imagenChange.emit(null);
    if (this.especificacion?.foco) this.cambiarFoco(null);
  }

  // ───────── Reemplazar, con la de antes a la vista ─────────

  empezarReemplazo(): void {
    this.anterior = this.imagen;
    this.focoAnterior = this.foco;
    this.reemplazando = true;
    this.etapa = null;
    this.error = null;
  }

  /** Se arrepiente antes de subir: queda la que estaba, sin cambios. */
  cancelarReemplazo(): void {
    this.reemplazando = false;
    this.anterior = null;
    this.archivos = [];
    this.error = null;
    this.soltarVistaPrevia();
  }

  /** Ya subida la nueva, vuelve a la anterior (con su punto focal). La nueva queda suelta y la limpieza la borra. */
  volverALaAnterior(): void {
    if (!this.anterior) return;
    this.imagen = this.anterior;
    this.imagenChange.emit(this.imagen);
    if (this.especificacion?.foco) this.cambiarFoco(this.focoAnterior);
    this.anterior = null;
    this.avisos = [];
    this.etapa = null;
  }

  quedarseConLaNueva(): void {
    this.anterior = null;
  }

  // ───────── Texto alternativo de la que ya está ─────────

  empezarEdicionAlt(): void {
    this.altEditado = this.imagen?.alt ?? '';
    this.decorativaEditada = this.imagen?.alt === '';
    this.editandoAlt = true;
    this.etapa = null;
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
