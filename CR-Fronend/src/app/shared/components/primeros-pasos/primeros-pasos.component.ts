import { Component, EventEmitter, OnInit, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';

import { PrimerosPasosService } from '../../../core/services';
import { PasoGuia, PrimerosPasos } from '../../../core/models';

/**
 * Primeros pasos: qué le toca hacer a quien acaba de entrar.
 *
 * Vive detrás del foquito de la barra de arriba. Antes fue un modal que
 * saltaba al entrar, después un aviso encima de todas las pantallas y
 * después un panel de la pantalla de inicio; las tres formas ocupaban sitio
 * de trabajo para algo que solo hace falta los primeros días. Detrás de un
 * ícono está siempre a mano y no estorba nunca.
 *
 * Solo se listan los pasos QUE FALTAN: un botón al lado de "Pon una
 * contraseña tuya", ya cambiada, únicamente invita a deshacerla. Los hechos
 * se cuentan arriba ("3 de 6 listos").
 */
@Component({
  selector: 'app-primeros-pasos',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './primeros-pasos.component.html',
})
export class PrimerosPasosComponent implements OnInit {
  private servicio = inject(PrimerosPasosService);
  private router = inject(Router);

  /** Para que quien lo abrió pueda cerrarlo. */
  @Output() cerrar = new EventEmitter<void>();

  guia: PrimerosPasos | null = null;

  ngOnInit(): void {
    this.servicio.estado$.subscribe((estado) => (this.guia = estado));
  }

  /**
   * Los pasos que faltan, con el número que les toca en la guía completa:
   * así el cuarto sigue siendo el cuarto aunque los tres de antes ya estén
   * hechos, y se entiende que van en ese orden.
   */
  get pendientes(): (PasoGuia & { numero: number })[] {
    return (this.guia?.pasos ?? [])
      .map((paso, i) => ({ ...paso, numero: i + 1 }))
      .filter((paso) => !paso.hecho);
  }

  /** De 0 a 100, para la barra. */
  get avance(): number {
    if (!this.guia?.total) return 0;
    return Math.round((this.guia.hechos / this.guia.total) * 100);
  }

  get intro(): string {
    return this.guia?.rol === 'empleado'
      ? 'Para dejar tu cuenta lista y estar al día con tus boletas.'
      : 'Para poder pagar la primera planilla.';
  }

  irAlPaso(paso: PasoGuia): void {
    this.cerrar.emit();
    this.router.navigateByUrl(paso.ruta);
  }

  /**
   * "Ya lo tengo": el foquito deja de insistir con el globito, pero sigue
   * ahí por si quiere volver a mirar. Se marca en el servidor y no en este
   * navegador, para que no reaparezca al entrar desde otra computadora.
   */
  noInsistir(): void {
    if (this.guia && !this.guia.vista) {
      this.servicio.marcarVista().subscribe({ error: () => undefined });
    }

    this.cerrar.emit();
  }
}
