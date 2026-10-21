import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { Router } from '@angular/router';
import { AuditoriaService, ToastService } from '../../../core/services';
import { DetalleAuditoria, RegistroAuditoria } from '../../../core/models';
import { fechaHoraLegible, mensajeErrorApi } from '../../../core/utils';
import { AccionFila, DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PanelLateralComponent } from '../../../shared/components/panel-lateral/panel-lateral.component';
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
import { FiltrosComponent } from '../../../shared/components/filtros/filtros.component';
import { CampoFiltro, ValoresFiltro } from '../../../shared/components/filtros/filtros.models';
@Component({
  selector: 'app-auditoria-list',
  standalone: true,
  imports: [FiltrosComponent, CommonModule, FormsModule, PageHeaderComponent, DataTableComponent, PanelLateralComponent, IconComponent],
  templateUrl: './auditoria-list.component.html',
})
export class AuditoriaListComponent implements OnInit {
  private auditoriaService = inject(AuditoriaService);
  private toastService = inject(ToastService);
  private router = inject(Router);

  registros: RegistroAuditoria[] = [];
  cargando = false;

  readonly TAMANO_PAGINA = 15;
  pagina = 0;
  busqueda = '';
  total = 0;


  readonly entidades = [
    { valor: '', etiqueta: 'Todo' },
    { valor: 'empleado', etiqueta: 'Empleados' },
    { valor: 'usuario', etiqueta: 'Cuentas de acceso' },
    { valor: 'concepto', etiqueta: 'Conceptos de pago' },
    { valor: 'planilla', etiqueta: 'Planillas' },
    { valor: 'linea', etiqueta: 'Líneas de una planilla' },
    { valor: 'configuración', etiqueta: 'Ajustes del sistema' },
    { valor: 'montos de ley', etiqueta: 'Montos de ley' },
    { valor: 'boleta', etiqueta: 'Boletas (firma digital)' },
  ];

