import { Component, OnInit, ViewChild, inject } from '@angular/core';
import { ProgresoService } from '../../../core/services/sistema/progreso.service';
import { EstadoListadoService } from '../../../core/services/sistema/estado-listado.service';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { Router } from '@angular/router';

import { forkJoin } from 'rxjs';

import { AreaService, CargoService, EmpleadoService, SedeService, TipoContratoService, ToastService, ConfirmService } from '../../../core/services';
import { Empleado } from '../../../core/models';
import { guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FiltrosComponent } from '../../../shared/components/filtros/filtros.component';
import { CampoFiltro, ValoresFiltro } from '../../../shared/components/filtros/filtros.models';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { BarraSeleccionComponent, resumirMarcados } from '../../../shared/components/barra-seleccion/barra-seleccion.component';
import { PistaDirective } from '../../../shared/directives/pista.directive';

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
  imports: [IconComponent, CommonModule, FormsModule, PageHeaderComponent, DataTableComponent, FiltrosComponent, FormModalComponent, BarraSeleccionComponent, PistaDirective],
  templateUrl: './empleados-list.component.html',
})
export class EmpleadosListComponent implements OnInit {
  /** El modal de avance de los procesos largos (ver ProgresoService). */
  private progreso = inject(ProgresoService);

  private estadoListados = inject(EstadoListadoService);

