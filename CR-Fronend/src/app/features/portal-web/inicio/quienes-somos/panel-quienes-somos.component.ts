import { Component, EventEmitter, OnInit, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ConfirmService, ToastService } from '../../../../core/services';
import { mensajeErrorApi } from '../../../../core/utils';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { CuerpoDesplazableComponent } from '../../compartido/cuerpo-desplazable/cuerpo-desplazable.component';
import { enfocarPrimerError, mostrarErrorDelPanel, moverEn, urlParaVer } from '../../compartido/panel';
import { cuantos, ResumenDeSeccion } from '../../compartido/resumen-de-seccion';
import { SelectorImagenComponent } from '../../compartido/selector-imagen/selector-imagen.component';
import { ImagenPortal, QuienesSomosPortal } from '../../portal-web.models';
import { PortalWebService } from '../../portal-web.service';

const MAX_PARRAFOS = 6;
const MAX_SIMBOLOS = 8;

type Simbolo = QuienesSomosPortal['simbolos'][number];

/**
 * Inicio › Quiénes somos: el título, de 1 a 6 párrafos, una foto vertical 4:5
 * y el significado de los símbolos del emblema (hasta 8; sin ninguno, el
 * bloque no se muestra).
 *
 * La pantalla es un resumen, como la ficha del alta de personal: lo guardado
 * se lee, y cada cosa se cambia en su modal corto («Editar texto y foto», o
 * un símbolo). El backend guarda la ficha entera, así que cada modal manda
 * todo: lo que cambió y lo que ya estaba guardado.
 */
