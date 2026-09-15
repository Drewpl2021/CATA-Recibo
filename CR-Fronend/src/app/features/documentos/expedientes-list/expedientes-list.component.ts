import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';

import { ExpedienteService, ToastService } from '../../../core/services';
import { Contrato, FilaExpediente, FiltroExpedientes, ResumenExpedientes } from '../../../core/models';
import { diasHasta, fechaDeDia, fechaLegible, mensajeErrorApi } from '../../../core/utils';
import { TIPO_CONTRATO_CONTRATO_OPCIONES } from '../../../shared/constants';
import { CeldaTablaDirective } from '../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

const SIN_RESUMEN: ResumenExpedientes = {
  trabajadores: 0,
  sin_hoja_de_vida: 0,
  boletas_por_firmar: 0,
  contrato_por_vencer: 0,
  de_baja: 0,
  dias_por_vencer: 30,
};

interface ChipExpediente {
  valor: FiltroExpedientes;
  etiqueta: string;
  icono: string;
  cuenta: (r: ResumenExpedientes) => number;
}

/**
 * Documentos del personal (RR.HH. y Administración).
 *
 * Antes "Documentos" abría Mis Documentos: RR.HH. veía una lista de archivos
 * —"Hoja de vida", "Contrato"— sin saber de quién era cada uno. Ahora se
 * parte de la persona: quién es, si tiene hoja de vida, qué contrato tiene y
 * cuántas boletas le faltan firmar. Al abrir a alguien se ve su expediente.
 *
 * Los chips son lo que RR.HH. suele ir a buscar: a quién le falta la hoja de
 * vida, quién no firma y qué contrato está por vencer.
 */
@Component({
  selector: 'app-expedientes-list',
  standalone: true,
  imports: [CommonModule, PageHeaderComponent, DataTableComponent, CeldaTablaDirective, IconComponent],
  templateUrl: './expedientes-list.component.html',
})
export class ExpedientesListComponent implements OnInit {
  private expedientes = inject(ExpedienteService);
  private toast = inject(ToastService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);

  filas: FilaExpediente[] = [];
  cargando = false;

  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  busqueda = '';
  total = 0;

  filtro: FiltroExpedientes = '';
  resumen: ResumenExpedientes = { ...SIN_RESUMEN };

  readonly chips: ChipExpediente[] = [
    { valor: '', etiqueta: 'Todo el personal', icono: 'people', cuenta: (r) => r.trabajadores },
    { valor: 'sin_hoja_de_vida', etiqueta: 'Sin hoja de vida', icono: 'description', cuenta: (r) => r.sin_hoja_de_vida },
    { valor: 'boletas_por_firmar', etiqueta: 'Con boletas por firmar', icono: 'signature', cuenta: (r) => r.boletas_por_firmar },
    { valor: 'contrato_por_vencer', etiqueta: 'Contrato por vencer', icono: 'clock', cuenta: (r) => r.contrato_por_vencer },
    { valor: 'de_baja', etiqueta: 'Dados de baja', icono: 'remove_circle', cuenta: (r) => r.de_baja },
  ];

  readonly columnas: ColumnaTabla<FilaExpediente>[] = [
    { campo: 'apellido', header: 'Trabajador', ancho: '26%' },
    { campo: 'hoja_de_vida_fecha', header: 'Hoja de vida', ancho: '14%' },
    { campo: 'contrato_vigente', header: 'Contrato', ancho: '19%' },
    { campo: 'documentos_count', header: 'Documentos', ancho: '10%' },
    { campo: 'boletas_por_firmar', header: 'Boletas', ancho: '14%' },
  ];

  readonly acciones: AccionPersonalizada<FilaExpediente>[] = [
    { id: 'abrir', titulo: 'Ver su expediente', icono: 'folder_open', etiqueta: 'Ver expediente' },
  ];

  get mensajeVacio(): string {
    if (this.busqueda) return 'Nadie coincide con esa búsqueda.';
    switch (this.filtro) {
      case 'sin_hoja_de_vida': return 'Todo el personal tiene su hoja de vida.';
      case 'boletas_por_firmar': return 'Nadie tiene boletas por firmar.';
      case 'contrato_por_vencer': return 'Ningún contrato vence en los próximos días.';
      case 'de_baja': return 'No hay trabajadores dados de baja.';
      default: return 'Todavía no hay trabajadores registrados.';
    }
  }

  ngOnInit(): void {
    // Llega ya filtrada desde el panel de control ("boletas sin firmar").
    const filtro = this.route.snapshot.queryParamMap.get('filtro') as FiltroExpedientes | null;
    if (filtro && this.chips.some((c) => c.valor === filtro)) this.filtro = filtro;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.expedientes
      .paginar({
        page: this.pagina,
        size: this.TAMANO_PAGINA,
        search: this.busqueda || undefined,
        filtro: this.filtro || undefined,
      })
      .subscribe({
        next: (res) => {
          this.filas = res.data.content;
          this.total = res.data.totalElements;
          this.resumen = res.data.resumen ?? { ...SIN_RESUMEN };
          this.cargando = false;
        },
        error: (err) => {
          this.cargando = false;
          this.toast.error('No se pudo cargar', mensajeErrorApi(err, 'Intenta de nuevo en un momento.'));
        },
      });
  }

  filtrar(valor: FiltroExpedientes): void {
    if (this.filtro === valor) return;
    this.filtro = valor;
    this.pagina = 0;
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

  abrir(fila: FilaExpediente): void {
    this.router.navigate(['/inicio/documentos', fila.id]);
  }

  // ── Cómo se lee cada celda ──

  fecha(valor: string | null): string {
    return fechaLegible(valor);
  }

  tipoContrato(valor: string | null | undefined): string {
    return TIPO_CONTRATO_CONTRATO_OPCIONES.find((o) => o.value === valor)?.label ?? 'Contrato';
  }

  /** "Hasta el 28/02/2027", "Vence en 12 días", "Sin fecha de fin". */
  vigencia(contrato: Contrato): string {
    if (!contrato.fecha_fin) return 'Sin fecha de fin';
    const dias = diasHasta(contrato.fecha_fin);
    if (dias < 0) return `Venció el ${fechaDeDia(contrato.fecha_fin)}`;
    if (dias === 0) return 'Vence hoy';
    if (dias <= this.resumen.dias_por_vencer) return `Vence en ${dias} día(s)`;
    return `Hasta el ${fechaDeDia(contrato.fecha_fin)}`;
  }

  porVencer(contrato: Contrato): boolean {
    return !!contrato.fecha_fin && diasHasta(contrato.fecha_fin) <= this.resumen.dias_por_vencer;
  }
}
