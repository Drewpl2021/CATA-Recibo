import { Component, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { FilaDelPortal, PanelDeLista } from '../../compartido/panel-de-lista';
import { CicloPortal, ModalidadDeCiclo, UniversidadPortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

const TOPE = 12;

type FilaCiclo = CicloPortal & FilaDelPortal;

/** Al menos una modalidad, y el fin no antes del inicio. */
function validarCiclo(grupo: AbstractControl): ValidationErrors | null {
  const v = grupo.value;
  const errores: ValidationErrors = {};
  if (!v.presencial && !v.virtual) errores['sinModalidad'] = true;
  if (v.inicio && v.fin && v.fin < v.inicio) errores['finAntes'] = true;
  return Object.keys(errores).length ? errores : null;
}

function soles(n: number | null): string {
  return n === null ? '—' : `S/ ${n.toLocaleString('es-PE', { minimumFractionDigits: n % 1 ? 2 : 0 })}`;
}

/**
 * Academia › Ciclos de una universidad: de 1 a 12, en su orden. No tienen
 * borrador: se ven cuando se ve su universidad.
 *
 * Precios y fechas solo si están vigentes; vacíos, el portal no los muestra
 * (mejor nada que un precio viejo).
 */
@Component({
  selector: 'app-panel-ciclos',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent],
  templateUrl: './panel-ciclos.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelCiclosComponent extends PanelDeLista<FilaCiclo> implements OnInit, OnChanges {
  private fb = inject(FormBuilder);

  @Input() universidades: UniversidadPortal[] = [];
  @Input() universidadId: string | null = null;
  @Output() universidadIdChange = new EventEmitter<string | null>();
  /** Cambió cuántos ciclos tiene la universidad: hay que recargar las universidades. */
  @Output() cambiaron = new EventEmitter<void>();

  protected readonly recurso = RECURSOS.ciclos;
  protected readonly nombre = { singular: 'ciclo', femenino: false };

  readonly tope = TOPE;

  // El turno y la modalidad van bajo el nombre; el precio y las fechas, en sus columnas.
  columnas: ColumnaTabla<FilaCiclo>[] = [
    this.columnaDeOrden(),
    { campo: 'nombre', header: 'Ciclo' },
    { campo: 'precio', header: 'Precio', ancho: '18%', formatear: (_: unknown, f: FilaCiclo) => this.precios(f) },
    { campo: 'inicio', header: 'Fechas', ancho: '18%', formatear: (_: unknown, f: FilaCiclo) => (f.inicio ? `${f.inicio}${f.fin ? ` al ${f.fin}` : ''}` : '—') },
  ];

  /** «Mañana · Presencial y virtual», bajo el nombre en la tabla. */
  detalle(c: FilaCiclo): string {
    const modalidad = c.modalidades.map((x: ModalidadDeCiclo) => (x === 'presencial' ? 'Presencial' : 'Virtual')).join(' y ');
    return [c.turno, modalidad].filter(Boolean).join(' · ');
  }

  form = this.fb.group(
    {
      nombre: ['', [Validators.required, Validators.maxLength(40)]],
      turno: ['', [Validators.maxLength(40)]],
      presencial: [true],
      virtual: [false],
      precio: [null as number | null, [Validators.min(0), Validators.max(99999)]],
      precio_virtual: [null as number | null, [Validators.min(0), Validators.max(99999)]],
      inicio: [''],
      fin: [''],
    },
    { validators: validarCiclo },
  );

  get universidad(): UniversidadPortal | undefined {
    return this.universidades.find((u) => u.id === this.universidadId);
  }

  override ngOnInit(): void {
    // Carga cuando hay universidad (ngOnChanges).
  }

  ngOnChanges(cambios: SimpleChanges): void {
    if (cambios['universidadId'] && this.universidadId !== this.cargadaPara) {
      this.cargar();
    }
    if (cambios['universidades'] && !this.universidad) {
      // Ninguna elegida, o se eliminó la elegida: la primera, si hay.
      const primera = this.universidades[0]?.id ?? null;
      this.universidadId = primera;
      this.cargar();
      queueMicrotask(() => this.universidadIdChange.emit(primera));
    }
  }

  /** Desde el selector. */
  elegir(id: string): void {
    this.universidadId = id;
    this.cargar();
    this.universidadIdChange.emit(id);
  }

  /** De qué universidad son las filas: para no volver a pedirlas cuando el padre confirma la elegida. */
  private cargadaPara: string | null = null;

  override cargar(): void {
    this.cargadaPara = this.universidadId;
    if (!this.universidadId) {
      this.filas = [];
      this.cargando = false;
      return;
    }
    super.cargar();
  }

  protected override filtro(): Record<string, string> {
    return this.universidadId ? { universidad: this.universidadId } : {};
  }

  protected override alCargar(): void {
    const u = this.universidad;
    if (u && u.ciclos !== this.filas.length) this.cambiaron.emit();
  }

  private precios(f: FilaCiclo): string {
    const partes: string[] = [];
    if (f.precio !== null) partes.push(soles(f.precio) + (f.modalidades.includes('virtual') && f.precio_virtual !== null ? ' presencial' : ''));
    if (f.precio_virtual !== null) partes.push(`${soles(f.precio_virtual)} virtual`);
    return partes.length ? partes.join(' · ') : '—';
  }

  protected describir(c: FilaCiclo): string {
    return `el ciclo «${c.nombre}» de «${this.universidad?.nombre ?? ''}»`;
  }

  nuevo(): void {
    this.editando = null;
    this.form.reset({ nombre: '', turno: '', presencial: true, virtual: false, precio: null, precio_virtual: null, inicio: '', fin: '' });
    this.modalVisible = true;
  }

  editar(c: FilaCiclo): void {
    this.editando = c;
    this.form.reset({
      nombre: c.nombre, turno: c.turno ?? '',
      presencial: c.modalidades.includes('presencial'), virtual: c.modalidades.includes('virtual'),
      precio: c.precio, precio_virtual: c.precio_virtual, inicio: c.inicio ?? '', fin: c.fin ?? '',
    });
    this.modalVisible = true;
  }

  /** La tabla del sistema pide confirmar; aquí antes se avisa si no se puede. */
  pedirEliminar(c: FilaCiclo): void {
    const u = this.universidad;
    if (u?.estado === 'publicado' && this.filas.length === 1) {
      this.toastService.warning(
        'No se puede eliminar',
        `«${c.nombre}» es el único ciclo de «${u.nombre}», que está publicada. Agrega otro o pasa la universidad a borrador antes.`,
      );
      return;
    }
    this.eliminar(c);
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  error(clave: string): boolean {
    return this.form.hasError(clave) && this.form.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      setTimeout(() => document.querySelector<HTMLElement>('.modal-container .is-invalid')?.focus());
      return;
    }

    const v = this.form.getRawValue();
    const modalidades: ModalidadDeCiclo[] = [];
    if (v.presencial) modalidades.push('presencial');
    if (v.virtual) modalidades.push('virtual');
    const numero = (n: number | null) => (n === null || (n as unknown) === '' ? null : Number(n));

    this.enviar({
      ...(this.editando ? {} : { universidad_id: this.universidadId }),
      nombre: v.nombre,
      turno: v.turno || null,
      modalidades,
      precio: numero(v.precio),
      precio_virtual: v.virtual ? numero(v.precio_virtual) : null,
      inicio: v.inicio || null,
      fin: v.fin || null,
    });
  }
}
