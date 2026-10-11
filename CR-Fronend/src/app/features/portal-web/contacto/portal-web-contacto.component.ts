import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormGroup, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
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
import { idsMovidos, mostrarErrorDelPanel } from '../compartido/panel';
import { SelectorImagenComponent } from '../compartido/selector-imagen/selector-imagen.component';
import {
  ContactoParaGuardar,
  ContactoPortal,
  EstadoPortal,
  ImagenPortal,
  PaginaConFormulario,
  SedePortal,
  SolicitudPortal,
} from '../portal-web.models';
import { PortalWebService, RECURSOS } from '../portal-web.service';

const TELEFONO_E164 = /^\+\d{8,15}$/;
const HTTPS = /^https:\/\//;
/** Lo único que el portal acepta para insertar un mapa (Google Maps u OpenStreetMap). */
const MAPA_INSERTABLE = /^https:\/\/(www\.google\.com\/maps(\/embed)?\?|www\.openstreetmap\.org\/export\/embed\.html\?)/;
/** En las plantillas de WhatsApp solo valen estas dos marcas. */
const MARCAS = /\{([^}]*)\}/g;

/**
 * Panel «Portal Web» › Contacto: la cabecera de la página (con el recorte de
 * un estudiante y el correo general), las sedes con su foto, horario y mapa,
 * y los textos del formulario «Solicitar información», que también aparece
 * al final de Matrícula.
 *
 * El backend vuelve a revisar todo y, antes de guardar, comprueba que el
 * portal pueda mostrar lo que queda.
 */
