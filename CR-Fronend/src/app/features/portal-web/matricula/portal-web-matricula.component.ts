import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../core/services';
import { mensajeErrorApi } from '../../../core/utils';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { CuerpoDesplazableComponent } from '../compartido/cuerpo-desplazable/cuerpo-desplazable.component';
import { IndiceDeSeccionesComponent } from '../compartido/indice-de-secciones/indice-de-secciones.component';
import { PantallaConIndice, SeccionDelIndice } from '../compartido/pantalla-con-indice';
import { enfocarPrimerError, mostrarErrorDelPanel, urlParaVer } from '../compartido/panel';
import { SelectorImagenComponent } from '../compartido/selector-imagen/selector-imagen.component';
import { ImagenPortal, MatriculaCabecera } from '../portal-web.models';
import { PortalWebService } from '../portal-web.service';
import { PanelFechasComponent } from './fechas/panel-fechas.component';
import { PanelGradosComponent } from './grados/panel-grados.component';
import { PanelPreguntasComponent } from './preguntas/panel-preguntas.component';
import { PanelProcesosComponent } from './procesos/panel-procesos.component';
import { PanelVacantesComponent } from './vacantes/panel-vacantes.component';

/**
 * Panel «Portal Web» › Matrícula: la cabecera de la página (con el año del
 * proceso y el recorte de un estudiante) y, en el orden en que se ven en el
 * portal, los procesos, las vacantes, el cronograma, las preguntas y las
 * opciones de grado del formulario. Cada parte es su propio panel.
 *
 * Con el molde de Ajustes: un índice con una sección por panel. La cabecera
 * se lee como ficha y se edita en un modal.
 */
@Component({
  selector: 'app-portal-web-matricula',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, PageHeaderComponent, IconComponent, FormModalComponent, SelectorImagenComponent,
    CuerpoDesplazableComponent, IndiceDeSeccionesComponent,
    PanelProcesosComponent, PanelVacantesComponent, PanelFechasComponent, PanelPreguntasComponent, PanelGradosComponent,
  ],
  templateUrl: './portal-web-matricula.component.html',
  styleUrls: ['../compartido/pw-estilos.scss'],
})
export class PortalWebMatriculaComponent extends PantallaConIndice implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  readonly secciones: SeccionDelIndice[] = [
    { id: 'cabecera', nombre: 'Cabecera', icono: 'clipboard_check' },
    { id: 'procesos', nombre: 'Procesos', icono: 'layers' },
    { id: 'vacantes', nombre: 'Vacantes', icono: 'people' },
    { id: 'cronograma', nombre: 'Cronograma', icono: 'date_range' },
    { id: 'preguntas', nombre: 'Preguntas frecuentes', icono: 'info' },
    { id: 'grados', nombre: 'Grados del formulario', icono: 'graduation_cap' },
  ];

  cargandoCabecera = true;
  guardandoCabecera = false;
  private versionCabecera: string | null = null;
  modalCabecera = false;
  /** Lo guardado: lo que se lee en la sección. */
  cabeceraGuardada: MatriculaCabecera | null = null;
  figuraGuardada: ImagenPortal | null = null;
  /** La del modal, mientras se edita. */
  figura: ImagenPortal | null = null;

  cabecera = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    bajada: ['', [Validators.maxLength(160)]],
    anio: [null as number | null, [Validators.min(2000), Validators.max(2100), Validators.pattern(/^\d{4}$/)]],
  });

  override ngOnInit(): void {
    super.ngOnInit();
    this.cargarCabecera();
  }

  ver(url: string): string {
    return urlParaVer(url);
  }

  cargarCabecera(): void {
    this.cargandoCabecera = true;
    this.portalWebService.getFicha<MatriculaCabecera>('matricula-cabecera').subscribe({
      next: (res) => {
        const c = res.data.contenido;
        this.versionCabecera = res.data.version;
        this.cabeceraGuardada = c ?? null;
        this.figuraGuardada = c?.figura ? res.data.imagenes[c.figura.imagenId] ?? null : null;
        this.cargandoCabecera = false;
        this.resumirCabecera();
      },
      error: (err) => {
        this.cargandoCabecera = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la cabecera de Matrícula.'));
      },
    });
  }

  /** El modal empieza con lo guardado: lo que se canceló antes no queda a medias. */
  abrirCabecera(): void {
    const c = this.cabeceraGuardada;
    this.cabecera.reset({ titulo: c?.titulo ?? '', bajada: c?.bajada ?? '', anio: c?.anio ?? null });
    this.figura = this.figuraGuardada;
    this.modalCabecera = true;
  }

  private resumirCabecera(): void {
    const c = this.cabeceraGuardada;
    if (!c) {
      this.alResumir('cabecera', { estado: 'Sin configurar: no se ve', aviso: true, pendientes: [{ cuantos: 1, texto: 'cabecera de Matrícula sin configurar' }] });
      return;
    }
    // El año no se calcula solo: si quedó atrás, la placa anuncia un proceso que ya pasó.
    const vencido = !!c.anio && c.anio < new Date().getFullYear();
    this.alResumir('cabecera', {
      estado: c.anio ? `Proceso ${c.anio}` + (vencido ? ': ya pasó' : '') : 'Sin año: no hay placa',
      aviso: vencido,
      pendientes: vencido ? [{ cuantos: 1, texto: `año del proceso por actualizar (dice ${c.anio})` }] : [],
    });
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
    const anio = v.anio === null || (v.anio as unknown) === '' ? null : Number(v.anio);
    const datos = { titulo: v.titulo!, bajada: v.bajada || null, anio, figura: this.figura?.id ?? null };

    this.guardandoCabecera = true;
    this.portalWebService.guardarFicha<MatriculaCabecera>('matricula-cabecera', datos, this.versionCabecera).subscribe({
      next: (res) => {
        this.guardandoCabecera = false;
        this.versionCabecera = res.data.version;
        this.cabeceraGuardada = { ...datos, figura: this.figura ? { imagenId: this.figura.id } : null } as MatriculaCabecera;
        this.figuraGuardada = this.figura;
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
