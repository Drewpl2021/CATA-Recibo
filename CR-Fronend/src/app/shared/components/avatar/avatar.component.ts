import { ChangeDetectorRef, Component, HostListener, Input, OnChanges, OnDestroy, inject } from '@angular/core';
import { Subscription } from 'rxjs';
import { FotoPerfilService } from '../../../core/services/personal/foto-perfil.service';
import { AlCuerpoDirective } from '../../directives/al-cuerpo.directive';
import { IconComponent } from '../icon/icon.component';

/** Lo que el servidor manda de la foto de una cuenta (ver FotoDePerfil en el backend). */
export interface FotoDeCuenta {
  usuario_id: number | string;
  version: string | null;
}

/**
 * El circulito de una persona: su foto o, si no tiene, sus iniciales. Con
 * foto, un clic la abre en grande.
 *
 *   <app-avatar [foto]="fila.foto" [nombre]="fila.nombre + ' ' + fila.apellido"></app-avatar>
 *
 * La imagen está en el disco privado: se pide con el token y se guarda en
 * memoria una sola vez por cuenta y versión (FotoPerfilService.urlDe), así
 * una tabla de treinta filas no hace treinta pedidos cada vez que se pinta.
 */
@Component({
  selector: 'app-avatar',
  standalone: true,
  imports: [AlCuerpoDirective, IconComponent],
  template: `
    <button type="button" class="avatar avatar--tabla" [style.width.px]="tamano" [style.height.px]="tamano"
      [style.font-size.px]="tamano * 0.38" [class.avatar--con-foto]="url"
      [disabled]="!url" (click)="abrir($event)"
      [attr.aria-label]="url ? 'Ver la foto de ' + nombre : null" [attr.title]="url ? 'Ver la foto' : null">
      @if (url) {
        <img class="avatar__foto" [src]="url" [alt]="'Foto de ' + nombre" />
      } @else {
        {{ iniciales }}
      }
    </button>

    @if (abierta && url) {
      <div class="visor-foto" appAlCuerpo role="dialog" aria-modal="true" [attr.aria-label]="'Foto de ' + nombre"
        (click)="cerrar()">
        <figure class="visor-foto__marco" (click)="$event.stopPropagation()">
          <img [src]="url" [alt]="'Foto de ' + nombre" />
          <figcaption>{{ nombre }}</figcaption>
        </figure>
        <button type="button" class="visor-foto__cerrar" (click)="cerrar()" aria-label="Cerrar">
          <app-icon icono="close" [tamano]="22"></app-icon>
        </button>
      </div>
    }
  `,
})
export class AvatarComponent implements OnChanges, OnDestroy {
  private fotos = inject(FotoPerfilService);
  private cdr = inject(ChangeDetectorRef);

  @Input() foto: FotoDeCuenta | null | undefined = null;
  @Input() nombre = '';
  @Input() tamano = 34;

  url: string | null = null;
  iniciales = '';
  abierta = false;
  private pedido?: Subscription;
  private claveActual: string | null = null;

  ngOnChanges(): void {
    this.iniciales = this.nombre
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((p) => p[0].toUpperCase())
      .join('');

    // Al recargar la tabla llega un objeto nuevo con la MISMA foto: si no
    // cambió la cuenta ni la versión, no se toca nada (sin parpadeo).
    const clave = this.foto?.version ? `${this.foto.usuario_id}:${this.foto.version}` : '';
    if (clave === this.claveActual) return;
    this.claveActual = clave;

    this.pedido?.unsubscribe();
    this.url = null;
    if (this.foto?.version) {
      this.pedido = this.fotos.urlDe(this.foto.usuario_id, this.foto.version).subscribe((url) => {
        this.url = url;
        this.cdr.markForCheck();
      });
    }
  }

  abrir(evento: Event): void {
    // La fila de la tabla puede tener su propio clic: la foto no lo dispara.
    evento.stopPropagation();
    if (this.url) this.abierta = true;
  }

  @HostListener('document:keydown.escape')
  cerrar(): void {
    this.abierta = false;
  }

  ngOnDestroy(): void {
    this.pedido?.unsubscribe();
  }
}
