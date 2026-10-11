import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { Router } from '@angular/router';

import { ConfirmService, ToastService } from '../../../core/services';
import { mensajeErrorApi } from '../../../core/utils';
import { CeldaTablaDirective } from '../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { EditorCifrasComponent } from '../compartido/editor-cifras/editor-cifras.component';
import { idsMovidos, mostrarErrorDelPanel } from '../compartido/panel';
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
 */
@Component({
  selector: 'app-portal-web-logros',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    PageHeaderComponent, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent,
    SelectorImagenComponent, EditorCifrasComponent,
  ],
  templateUrl: './portal-web-logros.component.html',
})
export class PortalWebLogrosComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);
  private router = inject(Router);

  readonly categorias = CATEGORIAS_DE_LOGRO;
  readonly anioActual = new Date().getFullYear();

  // ───────── Cabecera ─────────

  cargandoCabecera = true;
  guardandoCabecera = false;
  private versionCabecera: string | null = null;

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

  columnas: ColumnaTabla<LogroPortal>[] = [
    { campo: 'orden', header: 'Orden', ancho: '120px' },
    { campo: 'titulo', header: 'Logro' },
    { campo: 'categoria', header: 'Categoría', ancho: '12%', formatear: (v) => this.nombreDeCategoria(v) },
    { campo: 'anio', header: 'Año', ancho: '8%', formatear: (v) => (v ?? 'Sin año') },
    {
      campo: 'destacado', header: 'Destacado', tipo: 'badge', ancho: '10%',
      formatear: (v) => (v ? 'Sí' : 'No'),
      badgeSeveridad: (v) => (v ? 'info' : 'secondary'),
    },
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

  ngOnInit(): void {
    this.cargarCabecera();
    this.cargarLogros();
  }

  volver(): void {
    this.router.navigate(['/inicio/portal-web']);
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
        this.cabecera.reset({ titulo: res.data.contenido?.titulo ?? '', bajada: res.data.contenido?.bajada ?? '' });
        this.cargandoCabecera = false;
      },
      error: (err) => {
        this.cargandoCabecera = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la cabecera de Logros.'));
      },
    });
  }

  guardarCabecera(): void {
    if (this.cabecera.invalid) {
      this.cabecera.markAllAsTouched();
      return;
    }

    const v = this.cabecera.getRawValue();
    this.guardandoCabecera = true;
    this.portalWebService.guardarFicha<CabeceraSimple>('logros', { titulo: v.titulo, bajada: v.bajada || null }, this.versionCabecera).subscribe({
      next: (res) => {
        this.guardandoCabecera = false;
        this.versionCabecera = res.data.version;
        this.cabecera.markAsPristine();
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

  nuevo(): void {
    this.editando = null;
    this.foto = null;
    this.form.reset({ categoria: '', anio: null, destacado: false, estado: 'borrador' });
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

  guardar(): void {
    if (this.form.invalid || this.fuenteSinEtiqueta) {
      this.form.markAllAsTouched();
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
        this.cerrar();
        this.cargarLogros();
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
      next: () => {
        this.toastService.success(estado === 'publicado' ? 'Logro publicado' : 'Logro en borrador', 'El portal lo reflejará en hasta un minuto.');
        this.cargarLogros();
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

  mover(logro: LogroPortal, direccion: -1 | 1): void {
    const ids = idsMovidos(this.logros.map((l) => l.id), logro.id, direccion);
    if (!ids) return;

    this.ordenando = true;
    this.portalWebService.ordenarLista<LogroPortal>(RECURSOS.logros, ids).subscribe({
      next: (res) => {
        this.logros = res.data;
        this.ordenando = false;
      },
      error: (err: HttpErrorResponse) => {
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
