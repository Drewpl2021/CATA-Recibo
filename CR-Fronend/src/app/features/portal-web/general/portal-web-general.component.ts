import { Component, OnInit, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
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
import { IndiceDeSeccionesComponent } from '../compartido/indice-de-secciones/indice-de-secciones.component';
import { PantallaConIndice, SeccionDelIndice } from '../compartido/pantalla-con-indice';
import { conFila, enfocarPrimerError, enOrden, idsMovidos, mostrarErrorDelPanel } from '../compartido/panel';
import { cuantos } from '../compartido/resumen-de-seccion';
import { EstadoPortal, REDES_DEL_PORTAL, RedSocialPortal, SitioPortal } from '../portal-web.models';
import { PortalWebService } from '../portal-web.service';

/** E.164, como lo exige el contrato del portal: +51 y el número, sin espacios. */
const TELEFONO_E164 = /^\+\d{8,15}$/;
const HTTPS = /^https:\/\//;

/**
 * Panel «Portal Web» › General: lo que aparece en todas las páginas del
 * portal. Los datos del colegio (la ficha `sitio`) y sus redes sociales.
 *
 * Los largos máximos son los del contrato del portal; el backend los vuelve a
 * revisar y, antes de guardar, comprueba que el portal pueda mostrar lo que
 * queda. Si no, no guarda y dice por qué.
 *
 * Con el molde de Ajustes: en el índice, las cuatro partes de los datos del
 * colegio y las redes. Cada parte se lee en su sección y la ficha se edita
 * entera en un wizard de cuatro pasos (como el alta de personal), que se
 * abre en el paso de la parte elegida.
 */
@Component({
  selector: 'app-portal-web-general',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    PageHeaderComponent, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent,
    WizardComponent, CuerpoDesplazableComponent, IndiceDeSeccionesComponent,
  ],
  templateUrl: './portal-web-general.component.html',
  styleUrls: ['../compartido/pw-estilos.scss'],
})
export class PortalWebGeneralComponent extends PantallaConIndice implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  /** Las partes de la ficha `sitio`, en el orden de los pasos del wizard. */
  readonly partesDelSitio = [
    { id: 'colegio', nombre: 'El colegio', icono: 'domain', ayuda: 'El nombre y los datos que van en la cabecera y el pie de todas las páginas.' },
    { id: 'telefonos', nombre: 'Teléfonos', icono: 'phone', ayuda: 'Para llamar y para el botón de WhatsApp, que recibe las solicitudes de información.' },
    { id: 'direccion', nombre: 'Dirección', icono: 'location_on', ayuda: 'La de la sede principal, en el pie de todas las páginas.' },
    { id: 'plataforma', nombre: 'Plataforma académica', icono: 'graduation_cap', ayuda: 'El botón que lleva al inicio de sesión de la plataforma, en otra pestaña.' },
  ];

  readonly secciones: SeccionDelIndice[] = [
    ...this.partesDelSitio.map(({ id, nombre, icono }) => ({ id, nombre, icono })),
    { id: 'redes', nombre: 'Redes sociales', icono: 'people' },
  ];

  readonly pasosSitio: PasoWizard[] = [
    {
      id: 'colegio', titulo: 'El colegio', icono: 'domain',
      campos: ['nombre', 'nombreCorto', 'nombreLinea1', 'nombreLinea2', 'ciudad', 'anioFundacion', 'subtitulo', 'promotora'],
    },
    { id: 'telefonos', titulo: 'Teléfonos', icono: 'phone', campos: ['telefono.numero', 'telefono.visible', 'whatsapp.numero', 'whatsapp.visible'] },
    { id: 'direccion', titulo: 'Dirección', icono: 'location_on', campos: ['direccion.linea', 'direccion.referencia', 'direccion.distrito'] },
    { id: 'plataforma', titulo: 'Plataforma', icono: 'graduation_cap', campos: ['portalAcademico.nombre', 'portalAcademico.url'] },
  ];

  @ViewChild('wizard') private wizard?: WizardComponent;
  @ViewChild('cuerpo') private cuerpo?: CuerpoDesplazableComponent;

  readonly anioActual = new Date().getFullYear();

  // ───────── Datos del colegio ─────────

  cargandoSitio = true;
  guardandoSitio = false;
  modalSitio = false;
  pasoSitio = 0;
  /** Lo guardado: lo que se lee en cada sección. null mientras la ficha no exista. */
  sitioGuardado: SitioPortal | null = null;
  /** La versión leída: se manda al guardar para no pisar lo que otro guardó. */
  private versionSitio: string | null = null;
  /** Errores que devolvió el backend, por campo ('telefono.numero'). */
  erroresServidor: Record<string, string> = {};

  sitio = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(80)]],
    nombreCorto: ['', [Validators.required, Validators.maxLength(20)]],
    // El nombre junto a la sigla en la cabecera del portal, en una o dos líneas (contrato 2.13).
    nombreLinea1: ['', [Validators.required, Validators.maxLength(30)]],
    nombreLinea2: ['', [Validators.maxLength(30)]],
    subtitulo: ['', [Validators.maxLength(90)]],
    ciudad: ['', [Validators.required, Validators.maxLength(40)]],
    anioFundacion: [null as number | null, [Validators.required, Validators.min(1800), Validators.max(this.anioActual)]],
    promotora: ['', [Validators.maxLength(120)]],
    telefono: this.fb.group({
      numero: ['', [Validators.required, Validators.pattern(TELEFONO_E164)]],
      visible: ['', [Validators.required, Validators.maxLength(20)]],
    }),
    whatsapp: this.fb.group({
      numero: ['', [Validators.required, Validators.pattern(TELEFONO_E164)]],
      visible: ['', [Validators.required, Validators.maxLength(20)]],
    }),
    direccion: this.fb.group({
      linea: ['', [Validators.required, Validators.maxLength(80)]],
      referencia: ['', [Validators.maxLength(100)]],
      distrito: ['', [Validators.maxLength(80)]],
    }),
    conPlataforma: [true],
    portalAcademico: this.fb.group({
      nombre: ['', [Validators.maxLength(20)]],
      url: ['', [Validators.pattern(HTTPS), Validators.maxLength(255)]],
    }),
  });

  // ───────── Redes sociales ─────────

  redes: RedSocialPortal[] = [];
  cargandoRedes = true;
  ordenando = false;
  modalRedVisible = false;
  guardandoRed = false;
  redEditando: RedSocialPortal | null = null;

  columnasRedes: ColumnaTabla<RedSocialPortal>[] = [
    { campo: 'orden', header: 'Orden', ancho: '120px' },
    { campo: 'red', header: 'Red' },
    { campo: 'url', header: 'Dirección', romperTexto: true },
    {
      campo: 'estado', header: 'Estado', tipo: 'badge', ancho: '12%',
      formatear: (v) => (v === 'publicado' ? 'Publicada' : 'Borrador'),
      badgeSeveridad: (v) => (v === 'publicado' ? 'success' : 'secondary'),
    },
  ];

  accionesRedes: AccionPersonalizada<RedSocialPortal>[] = [
    { id: 'publicar', titulo: 'Publicar: se verá en el portal', icono: 'eye', severidad: 'success', visible: (r) => r.estado === 'borrador' },
    { id: 'ocultar', titulo: 'Pasar a borrador: deja de verse en el portal', icono: 'eye_off', visible: (r) => r.estado === 'publicado' },
  ];

  formRed = this.fb.group({
    red: ['', [Validators.required]],
    cuenta: ['', [Validators.required, Validators.maxLength(60)]],
    url: ['', [Validators.required, Validators.pattern(HTTPS), Validators.maxLength(255)]],
    aclaracion: ['', [Validators.maxLength(80)]],
    estado: ['borrador' as EstadoPortal],
  });

  override ngOnInit(): void {
    super.ngOnInit();
    this.cargarSitio();
    this.cargarRedes();

    // Sin plataforma académica, sus dos datos dejan de ser obligatorios.
    this.sitio.controls.conPlataforma.valueChanges.subscribe(() => this.ajustarPlataforma());
  }

  // ───────── Datos del colegio ─────────

  cargarSitio(): void {
    this.cargandoSitio = true;
    this.portalWebService.getFicha<SitioPortal>('sitio').subscribe({
      next: (res) => {
        this.versionSitio = res.data.version;
        this.sitioGuardado = res.data.contenido ?? null;
        this.cargandoSitio = false;
        this.resumirSitio();
      },
      error: (err) => {
        this.cargandoSitio = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los datos del colegio.'));
      },
    });
  }

  /** El formulario con lo guardado: al abrir el wizard, lo que se canceló antes no queda a medias. */
  private llenarSitio(c: SitioPortal | null): void {
    this.sitio.reset({
      nombre: c?.nombre ?? '',
      nombreCorto: c?.nombreCorto ?? '',
      nombreLinea1: c?.nombreEnLineas?.[0] ?? '',
      nombreLinea2: c?.nombreEnLineas?.[1] ?? '',
      subtitulo: c?.subtitulo ?? '',
      ciudad: c?.ciudad ?? '',
      anioFundacion: c?.anioFundacion ?? null,
      promotora: c?.promotora ?? '',
      telefono: { numero: c?.telefono?.numero ?? '', visible: c?.telefono?.visible ?? '' },
      whatsapp: { numero: c?.whatsapp?.numero ?? '', visible: c?.whatsapp?.visible ?? '' },
      direccion: {
        linea: c?.direccion?.linea ?? '',
        referencia: c?.direccion?.referencia ?? '',
        distrito: c?.direccion?.distrito ?? '',
      },
      conPlataforma: c ? !!c.portalAcademico : true,
      portalAcademico: { nombre: c?.portalAcademico?.nombre ?? '', url: c?.portalAcademico?.url ?? '' },
    });
    this.ajustarPlataforma();
    this.erroresServidor = {};
  }

  abrirSitio(paso: number): void {
    this.llenarSitio(this.sitioGuardado);
    this.pasoSitio = paso;
    this.modalSitio = true;
  }

  /** La línea del índice de cada parte y, si la ficha no existe, el pendiente. */
  private resumirSitio(): void {
    const s = this.sitioGuardado;
    if (!s) {
      this.partesDelSitio.forEach((p, i) => this.alResumir(p.id, {
        estado: 'Sin configurar', aviso: true,
        pendientes: i === 0 ? [{ cuantos: 1, texto: 'datos del colegio sin configurar: el portal no muestra su cabecera ni su pie' }] : [],
      }));
      return;
    }

    this.alResumir('colegio', { estado: `${s.nombreCorto} · ${s.ciudad}`, aviso: false, pendientes: [] });
    this.alResumir('telefonos', { estado: `${s.telefono.visible} · WhatsApp ${s.whatsapp.visible}`, aviso: false, pendientes: [] });
    this.alResumir('direccion', { estado: s.direccion.linea, aviso: false, pendientes: [] });
    this.alResumir('plataforma', { estado: s.portalAcademico ? `Botón «${s.portalAcademico.nombre}»` : 'Sin botón', aviso: false, pendientes: [] });
  }

  private resumirRedes(): void {
    const publicadas = this.redes.filter((r) => r.estado === 'publicado').length;
    const borradores = this.redes.length - publicadas;
    this.alResumir('redes', {
      estado: publicadas
        ? cuantos(publicadas, 'publicada', 'publicadas') + (borradores ? ` · ${cuantos(borradores, 'borrador', 'borradores')}` : '')
        : 'Ninguna publicada: no se ven',
      aviso: !publicadas,
      pendientes: borradores ? [{ cuantos: borradores, texto: borradores === 1 ? 'red en borrador' : 'redes en borrador' }] : [],
    });
  }

  /** El primer paso del wizard con un campo inválido, o -1. */
  private pasoConError(): number {
    return this.pasosSitio.findIndex((_, i) => !this.wizard?.validarPaso(i));
  }

  /** Si el paso tiene algo por corregir, no avanza y el foco va al primer error. */
  siguienteSitio(): void {
    const antes = this.pasoSitio;
    this.wizard?.siguiente();
    if (this.pasoSitio === antes) this.cuerpo?.irAlPrimerError();
  }

  guardarSitio(): void {
    const conError = this.pasoConError();
    if (conError >= 0 || this.sitio.invalid) {
      this.sitio.markAllAsTouched();
      this.pasoSitio = Math.max(conError, 0);
      this.cuerpo?.irAlPrimerError();
      return;
    }

    const v = this.sitio.getRawValue();
    const datos: SitioPortal = {
      nombre: v.nombre!,
      nombreCorto: v.nombreCorto!,
      nombreEnLineas: [v.nombreLinea1!, v.nombreLinea2!].map((l) => l.trim()).filter((l) => l !== ''),
      subtitulo: v.subtitulo || null,
      ciudad: v.ciudad!,
      anioFundacion: Number(v.anioFundacion),
      telefono: { numero: v.telefono.numero!, visible: v.telefono.visible! },
      whatsapp: { numero: v.whatsapp.numero!, visible: v.whatsapp.visible! },
      direccion: { linea: v.direccion.linea!, referencia: v.direccion.referencia || null, distrito: v.direccion.distrito || null },
      portalAcademico: v.conPlataforma ? { nombre: v.portalAcademico.nombre!, url: v.portalAcademico.url! } : null,
      promotora: v.promotora || null,
    };

    this.guardandoSitio = true;
    this.erroresServidor = {};
    this.portalWebService.guardarFicha('sitio', datos, this.versionSitio).subscribe({
      next: (res) => {
        this.guardandoSitio = false;
        this.versionSitio = res.data.version;
        this.sitio.markAsPristine();
        this.sitioGuardado = datos;
        this.modalSitio = false;
        this.resumirSitio();
        this.toastService.success('Datos guardados', 'El portal los mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoSitio = false;
        this.mostrarError(err, 'No se pudieron guardar los datos del colegio.', (campo, mensaje) => {
          // El backend dice «nombreEnLineas.1»; en la pantalla son dos campos.
          const enPantalla = campo === 'nombreEnLineas.1' ? 'nombreLinea2' : campo.startsWith('nombreEnLineas') ? 'nombreLinea1' : campo;
          this.erroresServidor[enPantalla] = mensaje;
          this.sitio.get(enPantalla)?.markAsTouched();
          // Al paso donde está ese campo.
          const paso = this.pasosSitio.findIndex((p) => p.campos?.includes(enPantalla));
          if (paso >= 0) this.pasoSitio = paso;
        });
        this.cuerpo?.irAlPrimerError();
      },
    });
  }

  /** El mensaje de error de un campo: el del backend si lo hay; si no, null (el HTML pone el suyo). */
  errorServidor(campo: string): string | null {
    return this.erroresServidor[campo] ?? null;
  }

  invalido(campo: string): boolean {
    const c = this.sitio.get(campo);
    return (!!c && c.invalid && c.touched) || !!this.erroresServidor[campo];
  }

  private ajustarPlataforma(): void {
    const con = !!this.sitio.controls.conPlataforma.value;
    const grupo = this.sitio.controls.portalAcademico;
    const nombre = grupo.controls.nombre;
    const url = grupo.controls.url;

    nombre.setValidators(con ? [Validators.required, Validators.maxLength(20)] : [Validators.maxLength(20)]);
    url.setValidators(con ? [Validators.required, Validators.pattern(HTTPS), Validators.maxLength(255)] : [Validators.pattern(HTTPS)]);
    con ? grupo.enable({ emitEvent: false }) : grupo.disable({ emitEvent: false });
    nombre.updateValueAndValidity({ emitEvent: false });
    url.updateValueAndValidity({ emitEvent: false });
  }

  // ───────── Redes sociales ─────────

  cargarRedes(): void {
    this.cargandoRedes = true;
    this.portalWebService.getRedes().subscribe({
      next: (res) => {
        this.redes = res.data;
        this.cargandoRedes = false;
        this.resumirRedes();
      },
      error: (err) => {
        this.cargandoRedes = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar las redes sociales.'));
      },
    });
  }

  nombreDeRed(valor: string): string {
    return REDES_DEL_PORTAL.find((r) => r.valor === valor)?.nombre ?? valor;
  }

  /** Las redes que se pueden elegir: cada una va una sola vez. */
  get redesDisponibles(): { valor: string; nombre: string }[] {
    const usadas = this.redes.filter((r) => r.id !== this.redEditando?.id).map((r) => r.red);
    return REDES_DEL_PORTAL.filter((r) => !usadas.includes(r.valor));
  }

  get esWhatsapp(): boolean {
    return this.formRed.controls.red.value === 'whatsapp';
  }

  nuevaRed(): void {
    this.redEditando = null;
    this.formRed.reset({ red: '', cuenta: '', url: '', aclaracion: '', estado: 'borrador' });
    this.modalRedVisible = true;
  }

  editarRed(red: RedSocialPortal): void {
    this.redEditando = red;
    this.formRed.reset({ red: red.red, cuenta: red.cuenta, url: red.url, aclaracion: red.aclaracion ?? '', estado: red.estado });
    this.modalRedVisible = true;
  }

  cerrarModalRed(): void {
    this.modalRedVisible = false;
    this.redEditando = null;
  }

  invalidoRed(campo: string): boolean {
    const c = this.formRed.get(campo);
    return !!c && c.invalid && c.touched;
  }

  guardarRed(): void {
    if (this.formRed.invalid) {
      this.formRed.markAllAsTouched();
      enfocarPrimerError();
      return;
    }

    const v = this.formRed.getRawValue();
    const datos: Partial<RedSocialPortal> = {
      red: v.red!, cuenta: v.cuenta!, url: v.url!, aclaracion: v.aclaracion || null, estado: v.estado as EstadoPortal,
    };

    this.guardandoRed = true;
    const peticion = this.redEditando
      ? this.portalWebService.editarRed(this.redEditando.id, datos)
      : this.portalWebService.crearRed(datos);

    peticion.subscribe({
      next: (res) => {
        this.guardandoRed = false;
        this.toastService.success(
          this.redEditando ? 'Red actualizada' : 'Red agregada',
          res.data.estado === 'publicado' ? 'El portal la mostrará en hasta un minuto.' : 'Quedó en borrador: publícala cuando esté lista.',
        );
        const editado = !!this.redEditando;
        this.cerrarModalRed();
        // Al editar basta con la fila que devolvió el servidor; al agregar, la lista entera.
        if (editado) this.cambiarFila(res.data);
        else this.cargarRedes();
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoRed = false;
        this.mostrarError(err, 'No se pudo guardar la red social.');
      },
    });
  }

  alAccionarRed(evento: { accion: string; fila: RedSocialPortal }): void {
    const estado: EstadoPortal = evento.accion === 'publicar' ? 'publicado' : 'borrador';

    this.portalWebService.cambiarEstadoRed(evento.fila.id, estado).subscribe({
      next: (res) => {
        this.toastService.success(
          estado === 'publicado' ? 'Red publicada' : 'Red en borrador',
          `${this.nombreDeRed(evento.fila.red)} ${estado === 'publicado' ? 'se verá' : 'dejará de verse'} en el portal en hasta un minuto.`,
        );
        this.cambiarFila(res.data);
      },
      error: (err: HttpErrorResponse) => this.mostrarError(err, 'No se pudo cambiar el estado de la red.'),
    });
  }

  eliminarRed(red: RedSocialPortal): void {
    this.confirmService.confirmarEliminar(`la red ${this.nombreDeRed(red.red)} (${red.cuenta})`, () => {
      this.portalWebService.eliminarRed(red.id).subscribe({
        next: () => {
          this.toastService.success('Red eliminada', `${this.nombreDeRed(red.red)} ya no aparecerá en el portal.`);
          this.cargarRedes();
        },
        error: (err: HttpErrorResponse) => this.mostrarError(err, 'No se pudo eliminar la red.'),
      });
    });
  }

  private cambiarFila(nueva: RedSocialPortal): void {
    this.redes = conFila(this.redes, nueva);
    this.resumirRedes();
  }

  /** Sube o baja una red en el orden del portal: se mueve en pantalla en el momento y se manda la lista entera; si falla, vuelve atrás. */
  mover(red: RedSocialPortal, direccion: -1 | 1): void {
    const ids = idsMovidos(this.redes.map((r) => r.id), red.id, direccion);
    if (!ids) return;

    const antes = this.redes;
    this.redes = enOrden(antes, ids);
    this.ordenando = true;
    this.portalWebService.ordenarRedes(ids).subscribe({
      next: (res) => {
        this.redes = res.data;
        this.ordenando = false;
      },
      error: (err: HttpErrorResponse) => {
        this.redes = antes;
        this.ordenando = false;
        this.mostrarError(err, 'No se pudo cambiar el orden.');
        this.cargarRedes();
      },
    });
  }

  esPrimera(red: RedSocialPortal): boolean {
    return this.redes[0]?.id === red.id;
  }

  esUltima(red: RedSocialPortal): boolean {
    return this.redes[this.redes.length - 1]?.id === red.id;
  }

  // ───────── Errores ─────────

  /** Ver mostrarErrorDelPanel (compartido/panel.ts). */
  private mostrarError(err: HttpErrorResponse, porDefecto: string, alCampo?: (campo: string, mensaje: string) => void): void {
    mostrarErrorDelPanel(this.toastService, err, porDefecto, alCampo);
  }

  /** Para el contador de caracteres: cuánto lleva un campo. */
  largo(control: AbstractControl | null): number {
    return String(control?.value ?? '').length;
  }

  grupo(nombre: string): FormGroup {
    return this.sitio.get(nombre) as FormGroup;
  }
}
