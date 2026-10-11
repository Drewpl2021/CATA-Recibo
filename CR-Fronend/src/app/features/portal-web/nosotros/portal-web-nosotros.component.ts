import { Component, OnInit, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { Router } from '@angular/router';

import { ToastService } from '../../../core/services';
import { mensajeErrorApi } from '../../../core/utils';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { mostrarErrorDelPanel } from '../compartido/panel';
import { CabeceraSimple, NivelDocentePortal } from '../portal-web.models';
import { PortalWebService } from '../portal-web.service';
import { PanelDocentesComponent } from './docentes/panel-docentes.component';
import { PanelNivelesDocentesComponent } from './docentes/panel-niveles-docentes.component';
import { PanelPaginasComponent } from './paginas/panel-paginas.component';

/**
 * Panel «Portal Web» › Nosotros: el título y la frase del índice, las
 * subpáginas y Personal Docente (sus niveles y, debajo, los docentes del
 * nivel elegido).
 */
@Component({
  selector: 'app-portal-web-nosotros',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, PageHeaderComponent, IconComponent, PanelPaginasComponent, PanelNivelesDocentesComponent, PanelDocentesComponent],
  templateUrl: './portal-web-nosotros.component.html',
})
export class PortalWebNosotrosComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);
  private router = inject(Router);

  @ViewChild(PanelNivelesDocentesComponent) private panelNiveles?: PanelNivelesDocentesComponent;

  niveles: NivelDocentePortal[] = [];
  nivelElegido: string | null = null;

  cargandoIndice = true;
  guardandoIndice = false;
  private versionIndice: string | null = null;

  indice = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    bajada: ['', [Validators.maxLength(200)]],
  });

  ngOnInit(): void {
    this.cargarIndice();
  }

  volver(): void {
    this.router.navigate(['/inicio/portal-web']);
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
        this.indice.reset({ titulo: res.data.contenido?.titulo ?? '', bajada: res.data.contenido?.bajada ?? '' });
        this.cargandoIndice = false;
      },
      error: (err) => {
        this.cargandoIndice = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar el índice de Nosotros.'));
      },
    });
  }

  invalidoIndice(campo: 'titulo' | 'bajada'): boolean {
    const c = this.indice.controls[campo];
    return c.invalid && c.touched;
  }

  guardarIndice(): void {
    if (this.indice.invalid) {
      this.indice.markAllAsTouched();
      return;
    }

    const v = this.indice.getRawValue();
    this.guardandoIndice = true;
    this.portalWebService.guardarFicha<CabeceraSimple>('nosotros', { titulo: v.titulo, bajada: v.bajada || null }, this.versionIndice).subscribe({
      next: (res) => {
        this.guardandoIndice = false;
        this.versionIndice = res.data.version;
        this.indice.markAsPristine();
        this.toastService.success('Índice guardado', 'El portal lo mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoIndice = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar el índice.');
      },
    });
  }
}
