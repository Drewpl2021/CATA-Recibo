import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../../core/services';
import { mensajeErrorApi } from '../../../../core/utils';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { CuerpoDesplazableComponent } from '../../compartido/cuerpo-desplazable/cuerpo-desplazable.component';
import { mostrarErrorDelPanel } from '../../compartido/panel';
import { SelectorImagenComponent } from '../../compartido/selector-imagen/selector-imagen.component';
import { PaginaNosotros } from '../../portal-web.models';
import { PortalWebService } from '../../portal-web.service';
import {
  BloqueEnEdicion, FORMATOS_DE_AUDIO, FORMATOS_DE_DOCUMENTO, NOMBRE_DE_TIPO, TIPOS_DE_BLOQUE, TipoDeBloque,
  aEditor, aGuardar, bloqueNuevo,
} from './bloques';

const MAX_BLOQUES = 30;

/** Una línea del índice: hasta 60 caracteres, cortada en una palabra. */
function corto(texto: string, maximo = 60): string {
  if (texto.length <= maximo) return texto;
  return texto.slice(0, maximo).replace(/\s+\S*$/, '') + '…';
}

/** El ícono de cada tipo en el índice (del catálogo del sistema). */
const ICONO_DE_TIPO: Record<string, string> = {
  texto: 'description', destacado: 'lightbulb', lista: 'menu', hitos: 'date_range', imagen: 'camera', personas: 'people',
  mensaje: 'mail', letra: 'file', audio: 'bell', organigrama: 'layers', enlaces: 'folder',
};

/**
 * El contenido de una subpágina de Nosotros: sus bloques, en orden, cada uno
 * con los campos de su tipo. Se guardan todos juntos.
 *
 * Lo que no cumpla el contrato no se guarda: el backend lo revisa con la
 * misma clase que sirve la página y dice qué falla en qué bloque; aquí cada
 * mensaje se pone en su bloque.
 *
 * Con el molde de Ajustes: el índice de bloques a la izquierda (su tipo, una
 * línea de resumen y si tiene algo por corregir) y el bloque elegido a la
 * derecha, en vez de los treinta uno debajo de otro.
 */
