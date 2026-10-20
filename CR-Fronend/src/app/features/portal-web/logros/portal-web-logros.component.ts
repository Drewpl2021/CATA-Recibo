import { Component, OnInit, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ConfirmService, ToastService } from '../../../core/services';
import { mensajeErrorApi } from '../../../core/utils';
import { CeldaTablaDirective } from '../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { WizardComponent } from '../../../shared/components/wizard/wizard.component';
import { PasoWizard } from '../../../shared/components/wizard/wizard.models';
import { CuerpoDesplazableComponent } from '../compartido/cuerpo-desplazable/cuerpo-desplazable.component';
import { EditorCifrasComponent } from '../compartido/editor-cifras/editor-cifras.component';
import { IndiceDeSeccionesComponent } from '../compartido/indice-de-secciones/indice-de-secciones.component';
import { PantallaConIndice, SeccionDelIndice } from '../compartido/pantalla-con-indice';
import { conFila, enfocarPrimerError, enOrden, idsMovidos, mostrarErrorDelPanel, urlParaVer } from '../compartido/panel';
import { cuantos } from '../compartido/resumen-de-seccion';
import { SelectorImagenComponent } from '../compartido/selector-imagen/selector-imagen.component';
import { CATEGORIAS_DE_LOGRO, CabeceraSimple, EstadoPortal, ImagenPortal, LogroPortal } from '../portal-web.models';
import { PortalWebService, RECURSOS } from '../portal-web.service';

const HTTPS = /^https:\/\//;

/**
 * Panel «Portal Web» › Logros: la cabecera de la página, sus cifras y los
 * logros del colegio.
 *
 * Solo los que el colegio confirmó, cada uno con su fuente. Lo que no se sabe
 * se deja vacío (no se inventa) y, si el dato está incompleto, se avisa a la
 * vista con la aclaración («Año por confirmar»). El portal los agrupa por
 * década y muestra arriba los destacados.
 *
 * Con el molde de Ajustes: índice con cabecera, cifras y logros. La cabecera
 * se lee en su sección y se edita en un modal; el logro, en un wizard de tres
 * pasos (el logro, su fuente y la foto).
 */
