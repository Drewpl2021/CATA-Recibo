import { Directive, OnInit, inject } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';

import { ConfirmService, ToastService } from '../../../core/services';
import { mensajeErrorApi } from '../../../core/utils';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { EstadoPortal } from '../portal-web.models';
import { PortalWebService } from '../portal-web.service';
import { idsMovidos, mostrarErrorDelPanel } from './panel';

/** Lo mínimo de una fila de cualquier lista del portal. */
export interface FilaDelPortal {
  id: string;
  estado: EstadoPortal;
}

/** Cómo se nombra un registro en los avisos, con su género: «proceso» / «fecha». */
export interface NombreDeRegistro {
  singular: string;
  femenino: boolean;
}

/** Publicar o pasar a borrador, las dos acciones de toda lista del portal. */
export function accionesDeEstado<T extends FilaDelPortal>(): AccionPersonalizada<T>[] {
  return [
    { id: 'publicar', titulo: 'Publicar: se verá en el portal', icono: 'eye', severidad: 'success', visible: (f) => f.estado === 'borrador' },
    { id: 'ocultar', titulo: 'Pasar a borrador: deja de verse en el portal', icono: 'eye_off', visible: (f) => f.estado === 'publicado' },
  ];
}

/**
 * Lo común a los paneles de una lista del portal (procesos, vacantes,
 * preguntas…): cargarla, publicar u ocultar, subir y bajar, eliminar, y
 * guardar lo del modal. Cada panel dice su recurso, cómo se llama un registro
 * y, al guardar, qué datos manda.
 *
 * Las pantallas de etapas anteriores (sedes, logros, cifras) hacen lo mismo
 * a mano; esto evita repetirlo en las listas que vienen.
 */
@Directive()
export abstract class PanelDeLista<T extends FilaDelPortal> implements OnInit {
  protected portalWebService = inject(PortalWebService);
  protected toastService = inject(ToastService);
  protected confirmService = inject(ConfirmService);

  /** El recurso de /web-portal (RECURSOS.…). */
  protected abstract readonly recurso: string;
  protected abstract readonly nombre: NombreDeRegistro;
  /** Cómo se nombra una fila al pedir confirmación para eliminarla: «la pregunta «¿…?»». */
  protected abstract describir(fila: T): string;

  filas: T[] = [];
  cargando = true;
  ordenando = false;
  modalVisible = false;
  guardando = false;
  editando: T | null = null;

  readonly acciones = accionesDeEstado<T>();

  ngOnInit(): void {
    this.cargar();
  }

  get publicados(): number {
    return this.filas.filter((f) => f.estado === 'publicado').length;
  }

  private get Nombre(): string {
    return this.nombre.singular.charAt(0).toUpperCase() + this.nombre.singular.slice(1);
  }

  private get el(): string {
    return this.nombre.femenino ? 'la' : 'el';
  }

  private get lo(): string {
    return this.nombre.femenino ? 'la' : 'lo';
  }

  /** «publicado» o «publicada», según el registro. */
  protected concuerda(masculino: string): string {
    return this.nombre.femenino ? masculino.replace(/o$/, 'a') : masculino;
  }

