import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';

import { PrimerosPasosService } from '../../../core/services';
import { PasoGuia, PrimerosPasos } from '../../../core/models';
import { IconComponent } from '../icon/icon.component';

/**
 * Primeros pasos: qué le toca hacer a quien acaba de entrar.
 *
 * Va DENTRO de la pantalla de inicio de cada rol —el Panel de Control de
 * RR.HH. y Mis Boletas del trabajador—, como un panel más de esa página.
 *
 * Antes fue un modal y después un aviso flotante encima de todas las
 * pantallas, y las dos formas estorbaban: una tapaba el trabajo y la otra
 * seguía al usuario a donde fuera. Una lista de tareas pendientes es
 * contenido de la pantalla de inicio, no una alarma.
 *
 * Solo se listan los pasos QUE FALTAN: un botón al lado de "Pon una
 * contraseña tuya", ya cambiada, únicamente invita a deshacerla. Los hechos
 * se cuentan arriba ("3 de 6 listos"). Cuando no falta ninguno, el panel
 * desaparece solo.
 */
@Component({
  selector: 'app-primeros-pasos',
  standalone: true,
  imports: [CommonModule, IconComponent],
  templateUrl: './primeros-pasos.component.html',
})
export class PrimerosPasosComponent implements OnInit {
  private servicio = inject(PrimerosPasosService);
  private router = inject(Router);

  guia: PrimerosPasos | null = null;

  /** Lo ocultó en esta visita (el "para siempre" lo guarda el servidor). */
  private ocultado = false;

  /** Lo pidió desde el menú: se enseña aunque ya lo hubiera ocultado. */
  private forzado = false;

  /** Plegado: se queda la cabecera con el avance y se esconde la lista. */
  plegado = false;

  ngOnInit(): void {
    this.cargar();

    // Si lo pide desde su nombre, arriba a la derecha, vuelve a salir.
    this.servicio.pedido$.subscribe((veces) => {
      if (veces > 0) {
        this.forzado = true;
        this.ocultado = false;
        this.plegado = false;
        this.cargar();
      }
    });
  }

  private cargar(): void {
    this.servicio.ver().subscribe({
      next: (res) => {
        if (res.success) this.guia = res.data;
      },
      // Si falla, el panel no aparece y la pantalla sigue igual.
      error: () => undefined,
    });
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

  get visible(): boolean {
    if (!this.guia || this.ocultado) return false;
    if (!this.pendientes.length) return false;

    return this.forzado || !this.guia.vista;
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
    this.router.navigateByUrl(paso.ruta);
  }

  /**
   * "Ya lo tengo": no vuelve a salir. Se marca en el servidor y no en este
   * navegador, para que no reaparezca al entrar desde otra computadora.
   */
  ocultar(): void {
    this.ocultado = true;
    this.forzado = false;

    if (this.guia && !this.guia.vista) {
      this.guia.vista = true;
      this.servicio.marcarVista().subscribe({ error: () => undefined });
    }
  }
}
