import { Component, EventEmitter, OnInit, Output, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, ValidatorFn, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../core/services';
import { mensajeErrorApi } from '../../../core/utils';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { CuerpoDesplazableComponent } from '../compartido/cuerpo-desplazable/cuerpo-desplazable.component';
import { mostrarErrorDelPanel } from '../compartido/panel';
import { ResumenDeSeccion } from '../compartido/resumen-de-seccion';
import { CampoDeTitulo, TITULOS_DE_PAGINAS, TitulosPortal } from '../portal-web.models';
import { PortalWebService } from '../portal-web.service';

type PaginaDeTitulos = (typeof TITULOS_DE_PAGINAS)[number];

/**
 * Menú y títulos › Los títulos de cada página (ficha `titulos`, contrato
 * 2.13): títulos de sección, botones y rótulos. Todos obligatorios; los
 * campos y sus largos salen de TITULOS_DE_PAGINAS. Los que ya tienen su
 * propia ficha (las cabeceras, el título de la academia…) se editan en su
 * página.
 *
 * Se leen por página y cada página se edita en su modal. Es una sola ficha:
 * al guardar una página, las demás van como estaban guardadas.
 */
@Component({
  selector: 'app-panel-titulos',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, FormModalComponent, CuerpoDesplazableComponent],
  templateUrl: './panel-titulos.component.html',
  styleUrls: ['../compartido/pw-estilos.scss'],
})
export class PanelTitulosComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  /** Cómo está la sección, para el índice de la pantalla. */
  @Output() resumen = new EventEmitter<ResumenDeSeccion>();

  @ViewChild('cuerpo') private cuerpo?: CuerpoDesplazableComponent;

  readonly paginas = TITULOS_DE_PAGINAS;
  /** La marca del número en «{n} sedes…» (en la plantilla, unas llaves sueltas serían sintaxis de Angular). */
  readonly marca = '{n}';

  cargando = true;
  guardando = false;
  private version: string | null = null;
  /** Lo guardado: lo que se lee en la sección. */
  guardados: TitulosPortal = {};
  /** La página que se edita en el modal. */
  editando: PaginaDeTitulos | null = null;

  form: FormGroup = this.fb.group(
    Object.fromEntries(
      TITULOS_DE_PAGINAS.map((p) => [
        p.pagina,
        this.fb.group(Object.fromEntries(p.campos.map((c) => [c.campo, ['', this.reglas(c)]]))),
      ]),
    ),
  );

  ngOnInit(): void {
    this.cargar();
  }

  private reglas(c: CampoDeTitulo): ValidatorFn[] {
    const reglas = [Validators.required, Validators.maxLength(c.maximo)];
    // El de varias sedes lleva el número: sin {n} diría lo mismo con dos que con ocho.
    if (c.ejemplo.includes('{n}')) reglas.push(Validators.pattern(/\{n\}/));
    return reglas;
  }

  cargar(): void {
    this.cargando = true;
    this.portalWebService.getFicha<TitulosPortal>('titulos').subscribe({
      next: (res) => {
        this.version = res.data.version;
        this.guardados = res.data.contenido ?? {};
        this.cargando = false;
        this.resumir();
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los títulos.'));
      },
    });
  }

  /** Lo guardado de un campo, o vacío. */
  valor(pagina: string, campo: string): string {
    return this.guardados[pagina]?.[campo] ?? '';
  }

  /** Cuántos títulos de una página faltan en lo guardado: el portal no tendría qué mostrar. */
  faltanEn(p: PaginaDeTitulos): number {
    return p.campos.filter((c) => !this.valor(p.pagina, c.campo).trim()).length;
  }

  private resumir(): void {
    const faltan = this.paginas.reduce((n, p) => n + this.faltanEn(p), 0);
    this.resumen.emit({
      estado: faltan ? `${faltan} ${faltan === 1 ? 'título sin llenar' : 'títulos sin llenar'}` : `${this.paginas.length} páginas, completas`,
      aviso: faltan > 0,
      pendientes: faltan ? [{ cuantos: faltan, texto: faltan === 1 ? 'título de página sin llenar' : 'títulos de página sin llenar' }] : [],
    });
  }

  grupoDe(pagina: string): FormGroup {
    return this.form.get(pagina) as FormGroup;
  }

  invalido(pagina: string, campo: string): boolean {
    const c = this.form.get([pagina, campo]);
    return !!c && c.invalid && c.touched;
  }

  largo(pagina: string, campo: string): number {
    return String(this.form.get([pagina, campo])?.value ?? '').length;
  }

  /** El modal de una página empieza con lo guardado: lo que se canceló antes no queda a medias. */
  abrir(p: PaginaDeTitulos): void {
    this.grupoDe(p.pagina).reset(Object.fromEntries(p.campos.map((c) => [c.campo, this.valor(p.pagina, c.campo)])));
    this.editando = p;
  }

  cerrar(): void {
    this.editando = null;
  }

  guardar(): void {
    const p = this.editando;
    if (!p) return;
    const grupo = this.grupoDe(p.pagina);
    if (grupo.invalid) {
      grupo.markAllAsTouched();
      this.cuerpo?.irAlPrimerError();
      return;
    }

    // Las demás páginas van como estaban guardadas.
    const pagina = Object.fromEntries(Object.entries(grupo.getRawValue()).map(([k, t]) => [k, String(t).trim()]));
    const datos: TitulosPortal = { ...this.guardados, [p.pagina]: pagina };

    this.guardando = true;
    this.portalWebService.guardarFicha<TitulosPortal>('titulos', datos, this.version).subscribe({
      next: (res) => {
        this.guardando = false;
        this.version = res.data.version;
        this.guardados = datos;
        this.editando = null;
        this.resumir();
        this.toastService.success('Títulos guardados', 'El portal los mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardando = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudieron guardar los títulos.', (campo) => {
          this.form.get(campo)?.setErrors({ servidor: true });
          this.form.get(campo)?.markAsTouched();
        });
      },
    });
  }
}