  cargar(): void {
    this.cargando = true;
    this.portalWebService.getLista<T>(this.recurso, this.filtro()).subscribe({
      next: (res) => {
        this.filas = res.data;
        this.cargando = false;
        this.alCargar();
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, `No se pudo cargar la lista: ${this.nombre.singular}.`));
      },
    });
  }

  /** Después de cada carga de la lista (para avisar a otro panel, por ejemplo). */
  protected alCargar(): void {}

  /** Para una lista que vive dentro de otra (los ciclos de una universidad): { universidad: id }. */
  protected filtro(): Record<string, string> {
    return {};
  }

  cerrar(): void {
    this.modalVisible = false;
    this.editando = null;
  }

  /** Crea o edita (según `editando`) con lo que arma el panel; si sale bien, cierra y recarga. */
  protected enviar(datos: object): void {
    const editando = this.editando;
    this.guardando = true;

    const peticion = editando
      ? this.portalWebService.editarEnLista<T>(this.recurso, editando.id, datos)
      : this.portalWebService.crearEnLista<T>(this.recurso, datos);

    peticion.subscribe({
      next: (res) => {
        this.guardando = false;
        this.toastService.success(
          `${this.Nombre} ${this.concuerda(editando ? 'actualizado' : 'agregado')}`,
          // Sin estado (las categorías) se ve igual que publicado.
          res.data.estado !== 'borrador'
            ? `El portal ${this.lo} mostrará en hasta un minuto.`
            : `Quedó en borrador: ${this.nombre.femenino ? 'publícala' : 'publícalo'} cuando esté ${this.concuerda('listo')}.`,
        );
        this.cerrar();
        this.cargar();
      },
      error: (err: HttpErrorResponse) => {
        this.guardando = false;
        mostrarErrorDelPanel(this.toastService, err, `No se pudo guardar ${this.el} ${this.nombre.singular}.`);
      },
    });
  }

  alAccionar(evento: { accion: string; fila: T }): void {
    const estado: EstadoPortal = evento.accion === 'publicar' ? 'publicado' : 'borrador';

    this.portalWebService.cambiarEstadoEnLista<T>(this.recurso, evento.fila.id, estado).subscribe({
      next: () => {
        this.toastService.success(
          estado === 'publicado' ? `${this.Nombre} ${this.concuerda('publicado')}` : `${this.Nombre} en borrador`,
          `El portal ${this.lo} reflejará en hasta un minuto.`,
        );
        this.cargar();
      },
      error: (err: HttpErrorResponse) => mostrarErrorDelPanel(this.toastService, err, `No se pudo cambiar el estado de ${this.el} ${this.nombre.singular}.`),
    });
  }

  eliminar(fila: T): void {
    this.confirmService.confirmarEliminar(this.describir(fila), () => {
      this.portalWebService.eliminarDeLista(this.recurso, fila.id).subscribe({
        next: () => {
          this.toastService.success(`${this.Nombre} ${this.concuerda('eliminado')}`, 'Ya no aparecerá en el portal.');
          this.cargar();
        },
        error: (err: HttpErrorResponse) => mostrarErrorDelPanel(this.toastService, err, `No se pudo eliminar ${this.el} ${this.nombre.singular}.`),
      });
    });
  }

  mover(fila: T, direccion: -1 | 1): void {
    const ids = idsMovidos(this.filas.map((f) => f.id), fila.id, direccion);
    if (!ids) return;

    this.ordenando = true;
    this.portalWebService.ordenarLista<T>(this.recurso, ids, this.filtro()).subscribe({
      next: (res) => {
        this.filas = res.data;
        this.ordenando = false;
      },
      error: (err: HttpErrorResponse) => {
        this.ordenando = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo cambiar el orden.');
        this.cargar();
      },
    });
  }

  esPrimero(fila: T): boolean {
    return this.filas[0]?.id === fila.id;
  }

  esUltimo(fila: T): boolean {
    return this.filas[this.filas.length - 1]?.id === fila.id;
  }

  /** Para las columnas de estado de la tabla. */
  protected columnaDeEstado(): ColumnaTabla<T> {
    return {
      campo: 'estado', header: 'Estado', tipo: 'badge', ancho: '12%',
      formatear: (v: EstadoPortal) => (v === 'publicado' ? this.concuerda('Publicado') : 'Borrador'),
      badgeSeveridad: (v: EstadoPortal) => (v === 'publicado' ? 'success' : 'secondary'),
    };
  }

  /** La columna de las flechas para subir y bajar (su celda la pone la plantilla). */
  protected columnaDeOrden(): ColumnaTabla<T> {
    return { campo: 'orden', header: 'Orden', ancho: '120px' };
  }
}