  private router = inject(Router);
  private empleadoService = inject(EmpleadoService);
  private areaService = inject(AreaService);
  private cargoService = inject(CargoService);
  private sedeService = inject(SedeService);
  private tipoContratoService = inject(TipoContratoService);
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
    { clave: 'tipo_contrato_id', etiqueta: 'Tipo de contrato', tipo: 'opciones', vacio: 'Todos', opciones: [] },
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
      clave: 'contrato_vencido', etiqueta: 'Contrato vencido', tipo: 'si-no',
      ayuda: 'Siguen activos pero su contrato ya terminó: renuévalo en su ficha o dalos de baja.',
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
      tiposContrato: this.tipoContratoService.getAll(),
    }).subscribe({
      next: ({ sedes, areas, cargos, tiposContrato }) => {
        this.ponerOpciones('sede_id', sedes.data);
        this.ponerOpciones('area_id', areas.data);
        this.ponerOpciones('cargo_id', cargos.data);
        this.ponerOpciones('tipo_contrato_id', tiposContrato.data);
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
      // Activo pero con el contrato ya terminado: se dice en la misma fila,
      // para no tener que entrar a la ficha a descubrirlo.
      formatear: (valor, e) => valor === 'inactivo' ? 'Inactivo'
        : this.contratoVencido(e) ? `Contrato vencido el ${this.fechaCorta(e.fecha_cese)}` : 'Activo',
      badgeSeveridad: (valor, e) => valor === 'inactivo' ? 'secondary' : this.contratoVencido(e) ? 'warning' : 'success',
    },
  ];

  /**
   * Ver la ficha, y dar de baja o volver a activar. Antes la baja era un
   * tachito de basura, y parecía que se borraba a la persona: no se borra
   * nada, queda inactiva con sus planillas y boletas.
   */
  accionesExtra: AccionPersonalizada<Empleado>[] = [
    { id: 'ver', titulo: 'Ver ficha completa', icono: 'person' },
    { id: 'baja', titulo: 'Dar de baja', icono: 'user_off', severidad: 'danger', visible: (e) => e.estado !== 'inactivo' },
    { id: 'activar', titulo: 'Volver a activar', icono: 'user_check', severidad: 'success', visible: (e) => e.estado === 'inactivo' },
  ];

  /** Los marcados con la casilla, de la página que se está viendo. */
  marcados: Empleado[] = [];
  cambiandoEstado = false;
  @ViewChild('tablaEmpleados') tablaEmpleados?: { limpiarSeleccion: () => void; marcarFilas: (filas: Empleado[]) => void };

  /** "Ana Prueba, Luis Mamani y 3 más". */
  get resumenMarcados(): string {
    return resumirMarcados(this.marcados.map((e) => `${e.nombre} ${e.apellido}`.trim()));
  }

  marcandoTodos = false;
  exportandoMarcados = false;

  /**
   * «Marcar los N de todas las páginas»: los que dan el buscador y los
   * filtros de ahora, no solo los de la página que se ve.
   */
  marcarTodos(): void {
    this.marcandoTodos = true;
    this.empleadoService.getTodos({ search: this.busqueda || undefined, ...this.filtros }).subscribe({
      next: (res) => {
        this.marcandoTodos = false;
        if (res.success) this.tablaEmpleados?.marcarFilas(res.data.content);
      },
      error: (err) => {
        this.marcandoTodos = false;
        this.toastService.error('No se marcaron', mensajeErrorApi(err, 'No se pudo traer la lista completa.'));
      },
    });
  }

  /** La ficha completa de los marcados, en Excel (la misma de «Descargar empleados»). */
  exportarMarcados(): void {
    if (!this.marcados.length) return;
    this.exportandoMarcados = true;
    const cuantos = this.marcados.length;
    this.empleadoService.exportar({}, this.marcados.map((e) => String(e.id))).subscribe({
      next: (blob) => {
        guardarArchivo(blob, this.nombreDelArchivo(cuantos));
        this.exportandoMarcados = false;
        this.toastService.success('Lista descargada', `${cuantos} trabajador(es) marcado(s), con su ficha completa.`);
      },
      error: (err) => {
        this.exportandoMarcados = false;
        this.toastService.error('No se descargó', mensajeErrorApi(err, 'No se pudo generar la lista.'));
      },
    });
  }

  soltarMarcados(): void {
    this.marcados = [];
    this.tablaEmpleados?.limpiarSeleccion();
  }

  cambiarEstadoMarcados(estado: 'activo' | 'inactivo'): void {
    this.confirmarCambioDeEstado(this.marcados, estado);
  }

  /**
   * Pregunta y aplica. Sirve para uno (el botón de su fila) o para varios
   * (los marcados): el servidor hace lo mismo en los dos casos.
   */
  private confirmarCambioDeEstado(empleados: Empleado[], estado: 'activo' | 'inactivo'): void {
    if (!empleados.length) return;
    // La baja pide su fecha: no siempre es hoy (ver abrirBaja).
    if (estado === 'inactivo') {
      this.abrirBaja(empleados);
      return;
    }
    const quienes = empleados.length === 1
      ? `${empleados[0].nombre} ${empleados[0].apellido}`.trim()
      : `${empleados.length} trabajadores`;

    const opciones = {
      titulo: `Volver a activar a ${quienes}`,
      mensaje: 'Vuelven a estar activos, con el mismo contrato que tenían antes de la baja, y su cuenta vuelve a entrar '
        + 'al sistema con la misma contraseña de antes.',
      aceptarTexto: 'Sí, activar',
      icono: 'user_check',
    };

    this.confirmService.confirmar(opciones).then((aceptado) => {
      if (aceptado) this.aplicarCambioDeEstado(empleados, 'activo');
    });
  }

  // ── Dar de baja: con su fecha ──
  /**
   * La baja no siempre es hoy: a quien se le acabó el contrato el 31/12 y
   * se registra el 5 de enero, su cese es el 31/12. Con la fecha de hoy, la
   * planilla de enero le pagaba esos 5 días.
   */
  bajaVisible = false;
  bajaEmpleados: Empleado[] = [];
  fechaBaja = '';
  usarFinDeContrato = true;
  readonly hoy = this.fechaLocal(new Date());

  /** Sigue activo pero su contrato terminó antes de hoy. */
  contratoVencido(e: Empleado): boolean {
    return e.estado !== 'inactivo' && !!e.fecha_cese && e.fecha_cese.slice(0, 10) < this.hoy;
  }

  /** Se está viendo la lista de contratos vencidos (el filtro, o el aviso del panel). */
  get viendoVencidos(): boolean {
    return this.filtros?.['contrato_vencido'] === '1';
  }

  /** Llegó desde el aviso del panel: al cargar, se marca a todos para actuar. */
  private marcarAlCargar = false;

  /** Los de la baja cuyo contrato ya terminó (su fin es hoy o antes). */
  get vencidosEnBaja(): Empleado[] {
    return this.bajaEmpleados.filter((e) => !!e.fecha_cese && e.fecha_cese.slice(0, 10) <= this.hoy);
  }

  get tituloBaja(): string {
    const e = this.bajaEmpleados;
    return e.length === 1 ? `Dar de baja a ${e[0].nombre} ${e[0].apellido}`.trim() : `Dar de baja a ${e.length} trabajadores`;
  }

  /** "31/12/2026", para decir cuándo se le acabó el contrato. */
  fechaCorta(fecha: string | null | undefined): string {
    if (!fecha) return '';
    const [a, m, d] = fecha.slice(0, 10).split('-');
    return `${d}/${m}/${a}`;
  }

  private abrirBaja(empleados: Empleado[]): void {
    this.bajaEmpleados = empleados;
    this.usarFinDeContrato = true;
    // Uno solo con el contrato ya vencido: su fin de contrato es la fecha natural.
    const unoVencido = empleados.length === 1 && this.vencidosEnBaja.length === 1;
    this.fechaBaja = unoVencido ? empleados[0].fecha_cese!.slice(0, 10) : this.hoy;
    this.bajaVisible = true;
  }

  confirmarBaja(): void {
    if (!this.fechaBaja || this.fechaBaja > this.hoy) {
      this.toastService.error('Revisa la fecha', 'La fecha de baja no puede ser una fecha que todavía no llega.');
      return;
    }
    this.bajaVisible = false;
    this.aplicarCambioDeEstado(this.bajaEmpleados, 'inactivo', {
      fecha_cese: this.fechaBaja,
      usar_fin_de_contrato: this.usarFinDeContrato,
    });
  }

  private fechaLocal(d: Date): string {
    const dos = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`;
  }

  /** Aplica el cambio (uno o varios) y dice qué pasó con cada uno. */
  private aplicarCambioDeEstado(
    empleados: Empleado[],
    estado: 'activo' | 'inactivo',
    baja: { fecha_cese?: string; usar_fin_de_contrato?: boolean } = {}
  ): void {
    {
      this.cambiandoEstado = true;

      this.progreso.seguir(estado === 'activo' ? 'Activando trabajadores' : 'Dando de baja', this.empleadoService.cambiarEstado(empleados.map((e) => e.id), estado, baja)).subscribe({
        next: (res) => {
          this.cambiandoEstado = false;
          const { hechos, omitidos } = res.data.resumen;
          const saltados = res.data.detalle.filter((d) => !d.hecho).map((d) => `${d.nombre}: ${d.motivo}`);
          const hecho = estado === 'activo' ? 'activado(s)' : 'dado(s) de baja';

          // Activado, pero con el contrato vencido: no dura si no se renueva.
          const avisos = res.data.detalle.filter((d) => d.hecho && d.aviso).map((d) => `${d.nombre}: ${d.aviso}`);
          if (avisos.length) {
            this.toastService.warning('Renueva su contrato', avisos.join(' · '));
          }

          if (hechos && !omitidos) {
            this.toastService.success(estado === 'activo' ? 'Trabajadores activados' : 'Trabajadores dados de baja', `${hechos} ${hecho}.`);
          } else if (hechos) {
            this.toastService.warning('Se hizo solo una parte', `${hechos} ${hecho}. No se tocó a: ${saltados.join(' · ')}`);
          } else {
            this.toastService.error('No se cambió a nadie', saltados.join(' · '));
          }

          this.soltarMarcados();
          this.cargar();
        },
        error: (err) => {
          this.cambiandoEstado = false;
          this.toastService.error('No se pudo cambiar el estado', mensajeErrorApi(err, 'Inténtalo de nuevo.'));
        },
      });
    }
  }

  private ruta = inject(ActivatedRoute);

  ngOnInit(): void {
    const recordado = this.estadoListados.leer<Record<string, unknown>>('empleados');
    if (recordado) Object.assign(this, recordado);
    // Desde el Panel de Control ("trabajadores con el contrato ya vencido").
    if (this.ruta.snapshot.queryParamMap.get('filtro') === 'contrato_vencido') {
      this.filtros = { contrato_vencido: '1' };
      this.busqueda = '';
      this.pagina = 0;
      this.marcarAlCargar = true;
      // Se quita de la dirección: al recargar la página no se vuelve a marcar.
      this.router.navigate([], { relativeTo: this.ruta, queryParams: {}, replaceUrl: true });
    }
    // Con filtros recordados, sus opciones hacen falta ya para mostrar los nombres.
    if (Object.keys(this.filtros ?? {}).length) this.cargarCatalogos();
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
    // Para devolvérselo al volver (ver EstadoListadoService).
    this.estadoListados.guardar('empleados', { busqueda: this.busqueda, pagina: this.pagina, filtros: this.filtros });
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
          if (this.marcarAlCargar) {
            this.marcarAlCargar = false;
            // Después de que la tabla reciba las filas nuevas.
            setTimeout(() => this.tablaEmpleados?.marcarFilas(this.empleados));
          }
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
  private nombreDelArchivo(marcados?: number): string {
    const hoy = new Date();
    const dosDigitos = (n: number) => String(n).padStart(2, '0');
    const fecha = `${hoy.getFullYear()}-${dosDigitos(hoy.getMonth() + 1)}-${dosDigitos(hoy.getDate())}`;

    return marcados ? `Empleados marcados (${marcados}) ${fecha}.xlsx` : `Empleados ${fecha}.xlsx`;
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
    if (evento.accion === 'baja') this.confirmarCambioDeEstado([evento.fila], 'inactivo');
    if (evento.accion === 'activar') this.confirmarCambioDeEstado([evento.fila], 'activo');
  }
}