@Component({
  selector: 'app-panel-quienes-somos',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, FormModalComponent, SelectorImagenComponent, CuerpoDesplazableComponent],
  templateUrl: './panel-quienes-somos.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelQuienesSomosComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  /** Cómo está la sección, para el índice de Inicio. */
  @Output() resumen = new EventEmitter<ResumenDeSeccion>();

  readonly maxParrafos = MAX_PARRAFOS;
  readonly maxSimbolos = MAX_SIMBOLOS;

  cargando = true;
  guardando = false;
  private version: string | null = null;

  /** Lo guardado: lo que se lee en la pantalla. */
  guardado: QuienesSomosPortal | null = null;
  fotoGuardada: ImagenPortal | null = null;

  modal: 'texto' | 'simbolo' | null = null;

  // ───────── Modal «Editar texto y foto» ─────────
  foto: ImagenPortal | null = null;

  form = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(80)]],
    parrafos: this.fb.array<FormControl<string | null>>([]),
  });

  // ───────── Modal de un símbolo ─────────
  /** El símbolo que se edita; null, uno nuevo. */
  simboloEditado: number | null = null;

  simboloForm = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(40)]],
    significado: ['', [Validators.required, Validators.maxLength(160)]],
  });

  get parrafos(): FormArray<FormControl<string | null>> {
    return this.form.controls.parrafos;
  }

  get simbolos(): Simbolo[] {
    return this.guardado?.simbolos ?? [];
  }

  get tieneTexto(): boolean {
    return !!this.guardado?.titulo;
  }

  /** El primer párrafo, corto, para el resumen. */
  get entrada(): string {
    const p = this.guardado?.parrafos?.[0] ?? '';
    return p.length > 180 ? p.slice(0, 180).trimEnd() + '…' : p;
  }

  ngOnInit(): void {
    this.cargar();
  }

  private parrafo(texto = '') {
    return this.fb.control(texto, [Validators.required, Validators.maxLength(600)]);
  }

  cargar(): void {
    this.cargando = true;
    this.portalWebService.getFicha<QuienesSomosPortal>('quienes-somos').subscribe({
      next: (res) => {
        const c = res.data.contenido;
        this.version = res.data.version;
        this.guardado = c ?? null;
        this.fotoGuardada = c?.foto ? res.data.imagenes[c.foto.imagenId] ?? null : null;
        this.cargando = false;
        this.resumir();
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar Quiénes somos.'));
      },
    });
  }

  private resumir(): void {
    if (!this.tieneTexto) {
      this.resumen.emit({
        estado: 'Sin configurar: no se ve',
        aviso: true,
        pendientes: [{ cuantos: 1, texto: 'sección sin configurar: Quiénes somos' }],
      });
      return;
    }

    const partes = [cuantos(this.simbolos.length, 'símbolo', 'símbolos')];
    if (!this.fotoGuardada) partes.unshift('Sin foto');
    this.resumen.emit({ estado: partes.join(' · '), aviso: false, pendientes: [] });
  }

  ver(url: string): string {
    return urlParaVer(url);
  }

  invalido(control: { invalid: boolean; touched: boolean } | null | undefined): boolean {
    return !!control && control.invalid && control.touched;
  }

  cerrar(): void {
    this.modal = null;
  }

  // ───────── Texto y foto ─────────

  abrirTexto(): void {
    const g = this.guardado;
    this.form.reset({ titulo: g?.titulo ?? '' });
    this.parrafos.clear();
    (g?.parrafos?.length ? g.parrafos : ['']).forEach((p) => this.parrafos.push(this.parrafo(p)));
    this.foto = this.fotoGuardada;
    this.modal = 'texto';
  }

  agregarParrafo(): void {
    if (this.parrafos.length < MAX_PARRAFOS) this.parrafos.push(this.parrafo());
  }

  quitarParrafo(i: number): void {
    if (this.parrafos.length > 1) this.parrafos.removeAt(i);
  }

  moverParrafo(i: number, direccion: -1 | 1): void {
    moverEn(this.parrafos, i, direccion);
  }

  guardarTexto(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      enfocarPrimerError();
      return;
    }

    const v = this.form.getRawValue();
    this.enviar(
      { titulo: v.titulo ?? '', parrafos: v.parrafos.map((p) => (p ?? '').trim()), simbolos: this.simbolos },
      this.foto,
      'Quiénes somos guardado',
    );
  }

  // ───────── Símbolos ─────────

  nuevoSimbolo(): void {
    if (!this.puedeTocarSimbolos()) return;
    this.simboloEditado = null;
    this.simboloForm.reset({ nombre: '', significado: '' });
    this.modal = 'simbolo';
  }

  editarSimbolo(i: number): void {
    this.simboloEditado = i;
    this.simboloForm.reset({ ...this.simbolos[i] });
    this.modal = 'simbolo';
  }

  guardarSimbolo(): void {
    if (this.simboloForm.invalid) {
      this.simboloForm.markAllAsTouched();
      enfocarPrimerError();
      return;
    }

    const v = this.simboloForm.getRawValue();
    const simbolo = { nombre: (v.nombre ?? '').trim(), significado: (v.significado ?? '').trim() };
    const simbolos = [...this.simbolos];
    if (this.simboloEditado === null) simbolos.push(simbolo);
    else simbolos[this.simboloEditado] = simbolo;

    this.enviarSimbolos(simbolos, this.simboloEditado === null ? 'Símbolo agregado' : 'Símbolo guardado');
  }

  quitarSimbolo(i: number): void {
    const s = this.simbolos[i];
    this.confirmService.confirmarEliminar(`el símbolo «${s.nombre}»`, () => {
      this.enviarSimbolos(this.simbolos.filter((_, j) => j !== i), 'Símbolo quitado');
    });
  }

  moverSimbolo(i: number, direccion: -1 | 1): void {
    const j = i + direccion;
    if (j < 0 || j >= this.simbolos.length) return;
    const simbolos = [...this.simbolos];
    [simbolos[i], simbolos[j]] = [simbolos[j], simbolos[i]];
    this.enviarSimbolos(simbolos, 'Orden guardado');
  }

  /** Los símbolos se guardan con el texto de la ficha: sin texto todavía, primero va el texto. */
  private puedeTocarSimbolos(): boolean {
    if (this.tieneTexto) return true;
    this.toastService.warning('Primero el texto', 'Escribe el título y el texto de Quiénes somos; después, los símbolos.');
    this.abrirTexto();
    return false;
  }

  private enviarSimbolos(simbolos: Simbolo[], aviso: string): void {
    const g = this.guardado;
    if (!g || !this.puedeTocarSimbolos()) return;
    this.enviar({ titulo: g.titulo, parrafos: g.parrafos, simbolos }, this.fotoGuardada, aviso);
  }

  // ───────── Guardar la ficha entera ─────────

  private enviar(contenido: Omit<QuienesSomosPortal, 'foto'>, foto: ImagenPortal | null, aviso: string): void {
    const datos = { ...contenido, foto: foto?.id ?? null };

    this.guardando = true;
    this.portalWebService.guardarFicha<QuienesSomosPortal>('quienes-somos', datos, this.version).subscribe({
      next: (res) => {
        this.guardando = false;
        this.version = res.data.version;
        this.guardado = { ...contenido, foto: foto ? { imagenId: foto.id } : null };
        this.fotoGuardada = foto;
        this.modal = null;
        this.resumir();
        this.toastService.success(aviso, 'El portal lo mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardando = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar Quiénes somos.');
      },
    });
  }
}
