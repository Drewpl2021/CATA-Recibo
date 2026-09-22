import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';

import { forkJoin } from 'rxjs';

import { AreaService, CargoService, EmpleadoService, SedeService, ToastService, ConfirmService } from '../../../core/services';
import { Empleado } from '../../../core/models';
import { guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FiltrosComponent } from '../../../shared/components/filtros/filtros.component';
import { CampoFiltro, ValoresFiltro } from '../../../shared/components/filtros/filtros.models';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

/**
 * Listado del personal (RR.HH. y Admin).
 *
 * Dar de baja no borra a nadie: deja al empleado inactivo y desactiva su
 * cuenta de acceso (el backend le cierra además las sesiones abiertas). Se
 * mantiene su historial de planillas y boletas, que es lo que interesa
 * conservar. Reactivar se hace desde el propio formulario, cambiando su
 * estado a "Activo".
 */
@Component({
  selector: 'app-empleados-list',
  standalone: true,
  imports: [CommonModule, PageHeaderComponent, DataTableComponent, FiltrosComponent],
  templateUrl: './empleados-list.component.html',
})
export class EmpleadosListComponent implements OnInit {
  private router = inject(Router);
  private empleadoService = inject(EmpleadoService);
  private areaService = inject(AreaService);
  private cargoService = inject(CargoService);
  private sedeService = inject(SedeService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  empleados: Empleado[] = [];
  cargando = false;

  /** Filas por página; el backend corta y cuenta, acá solo se pinta. */
  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  busqueda = '';
  /** Cuántos hay en total, según el backend — no el largo de la página. */
  total = 0;
  /** Los conteos que manda el backend junto a la página. */
  activos = 0;
  inactivos = 0;

  /*
   * Los filtros de la tabla.
   *
   * Antes solo había buscador por texto, y las preguntas de RR.HH. no son de
   * texto: "los de Jerusalén con plazo fijo", "quién no tiene sueldo puesto",
   * "los que entraron este año". Eso se contestaba bajando la lista a Excel.
   *
   * Filtra el servidor, no el navegador: las cifras de la cabecera y el
   * Excel salen del mismo conjunto, y con 150 trabajadores no se baja la
   * plantilla entera para descartar en pantalla.
   */
  filtros: ValoresFiltro = {};

  camposFiltro: CampoFiltro[] = [
    {
      clave: 'estado', etiqueta: 'Estado', tipo: 'opciones', vacio: 'Todos',
      opciones: [
        { valor: 'activo', etiqueta: 'Activos' },
        { valor: 'inactivo', etiqueta: 'De baja' },
      ],
    },
    { clave: 'sede_id', etiqueta: 'Sede', tipo: 'opciones', vacio: 'Todas', opciones: [] },
    { clave: 'area_id', etiqueta: 'Área', tipo: 'opciones', vacio: 'Todas', opciones: [] },
    { clave: 'cargo_id', etiqueta: 'Cargo', tipo: 'opciones', vacio: 'Todos', opciones: [] },
    {
      clave: 'tipo_contrato', etiqueta: 'Tipo de contrato', tipo: 'opciones', vacio: 'Todos',
      opciones: [
        { valor: 'indeterminado', etiqueta: 'Indeterminado' },
        { valor: 'plazo_fijo', etiqueta: 'Plazo fijo' },
        { valor: 'suplencia', etiqueta: 'Suplencia' },
        { valor: 'practicas', etiqueta: 'Prácticas' },
      ],
    },
    {
      clave: 'sistema_pensiones', etiqueta: 'Pensión', tipo: 'opciones', vacio: 'Todas',
      opciones: [
        { valor: 'ONP', etiqueta: 'ONP' },
        { valor: 'AFP', etiqueta: 'AFP' },
        { valor: 'ninguno', etiqueta: 'No aporta' },
      ],
    },
    {
      clave: 'forma_pago', etiqueta: 'Cómo cobra', tipo: 'opciones', vacio: 'Todas',
      opciones: [
        { valor: 'banco', etiqueta: 'Por banco' },
        { valor: 'efectivo', etiqueta: 'En efectivo' },
      ],
    },
    {
      clave: 'sin_sueldo', etiqueta: 'Sin sueldo puesto', tipo: 'si-no',
      ayuda: 'Sin sueldo no se le puede armar planilla: la generación lo salta.',
    },
    {
      clave: 'ingreso_desde', claveHasta: 'ingreso_hasta',
      etiqueta: 'Fecha de ingreso', tipo: 'fecha', ancho: 'doble',
    },
  ];

  /** Cambió un filtro: se vuelve a la primera página. */
  alFiltrar(): void {
    this.pagina = 0;
    this.cargar();
  }

  /**
   * Los catálogos de los desplegables, la primera vez que se abre el panel.
   *
   * Pedirlos al entrar a la pantalla serían tres listas más en cada visita,
   * y la mayoría de las veces nadie abre los filtros.
   */
  private catalogosListos = false;

  cargarCatalogos(): void {
    if (this.catalogosListos) return;
    this.catalogosListos = true;

    forkJoin({
      sedes: this.sedeService.getAll(),
      areas: this.areaService.getAll(),
      cargos: this.cargoService.getAll(),
    }).subscribe({
      next: ({ sedes, areas, cargos }) => {
        this.ponerOpciones('sede_id', sedes.data);
        this.ponerOpciones('area_id', areas.data);
        this.ponerOpciones('cargo_id', cargos.data);
      },
      // Si falla, los otros filtros siguen sirviendo: no es motivo para
      // dejar el panel inservible.
      error: () => {
        this.catalogosListos = false;
        this.toastService.error('Filtros', 'No se pudieron cargar las sedes, áreas y cargos.');
      },
    });
  }

  private ponerOpciones(clave: string, lista: { id: string; nombre: string }[]): void {
    const campo = this.camposFiltro.find((c) => c.clave === clave);
    if (!campo) return;

    campo.opciones = (lista ?? [])
      .map((x) => ({ valor: x.id, etiqueta: x.nombre }))
      .sort((a, b) => a.etiqueta.localeCompare(b.etiqueta, 'es'));
  }

  /** Lo que pinta la cabecera: total, en uso y dados de baja. */
  get cifras(): CifraCabecera[] {
    return [
      { icono: 'layers', valor: this.total, etiqueta: 'Total', tono: 'brand' },
      { icono: 'check_circle', valor: this.activos, etiqueta: 'Activos', tono: 'success' },
      { icono: 'pause_circle', valor: this.inactivos, etiqueta: 'De baja', tono: 'muted' },
    ];
  }

  /**
   * Se muestran solo las columnas que sirven para localizar a alguien; la
   * sede y el resto de la ficha están un clic más allá, en "Ver ficha". Con
   * más columnas la tabla se desbordaba y las acciones quedaban fuera.
   */
  columnas: ColumnaTabla<Empleado>[] = [
    { campo: 'dni', header: 'DNI', ancho: '11%' },
    {
      campo: 'nombre',
      header: 'Nombres y apellidos',
      ancho: '26%',
      formatear: (_v, fila) => `${fila.nombre ?? ''} ${fila.apellido ?? ''}`.trim(),
    },
    { campo: 'cargo.nombre', header: 'Cargo', ancho: '15%' },
    { campo: 'area.nombre', header: 'Área', ancho: '15%' },
    {
      // Con ancho fijo a propósito: un correo es una cadena larga y sin
      // espacios, así que sin tope se comía la fila y dejaba los nombres
      // partidos en tres líneas.
      campo: 'usuario.email',
      header: 'Correo',
      ancho: '23%',
      romperTexto: true,
      formatear: (valor) => valor || 'Sin cuenta',
    },
    {
      campo: 'estado',
      header: 'Estado',
      ancho: '10%',
      tipo: 'badge',
      formatear: (valor) => (valor === 'inactivo' ? 'Inactivo' : 'Activo'),
      badgeSeveridad: (valor) => (valor === 'inactivo' ? 'secondary' : 'success'),
    },
  ];

  /** Ver la ficha completa sin poder tocarla. */
  accionesExtra: AccionPersonalizada<Empleado>[] = [
    { id: 'ver', titulo: 'Ver ficha completa', icono: 'person' },
  ];

  ngOnInit(): void {
    this.cargar();
  }

  /** El usuario pidió otra página. */
  irAPagina(pagina: number): void {
    this.pagina = pagina;
    this.cargar();
  }

  /** Búsqueda contra el backend; llega ya con el retardo aplicado. */
  buscar(termino: string): void {
    this.busqueda = termino;
    this.pagina = 0;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.empleadoService
      .getPagina({
        page: this.pagina,
        size: this.TAMANO_PAGINA,
        search: this.busqueda || undefined,
        ...this.filtros,
      })
      .subscribe({
      next: (res) => {
        if (res.success) {
          this.empleados = res.data.content;
          this.total = res.data.totalElements;
          this.activos = res.data.activos ?? 0;
          this.inactivos = res.data.inactivos ?? 0;
        }
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los empleados.'));
      },
    });
  }

  // ────────── Descargar la lista del personal ──────────

  exportando = false;

  /**
   * Baja la ficha completa de cada trabajador en Excel: sus datos personales,
   * los laborales, los de planilla y los bancarios. El mismo archivo se puede
   * corregir y volver a subir por Importar empleados, sin convertir nada.
   *
   * Va el mismo buscador de la tabla, así que si arriba se filtró por un
   * área o un apellido, el archivo sale con esa misma gente.
   */
  exportar(): void {
    this.exportando = true;

    this.empleadoService.exportar({ search: this.busqueda || undefined, ...this.filtros }).subscribe({
      next: (blob) => {
        guardarArchivo(blob, this.nombreDelArchivo());
        this.exportando = false;
        this.toastService.success('Lista descargada', `${this.total} trabajador(es), con su ficha completa.`);
      },
      error: (err) => {
        this.exportando = false;
        this.toastService.error('No se descargó', mensajeErrorApi(err, 'No se pudo generar la lista.'));
      },
    });
  }

  /**
   * "Empleados 2026-09-13.xlsx".
   *
   * La fecha va en el nombre porque esta lista se vuelve a bajar cada poco y
   * sin ella acaban tres archivos iguales en la carpeta de descargas. Se arma
   * acá y no se lee de la respuesta porque el backend no expone
   * Content-Disposition al navegador.
   */
  private nombreDelArchivo(): string {
    const hoy = new Date();
    const dosDigitos = (n: number) => String(n).padStart(2, '0');
    const fecha = `${hoy.getFullYear()}-${dosDigitos(hoy.getMonth() + 1)}-${dosDigitos(hoy.getDate())}`;

    return `Empleados ${fecha}.xlsx`;
  }

  /** Altas y cambios de varios trabajadores desde el Excel de RR.HH. */
  importar(): void {
    this.router.navigate(['/inicio/empleados/importar']);
  }

  nuevo(): void {
    this.router.navigate(['/inicio/empleados/nuevo']);
  }

  editar(empleado: Empleado): void {
    this.router.navigate(['/inicio/empleados/editar', empleado.id]);
  }

  ver(empleado: Empleado): void {
    this.router.navigate(['/inicio/empleados/ver', empleado.id]);
  }

  alAccionar(evento: { accion: string; fila: Empleado }): void {
    if (evento.accion === 'ver') this.ver(evento.fila);
  }

  darDeBaja(empleado: Empleado): void {
    const nombre = `${empleado.nombre} ${empleado.apellido}`.trim();

    if (empleado.estado === 'inactivo') {
      this.toastService.error('Ya está inactivo', `${nombre} ya estaba dado de baja.`);
      return;
    }

    this.confirmService.confirmarEliminar(
      `a ${nombre}. Quedará inactivo y su cuenta dejará de entrar al sistema, pero se conservan sus planillas y boletas`,
      () => {
        this.empleadoService.delete(empleado.id).subscribe({
          next: () => {
            this.toastService.success('Empleado dado de baja', `${nombre} quedó inactivo.`);
            this.cargar();
          },
          error: (err) => {
            this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo dar de baja al empleado.'));
          },
        });
      }
    );
  }
}
