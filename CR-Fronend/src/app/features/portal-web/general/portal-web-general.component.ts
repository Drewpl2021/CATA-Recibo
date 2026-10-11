import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
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
 */
@Component({
  selector: 'app-portal-web-general',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    PageHeaderComponent, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent,
  ],
  templateUrl: './portal-web-general.component.html',
})
export class PortalWebGeneralComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);
  private router = inject(Router);

  readonly anioActual = new Date().getFullYear();

  // ───────── Datos del colegio ─────────

  cargandoSitio = true;
  guardandoSitio = false;
  /** La versión leída: se manda al guardar para no pisar lo que otro guardó. */
  private versionSitio: string | null = null;
  /** Errores que devolvió el backend, por campo ('telefono.numero'). */
  erroresServidor: Record<string, string> = {};

  sitio = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(80)]],
    nombreCorto: ['', [Validators.required, Validators.maxLength(20)]],
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
    { campo: 'red', header: 'Red', ancho: '14%', formatear: (v) => this.nombreDeRed(v) },
    { campo: 'cuenta', header: 'Cuenta' },
    { campo: 'url', header: 'Dirección', romperTexto: true },
    { campo: 'aclaracion', header: 'Aviso', formatear: (v) => v ?? '—' },
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

  ngOnInit(): void {
    this.cargarSitio();
    this.cargarRedes();

    // Sin plataforma académica, sus dos datos dejan de ser obligatorios.
    this.sitio.controls.conPlataforma.valueChanges.subscribe(() => this.ajustarPlataforma());
  }

  volver(): void {
    this.router.navigate(['/inicio/portal-web']);
  }

  // ───────── Datos del colegio ─────────

  cargarSitio(): void {
    this.cargandoSitio = true;
    this.portalWebService.getFicha<SitioPortal>('sitio').subscribe({
      next: (res) => {
        const c = res.data.contenido;
        this.versionSitio = res.data.version;
        this.sitio.reset({
          nombre: c?.nombre ?? '',
          nombreCorto: c?.nombreCorto ?? '',
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
        this.cargandoSitio = false;
      },
      error: (err) => {
        this.cargandoSitio = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los datos del colegio.'));
      },
    });
  }

  guardarSitio(): void {
    if (this.sitio.invalid) {
      this.sitio.markAllAsTouched();
      this.toastService.warning('Revisa el formulario', 'Hay datos que faltan o no tienen el formato correcto.');
      return;
    }

    const v = this.sitio.getRawValue();
    const datos: SitioPortal = {
      nombre: v.nombre!,
      nombreCorto: v.nombreCorto!,
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
        this.toastService.success('Datos guardados', 'El portal los mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoSitio = false;
        this.mostrarError(err, 'No se pudieron guardar los datos del colegio.', (campo, mensaje) => {
          this.erroresServidor[campo] = mensaje;
          this.sitio.get(campo)?.markAsTouched();
        });
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
        this.cerrarModalRed();
        this.cargarRedes();
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
      next: () => {
        this.toastService.success(
          estado === 'publicado' ? 'Red publicada' : 'Red en borrador',
          `${this.nombreDeRed(evento.fila.red)} ${estado === 'publicado' ? 'se verá' : 'dejará de verse'} en el portal en hasta un minuto.`,
        );
        this.cargarRedes();
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

  /** Sube o baja una red en el orden del portal. Se manda la lista entera. */
  mover(red: RedSocialPortal, direccion: -1 | 1): void {
    const ids = idsMovidos(this.redes.map((r) => r.id), red.id, direccion);
    if (!ids) return;

    this.ordenando = true;
    this.portalWebService.ordenarRedes(ids).subscribe({
      next: (res) => {
        this.redes = res.data;
        this.ordenando = false;
      },
      error: (err: HttpErrorResponse) => {
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