@Component({
  selector: 'app-portal-web-logros',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    PageHeaderComponent, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent,
    SelectorImagenComponent, EditorCifrasComponent, WizardComponent, CuerpoDesplazableComponent, IndiceDeSeccionesComponent,
  ],
  templateUrl: './portal-web-logros.component.html',
  styleUrls: ['../compartido/pw-estilos.scss'],
})
export class PortalWebLogrosComponent extends PantallaConIndice implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  readonly categorias = CATEGORIAS_DE_LOGRO;
  readonly anioActual = new Date().getFullYear();

  readonly secciones: SeccionDelIndice[] = [
    { id: 'cabecera', nombre: 'Cabecera', icono: 'description' },
    { id: 'cifras', nombre: 'Cifras', icono: 'bar_chart' },
    { id: 'logros', nombre: 'Logros', icono: 'trending_up' },
  ];

  readonly pasos: PasoWizard[] = [
    { id: 'logro', titulo: 'El logro', icono: 'trending_up', campos: ['titulo', 'categoria', 'anio', 'nivel', 'descripcion'] },
    { id: 'fuente', titulo: 'Fuente', icono: 'description', campos: ['fuenteEtiqueta', 'fuenteUrl', 'aclaracion'] },
    { id: 'foto', titulo: 'Foto', icono: 'camera', campos: [] },
  ];

  @ViewChild('wizard') private wizard?: WizardComponent;
  @ViewChild('cuerpo') private cuerpo?: CuerpoDesplazableComponent;

  // ───────── Cabecera ─────────

  cargandoCabecera = true;
  guardandoCabecera = false;
  private versionCabecera: string | null = null;
  modalCabecera = false;
  /** Lo guardado: lo que se lee en la sección. */
  cabeceraGuardada: CabeceraSimple | null = null;

  cabecera = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    bajada: ['', [Validators.maxLength(200)]],
  });

  // ───────── Logros ─────────

  logros: LogroPortal[] = [];
  cargandoLogros = true;
  ordenando = false;
  modalVisible = false;
  guardando = false;
  editando: LogroPortal | null = null;
  foto: ImagenPortal | null = null;
  paso = 0;

  // La categoría, el año, el nivel y si es destacado van bajo el título: así caben las acciones.
  columnas: ColumnaTabla<LogroPortal>[] = [
    { campo: 'orden', header: 'Orden', ancho: '120px' },
    { campo: 'titulo', header: 'Logro' },
    {
      campo: 'estado', header: 'Estado', tipo: 'badge', ancho: '11%',
      formatear: (v) => (v === 'publicado' ? 'Publicado' : 'Borrador'),
      badgeSeveridad: (v) => (v === 'publicado' ? 'success' : 'secondary'),
    },
  ];

  acciones: AccionPersonalizada<LogroPortal>[] = [
    { id: 'publicar', titulo: 'Publicar: se verá en el portal', icono: 'eye', severidad: 'success', visible: (l) => l.estado === 'borrador' },
    { id: 'ocultar', titulo: 'Pasar a borrador: deja de verse en el portal', icono: 'eye_off', visible: (l) => l.estado === 'publicado' },
  ];

  form = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(140)]],
    categoria: ['', [Validators.required]],
    anio: [null as number | null, [Validators.min(1900), Validators.max(2100)]],
    nivel: ['', [Validators.maxLength(60)]],
    descripcion: ['', [Validators.maxLength(300)]],
    destacado: [false],
    fuenteEtiqueta: ['', [Validators.maxLength(120)]],
    fuenteUrl: ['', [Validators.pattern(HTTPS), Validators.maxLength(2048)]],
    aclaracion: ['', [Validators.maxLength(60)]],
    estado: ['borrador' as EstadoPortal],
  });

  override ngOnInit(): void {
    super.ngOnInit();
    this.cargarCabecera();
    this.cargarLogros();
  }

  ver(url: string): string {
    return urlParaVer(url);
  }

  /** «Música · 2011 · Banda Sinfónica · destacado», bajo el título en la tabla. */
  detalle(l: LogroPortal): string {
    return [this.nombreDeCategoria(l.categoria), l.anio ?? 'Sin año', l.nivel, l.destacado ? 'destacado' : null].filter(Boolean).join(' · ');
  }

  nombreDeCategoria(valor: string): string {
    return this.categorias.find((c) => c.valor === valor)?.nombre ?? valor;
  }

  // ───────── Cabecera ─────────

  cargarCabecera(): void {
    this.cargandoCabecera = true;
    this.portalWebService.getFicha<CabeceraSimple>('logros').subscribe({
      next: (res) => {
        this.versionCabecera = res.data.version;
        this.cabeceraGuardada = res.data.contenido ?? null;
        this.cargandoCabecera = false;
        this.resumirCabecera();
      },
      error: (err) => {
        this.cargandoCabecera = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la cabecera de Logros.'));
      },
    });
  }

  /** El modal empieza con lo guardado: lo que se canceló antes no queda a medias. */
  abrirCabecera(): void {
    const c = this.cabeceraGuardada;
    this.cabecera.reset({ titulo: c?.titulo ?? '', bajada: c?.bajada ?? '' });
    this.modalCabecera = true;
  }

  private resumirCabecera(): void {
    const c = this.cabeceraGuardada;
    this.alResumir('cabecera', c
      ? { estado: c.titulo, aviso: false, pendientes: [] }
      : { estado: 'Sin configurar: no se ve', aviso: true, pendientes: [{ cuantos: 1, texto: 'cabecera de Logros sin configurar' }] });
  }

  guardarCabecera(): void {
    if (this.cabecera.invalid) {
      this.cabecera.markAllAsTouched();
      enfocarPrimerError();
      return;
    }

    const v = this.cabecera.getRawValue();
    const datos = { titulo: v.titulo!, bajada: v.bajada || null };
    this.guardandoCabecera = true;
    this.portalWebService.guardarFicha<CabeceraSimple>('logros', datos, this.versionCabecera).subscribe({
      next: (res) => {
        this.guardandoCabecera = false;
        this.versionCabecera = res.data.version;
        this.cabeceraGuardada = datos as CabeceraSimple;
        this.modalCabecera = false;
        this.resumirCabecera();
        this.toastService.success('Cabecera guardada', 'El portal la mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoCabecera = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar la cabecera.');
      },
    });
  }

  invalidoCabecera(campo: string): boolean {
    const c = this.cabecera.get(campo);
    return !!c && c.invalid && c.touched;
  }

  // ───────── Logros ─────────

  cargarLogros(): void {
    this.cargandoLogros = true;
    this.portalWebService.getLista<LogroPortal>(RECURSOS.logros).subscribe({
      next: (res) => {
        this.logros = res.data;
        this.cargandoLogros = false;
        this.resumirLogros();
      },
      error: (err) => {
        this.cargandoLogros = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los logros.'));
      },
    });
  }

  get publicados(): number {
    return this.logros.filter((l) => l.estado === 'publicado').length;
  }

  /** Cada logro publicado necesita su fuente: los que no la tienen suben a Pendientes. */
  private resumirLogros(): void {
    const publicados = this.logros.filter((l) => l.estado === 'publicado');
    const sinFuente = publicados.filter((l) => !l.fuente).length;
    const borradores = this.logros.length - publicados.length;
    let estado = this.logros.length ? cuantos(publicados.length, 'publicado', 'publicados') : 'Sin logros';
    if (sinFuente) estado += ` · ${sinFuente} sin fuente`;
    else if (borradores) estado += ` · ${cuantos(borradores, 'borrador', 'borradores')}`;
    this.alResumir('logros', {
      estado,
      aviso: !publicados.length || sinFuente > 0,
      pendientes: sinFuente ? [{ cuantos: sinFuente, texto: sinFuente === 1 ? 'logro publicado sin fuente' : 'logros publicados sin fuente' }] : [],
    });
  }

  nuevo(): void {
    this.editando = null;
    this.foto = null;
    this.form.reset({ categoria: '', anio: null, destacado: false, estado: 'borrador' });
    this.paso = 0;
    this.modalVisible = true;
  }

  editar(logro: LogroPortal): void {
    this.editando = logro;
    this.foto = logro.imagen;
    this.form.reset({
      titulo: logro.titulo,
      categoria: logro.categoria,
      anio: logro.anio,
      nivel: logro.nivel ?? '',
      descripcion: logro.descripcion ?? '',
      destacado: logro.destacado,
      fuenteEtiqueta: logro.fuente?.etiqueta ?? '',
      fuenteUrl: logro.fuente?.url ?? '',
      aclaracion: logro.aclaracion ?? '',
      estado: logro.estado,
    });
    this.paso = 0;
    this.modalVisible = true;
  }

  cerrar(): void {
    this.modalVisible = false;
    this.editando = null;
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  /** Una dirección de fuente sin decir cuál es la fuente no sirve: el portal muestra la etiqueta. */
  get fuenteSinEtiqueta(): boolean {
    const v = this.form.getRawValue();
    return !!v.fuenteUrl && !v.fuenteEtiqueta;
  }

  /** Si el paso tiene algo por corregir, no avanza y el foco va al primer error. */
  siguiente(): void {
    const antes = this.paso;
    this.wizard?.siguiente();
    if (this.paso === antes) this.cuerpo?.irAlPrimerError();
  }

  /** Si algo falta, el wizard vuelve al primer paso con error (la fuente sin etiqueta es del segundo). */
  guardar(): void {
    const conError = this.pasos.findIndex((_, i) => !this.wizard?.validarPaso(i));
    if (conError >= 0 || this.form.invalid || this.fuenteSinEtiqueta) {
      this.form.markAllAsTouched();
      this.paso = conError >= 0 ? conError : 1;
      this.cuerpo?.irAlPrimerError();
      return;
    }

    const v = this.form.getRawValue();
    const datos = {
      titulo: v.titulo,
      categoria: v.categoria,
      anio: v.anio === null || (v.anio as unknown) === '' ? null : Number(v.anio),
      nivel: v.nivel || null,
      descripcion: v.descripcion || null,
      imagen_id: this.foto?.id ?? null,
      destacado: !!v.destacado,
      fuente: v.fuenteEtiqueta ? { etiqueta: v.fuenteEtiqueta, url: v.fuenteUrl || null } : null,
      aclaracion: v.aclaracion || null,
      estado: v.estado,
    };

    this.guardando = true;
    const peticion = this.editando
      ? this.portalWebService.editarEnLista<LogroPortal>(RECURSOS.logros, this.editando.id, datos)
      : this.portalWebService.crearEnLista<LogroPortal>(RECURSOS.logros, datos);

    peticion.subscribe({
      next: (res) => {
        this.guardando = false;
        this.toastService.success(
          this.editando ? 'Logro actualizado' : 'Logro agregado',
          res.data.estado === 'publicado' ? 'El portal lo mostrará en hasta un minuto.' : 'Quedó en borrador: publícalo cuando el colegio lo confirme.',
        );
        const editado = !!this.editando;
        this.cerrar();
        // Al editar basta con la fila que devolvió el servidor; al agregar, la lista entera.
        if (editado) this.cambiarFila(res.data);
        else this.cargarLogros();
      },
      error: (err: HttpErrorResponse) => {
        this.guardando = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar el logro.');
      },
    });
  }

  alAccionar(evento: { accion: string; fila: LogroPortal }): void {
    const estado: EstadoPortal = evento.accion === 'publicar' ? 'publicado' : 'borrador';

    this.portalWebService.cambiarEstadoEnLista<LogroPortal>(RECURSOS.logros, evento.fila.id, estado).subscribe({
      next: (res) => {
        this.toastService.success(estado === 'publicado' ? 'Logro publicado' : 'Logro en borrador', 'El portal lo reflejará en hasta un minuto.');
        this.cambiarFila(res.data);
      },
      error: (err: HttpErrorResponse) => mostrarErrorDelPanel(this.toastService, err, 'No se pudo cambiar el estado del logro.'),
    });
  }

  eliminar(logro: LogroPortal): void {
    this.confirmService.confirmarEliminar(`el logro «${logro.titulo}»`, () => {
      this.portalWebService.eliminarDeLista(RECURSOS.logros, logro.id).subscribe({
        next: () => {
          this.toastService.success('Logro eliminado', 'Ya no aparecerá en el portal.');
          this.cargarLogros();
        },
        error: (err: HttpErrorResponse) => mostrarErrorDelPanel(this.toastService, err, 'No se pudo eliminar el logro.'),
      });
    });
  }

  private cambiarFila(nueva: LogroPortal): void {
    this.logros = conFila(this.logros, nueva);
    this.resumirLogros();
  }

  /** Se mueve en pantalla en el momento; si el servidor no acepta el orden, vuelve atrás. */
  mover(logro: LogroPortal, direccion: -1 | 1): void {
    const ids = idsMovidos(this.logros.map((l) => l.id), logro.id, direccion);
    if (!ids) return;

    const antes = this.logros;
    this.logros = enOrden(antes, ids);
    this.ordenando = true;
    this.portalWebService.ordenarLista<LogroPortal>(RECURSOS.logros, ids).subscribe({
      next: (res) => {
        this.logros = res.data;
        this.ordenando = false;
        this.resumirLogros();
      },
      error: (err: HttpErrorResponse) => {
        this.logros = antes;
        this.ordenando = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo cambiar el orden.');
        this.cargarLogros();
      },
    });
  }

  esPrimero(l: LogroPortal): boolean {
    return this.logros[0]?.id === l.id;
  }

  esUltimo(l: LogroPortal): boolean {
    return this.logros[this.logros.length - 1]?.id === l.id;
  }
}
