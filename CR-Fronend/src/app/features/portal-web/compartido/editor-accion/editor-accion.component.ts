import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, FormGroup, ReactiveFormsModule, ValidationErrors } from '@angular/forms';

import { AccionPortal } from '../../portal-web.models';

/** Las secciones de Inicio a las que puede llevar un botón (contrato §1.8). */
export const SECCIONES_DE_INICIO: { valor: string; nombre: string }[] = [
  { valor: 'quienes-somos', nombre: 'Quiénes somos' },
  { valor: 'niveles', nombre: 'Niveles' },
  { valor: 'sedes', nombre: 'Sedes' },
  { valor: 'noticias', nombre: 'Noticias' },
  { valor: 'contacto', nombre: 'Contacto (al pie de Inicio)' },
];

/** Las páginas del portal, para sugerir (se puede escribir otra, como una de Nosotros). */
export const PAGINAS_DEL_PORTAL: { ruta: string; nombre: string }[] = [
  { ruta: '/', nombre: 'Inicio' },
  { ruta: '/matricula', nombre: 'Matrícula' },
  { ruta: '/propuesta-educativa', nombre: 'Propuesta Educativa' },
  { ruta: '/proyectos', nombre: 'Proyectos' },
  { ruta: '/logros', nombre: 'Logros' },
  { ruta: '/contacto', nombre: 'Contacto' },
  { ruta: '/nosotros', nombre: 'Nosotros' },
];

const RUTA = /^\/[a-z0-9/-]*$/;
const HTTPS = /^https:\/\/\S+$/;

/** Lo que el contrato pide de un botón, solo si lo lleva. */
function validarAccion(grupo: AbstractControl): ValidationErrors | null {
  const v = grupo.value;
  if (!v.conBoton) return null;

  const errores: ValidationErrors = {};
  const etiqueta = (v.etiqueta ?? '').trim();
  if (!etiqueta) errores['etiquetaFalta'] = true;
  else if (etiqueta.length > 32) errores['etiquetaLarga'] = true;

  if (v.tipo === 'seccion' && !v.seccion) errores['seccionFalta'] = true;
  if (v.tipo === 'pagina' && !RUTA.test(v.ruta ?? '')) errores['rutaMala'] = true;
  if (v.tipo === 'url' && !HTTPS.test(v.url ?? '')) errores['urlMala'] = true;

  return Object.keys(errores).length ? errores : null;
}

/** El grupo del formulario para un botón. */
export function grupoDeAccion(fb: FormBuilder): FormGroup {
  return fb.group(
    {
      conBoton: [false],
      etiqueta: [''],
      tipo: ['pagina' as 'seccion' | 'pagina' | 'url'],
      seccion: [''],
      ruta: [''],
      url: [''],
      externo: [true],
    },
    { validators: validarAccion },
  );
}

/** Pone en el grupo un botón guardado (o ninguno). */
export function llenarAccion(grupo: FormGroup, accion: AccionPortal | null): void {
  const d = accion?.destino;
  grupo.reset({
    conBoton: !!accion,
    etiqueta: accion?.etiqueta ?? '',
    tipo: d?.tipo ?? 'pagina',
    seccion: d?.tipo === 'seccion' ? d.seccion : '',
    ruta: d?.tipo === 'pagina' ? d.ruta : '',
    url: d?.tipo === 'url' ? d.url : '',
    externo: d?.tipo === 'url' ? d.externo : true,
  });
}

/** El botón como lo pide el backend, o null si no lleva. */
export function accionDelGrupo(grupo: FormGroup): AccionPortal | null {
  const v = grupo.getRawValue();
  if (!v.conBoton) return null;

  const etiqueta = v.etiqueta.trim();
  switch (v.tipo) {
    case 'seccion': return { etiqueta, destino: { tipo: 'seccion', seccion: v.seccion } };
    case 'url': return { etiqueta, destino: { tipo: 'url', url: v.url.trim(), externo: !!v.externo } };
    default: return { etiqueta, destino: { tipo: 'pagina', ruta: v.ruta.trim() } };
  }
}

/**
 * Un botón del portal: si lo lleva, qué dice y a dónde lleva (una sección de
 * Inicio, otra página del portal o una dirección web). Va dentro del
 * formulario de quien lo usa:
 *
 *   accion = grupoDeAccion(this.fb);
 *   <app-editor-accion [grupo]="accion" idBase="banner-accion"></app-editor-accion>
 */
@Component({
  selector: 'app-editor-accion',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './editor-accion.component.html',
})
export class EditorAccionComponent {
  @Input({ required: true }) grupo!: FormGroup;
  /** Para los id de los campos, únicos en la pantalla. */
  @Input() idBase = 'accion';
  @Input() etiquetaDelCheck = 'Lleva un botón';

  readonly secciones = SECCIONES_DE_INICIO;
  readonly paginas = PAGINAS_DEL_PORTAL;

  error(clave: string): boolean {
    return this.grupo.hasError(clave) && this.grupo.touched;
  }
}
