import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';

import { EmpleadoService, ToastService, ConfirmService } from '../../../core/services';
import { Empleado } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
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
  imports: [CommonModule, PageHeaderComponent, DataTableComponent],
  templateUrl: './empleados-list.component.html',
})
export class EmpleadosListComponent implements OnInit {
  private router = inject(Router);
  private empleadoService = inject(EmpleadoService);
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
      .getPagina({ page: this.pagina, size: this.TAMANO_PAGINA, search: this.busqueda || undefined })
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
