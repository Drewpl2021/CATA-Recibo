import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { SeccionEmpleadoBase } from './seccion-base';
import { ConsultaDniService, ToastService } from '../../../../core/services';
import { PersonaPorDni } from '../../../../core/models';
import { mensajeErrorApi } from '../../../../core/utils';

/**
 * Paso 1: quién es la persona.
 *
 * El DNI se busca en el padrón y trae los nombres ya escritos. No es solo
 * ahorrarle tecleo a RR.HH.: el nombre que se guarde aquí es el que sale
 * impreso en todas las boletas de esa persona, y una errata ahí se arrastra
 * el año entero.
 *
 * La búsqueda también avisa si ese DNI ya tiene ficha en el colegio. Antes
 * eso solo se descubría al guardar, con los cinco pasos ya llenados.
 */
@Component({
  selector: 'app-seccion-personales',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './seccion-personales.component.html',
})
export class SeccionPersonalesComponent extends SeccionEmpleadoBase {
  private consultaDni = inject(ConsultaDniService);
  private toast = inject(ToastService);
  private router = inject(Router);

  buscando = false;
  resultado: PersonaPorDni | null = null;

  /**
   * Lo que escribió la búsqueda anterior, campo por campo.
   *
   * Sirve para poder deshacerlo: si se busca otro DNI y ese no aparece, los
   * nombres del anterior tienen que irse — dejarlos es enseñar el nombre de
   * una persona junto al DNI de otra. Se guarda lo que se escribió para
   * borrar solo eso y nunca lo que RR.HH. haya tecleado a mano.
   */
  private rellenado: Record<string, string> = {};

  /** El DNI tal como está escrito, sin lo que no sea número. */
  private get dni(): string {
    return String(this.form.get('dni')?.value ?? '').replace(/\D/g, '');
  }

  /** La búsqueda solo tiene sentido al dar de alta, y con los 8 dígitos. */
  get puedeBuscar(): boolean {
    return this.esNuevo && !this.soloLectura && this.dni.length === 8 && !this.buscando;
  }

  get mostrarBuscador(): boolean {
    return this.esNuevo && !this.soloLectura;
  }

  /** El aviso de que ese DNI ya tiene ficha, para poder ir a verla. */
  get yaEsEmpleado(): PersonaPorDni['yaEsEmpleado'] | undefined {
    return this.resultado?.yaEsEmpleado;
  }

  /** Al escribir otro DNI, el resultado de antes deja de valer. */
  alEscribirDni(): void {
    if (this.resultado) this.resultado = null;
  }

  buscarPorDni(): void {
    if (!this.puedeBuscar) {
      if (!this.buscando) {
        this.toast.error('Falta el DNI', 'Escribe los 8 dígitos para poder buscarlo.');
      }
      return;
    }

    this.buscando = true;
    this.resultado = null;
    this.deshacerRelleno();

    this.consultaDni.buscar(this.dni).subscribe({
      next: (res) => {
        this.buscando = false;
        if (!res.success) return;

        this.resultado = res.data;

        if (res.data.yaEsEmpleado) {
          this.toast.error('Ese DNI ya está registrado', res.data.mensaje ?? '');
          return;
        }

        if (!res.data.encontrado) {
          this.toast.info('No aparece en el padrón', 'Escribe sus datos a mano.');
          return;
        }

        this.rellenar(res.data);
        this.toast.success('Datos encontrados', `Traídos de ${res.data.fuente}. Revísalos antes de seguir.`);
      },
      error: (err) => {
        this.buscando = false;
        this.toast.error('No se pudo buscar', mensajeErrorApi(err, 'La consulta del DNI no respondió.'));
      },
    });
  }

  /**
   * Nombres y apellidos se pisan: son el dato oficial y es a lo que se vino.
   * La fecha de nacimiento y la dirección solo se rellenan si estaban en
   * blanco — la dirección del padrón suele estar vieja, y si RR.HH. ya puso
   * la de ahora, cambiarla sería un mal negocio.
   */
  private rellenar(persona: PersonaPorDni): void {
    this.rellenado = {
      nombre: persona.nombres ?? '',
      apellido: persona.apellidos ?? '',
    };

    this.form.patchValue(this.rellenado);

    if (persona.fecha_nacimiento && !this.form.get('fecha_nacimiento')?.value) {
      this.rellenado['fecha_nacimiento'] = persona.fecha_nacimiento;
      this.form.patchValue({ fecha_nacimiento: persona.fecha_nacimiento });
    }

    if (persona.direccion && !this.form.get('direccion')?.value) {
      this.rellenado['direccion'] = persona.direccion;
      this.form.patchValue({ direccion: persona.direccion });
    }

    this.form.get('nombre')?.markAsDirty();
    this.form.get('apellido')?.markAsDirty();
  }

  /**
   * Quita lo que puso la búsqueda anterior, y solo eso: un campo que el
   * usuario cambió después ya no coincide con lo que escribimos, y se queda
   * como está.
   */
  private deshacerRelleno(): void {
    for (const [campo, valor] of Object.entries(this.rellenado)) {
      if (this.form.get(campo)?.value === valor) {
        this.form.patchValue({ [campo]: '' });
      }
    }

    this.rellenado = {};
  }

  verFichaExistente(): void {
    if (this.yaEsEmpleado) {
      this.router.navigate(['/inicio/empleados/ver', this.yaEsEmpleado.id]);
    }
  }
}
