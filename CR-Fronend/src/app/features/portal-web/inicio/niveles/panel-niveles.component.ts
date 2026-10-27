import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { mensajeErrorApi } from '../../../../core/utils';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { CuerpoDesplazableComponent } from '../../compartido/cuerpo-desplazable/cuerpo-desplazable.component';
import { enfocarPrimerError, mostrarErrorDelPanel, porId, urlParaVer } from '../../compartido/panel';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { cuantos, ResumenDeSeccion } from '../../compartido/resumen-de-seccion';
import { SelectorImagenComponent } from '../../compartido/selector-imagen/selector-imagen.component';
import { EstadoPortal, ImagenPortal, NivelPortal, SeccionNiveles } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

/**
 * Inicio › Niveles: el título de la sección, el enlace a la propuesta
 * educativa y hasta 4 niveles con su foto 3:2.
 *
 * Son los de Inicio: Propuesta Educativa tiene su propia lista de niveles.
 * Cada nivel es una tarjeta, como las del dashboard; el título y el enlace
 * se leen arriba y se cambian en su propio modal.
 */
@Component({
  selector: 'app-panel-niveles',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, FormModalComponent, SelectorImagenComponent, CuerpoDesplazableComponent],
  templateUrl: './panel-niveles.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelNivelesComponent extends PanelDeLista<NivelPortal> implements OnInit {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.niveles;
  protected readonly nombre = { singular: 'nivel', femenino: false };
  readonly porId = porId;

  foto: ImagenPortal | null = null;

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(24)]],
    rango: ['', [Validators.maxLength(40)]],
    sede: ['', [Validators.maxLength(60)]],
    reconocimiento: ['', [Validators.maxLength(60)]],
    estado: ['borrador' as EstadoPortal],
  });

  // ───────── La ficha: título y enlace ─────────

  cargandoSeccion = true;
  guardandoSeccion = false;
  modalSeccion = false;
  private versionSeccion: string | null = null;
  /** Lo guardado: lo que se lee arriba de las tarjetas. */
  seccionGuardada: SeccionNiveles | null = null;

  seccion = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    propuestaUrl: ['', [Validators.pattern(/^https:\/\/\S+$/)]],
  });

  override ngOnInit(): void {
    super.ngOnInit();
    this.cargarSeccion();
  }

  protected describir(n: NivelPortal): string {
    return `el nivel «${n.nombre}» de Inicio`;
  }

  /** Solo la primera vez: al recargar después de guardar, las tarjetas se quedan donde están. */
  get primeraCarga(): boolean {
    return (this.cargando && !this.filas.length) || this.cargandoSeccion;
  }

  /** Los publicados sin foto: su tarjeta se ve vacía en el portal. */
  get publicadosSinFoto(): number {
    return this.filas.filter((n) => n.estado === 'publicado' && !n.imagen_id).length;
  }

  protected override resumir(): ResumenDeSeccion | null {
    if (this.cargando || this.cargandoSeccion) return null;

    const sinFoto = this.publicadosSinFoto;
    const sinTitulo = !this.seccionGuardada?.titulo;
    const pendientes = [];
    if (sinTitulo) pendientes.push({ cuantos: 1, texto: 'sección sin título: Niveles' });
    if (sinFoto) pendientes.push({ cuantos: sinFoto, texto: sinFoto === 1 ? 'nivel publicado sin foto' : 'niveles publicados sin foto' });

    let estado = this.filas.length ? cuantos(this.publicados, 'publicado', 'publicados') : 'Sin niveles';
    if (sinFoto) estado += ` · ${sinFoto} sin foto`;
    if (sinTitulo) estado = 'Falta el título de la sección';

    return { estado, aviso: sinTitulo || sinFoto > 0, pendientes };
  }

  ver(url: string): string {
    return urlParaVer(url);
  }

  nuevo(): void {
    this.editando = null;
    this.foto = null;
    this.form.reset({ nombre: '', rango: '', sede: '', reconocimiento: '', estado: 'borrador' });
    this.modalVisible = true;
  }

  editar(n: NivelPortal): void {
    this.editando = n;
    this.foto = n.imagen;
    this.form.reset({ nombre: n.nombre, rango: n.rango ?? '', sede: n.sede ?? '', reconocimiento: n.reconocimiento ?? '', estado: n.estado });
    this.modalVisible = true;
  }

  /** Publicar o pasar a borrador, desde el botón de la tarjeta. */
  alternarEstado(n: NivelPortal): void {
    this.alAccionar({ accion: n.estado === 'publicado' ? 'ocultar' : 'publicar', fila: n });
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  invalidoSeccion(campo: string): boolean {
    const c = this.seccion.get(campo);
    return !!c && c.invalid && c.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      enfocarPrimerError();
      return;
    }

    const v = this.form.getRawValue();
    this.enviar({
      nombre: v.nombre, rango: v.rango || null, sede: v.sede || null, reconocimiento: v.reconocimiento || null,
      imagen_id: this.foto?.id ?? null, estado: v.estado,
    });
  }

  // ───────── La ficha ─────────

  cargarSeccion(): void {
    this.cargandoSeccion = true;
    this.portalWebService.getFicha<SeccionNiveles>('niveles').subscribe({
      next: (res) => {
        this.versionSeccion = res.data.version;
        this.seccionGuardada = res.data.contenido ?? null;
        this.cargandoSeccion = false;
        this.avisarResumen();
      },
      error: (err) => {
        this.cargandoSeccion = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la sección Niveles.'));
      },
    });
  }

  abrirSeccion(): void {
    this.seccion.reset({ titulo: this.seccionGuardada?.titulo ?? '', propuestaUrl: this.seccionGuardada?.propuestaUrl ?? '' });
    this.modalSeccion = true;
  }

  guardarSeccion(): void {
    if (this.seccion.invalid) {
      this.seccion.markAllAsTouched();
      enfocarPrimerError();
      return;
    }

    const v = this.seccion.getRawValue();
    const datos: SeccionNiveles = { titulo: v.titulo ?? '', propuestaUrl: v.propuestaUrl || null };
    this.guardandoSeccion = true;
    this.portalWebService.guardarFicha<SeccionNiveles>('niveles', datos, this.versionSeccion).subscribe({
      next: (res) => {
        this.guardandoSeccion = false;
        this.versionSeccion = res.data.version;
        this.seccionGuardada = datos;
        this.modalSeccion = false;
        this.avisarResumen();
        this.toastService.success('Sección guardada', 'El portal la mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoSeccion = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar la sección Niveles.');
      },
    });
  }
}
