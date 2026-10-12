import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../core/services';
import { mensajeErrorApi } from '../../../core/utils';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { mostrarErrorDelPanel, moverEn } from '../compartido/panel';
import {
  COLUMNAS_DEL_PIE,
  NavegacionPortal,
  PAGINAS_DEL_MENU,
  PaginaDelMenu,
  PaginaNosotros,
} from '../portal-web.models';
import { PortalWebService, RECURSOS } from '../portal-web.service';

const MAX_GRUPOS = 4;

/**
 * Menú y títulos › El menú, los grupos de Nosotros y el pie (ficha
 * `navegacion`, contrato 2.13).
 *
 *  - El menú: qué páginas van, en qué orden y con qué nombre. La que no está
 *    no sale en el menú ni en el pie, pero su dirección sigue abriendo.
 *  - Los grupos de Nosotros: sus columnas. Qué subpáginas van en cada uno se
 *    elige en Nosotros › Subpáginas; aquí se ve cuántas tiene.
 *  - Los títulos de las columnas del pie.
 */
@Component({
  selector: 'app-panel-menu',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent],
  templateUrl: './panel-menu.component.html',
})
export class PanelMenuComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  readonly maxGrupos = MAX_GRUPOS;
  readonly columnasDelPie = COLUMNAS_DEL_PIE;

  cargando = true;
  guardando = false;
  private version: string | null = null;
  /** Las subpáginas, para decir cuántas tiene cada grupo. */
  paginas: PaginaNosotros[] = [];

  form = this.fb.group({
    menu: this.fb.array<FormGroup>([]),
    grupos: this.fb.array<FormGroup>([]),
    pie: this.fb.group({
      colegio: ['', [Validators.required, Validators.maxLength(30)]],
      nosotros: ['', [Validators.required, Validators.maxLength(30)]],
      contacto: ['', [Validators.required, Validators.maxLength(30)]],
      redes: ['', [Validators.required, Validators.maxLength(30)]],
    }),
  });

  get menu(): FormArray<FormGroup> {
    return this.form.controls.menu;
  }

  get grupos(): FormArray<FormGroup> {
    return this.form.controls.grupos;
  }

  /** Las páginas que todavía no están en el menú. */
  get fueraDelMenu(): typeof PAGINAS_DEL_MENU {
    const usadas = new Set(this.menu.controls.map((c) => c.value.pagina));
    return PAGINAS_DEL_MENU.filter((p) => !usadas.has(p.pagina));
  }

  ngOnInit(): void {
    this.cargar();
  }

  private item(pagina: PaginaDelMenu, etiqueta = ''): FormGroup {
    return this.fb.group({
      pagina: [pagina],
      etiqueta: [etiqueta, [Validators.required, Validators.maxLength(24)]],
    });
  }

  private grupo(g?: { id: string | null; titulo: string }): FormGroup {
    return this.fb.group({
      id: [g?.id ?? null],
      titulo: [g?.titulo ?? '', [Validators.required, Validators.maxLength(30)]],
    });
  }

  cargar(): void {
    this.cargando = true;
    this.portalWebService.getLista<PaginaNosotros>(RECURSOS.paginas).subscribe({
      next: (res) => (this.paginas = res.data),
    });
    this.portalWebService.getFicha<NavegacionPortal>('navegacion').subscribe({
      next: (res) => {
        const c = res.data.contenido;
        this.version = res.data.version;
        this.menu.clear();
        this.grupos.clear();
        (c?.menu ?? []).forEach((m) => this.menu.push(this.item(m.pagina, m.etiqueta)));
        (c?.grupos ?? []).forEach((g) => this.grupos.push(this.grupo(g)));
        this.form.controls.pie.reset({
          colegio: c?.pie?.colegio ?? '',
          nosotros: c?.pie?.nosotros ?? '',
          contacto: c?.pie?.contacto ?? '',
          redes: c?.pie?.redes ?? '',
        });
        this.form.markAsPristine();
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar el menú.'));
      },
    });
  }

  nombreDePagina(pagina: PaginaDelMenu): string {
    return PAGINAS_DEL_MENU.find((p) => p.pagina === pagina)?.nombre ?? pagina;
  }

  /** Las subpáginas de un grupo: cuántas y cuántas se ven. */
  paginasDe(grupo: FormGroup): { total: number; publicadas: number } {
    const id = grupo.value.id;
    const suyas = id ? this.paginas.filter((p) => p.grupo === id) : [];
    return { total: suyas.length, publicadas: suyas.filter((p) => p.estado === 'publicado').length };
  }

  agregarAlMenu(pagina: string): void {
    const elegida = PAGINAS_DEL_MENU.find((p) => p.pagina === pagina);
    if (!elegida) return;
    this.menu.push(this.item(elegida.pagina, elegida.nombre));
    this.menu.markAsDirty();
  }

  quitarDelMenu(i: number): void {
    if (this.menu.length > 1) {
      this.menu.removeAt(i);
      this.menu.markAsDirty();
    }
  }

  moverEnMenu(i: number, direccion: -1 | 1): void {
    moverEn(this.menu, i, direccion);
  }

  agregarGrupo(): void {
    if (this.grupos.length < MAX_GRUPOS) {
      this.grupos.push(this.grupo());
      this.grupos.markAsDirty();
    }
  }

  quitarGrupo(i: number): void {
    this.grupos.removeAt(i);
    this.grupos.markAsDirty();
  }

  moverGrupo(i: number, direccion: -1 | 1): void {
    moverEn(this.grupos, i, direccion);
  }

  invalido(control: { invalid: boolean; touched: boolean } | null | undefined): boolean {
    return !!control && control.invalid && control.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.toastService.warning('Revisa el formulario', 'Hay nombres que faltan o son demasiado largos.');
      return;
    }

    const v = this.form.getRawValue();
    const datos: NavegacionPortal = {
      menu: v.menu.map((m) => ({ pagina: m['pagina'], etiqueta: String(m['etiqueta']).trim() })),
      grupos: v.grupos.map((g) => ({ id: g['id'] ?? null, titulo: String(g['titulo']).trim() })),
      pie: {
        colegio: v.pie.colegio!.trim(),
        nosotros: v.pie.nosotros!.trim(),
        contacto: v.pie.contacto!.trim(),
        redes: v.pie.redes!.trim(),
      },
    };

    this.guardando = true;
    this.portalWebService.guardarFicha<NavegacionPortal>('navegacion', datos, this.version).subscribe({
      next: () => {
        this.guardando = false;
        this.toastService.success('Menú guardado', 'El portal lo mostrará en hasta un minuto.');
        // Vuelve a leerlo: un grupo nuevo ya tiene su id, y se puede elegir en las subpáginas.
        this.cargar();
      },
      error: (err: HttpErrorResponse) => {
        this.guardando = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar el menú.');
      },
    });
  }
}
