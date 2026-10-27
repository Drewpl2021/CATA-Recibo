import { Component, EventEmitter, OnInit, Output, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../../core/services';
import { mensajeErrorApi } from '../../../../core/utils';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { WizardComponent } from '../../../../shared/components/wizard/wizard.component';
import { PasoWizard } from '../../../../shared/components/wizard/wizard.models';
import { CuerpoDesplazableComponent } from '../../compartido/cuerpo-desplazable/cuerpo-desplazable.component';
import { EditorAccionComponent, accionDelGrupo, grupoDeAccion, llenarAccion } from '../../compartido/editor-accion/editor-accion.component';
import { mostrarErrorDelPanel, urlParaVer } from '../../compartido/panel';
import { ResumenDeSeccion } from '../../compartido/resumen-de-seccion';
import { SelectorImagenComponent } from '../../compartido/selector-imagen/selector-imagen.component';
import { AccionPortal, CabeceraPropuesta, ImagenPortal, TipoDeFigura } from '../../portal-web.models';
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
 *
 * Se lee en su sección y se edita en un wizard de tres pasos: el texto, los
 * botones y las figuras.
 */
@Component({
  selector: 'app-panel-cabecera-propuesta',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, IconComponent, FormModalComponent, SelectorImagenComponent, EditorAccionComponent,
    WizardComponent, CuerpoDesplazableComponent,
  ],
  templateUrl: './panel-cabecera-propuesta.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelCabeceraPropuestaComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  /** Cómo está la sección, para el índice de la pantalla. */
  @Output() resumen = new EventEmitter<ResumenDeSeccion>();

  @ViewChild('wizard') private wizard?: WizardComponent;
  @ViewChild('cuerpo') private cuerpo?: CuerpoDesplazableComponent;

  readonly maxFiguras = MAX_FIGURAS;
  readonly ver = urlParaVer;

  readonly pasos: PasoWizard[] = [
    { id: 'texto', titulo: 'Texto', icono: 'description', campos: ['titulo', 'bajada'] },
    { id: 'botones', titulo: 'Botones', icono: 'arrow_right', campos: ['accionPrincipal', 'accionSecundaria'] },
    { id: 'figuras', titulo: 'Figuras', icono: 'camera', campos: [] },
  ];
  paso = 0;

  cargando = true;
  guardando = false;
  modalVisible = false;
  private version: string | null = null;
  /** Lo guardado: lo que se lee en la sección. */
  guardada: CabeceraPropuesta | null = null;
  figurasGuardadas: Figura[] = [];
  /** Las del modal, mientras se edita. */
  figuras: Figura[] = [];
  /** Se intentó guardar: para mostrar lo que falta. */
  intentado = false;

  form = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(70)]],
    bajada: ['', [Validators.maxLength(180)]],
    accionPrincipal: grupoDeAccion(this.fb),
    accionSecundaria: grupoDeAccion(this.fb),
  });

  get faltaPrincipal(): boolean {
    return !this.form.controls.accionPrincipal.value.conBoton;
  }

  get figuraSinImagen(): boolean {
    return this.figuras.some((f) => !f.imagen);
  }

  /** Al guardar, las figuras leídas se vuelven a armar: la misma es la de la misma imagen. */
  readonly porFigura = (i: number, f: Figura): string => f.imagen?.id ?? String(i);

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.portalWebService.getFicha<CabeceraPropuesta>('propuesta-cabecera').subscribe({
      next: (res) => {
        const c = res.data.contenido;
        this.version = res.data.version;
        this.guardada = c ?? null;
        this.figurasGuardadas = (c?.figuras ?? []).map((f) => ({ tipo: f.tipo, imagen: res.data.imagenes[f.imagen.imagenId] ?? null, vuelta: 0 }));
        this.cargando = false;
        this.resumir();
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la cabecera de Propuesta Educativa.'));
      },
    });
  }

  private resumir(): void {
    const c = this.guardada;
    if (!c) {
      this.resumen.emit({ estado: 'Sin configurar: no se ve', aviso: true, pendientes: [{ cuantos: 1, texto: 'cabecera de Propuesta Educativa sin configurar' }] });
      return;
    }
    const n = this.figurasGuardadas.length;
    this.resumen.emit({ estado: n ? `${n === 1 ? '1 figura' : `${n} figuras`} · ${c.accionSecundaria ? '2 botones' : '1 botón'}` : 'Solo texto, sin figuras', aviso: false, pendientes: [] });
  }

  /** A dónde lleva un botón, para leerlo en la ficha. */
  adonde(a: AccionPortal): string {
    const d = a.destino;
    if (d.tipo === 'seccion') return `la sección «${d.seccion}» de Inicio`;
    if (d.tipo === 'pagina') return d.ruta;
    return d.url;
  }

  /** El modal empieza con lo guardado: lo que se canceló antes no queda a medias. */
  abrir(): void {
    const c = this.guardada;
    this.form.reset({ titulo: c?.titulo ?? '', bajada: c?.bajada ?? '' });
    llenarAccion(this.form.controls.accionPrincipal, c?.accionPrincipal ?? null);
    llenarAccion(this.form.controls.accionSecundaria, c?.accionSecundaria ?? null);
    this.figuras = this.figurasGuardadas.map((f) => ({ ...f }));
    this.intentado = false;
    this.paso = 0;
    this.modalVisible = true;
  }

  cerrar(): void {
    this.modalVisible = false;
  }

  /** Los botones son grupos: se marcan sus campos y, sin el principal, no se sigue. */
  siguiente(): void {
    this.pasos[this.paso].campos?.forEach((c) => this.form.get(c)?.markAllAsTouched());
    if (this.paso === 1 && this.faltaPrincipal) {
      this.intentado = true;
      this.cuerpo?.irAlPrimerError();
      return;
    }
    const antes = this.paso;
    this.wizard?.siguiente();
    if (this.paso === antes) this.cuerpo?.irAlPrimerError();
  }

  /** El uso de imagen de cada tipo: el recorte, vertical y sin fondo; la foto, 4:3. */
  usoDe(tipo: TipoDeFigura): string {
    return tipo === 'recorte' ? 'recorte' : 'foto-cabecera';
  }

  agregarFigura(): void {
    if (this.figuras.length < MAX_FIGURAS) this.figuras = [...this.figuras, { tipo: 'recorte', imagen: null, vuelta: 0 }];
  }

  quitarFigura(i: number): void {
    this.figuras = this.figuras.filter((_, j) => j !== i);
  }

  moverFigura(i: number, direccion: -1 | 1): void {
    const j = i + direccion;
    if (j < 0 || j >= this.figuras.length) return;
    const nuevas = [...this.figuras];
    [nuevas[i], nuevas[j]] = [nuevas[j], nuevas[i]];
    this.figuras = nuevas;
  }

  cambiarTipo(f: Figura, tipo: string): void {
    f.tipo = tipo as TipoDeFigura;
    f.vuelta++;
  }

  cambiarImagen(f: Figura, imagen: ImagenPortal | null): void {
    f.imagen = imagen;
  }

  invalido(campo: 'titulo' | 'bajada'): boolean {
    const c = this.form.controls[campo];
    return c.invalid && c.touched;
  }

  /** Si algo falta, el wizard vuelve al primer paso con error. */
  guardar(): void {
    this.intentado = true;
    this.form.markAllAsTouched();
    const conError = this.form.controls.titulo.invalid || this.form.controls.bajada.invalid ? 0
      : this.faltaPrincipal || this.form.controls.accionPrincipal.invalid || this.form.controls.accionSecundaria.invalid ? 1
      : this.figuraSinImagen ? 2 : -1;
    if (conError >= 0) {
      this.paso = conError;
      this.cuerpo?.irAlPrimerError();
      return;
    }

    const v = this.form.getRawValue();
    const datos = {
      titulo: v.titulo!,
      bajada: v.bajada || null,
      figuras: this.figuras.map((f) => ({ tipo: f.tipo, imagen: f.imagen!.id })),
      accionPrincipal: accionDelGrupo(this.form.controls.accionPrincipal)!,
      accionSecundaria: accionDelGrupo(this.form.controls.accionSecundaria),
    };

    this.guardando = true;
    this.portalWebService.guardarFicha<CabeceraPropuesta>('propuesta-cabecera', datos, this.version).subscribe({
      next: (res) => {
        this.guardando = false;
        this.version = res.data.version;
        this.guardada = { ...datos, figuras: this.figuras.map((f) => ({ tipo: f.tipo, imagen: { imagenId: f.imagen!.id } })) } as CabeceraPropuesta;
        this.figurasGuardadas = this.figuras.map((f) => ({ ...f }));
        this.modalVisible = false;
        this.resumir();
        this.toastService.success('Cabecera guardada', 'El portal la mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardando = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar la cabecera.');
      },
    });
  }
}
