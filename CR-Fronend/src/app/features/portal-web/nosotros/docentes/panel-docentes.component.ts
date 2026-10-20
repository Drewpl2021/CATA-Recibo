import { Component, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { CuerpoDesplazableComponent } from '../../compartido/cuerpo-desplazable/cuerpo-desplazable.component';
import { enfocarPrimerError, urlParaVer } from '../../compartido/panel';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { SelectorImagenComponent } from '../../compartido/selector-imagen/selector-imagen.component';
import { DocentePortal, EstadoPortal, ImagenPortal, NivelDocentePortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

/**
 * Personal Docente › Docentes de un nivel: nombre, cargo y foto, nada más
 * (hasta 150 publicados en total). Se ordenan dentro de su nivel, que es como
 * los muestra el portal; quien cambia de nivel pasa al final del nuevo.
 *
 * No salen de la ficha de RR.HH.: se escriben aquí, con lo que el colegio
 * decide publicar y la autorización de uso de imagen de cada docente.
 */
@Component({
  selector: 'app-panel-docentes',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent, SelectorImagenComponent,
    CuerpoDesplazableComponent,
  ],
  templateUrl: './panel-docentes.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelDocentesComponent extends PanelDeLista<DocentePortal> implements OnInit, OnChanges {
  private fb = inject(FormBuilder);

  @Input() niveles: NivelDocentePortal[] = [];
  @Input() nivelId: string | null = null;
  @Output() nivelIdChange = new EventEmitter<string | null>();
  /** Cambió cuántos docentes tiene un nivel: hay que recargar los niveles. */
  @Output() cambiaron = new EventEmitter<void>();

  protected readonly recurso = RECURSOS.docentes;
  protected readonly nombre = { singular: 'docente', femenino: false };
  /** Al editar se vuelve a pedir la lista: se ven las del nivel elegido: al cambiar de nivel, sale. */
  protected override readonly recargarAlEditar = true;

  foto: ImagenPortal | null = null;
  readonly ver = urlParaVer;

  // El cargo y la foto van con el nombre: así caben las acciones.
  columnas: ColumnaTabla<DocentePortal>[] = [
    this.columnaDeOrden(),
    { campo: 'nombre', header: 'Docente' },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(80)]],
    cargo: ['', [Validators.required, Validators.maxLength(80)]],
    nivel_id: ['', [Validators.required]],
    estado: ['borrador' as EstadoPortal],
  });

  get nivel(): NivelDocentePortal | undefined {
    return this.niveles.find((n) => n.id === this.nivelId);
  }

  override ngOnInit(): void {
    // Carga cuando hay nivel (ngOnChanges).
  }

  ngOnChanges(cambios: SimpleChanges): void {
    if (cambios['nivelId'] && this.nivelId !== this.cargadoPara) {
      this.cargar();
    }
    if (cambios['niveles'] && !this.nivel) {
      // Ninguno elegido, o se eliminó el elegido: el primero, si hay.
      const primero = this.niveles[0]?.id ?? null;
      this.nivelId = primero;
      this.cargar();
      queueMicrotask(() => this.nivelIdChange.emit(primero));
    }
  }

  /** Desde el selector. */
  elegir(id: string): void {
    this.nivelId = id;
    this.cargar();
    this.nivelIdChange.emit(id);
  }

  /** De qué nivel son las filas: para no volver a pedirlas cuando el padre confirma el elegido. */
  private cargadoPara: string | null = null;

  override cargar(): void {
    this.cargadoPara = this.nivelId;
    if (!this.nivelId) {
      this.filas = [];
      this.cargando = false;
      return;
    }
    super.cargar();
  }

  protected override filtro(): Record<string, string> {
    return this.nivelId ? { nivel: this.nivelId } : {};
  }

  protected override alCargar(): void {
    const n = this.nivel;
    if (n && n.docentes !== this.filas.length) this.cambiaron.emit();
  }

  protected describir(d: DocentePortal): string {
    return `a «${d.nombre}» de Personal Docente`;
  }

  nuevo(): void {
    this.editando = null;
    this.foto = null;
    this.form.reset({ nombre: '', cargo: '', nivel_id: this.nivelId ?? '', estado: 'borrador' });
    this.modalVisible = true;
  }

  editar(d: DocentePortal): void {
    this.editando = d;
    this.foto = d.imagen;
    this.form.reset({ nombre: d.nombre, cargo: d.cargo, nivel_id: d.nivel_id, estado: d.estado });
    this.modalVisible = true;
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      enfocarPrimerError();
      return;
    }

    const v = this.form.getRawValue();
    this.enviar({ nombre: v.nombre, cargo: v.cargo, nivel_id: v.nivel_id, estado: v.estado, imagen_id: this.foto?.id ?? null });
  }
}
