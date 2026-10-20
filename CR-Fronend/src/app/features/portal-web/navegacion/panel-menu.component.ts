import { Component, EventEmitter, Input, OnInit, Output, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../core/services';
import { mensajeErrorApi } from '../../../core/utils';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { WizardComponent } from '../../../shared/components/wizard/wizard.component';
import { PasoWizard } from '../../../shared/components/wizard/wizard.models';
import { CuerpoDesplazableComponent } from '../compartido/cuerpo-desplazable/cuerpo-desplazable.component';
import { mostrarErrorDelPanel, moverEn } from '../compartido/panel';
import { cuantos, ResumenDeSeccion } from '../compartido/resumen-de-seccion';
import {
  COLUMNAS_DEL_PIE,
  NavegacionPortal,
  PAGINAS_DEL_MENU,
  PaginaDelMenu,
  PaginaNosotros,
} from '../portal-web.models';
import { PortalWebService, RECURSOS } from '../portal-web.service';

const MAX_GRUPOS = 4;

/** Las tres partes de la ficha, cada una con su sección en el índice. */
export type ParteDelMenu = 'menu' | 'grupos' | 'pie';

/**
 * Menú y títulos › El menú, los grupos de Nosotros y el pie (ficha
 * `navegacion`, contrato 2.13).
 *
 *  - El menú: qué páginas van, en qué orden y con qué nombre. La que no está
 *    no sale en el menú ni en el pie, pero su dirección sigue abriendo.
 *  - Los grupos de Nosotros: sus columnas. Qué subpáginas van en cada uno se
 *    elige en Nosotros › Subpáginas; aquí se ve cuántas tiene.
 *  - Los títulos de las columnas del pie.
 *
 * Es una sola ficha y se guarda entera, pero cada parte tiene su sección en
 * el índice: se lee ahí, y «Editar» abre el wizard de tres pasos en esa parte.
 */
@Component({
  selector: 'app-panel-menu',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, FormModalComponent, WizardComponent, CuerpoDesplazableComponent],
  templateUrl: './panel-menu.component.html',
  styleUrls: ['../compartido/pw-estilos.scss'],
})
export class PanelMenuComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  /** La sección elegida en el índice: las otras dos partes quedan ocultas. */
  @Input() actual = '';
  /** Cómo está cada parte, para el índice de la pantalla. */
  @Output() resumen = new EventEmitter<{ parte: ParteDelMenu; resumen: ResumenDeSeccion }>();

  @ViewChild('wizard') private wizard?: WizardComponent;
  @ViewChild('cuerpo') private cuerpo?: CuerpoDesplazableComponent;

  readonly maxGrupos = MAX_GRUPOS;
  readonly columnasDelPie = COLUMNAS_DEL_PIE;

  readonly pasos: PasoWizard[] = [
    { id: 'menu', titulo: 'Menú', icono: 'menu', campos: ['menu'] },
    { id: 'grupos', titulo: 'Grupos de Nosotros', icono: 'people', campos: ['grupos'] },
    { id: 'pie', titulo: 'Pie', icono: 'layers', campos: ['pie'] },
  ];
  paso = 0;

  cargando = true;
  guardando = false;
  modalVisible = false;
  private version: string | null = null;
  /** Lo guardado: lo que se lee en las tres secciones. */
  guardada: NavegacionPortal | null = null;
  /** Las subpáginas, para decir cuántas tiene cada grupo. */
  paginas: PaginaNosotros[] = [];
  private paginasCargadas = false;

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

  /** Las páginas que todavía no están en el menú (del modal). */
  get fueraDelMenu(): typeof PAGINAS_DEL_MENU {
    const usadas = new Set(this.menu.controls.map((c) => c.value.pagina));
    return PAGINAS_DEL_MENU.filter((p) => !usadas.has(p.pagina));
  }

  /** Las que no están en el menú guardado, para leerlas en su sección. */
  get fueraDelMenuGuardado(): string[] {
    const usadas = new Set((this.guardada?.menu ?? []).map((m) => m.pagina));
    return PAGINAS_DEL_MENU.filter((p) => !usadas.has(p.pagina)).map((p) => p.nombre);
  }

  /** Lo leído se recarga al guardar: el mismo es el de la misma página o el mismo grupo. */
  readonly porPagina = (_: number, m: { pagina: string }): string => m.pagina;
  readonly porGrupo = (_: number, g: { id: string | null; titulo: string }): string => g.id ?? g.titulo;

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
    this.cargando = !this.guardada; // al recargar tras guardar, lo leído se queda
    this.portalWebService.getLista<PaginaNosotros>(RECURSOS.paginas).subscribe({
      next: (res) => {
        this.paginas = res.data;
        this.paginasCargadas = true;
        this.resumir();
      },
    });
    this.portalWebService.getFicha<NavegacionPortal>('navegacion').subscribe({
      next: (res) => {
        this.version = res.data.version;
        this.guardada = res.data.contenido ?? null;
        this.cargando = false;
        this.resumir();
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar el menú.'));
      },
    });
  }

  private resumir(): void {
    if (this.cargando) return;
    const c = this.guardada;
    if (!c) {
      const sin: ResumenDeSeccion = { estado: 'Sin configurar', aviso: true, pendientes: [] };
      this.resumen.emit({ parte: 'menu', resumen: { ...sin, pendientes: [{ cuantos: 1, texto: 'menú del portal sin configurar' }] } });
      this.resumen.emit({ parte: 'grupos', resumen: sin });
      this.resumen.emit({ parte: 'pie', resumen: sin });
      return;
    }
    this.resumen.emit({ parte: 'menu', resumen: { estado: cuantos(c.menu.length, 'página', 'páginas'), aviso: false, pendientes: [] } });
    // Un grupo sin subpáginas publicadas no sale en el portal.
    const vacios = this.paginasCargadas ? c.grupos.filter((g) => !this.paginasDe(g.id).publicadas).length : 0;
    this.resumen.emit({
      parte: 'grupos',
      resumen: {
        estado: cuantos(c.grupos.length, 'grupo', 'grupos') + (vacios ? ` · ${vacios} sin subpáginas` : ''),
        aviso: vacios > 0,
        pendientes: vacios ? [{ cuantos: vacios, texto: vacios === 1 ? 'grupo de Nosotros sin subpáginas publicadas: no sale' : 'grupos de Nosotros sin subpáginas publicadas: no salen' }] : [],
      },
    });
    this.resumen.emit({ parte: 'pie', resumen: { estado: 'Cuatro columnas con título', aviso: false, pendientes: [] } });
  }

  nombreDePagina(pagina: PaginaDelMenu): string {
    return PAGINAS_DEL_MENU.find((p) => p.pagina === pagina)?.nombre ?? pagina;
  }

  /** Las subpáginas de un grupo: cuántas y cuántas se ven. */
  paginasDe(id: string | null): { total: number; publicadas: number } {
    const suyas = id ? this.paginas.filter((p) => p.grupo === id) : [];
    return { total: suyas.length, publicadas: suyas.filter((p) => p.estado === 'publicado').length };
  }

  /** «3 subpáginas (2 publicadas)». */
  textoDePaginas(id: string | null): string {
    const n = this.paginasDe(id);
    return `${n.total === 1 ? '1 subpágina' : `${n.total} subpáginas`} (${n.publicadas} ${n.publicadas === 1 ? 'publicada' : 'publicadas'})`;
  }

  /** El modal empieza con lo guardado y en la parte elegida. */
  abrir(parte: ParteDelMenu): void {
    const c = this.guardada;
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
    this.paso = this.pasos.findIndex((p) => p.id === parte);
    this.modalVisible = true;
  }

  cerrar(): void {
    this.modalVisible = false;
  }

  /** El wizard marca como tocada la lista entera: aquí se marcan sus campos, para que se vea cuál falta. */
  siguiente(): void {
    this.form.get(this.pasos[this.paso].id)?.markAllAsTouched();
    const antes = this.paso;
    this.wizard?.siguiente();
    if (this.paso === antes) this.cuerpo?.irAlPrimerError();
  }

  agregarAlMenu(pagina: string): void {
    const elegida = PAGINAS_DEL_MENU.find((p) => p.pagina === pagina);
    if (!elegida) return;
    this.menu.push(this.item(elegida.pagina, elegida.nombre));
  }

  quitarDelMenu(i: number): void {
    if (this.menu.length > 1) this.menu.removeAt(i);
  }

  moverEnMenu(i: number, direccion: -1 | 1): void {
    moverEn(this.menu, i, direccion);
  }

  agregarGrupo(): void {
    if (this.grupos.length < MAX_GRUPOS) this.grupos.push(this.grupo());
  }

  quitarGrupo(i: number): void {
    this.grupos.removeAt(i);
  }

  moverGrupo(i: number, direccion: -1 | 1): void {
    moverEn(this.grupos, i, direccion);
  }

  invalido(control: { invalid: boolean; touched: boolean } | null | undefined): boolean {
    return !!control && control.invalid && control.touched;
  }

  /** Si algo falta, el wizard va a la primera parte con error. */
  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.paso = this.pasos.findIndex((p) => this.form.get(p.id)?.invalid);
      this.cuerpo?.irAlPrimerError();
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
        this.modalVisible = false;
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