@Component({
  selector: 'app-portal-web-contacto',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, FormsModule,
    PageHeaderComponent, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent, SelectorImagenComponent,
  ],
  templateUrl: './portal-web-contacto.component.html',
})
export class PortalWebContactoComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);
  private router = inject(Router);

  // ───────── Cabecera ─────────

  cargandoCabecera = true;
  guardandoCabecera = false;
  private versionCabecera: string | null = null;
  figura: ImagenPortal | null = null;
  figuraCambiada = false;

  cabecera = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    bajada: ['', [Validators.maxLength(160)]],
    correo: ['', [Validators.email, Validators.maxLength(80)]],
  });

  // ───────── Formulario «Solicitar información» ─────────

  cargandoSolicitud = true;
  guardandoSolicitud = false;
  private versionSolicitud: string | null = null;

  solicitud = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(40)]],
    texto: ['', [Validators.maxLength(200)]],
    mensajeWhatsapp: ['', [Validators.required, Validators.maxLength(300)]],
    mensajeMatricula: ['', [Validators.maxLength(300)]],
    mensajeContacto: ['', [Validators.maxLength(300)]],
  });

  // ───────── Sedes ─────────

  sedes: SedePortal[] = [];
  cargandoSedes = true;
  ordenando = false;
  modalSedeVisible = false;
  guardandoSede = false;
  sedeEditando: SedePortal | null = null;
  fotoSede: ImagenPortal | null = null;

  columnasSedes: ColumnaTabla<SedePortal>[] = [
    { campo: 'orden', header: 'Orden', ancho: '120px' },
    { campo: 'nombre', header: 'Sede', ancho: '22%' },
    { campo: 'direccion', header: 'Dirección' },
    { campo: 'telefono', header: 'Teléfono', ancho: '14%', formatear: (t) => t?.visible ?? '—' },
    { campo: 'mapa_embed_url', header: 'Mapa', ancho: '10%', formatear: (m) => (m ? 'Sí' : 'Sin mapa') },
    {
      campo: 'estado', header: 'Estado', tipo: 'badge', ancho: '12%',
      formatear: (v) => (v === 'publicado' ? 'Publicada' : 'Borrador'),
      badgeSeveridad: (v) => (v === 'publicado' ? 'success' : 'secondary'),
    },
  ];

  accionesSedes: AccionPersonalizada<SedePortal>[] = [
    { id: 'publicar', titulo: 'Publicar: se verá en el portal', icono: 'eye', severidad: 'success', visible: (s) => s.estado === 'borrador' },
    { id: 'ocultar', titulo: 'Pasar a borrador: deja de verse en el portal', icono: 'eye_off', visible: (s) => s.estado === 'publicado' },
  ];

  formSede = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(40)]],
    direccion: ['', [Validators.required, Validators.maxLength(90)]],
    referencia: ['', [Validators.maxLength(100)]],
    distrito: ['', [Validators.maxLength(80)]],
    telefonoNumero: ['', [Validators.pattern(TELEFONO_E164)]],
    telefonoVisible: ['', [Validators.maxLength(20)]],
    nota: ['', [Validators.maxLength(160)]],
    correo: ['', [Validators.email, Validators.maxLength(80)]],
    mapaUrl: ['', [Validators.pattern(HTTPS)]],
    mapaEmbedUrl: ['', [Validators.pattern(MAPA_INSERTABLE)]],
    horario: this.fb.array<FormGroup>([]),
    estado: ['borrador' as EstadoPortal],
  });

  ngOnInit(): void {
    this.cargarCabecera();
    this.cargarSolicitud();
    this.cargarSedes();
  }

  volver(): void {
    this.router.navigate(['/inicio/portal-web']);
  }

  // ───────── Cabecera ─────────

  cargarCabecera(): void {
    this.cargandoCabecera = true;
    this.portalWebService.getFicha<ContactoPortal>('contacto').subscribe({
      next: (res) => {
        const c = res.data.contenido;
        this.versionCabecera = res.data.version;
        this.cabecera.reset({ titulo: c?.titulo ?? '', bajada: c?.bajada ?? '', correo: c?.correo ?? '' });
        this.figura = c?.figura ? res.data.imagenes[c.figura.imagenId] ?? null : null;
        this.figuraCambiada = false;
        this.cargandoCabecera = false;
      },
      error: (err) => {
        this.cargandoCabecera = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la cabecera de Contacto.'));
      },
    });
  }

  alCambiarFigura(imagen: ImagenPortal | null): void {
    this.figura = imagen;
    this.figuraCambiada = true;
  }

  get cabeceraConCambios(): boolean {
    return this.cabecera.dirty || this.figuraCambiada;
  }

  guardarCabecera(): void {
    if (this.cabecera.invalid) {
      this.cabecera.markAllAsTouched();
      return;
    }

    const v = this.cabecera.getRawValue();
    const datos: ContactoParaGuardar = {
      titulo: v.titulo!, bajada: v.bajada || null, figura: this.figura?.id ?? null, correo: v.correo || null,
    };

    this.guardandoCabecera = true;
    this.portalWebService.guardarFicha<ContactoPortal>('contacto', datos, this.versionCabecera).subscribe({
      next: (res) => {
        this.guardandoCabecera = false;
        this.versionCabecera = res.data.version;
        this.cabecera.markAsPristine();
        this.figuraCambiada = false;
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

  // ───────── Formulario «Solicitar información» ─────────

  cargarSolicitud(): void {
    this.cargandoSolicitud = true;
    this.portalWebService.getFicha<SolicitudPortal>('solicitud').subscribe({
      next: (res) => {
        const s = res.data.contenido;
        const propio = (p: PaginaConFormulario) => s?.mensajesPorPagina.find((m) => m.pagina === p)?.mensaje ?? '';
        this.versionSolicitud = res.data.version;
        this.solicitud.reset({
          titulo: s?.titulo ?? '',
          texto: s?.texto ?? '',
          mensajeWhatsapp: s?.mensajeWhatsapp ?? '',
          mensajeMatricula: propio('matricula'),
          mensajeContacto: propio('contacto'),
        });
        this.cargandoSolicitud = false;
      },
      error: (err) => {
        this.cargandoSolicitud = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los textos del formulario.'));
      },
    });
  }

  guardarSolicitud(): void {
    if (this.solicitud.invalid || this.marcasDesconocidas().length) {
      this.solicitud.markAllAsTouched();
      return;
    }

    const v = this.solicitud.getRawValue();
    const mensajesPorPagina: SolicitudPortal['mensajesPorPagina'] = [];
    if (v.mensajeMatricula?.trim()) mensajesPorPagina.push({ pagina: 'matricula', mensaje: v.mensajeMatricula.trim() });
    if (v.mensajeContacto?.trim()) mensajesPorPagina.push({ pagina: 'contacto', mensaje: v.mensajeContacto.trim() });

    const datos: SolicitudPortal = {
      titulo: v.titulo!, texto: v.texto || null, mensajeWhatsapp: v.mensajeWhatsapp!, mensajesPorPagina,
    };

    this.guardandoSolicitud = true;
    this.portalWebService.guardarFicha<SolicitudPortal>('solicitud', datos, this.versionSolicitud).subscribe({
      next: (res) => {
        this.guardandoSolicitud = false;
        this.versionSolicitud = res.data.version;
        this.solicitud.markAsPristine();
        this.toastService.success('Textos guardados', 'El formulario de Matrícula y Contacto los usará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoSolicitud = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudieron guardar los textos del formulario.');
      },
    });
  }

  invalidoSolicitud(campo: string): boolean {
    const c = this.solicitud.get(campo);
    return !!c && c.invalid && c.touched;
  }

  /** Las marcas entre llaves que el portal no reemplaza (solo valen {nombre} y {grado}). */
  marcasDesconocidas(texto?: string | null): string[] {
    const textos = texto !== undefined ? [texto] : Object.values(this.solicitud.getRawValue());
    return textos.flatMap((t) => [...String(t ?? '').matchAll(MARCAS)].map((m) => m[1]).filter((m) => m !== 'nombre' && m !== 'grado'));
  }

  /** Cómo le llegaría el mensaje al colegio, con datos de ejemplo. */
  ejemploDe(plantilla: string | null | undefined): string {
    return String(plantilla ?? '').replaceAll('{nombre}', 'María Quispe').replaceAll('{grado}', '1.er grado de primaria');
  }

  // ───────── Sedes ─────────

  cargarSedes(): void {
    this.cargandoSedes = true;
    this.portalWebService.getLista<SedePortal>(RECURSOS.sedes).subscribe({
      next: (res) => {
        this.sedes = res.data;
        this.cargandoSedes = false;
      },
      error: (err) => {
        this.cargandoSedes = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar las sedes.'));
      },
    });
  }

  get horario(): FormArray {
    return this.formSede.controls.horario as FormArray;
  }

  filaHorario(dias = '', horas = ''): FormGroup {
    return this.fb.group({
      dias: [dias, [Validators.required, Validators.maxLength(40)]],
      horas: [horas, [Validators.required, Validators.maxLength(40)]],
    });
  }

  agregarHorario(): void {
    if (this.horario.length < 4) this.horario.push(this.filaHorario());
  }

  quitarHorario(i: number): void {
    this.horario.removeAt(i);
  }

  nuevaSede(): void {
    this.sedeEditando = null;
    this.fotoSede = null;
    this.formSede.reset({ estado: 'borrador' });
    this.horario.clear();
    this.modalSedeVisible = true;
  }

  editarSede(sede: SedePortal): void {
    this.sedeEditando = sede;
    this.fotoSede = sede.imagen;
    this.formSede.reset({
      nombre: sede.nombre,
      direccion: sede.direccion,
      referencia: sede.referencia ?? '',
      distrito: sede.distrito ?? '',
      telefonoNumero: sede.telefono?.numero ?? '',
      telefonoVisible: sede.telefono?.visible ?? '',
      nota: sede.nota ?? '',
      correo: sede.correo ?? '',
      mapaUrl: sede.mapa_url ?? '',
      mapaEmbedUrl: sede.mapa_embed_url ?? '',
      estado: sede.estado,
    });
    this.horario.clear();
    sede.horario.forEach((f) => this.horario.push(this.filaHorario(f.dias, f.horas)));
    this.modalSedeVisible = true;
  }

  cerrarModalSede(): void {
    this.modalSedeVisible = false;
    this.sedeEditando = null;
  }

  invalidoSede(campo: string): boolean {
    const c = this.formSede.get(campo);
    return !!c && c.invalid && c.touched;
  }

  /** Con teléfono van los dos datos; sin él, ninguno. */
  get telefonoIncompleto(): boolean {
    const v = this.formSede.getRawValue();
    return !!v.telefonoNumero !== !!v.telefonoVisible;
  }

  /**
   * Google Maps › Compartir › Insertar un mapa da un <iframe> entero. Si se
   * pega así, se toma solo su dirección (src), que es lo que pide el portal.
   */
  alPegarMapa(): void {
    const control = this.formSede.controls.mapaEmbedUrl;
    const valor = control.value ?? '';
    const src = valor.match(/<iframe[^>]*\ssrc=["']([^"']+)["']/i)?.[1];
    if (src) control.setValue(src.replaceAll('&amp;', '&'));
  }

  guardarSede(): void {
    if (this.formSede.invalid || this.telefonoIncompleto) {
      this.formSede.markAllAsTouched();
      return;
    }

    const v = this.formSede.getRawValue();
    const datos = {
      nombre: v.nombre,
      direccion: v.direccion,
      referencia: v.referencia || null,
      distrito: v.distrito || null,
      telefono: v.telefonoNumero ? { numero: v.telefonoNumero, visible: v.telefonoVisible } : null,
      nota: v.nota || null,
      imagen_id: this.fotoSede?.id ?? null,
      correo: v.correo || null,
      mapa_url: v.mapaUrl || null,
      mapa_embed_url: v.mapaEmbedUrl || null,
      horario: v.horario,
      estado: v.estado,
    };

    this.guardandoSede = true;
    const peticion = this.sedeEditando
      ? this.portalWebService.editarEnLista<SedePortal>(RECURSOS.sedes, this.sedeEditando.id, datos)
      : this.portalWebService.crearEnLista<SedePortal>(RECURSOS.sedes, datos);

    peticion.subscribe({
      next: (res) => {
        this.guardandoSede = false;
        this.toastService.success(
          this.sedeEditando ? 'Sede actualizada' : 'Sede agregada',
          res.data.estado === 'publicado' ? 'El portal la mostrará en hasta un minuto.' : 'Quedó en borrador: publícala cuando esté lista.',
        );
        this.cerrarModalSede();
        this.cargarSedes();
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoSede = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar la sede.');
      },
    });
  }

  alAccionarSede(evento: { accion: string; fila: SedePortal }): void {
    const estado: EstadoPortal = evento.accion === 'publicar' ? 'publicado' : 'borrador';

    this.portalWebService.cambiarEstadoEnLista<SedePortal>(RECURSOS.sedes, evento.fila.id, estado).subscribe({
      next: () => {
        this.toastService.success(
          estado === 'publicado' ? 'Sede publicada' : 'Sede en borrador',
          `${evento.fila.nombre} ${estado === 'publicado' ? 'se verá' : 'dejará de verse'} en el portal en hasta un minuto.`,
        );
        this.cargarSedes();
      },
      error: (err: HttpErrorResponse) => mostrarErrorDelPanel(this.toastService, err, 'No se pudo cambiar el estado de la sede.'),
    });
  }

  eliminarSede(sede: SedePortal): void {
    this.confirmService.confirmarEliminar(`la sede «${sede.nombre}» del portal`, () => {
      this.portalWebService.eliminarDeLista(RECURSOS.sedes, sede.id).subscribe({
        next: () => {
          this.toastService.success('Sede eliminada', `${sede.nombre} ya no aparecerá en el portal.`);
          this.cargarSedes();
        },
        error: (err: HttpErrorResponse) => mostrarErrorDelPanel(this.toastService, err, 'No se pudo eliminar la sede.'),
      });
    });
  }

  moverSede(sede: SedePortal, direccion: -1 | 1): void {
    const ids = idsMovidos(this.sedes.map((s) => s.id), sede.id, direccion);
    if (!ids) return;

    this.ordenando = true;
    this.portalWebService.ordenarLista<SedePortal>(RECURSOS.sedes, ids).subscribe({
      next: (res) => {
        this.sedes = res.data;
        this.ordenando = false;
      },
      error: (err: HttpErrorResponse) => {
        this.ordenando = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo cambiar el orden.');
        this.cargarSedes();
      },
    });
  }

  esPrimera(sede: SedePortal): boolean {
    return this.sedes[0]?.id === sede.id;
  }

  esUltima(sede: SedePortal): boolean {
    return this.sedes[this.sedes.length - 1]?.id === sede.id;
  }
}
