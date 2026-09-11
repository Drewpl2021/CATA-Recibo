import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { AuditoriaService, ToastService } from '../../../core/services';
import { RegistroAuditoria } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

/**
 * Auditoría: quién cambió qué, y cuándo.
 *
 * Es de solo lectura a propósito. No hay botón de editar ni de borrar, ni
 * aquí ni en el backend: un registro que se puede retocar no prueba nada.
 *
 * Responde a las preguntas de un reclamo —"¿quién me bajó el sueldo?",
 * "¿quién me puso este descuento?", "¿quién reabrió la planilla ya
 * pagada?"— con un nombre, una fecha y el valor de antes y de después.
 */
@Component({
  selector: 'app-auditoria-list',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent, DataTableComponent],
  templateUrl: './auditoria-list.component.html',
})
export class AuditoriaListComponent implements OnInit {
  private auditoriaService = inject(AuditoriaService);
  private toastService = inject(ToastService);

  registros: RegistroAuditoria[] = [];
  cargando = false;

  readonly TAMANO_PAGINA = 15;
  pagina = 0;
  busqueda = '';
  total = 0;

  /** Sobre qué: el trabajador, su cuenta, el catálogo o una planilla. */
  filtroEntidad = '';

  readonly entidades = [
    { valor: '', etiqueta: 'Todo' },
    { valor: 'empleado', etiqueta: 'Empleados' },
    { valor: 'usuario', etiqueta: 'Cuentas de acceso' },
    { valor: 'concepto', etiqueta: 'Conceptos de pago' },
    { valor: 'planilla', etiqueta: 'Planillas' },
    { valor: 'linea', etiqueta: 'Líneas de una planilla' },
  ];

  get cifras(): CifraCabecera[] {
    return [{ icono: 'clock', valor: this.total, etiqueta: 'Registros', tono: 'brand' }];
  }

  columnas: ColumnaTabla<RegistroAuditoria>[] = [
    { campo: 'created_at', header: 'Cuándo', tipo: 'fecha-hora', ancho: '14%' },
    { campo: 'usuario_nombre', header: 'Quién', ancho: '16%', formatear: (v) => (v as string) || '—' },
    {
      campo: 'accion', header: 'Acción', tipo: 'badge', ancho: '10%',
      formatear: (v) => this.etiquetaAccion(String(v)),
      badgeSeveridad: (v) => this.colorAccion(String(v)),
    },
    { campo: 'descripcion', header: 'Qué pasó', romperTexto: true },
    {
      campo: 'cambios', header: 'Antes → después', ancho: '26%', romperTexto: true,
      formatear: (_v, fila) => this.resumirCambios(fila),
    },
  ];

  ngOnInit(): void {
    this.cargar();
  }

  irAPagina(pagina: number): void {
    this.pagina = pagina;
    this.cargar();
  }

  buscar(termino: string): void {
    this.busqueda = termino;
    this.pagina = 0;
    this.cargar();
  }

  alFiltrar(): void {
    this.pagina = 0;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.auditoriaService
      .getPagina({
        page: this.pagina,
        size: this.TAMANO_PAGINA,
        search: this.busqueda || undefined,
        entidad: this.filtroEntidad || undefined,
      })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.registros = res.data.content;
            this.total = res.data.totalElements;
          }
          this.cargando = false;
        },
        error: (err) => {
          this.cargando = false;
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la auditoría.'));
        },
      });
  }

  private etiquetaAccion(accion: string): string {
    const etiquetas: Record<string, string> = {
      'creó': 'Alta', 'cambió': 'Cambio', 'borró': 'Baja', 'aplicó': 'Aplicó',
      'agregó': 'Agregó', 'quitó': 'Quitó',
    };
    return etiquetas[accion] ?? accion;
  }

  private colorAccion(accion: string): 'success' | 'warning' | 'info' | 'secondary' {
    if (accion === 'creó' || accion === 'agregó') return 'success';
    if (accion === 'borró' || accion === 'quitó') return 'secondary';
    if (accion === 'aplicó') return 'warning';
    return 'info';
  }

  /**
   * Los cambios, en una línea que se lee: "sueldo base: 2700.00 → 3456.78".
   *
   * Lo que no es un par antes/después (los números de una aplicación en
   * bloque) sale como "campo: valor".
   */
  resumirCambios(fila: RegistroAuditoria): string {
    const cambios = fila.cambios;
    if (!cambios || !Object.keys(cambios).length) return '—';

    const legible = (valor: unknown): string => {
      if (valor === null || valor === undefined || valor === '') return 'vacío';
      if (typeof valor === 'boolean') return valor ? 'sí' : 'no';
      return String(valor);
    };

    return Object.entries(cambios)
      .map(([campo, valor]) => {
        const nombre = campo.replace(/_/g, ' ');
        return Array.isArray(valor) && valor.length === 2
          ? `${nombre}: ${legible(valor[0])} → ${legible(valor[1])}`
          : `${nombre}: ${legible(valor)}`;
      })
      .join('; ');
  }
}
