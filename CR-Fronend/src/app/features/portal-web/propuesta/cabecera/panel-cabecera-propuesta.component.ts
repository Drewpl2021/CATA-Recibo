import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../../core/services';
import { mensajeErrorApi } from '../../../../core/utils';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { EditorAccionComponent, accionDelGrupo, grupoDeAccion, llenarAccion } from '../../compartido/editor-accion/editor-accion.component';
import { mostrarErrorDelPanel } from '../../compartido/panel';
import { SelectorImagenComponent } from '../../compartido/selector-imagen/selector-imagen.component';
import { CabeceraPropuesta, ImagenPortal, TipoDeFigura } from '../../portal-web.models';
import { PortalWebService } from '../../portal-web.service';

const MAX_FIGURAS = 3;

/** Una figura de la escena, con su imagen completa para la vista previa. */
interface Figura {
  tipo: TipoDeFigura;
  imagen: ImagenPortal | null;
  /** Cambia al cambiar el tipo, para volver a crear el selector con el uso nuevo. */
  vuelta: number;
}

/**
 * Propuesta Educativa › Cabecera: título, frase, hasta 3 figuras (recortes
 * de estudiantes sin fondo, o fotos) y dos botones, el principal obligatorio.
 * Es una ficha: se guarda entera.
 */
@Component({
  selector: 'app-panel-cabecera-propuesta',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, SelectorImagenComponent, EditorAccionComponent],
  templateUrl: './panel-cabecera-propuesta.component.html',
})
export class PanelCabeceraPropuestaComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  readonly maxFiguras = MAX_FIGURAS;

  cargando = true;
  guardando = false;
  private version: string | null = null;
  figuras: Figura[] = [];
  figurasCambiadas = false;
  /** Se intentó guardar: para mostrar lo que falta. */
  intentado = false;

  form = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(70)]],
    bajada: ['', [Validators.maxLength(180)]],
    accionPrincipal: grupoDeAccion(this.fb),
    accionSecundaria: grupoDeAccion(this.fb),
  });

  get conCambios(): boolean {
    return this.form.dirty || this.figurasCambiadas;
  }

  get faltaPrincipal(): boolean {
    return !this.form.controls.accionPrincipal.value.conBoton;
  }

  get figuraSinImagen(): boolean {
    return this.figuras.some((f) => !f.imagen);
  }

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.portalWebService.getFicha<CabeceraPropuesta>('propuesta-cabecera').subscribe({
      next: (res) => {
        const c = res.data.contenido;
        this.version = res.data.version;
        this.form.reset({ titulo: c?.titulo ?? '', bajada: c?.bajada ?? '' });
        llenarAccion(this.form.controls.accionPrincipal, c?.accionPrincipal ?? null);
        llenarAccion(this.form.controls.accionSecundaria, c?.accionSecundaria ?? null);
        this.figuras = (c?.figuras ?? []).map((f) => ({ tipo: f.tipo, imagen: res.data.imagenes[f.imagen.imagenId] ?? null, vuelta: 0 }));
        this.form.markAsPristine();
        this.figurasCambiadas = false;
        this.intentado = false;
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la cabecera de Propuesta Educativa.'));
      },
    });
  }

  /** El uso de imagen de cada tipo: el recorte, vertical y sin fondo; la foto, 4:3. */
  usoDe(tipo: TipoDeFigura): string {
    return tipo === 'recorte' ? 'recorte' : 'foto-cabecera';
  }

  agregarFigura(): void {
    if (this.figuras.length < MAX_FIGURAS) {
      this.figuras = [...this.figuras, { tipo: 'recorte', imagen: null, vuelta: 0 }];
      this.figurasCambiadas = true;
    }
  }

  quitarFigura(i: number): void {
    this.figuras = this.figuras.filter((_, j) => j !== i);
    this.figurasCambiadas = true;
  }

  moverFigura(i: number, direccion: -1 | 1): void {
    const j = i + direccion;
    if (j < 0 || j >= this.figuras.length) return;
    const nuevas = [...this.figuras];
    [nuevas[i], nuevas[j]] = [nuevas[j], nuevas[i]];
    this.figuras = nuevas;
    this.figurasCambiadas = true;
  }

  cambiarTipo(f: Figura, tipo: string): void {
    f.tipo = tipo as TipoDeFigura;
    f.vuelta++;
    this.figurasCambiadas = true;
  }

  cambiarImagen(f: Figura, imagen: ImagenPortal | null): void {
    f.imagen = imagen;
    this.figurasCambiadas = true;
  }

  invalido(campo: 'titulo' | 'bajada'): boolean {
    const c = this.form.controls[campo];
    return c.invalid && c.touched;
  }

  guardar(): void {
    this.intentado = true;
    if (this.form.invalid || this.faltaPrincipal || this.figuraSinImagen) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    const datos = {
      titulo: v.titulo,
      bajada: v.bajada || null,
      figuras: this.figuras.map((f) => ({ tipo: f.tipo, imagen: f.imagen!.id })),
      accionPrincipal: accionDelGrupo(this.form.controls.accionPrincipal),
      accionSecundaria: accionDelGrupo(this.form.controls.accionSecundaria),
    };

    this.guardando = true;
    this.portalWebService.guardarFicha<CabeceraPropuesta>('propuesta-cabecera', datos, this.version).subscribe({
      next: (res) => {
        this.guardando = false;
        this.version = res.data.version;
        this.form.markAsPristine();
        this.figurasCambiadas = false;
        this.intentado = false;
        this.toastService.success('Cabecera guardada', 'El portal la mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardando = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar la cabecera.');
      },
    });
  }
}