  /** El embudo: sobre qué, qué se hizo y entre qué fechas. El backend entiende estas claves. */
  filtros: ValoresFiltro = {};
  readonly camposFiltro: CampoFiltro[] = [
    {
      clave: 'entidad', etiqueta: 'Sobre', tipo: 'opciones', vacio: 'Todo',
      opciones: this.entidades.filter((e) => e.valor).map((e) => ({ valor: e.valor, etiqueta: e.etiqueta })),
    },
    {
      clave: 'accion', etiqueta: 'Acción', tipo: 'opciones', vacio: 'Todas',
      opciones: ['creó', 'cambió', 'borró', 'aplicó', 'importó', 'recalculó', 'agregó', 'quitó', 'firmó'].map((a) => ({ valor: a, etiqueta: this.etiquetaAccion(a) })),
    },
    { clave: 'desde', claveHasta: 'hasta', etiqueta: 'Cuándo', tipo: 'fecha', ancho: 'doble' },
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

  /**
   * Sin botón de editar ni de borrar: esta pantalla no los usa (es de solo
   * lectura), y el valor por defecto de app-data-table trae esos dos. Sin
   * esto salían igual, sin hacer nada al tocarlos.
   */
  readonly acciones: AccionFila[] = [];

  readonly accionesAuditoria: AccionPersonalizada<RegistroAuditoria>[] = [
    { id: 'ver_mas', titulo: 'Ver todo lo que pasó', icono: 'description', etiqueta: 'Ver más' },
  ];

  // ── El detalle («Ver más») ──
  /**
   * Un panel a la derecha, no un modal: se sigue viendo la lista, y desde el
   * historial se salta a otro registro sin cerrar nada. El detalle lo arma el
   * backend (DetalleDeAuditoria): campos en palabras, montos en soles, ids
   * por su nombre, quién era la persona y lo que pasó alrededor.
   */
  panelAbierto = false;
  detalle: DetalleAuditoria | null = null;
  cargandoDetalle = false;
  /** Los registros por los que se fue pasando, para «Volver». */
  private recorridos: number[] = [];

  verMas(fila: RegistroAuditoria): void {
    this.recorridos = [];
    this.abrirDetalle(fila.id);
  }

  /** Otro registro desde el historial o los cercanos, sin cerrar el panel. */
  irARegistro(id: number): void {
    if (this.detalle) this.recorridos.push(this.detalle.registro.id);
    this.abrirDetalle(id);
  }

  volverAlAnterior(): void {
    const anterior = this.recorridos.pop();
    if (anterior !== undefined) this.abrirDetalle(anterior);
  }

  get hayAnterior(): boolean {
    return this.recorridos.length > 0;
  }

  private abrirDetalle(id: number): void {
    this.panelAbierto = true;
    this.cargandoDetalle = true;
    this.auditoriaService.detalle(id).subscribe({
      next: (res) => {
        this.cargandoDetalle = false;
        if (res.success) this.detalle = res.data;
      },
      error: (err) => {
        this.cargandoDetalle = false;
        this.toastService.error('No se pudo abrir', mensajeErrorApi(err, 'Intenta de nuevo en un momento.'));
      },
    });
  }

  abrirEnElSistema(enlace: string): void {
    this.panelAbierto = false;
    this.router.navigateByUrl(enlace);
  }

  etiquetaEntidad(entidad: string): string {
    return this.entidades.find((e) => e.valor === entidad)?.etiqueta ?? entidad;
  }

  fechaCompleta(valor: string | null | undefined): string {
    return valor ? fechaHoraLegible(valor) : '—';
  }

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

  get mensajeVacio(): string {
    return Object.values(this.filtros).some((v) => !!v)
      ? 'Nada anotado con estos filtros. Quita alguno con su «×».'
      : 'Todavía no hay nada anotado. Aparece aquí en cuanto alguien cambie un sueldo, una cuenta, un concepto o una planilla.';
  }

  alFiltrar(): void {
    this.pagina = 0;
    this.cargar();
  }

  alAccionar(evento: { accion: string; fila: RegistroAuditoria }): void {
    if (evento.accion === 'ver_mas') this.verMas(evento.fila);
  }

  cargar(): void {
    this.cargando = true;
    this.auditoriaService
      .getPagina({
        page: this.pagina,
        size: this.TAMANO_PAGINA,
        search: this.busqueda || undefined,
        ...this.filtros,
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

  etiquetaAccion(accion: string): string {
    const etiquetas: Record<string, string> = {
      'creó': 'Alta', 'cambió': 'Cambio', 'borró': 'Baja', 'aplicó': 'Aplicó',
      'agregó': 'Agregó', 'quitó': 'Quitó', 'firmó': 'Firma digital',
      'importó': 'Importó', 'recalculó': 'Recalculó',
    };
    return etiquetas[accion] ?? accion;
  }

  colorAccion(accion: string): 'success' | 'warning' | 'info' | 'secondary' {
    if (accion === 'creó' || accion === 'agregó') return 'success';
    if (accion === 'borró' || accion === 'quitó') return 'secondary';
    if (accion === 'aplicó' || accion === 'importó' || accion === 'recalculó') return 'warning';
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

    // Lo anidado (los nuevos de una importación) se lee «activos: 1, cesados: 0», nunca [object Object].
    const legible = (valor: unknown): string => {
      if (valor === null || valor === undefined || valor === '') return 'vacío';
      if (typeof valor === 'boolean') return valor ? 'sí' : 'no';
      if (Array.isArray(valor)) return valor.map(legible).join(', ') || 'ninguno';
      if (typeof valor === 'object') {
        return Object.entries(valor as Record<string, unknown>).map(([k, v]) => `${k.replace(/_/g, ' ')}: ${legible(v)}`).join(', ');
      }
      return String(valor);
    };

    return Object.entries(cambios)
      .map(([campo, valor]) => {
        const nombre = campo.replace(/_/g, ' ');
        return fila.accion === 'cambió' && Array.isArray(valor) && valor.length === 2
          ? `${nombre}: ${legible(valor[0])} → ${legible(valor[1])}`
          : `${nombre}: ${legible(valor)}`;
      })
      .join('; ');
  }
}
