import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, ValidatorFn, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../core/services';
import { mensajeErrorApi } from '../../../core/utils';
import { mostrarErrorDelPanel } from '../compartido/panel';
import { CampoDeTitulo, TITULOS_DE_PAGINAS, TitulosPortal } from '../portal-web.models';
import { PortalWebService } from '../portal-web.service';

/**
 * Menú y títulos › Los títulos de cada página (ficha `titulos`, contrato
 * 2.13): títulos de sección, botones y rótulos. Todos obligatorios; los
 * campos y sus largos salen de TITULOS_DE_PAGINAS. Los que ya tienen su
 * propia ficha (las cabeceras, el título de la academia…) se editan en su
 * página.
 */
@Component({
  selector: 'app-panel-titulos',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './panel-titulos.component.html',
})
export class PanelTitulosComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  readonly paginas = TITULOS_DE_PAGINAS;
  /** La marca del número en «{n} sedes…» (en la plantilla, unas llaves sueltas serían sintaxis de Angular). */
  readonly marca = '{n}';

  cargando = true;
  guardando = false;
  private version: string | null = null;
  /** La página que se ve; las demás quedan plegadas. */
  abierta = TITULOS_DE_PAGINAS[0].pagina;

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
        const c = res.data.contenido ?? {};
        this.version = res.data.version;
        this.form.reset(
          Object.fromEntries(
            TITULOS_DE_PAGINAS.map((p) => [p.pagina, Object.fromEntries(p.campos.map((f) => [f.campo, c[p.pagina]?.[f.campo] ?? '']))]),
          ),
        );
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los títulos.'));
      },
    });
  }

  grupoDe(pagina: string): FormGroup {
    return this.form.get(pagina) as FormGroup;
  }

  /** Cuántos campos de una página tienen algún problema, para avisarlo aunque esté plegada. */
  problemasEn(pagina: string): number {
    const grupo = this.grupoDe(pagina);
    return Object.values(grupo.controls).filter((c) => c.invalid && (c.touched || c.dirty)).length;
  }

  invalido(pagina: string, campo: string): boolean {
    const c = this.form.get([pagina, campo]);
    return !!c && c.invalid && c.touched;
  }

  largo(pagina: string, campo: string): number {
    return String(this.form.get([pagina, campo])?.value ?? '').length;
  }

  alternar(pagina: string): void {
    this.abierta = this.abierta === pagina ? '' : pagina;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      // Abre la primera página con algo por corregir.
      this.abierta = this.paginas.find((p) => this.grupoDe(p.pagina).invalid)?.pagina ?? this.abierta;
      this.toastService.warning('Revisa los títulos', 'Hay títulos que faltan o son demasiado largos.');
      return;
    }

    const v = this.form.getRawValue() as TitulosPortal;
    const datos = Object.fromEntries(
      Object.entries(v).map(([pagina, campos]) => [pagina, Object.fromEntries(Object.entries(campos).map(([k, t]) => [k, String(t).trim()]))]),
    );

    this.guardando = true;
    this.portalWebService.guardarFicha<TitulosPortal>('titulos', datos, this.version).subscribe({
      next: (res) => {
        this.guardando = false;
        this.version = res.data.version;
        this.form.markAsPristine();
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