@Component({
  selector: 'app-editor-bloques',
  standalone: true,
  imports: [CommonModule, FormsModule, IconComponent, FormModalComponent, SelectorImagenComponent, CuerpoDesplazableComponent],
  templateUrl: './editor-bloques.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class EditorBloquesComponent implements OnChanges {
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  /** El cuerpo del modal, con su scroll: sabe llevar la vista al primer bloque con problema. */
  @ViewChild('cuerpo') private cuerpo?: CuerpoDesplazableComponent;

  /** La página a editar; null = cerrado. */
  @Input() pagina: PaginaNosotros | null = null;
  @Output() cerrado = new EventEmitter<boolean>();

  readonly tipos = TIPOS_DE_BLOQUE;
  readonly nombreDeTipo = NOMBRE_DE_TIPO;
  readonly formatosDeAudio = FORMATOS_DE_AUDIO;
  readonly formatosDeDocumento = FORMATOS_DE_DOCUMENTO;
  readonly maxBloques = MAX_BLOQUES;

  visible = false;
  cargando = false;
  guardando = false;
  bloques: BloqueEnEdicion[] = [];
  /** La posición del bloque que se ve a la derecha. */
  actual = 0;
  tipoNuevo: TipoDeBloque = 'texto';
  private version: string | null = null;
  private cambiado = false;

  /** Los mensajes del backend, en su bloque (por posición) o de la página. */
  erroresPorBloque: Record<number, string[]> = {};
  erroresDeLaPagina: string[] = [];

  get ayudaDelTipoNuevo(): string {
    return this.tipos.find((t) => t.valor === this.tipoNuevo)?.ayuda ?? '';
  }

  ngOnChanges(cambios: SimpleChanges): void {
    if (cambios['pagina'] && this.pagina) {
      this.visible = true;
      this.cargar();
    }
  }

  cargar(): void {
    if (!this.pagina) return;
    this.cargando = true;
    this.limpiarErrores();
    this.portalWebService.getPaginaConBloques(this.pagina.id).subscribe({
      next: (res) => {
        this.version = res.data.version;
        this.bloques = res.data.bloques.map((b) => aEditor(b, res.data.imagenes));
        this.actual = 0;
        this.cambiado = false;
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar el contenido de la página.'));
      },
    });
  }

  cerrar(guardo = false): void {
    this.visible = false;
    this.cerrado.emit(guardo);
  }

  /** Cancelar (o cerrar el modal) con cambios sin guardar: se avisa que se pierden. */
  alCancelar(): void {
    if (!this.visible) return;
    if (this.cambiado) this.toastService.info('Sin guardar', 'Los cambios del contenido no se guardaron.');
    this.cerrar();
  }

  marcarCambio(): void {
    this.cambiado = true;
  }

  // ───────── Los bloques ─────────

  readonly porClave = (_: number, b: BloqueEnEdicion) => b.clave;

  elegir(i: number): void {
    this.actual = i;
  }

  iconoDe(tipo: string): string {
    return ICONO_DE_TIPO[tipo] ?? 'description';
  }

  ayudaDe(tipo: string): string {
    return this.tipos.find((t) => t.valor === tipo)?.ayuda ?? '';
  }

  /** La línea bajo el tipo en el índice: lo que hace reconocible a cada bloque. */
  resumenDe(b: BloqueEnEdicion): string {
    const n = (lista: unknown[] | undefined, uno: string, varios: string) => {
      const c = lista?.length ?? 0;
      return c ? `${c} ${c === 1 ? uno : varios}` : `sin ${varios}`;
    };
    const primera = (texto: string | undefined) => (texto ?? '').split('\n').find((l) => l.trim())?.trim() ?? '';
    return corto(this.resumenLargo(b, n, primera));
  }

  private resumenLargo(
    b: BloqueEnEdicion,
    n: (lista: unknown[] | undefined, uno: string, varios: string) => string,
    primera: (texto: string | undefined) => string,
  ): string {
    switch (b.tipo) {
      case 'texto': return b['titulo'] || primera(b['parrafos']) || 'Sin texto';
      case 'destacado': return b['rotulo'] || 'Sin rótulo';
      case 'lista': return [b['titulo'], n(b['items'], 'elemento', 'elementos')].filter(Boolean).join(' · ');
      case 'hitos': return [b['titulo'], n(b['items'], 'fecha', 'fechas')].filter(Boolean).join(' · ');
      case 'imagen': return b['imagen'] ? b['pie'] || 'Con imagen' : 'Sin imagen';
      case 'personas': return [b['titulo'], n(b['personas'], 'persona', 'personas')].filter(Boolean).join(' · ');
      case 'mensaje': return b['firmaNombre'] ? `Firma ${b['firmaNombre']}` : 'Sin firma';
      case 'letra': return [b['titulo'], n(b['partes'], 'parte', 'partes')].filter(Boolean).join(' · ');
      case 'audio': return b['titulo'] || 'Sin título';
      case 'organigrama': return primera(b['arbol']).split(' — ')[0] || 'Sin cargos';
      case 'enlaces': return [b['titulo'], n(b['enlaces'], 'documento', 'documentos')].filter(Boolean).join(' · ');
      default: return 'Se conserva tal cual';
    }
  }

  /** El nuevo va al final y queda elegido. */
  agregarBloque(): void {
    if (this.bloques.length >= MAX_BLOQUES) return;
    this.bloques = [...this.bloques, bloqueNuevo(this.tipoNuevo)];
    this.actual = this.bloques.length - 1;
    this.alCambiarLaLista();
  }

  /** El elegido acompaña al bloque que se mueve. */
  moverBloque(i: number, direccion: -1 | 1): void {
    const j = i + direccion;
    if (j < 0 || j >= this.bloques.length) return;
    const nuevos = [...this.bloques];
    [nuevos[i], nuevos[j]] = [nuevos[j], nuevos[i]];
    this.bloques = nuevos;
    if (this.actual === i) this.actual = j;
    this.alCambiarLaLista();
  }

  /** Queda elegido el que ocupa su lugar (o el anterior, si era el último). */
  quitarBloque(i: number): void {
    this.bloques = this.bloques.filter((_, j) => j !== i);
    this.actual = Math.min(this.actual > i ? this.actual - 1 : this.actual, Math.max(this.bloques.length - 1, 0));
    this.alCambiarLaLista();
  }

  /** Los mensajes iban por posición: al mover o quitar, ya no calzan. */
  private alCambiarLaLista(): void {
    this.cambiado = true;
    this.limpiarErrores();
  }

  // ───────── Las listas dentro de un bloque (ítems, personas, partes…) ─────────

  agregar<T>(lista: T[], nuevo: T, maximo: number): void {
    if (lista.length < maximo) {
      lista.push(nuevo);
      this.cambiado = true;
    }
  }

  mover<T>(lista: T[], i: number, direccion: -1 | 1): void {
    const j = i + direccion;
    if (j < 0 || j >= lista.length) return;
    [lista[i], lista[j]] = [lista[j], lista[i]];
    this.cambiado = true;
  }

  quitar<T>(lista: T[], i: number): void {
    lista.splice(i, 1);
    this.cambiado = true;
  }

  nuevaPersona(): object {
    return { id: null, nombre: '', cargo: '', foto: null, clave: Math.random() };
  }

  // ───────── Guardar ─────────

  private limpiarErrores(): void {
    this.erroresPorBloque = {};
    this.erroresDeLaPagina = [];
  }

  /** «Bloque 3 (Lista): …» → en el bloque 3, sin el prefijo. */
  private repartirErrores(mensajes: string[]): void {
    this.limpiarErrores();
    for (const m of mensajes) {
      const partes = m.match(/^Bloque (\d+)(?: \([^)]*\))?: (.*)$/);
      if (partes) {
        const i = Number(partes[1]) - 1;
        (this.erroresPorBloque[i] ??= []).push(partes[2]);
      } else {
        this.erroresDeLaPagina.push(m);
      }
    }
  }

  /** Los mensajes de un bloque, o ninguno. */
  erroresDe(i: number): string[] {
    return this.erroresPorBloque[i] ?? [];
  }

  get cuantosConErrores(): number {
    return Object.keys(this.erroresPorBloque).length;
  }

  guardar(): void {
    if (!this.pagina) return;

    this.guardando = true;
    this.portalWebService.guardarBloques(this.pagina.id, this.bloques.map((b) => aGuardar(b)), this.version).subscribe({
      next: () => {
        this.guardando = false;
        this.toastService.success(
          'Contenido guardado',
          this.pagina?.estado === 'publicado' ? 'El portal lo mostrará en hasta un minuto.' : 'La página sigue en borrador: publícala cuando esté lista.',
        );
        this.cerrar(true);
      },
      error: (err: HttpErrorResponse) => {
        this.guardando = false;
        const mensajes: string[] = err.error?.errors?.bloques ?? [];
        if (err.status === 422 && mensajes.length) {
          this.repartirErrores(mensajes);
          // A la derecha, el primer bloque con problema.
          const primero = Math.min(...Object.keys(this.erroresPorBloque).map(Number));
          if (Number.isFinite(primero)) this.actual = primero;
          // Lleva al primer bloque con problema: con 15 personas, puede estar muy abajo.
          this.cuerpo?.irAlPrimerError();
          this.toastService.error(
            'No se guardó',
            this.cuantosConErrores
              ? `Hay que corregir ${this.cuantosConErrores === 1 ? 'un bloque' : `${this.cuantosConErrores} bloques`}: cada uno dice qué le falta.`
              : mensajes[0],
          );
          return;
        }
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar el contenido.');
      },
    });
  }
}
