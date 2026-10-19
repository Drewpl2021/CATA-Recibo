import { Component, OnInit, ViewChild, inject } from '@angular/core';
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
import { CabeceraSimple, NivelDocentePortal } from '../portal-web.models';
import { PortalWebService } from '../portal-web.service';
import { PanelDocentesComponent } from './docentes/panel-docentes.component';
import { PanelNivelesDocentesComponent } from './docentes/panel-niveles-docentes.component';
import { PanelPaginasComponent } from './paginas/panel-paginas.component';

/**
 * Panel «Portal Web» › Nosotros: el título y la frase del índice, las
 * subpáginas y Personal Docente (sus niveles y, debajo, los docentes del
 * nivel elegido).
 *
 * Con el molde de Ajustes: índice con el índice de Nosotros, las subpáginas
 * y Personal Docente. El índice se lee como ficha y se edita en un modal.
 */
@Component({
  selector: 'app-portal-web-nosotros',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, PageHeaderComponent, IconComponent, FormModalComponent, IndiceDeSeccionesComponent,
    PanelPaginasComponent, PanelNivelesDocentesComponent, PanelDocentesComponent,
  ],
  templateUrl: './portal-web-nosotros.component.html',
  styleUrls: ['../compartido/pw-estilos.scss'],
})
export class PortalWebNosotrosComponent extends PantallaConIndice implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  @ViewChild(PanelNivelesDocentesComponent) private panelNiveles?: PanelNivelesDocentesComponent;

  readonly secciones: SeccionDelIndice[] = [
    { id: 'indice', nombre: 'Índice de Nosotros', icono: 'description' },
    { id: 'subpaginas', nombre: 'Subpáginas', icono: 'folder_open' },
    { id: 'docentes', nombre: 'Personal Docente', icono: 'people' },
  ];

  niveles: NivelDocentePortal[] = [];
  nivelElegido: string | null = null;

  cargandoIndice = true;
  guardandoIndice = false;
  private versionIndice: string | null = null;
  modalIndice = false;
  /** Lo guardado: lo que se lee en la sección. */
  indiceGuardado: CabeceraSimple | null = null;

  indice = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    bajada: ['', [Validators.maxLength(200)]],
  });

  override ngOnInit(): void {
    super.ngOnInit();
    this.cargarIndice();
  }

  /** Desde la fila de un nivel: sus docentes, a la vista. */
  verDocentes(n: NivelDocentePortal): void {
    this.nivelElegido = n.id;
    document.getElementById('docentes-del-nivel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /** Cambió cuántos docentes tiene un nivel: su cuenta está en la tabla de niveles. */
  recargarNiveles(): void {
    this.panelNiveles?.cargar();
  }

  cargarIndice(): void {
    this.cargandoIndice = true;
    this.portalWebService.getFicha<CabeceraSimple>('nosotros').subscribe({
      next: (res) => {
        this.versionIndice = res.data.version;
        this.indiceGuardado = res.data.contenido ?? null;
        this.cargandoIndice = false;
        this.resumirIndice();
      },
      error: (err) => {
        this.cargandoIndice = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar el índice de Nosotros.'));
      },
    });
  }

  private resumirIndice(): void {
    const c = this.indiceGuardado;
    this.alResumir('indice', c
      ? { estado: c.titulo, aviso: false, pendientes: [] }
      : { estado: 'Sin configurar: no se ve', aviso: true, pendientes: [{ cuantos: 1, texto: 'índice de Nosotros sin configurar' }] });
  }

  /** El modal empieza con lo guardado: lo que se canceló antes no queda a medias. */
  abrirIndice(): void {
    const c = this.indiceGuardado;
    this.indice.reset({ titulo: c?.titulo ?? '', bajada: c?.bajada ?? '' });
    this.modalIndice = true;
  }

  invalidoIndice(campo: 'titulo' | 'bajada'): boolean {
    const c = this.indice.controls[campo];
    return c.invalid && c.touched;
  }

  guardarIndice(): void {
    if (this.indice.invalid) {
      this.indice.markAllAsTouched();
      enfocarPrimerError();
      return;
    }

    const v = this.indice.getRawValue();
    const datos = { titulo: v.titulo!, bajada: v.bajada || null };
    this.guardandoIndice = true;
    this.portalWebService.guardarFicha<CabeceraSimple>('nosotros', datos, this.versionIndice).subscribe({
      next: (res) => {
        this.guardandoIndice = false;
        this.versionIndice = res.data.version;
        this.indiceGuardado = datos as CabeceraSimple;
        this.modalIndice = false;
        this.resumirIndice();
        this.toastService.success('Índice guardado', 'El portal lo mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoIndice = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar el índice.');
      },
    });
  }
}
