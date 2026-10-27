import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../core/services';
import { mensajeErrorApi } from '../../../core/utils';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IndiceDeSeccionesComponent } from '../compartido/indice-de-secciones/indice-de-secciones.component';
import { PantallaConIndice, SeccionDelIndice } from '../compartido/pantalla-con-indice';
import { enfocarPrimerError, mostrarErrorDelPanel } from '../compartido/panel';
import { CabeceraSimple, CategoriaProyectoPortal } from '../portal-web.models';
import { PortalWebService } from '../portal-web.service';
import { PanelCategoriasComponent } from './categorias/panel-categorias.component';
import { PanelProyectosComponent } from './proyectos/panel-proyectos.component';

/**
 * Panel «Portal Web» › Proyectos: la cabecera de la página, las categorías
 * del filtro y los proyectos, cada uno con su detalle y su galería.
 *
 * Con el molde de Ajustes: índice con cabecera, categorías y proyectos. La
 * cabecera se lee en su sección y se edita en un modal.
 */
@Component({
  selector: 'app-portal-web-proyectos',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, PageHeaderComponent, IconComponent, FormModalComponent, IndiceDeSeccionesComponent,
    PanelCategoriasComponent, PanelProyectosComponent,
  ],
  templateUrl: './portal-web-proyectos.component.html',
  styleUrls: ['../compartido/pw-estilos.scss'],
})
export class PortalWebProyectosComponent extends PantallaConIndice implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  readonly secciones: SeccionDelIndice[] = [
    { id: 'cabecera', nombre: 'Cabecera', icono: 'description' },
    { id: 'categorias', nombre: 'Categorías', icono: 'filter' },
    { id: 'proyectos', nombre: 'Proyectos', icono: 'lightbulb' },
  ];

  /** Las del panel de categorías, para el selector de cada proyecto. */
  categorias: CategoriaProyectoPortal[] = [];

  cargandoCabecera = true;
  guardandoCabecera = false;
  private versionCabecera: string | null = null;
  modalCabecera = false;
  /** Lo guardado: lo que se lee en la sección. */
  cabeceraGuardada: CabeceraSimple | null = null;

  cabecera = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    bajada: ['', [Validators.maxLength(240)]],
  });

  override ngOnInit(): void {
    super.ngOnInit();
    this.cargarCabecera();
  }

  cargarCabecera(): void {
    this.cargandoCabecera = true;
    this.portalWebService.getFicha<CabeceraSimple>('proyectos').subscribe({
      next: (res) => {
        this.versionCabecera = res.data.version;
        this.cabeceraGuardada = res.data.contenido ?? null;
        this.cargandoCabecera = false;
        this.resumirCabecera();
      },
      error: (err) => {
        this.cargandoCabecera = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la cabecera de Proyectos.'));
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
      : { estado: 'Sin configurar: no se ve', aviso: true, pendientes: [{ cuantos: 1, texto: 'cabecera de Proyectos sin configurar' }] });
  }

  invalidoCabecera(campo: string): boolean {
    const c = this.cabecera.get(campo);
    return !!c && c.invalid && c.touched;
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
    this.portalWebService.guardarFicha<CabeceraSimple>('proyectos', datos, this.versionCabecera).subscribe({
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
}
