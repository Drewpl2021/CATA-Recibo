import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IconComponent } from '../icon/icon.component';

/** "Hoja de Vida.PDF" → "pdf" */
function extensionDe(nombre: string): string {
  const punto = nombre.lastIndexOf('.');
  return punto >= 0 ? nombre.slice(punto + 1).toLowerCase() : '';
}

/**
 * Elegir archivos, arrastrándolos o con un clic.
 *
 * Existe porque cada pantalla que subía algo lo hacía a su manera: la ficha
 * del empleado con el botón gris del navegador ("Seleccionar archivo"), la
 * importación de conceptos con su propia zona, y Mis Documentos no tenía
 * nada. Ahora las tres usan esto.
 *
 * Valida extensión y peso ANTES de mandar nada, con los mismos topes que el
 * backend: enterarse de que el archivo pesaba demasiado después de subirlo es
 * la peor forma de enterarse. El backend lo vuelve a revisar igual.
 *
 *   <app-selector-archivo
 *     [extensiones]="['pdf','docx']" [pesoMaximoMb]="5"
 *     [(archivos)]="cv" ayuda="PDF o Word, hasta 5 MB.">
 *   </app-selector-archivo>
 */
@Component({
  selector: 'app-selector-archivo',
  standalone: true,
  imports: [CommonModule, IconComponent],
  templateUrl: './selector-archivo.component.html',
})
export class SelectorArchivoComponent {
  /** Lo que acepta, sin punto: ['pdf', 'docx']. Vacío = cualquiera. */
  @Input() extensiones: string[] = [];
  @Input() pesoMaximoMb = 5;
  /** Varios a la vez (los CVs en lote) o uno solo. */
  @Input() multiple = false;
  @Input() deshabilitado = false;
  /** Mientras la pantalla procesa lo elegido (leer un Excel, subir). */
  @Input() ocupado = false;
  @Input() textoOcupado = 'Procesando el archivo…';
  @Input() texto = 'Arrastra aquí el archivo o haz clic para elegirlo';
  @Input() ayuda = '';
  /** Mostrar debajo la lista de lo elegido, con su botón de quitar. */
  @Input() mostrarLista = true;

  /** Lo elegido. La pantalla lo controla con [(archivos)]. */
  @Input() archivos: File[] = [];
  @Output() archivosChange = new EventEmitter<File[]>();

  error = '';
  arrastrando = false;

  /** El atributo accept del input, a partir de las extensiones. */
  get accept(): string {
    return this.extensiones.map((e) => '.' + e).join(',');
  }

  alElegir(evento: Event): void {
    const input = evento.target as HTMLInputElement;
    const lista = Array.from(input.files ?? []);
    // Se vacía para que elegir otra vez el mismo archivo vuelva a avisar.
    input.value = '';
    this.recibir(lista);
  }

  alArrastrar(evento: DragEvent): void {
    evento.preventDefault();
    if (!this.deshabilitado && !this.ocupado) this.arrastrando = true;
  }

  alSoltar(evento: DragEvent): void {
    evento.preventDefault();
    this.arrastrando = false;
    if (this.deshabilitado || this.ocupado) return;
    this.recibir(Array.from(evento.dataTransfer?.files ?? []));
  }

  quitar(archivo: File): void {
    this.archivos = this.archivos.filter((a) => a !== archivo);
    this.error = '';
    this.archivosChange.emit(this.archivos);
  }

  /** "340 KB", "1.2 MB". */
  peso(archivo: File): string {
    const kb = archivo.size / 1024;
    return kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(kb))} KB`;
  }

  private recibir(lista: File[]): void {
    if (!lista.length) return;
    if (!this.multiple) lista = lista.slice(0, 1);

    const formatoMalo = this.extensiones.length
      ? lista.find((f) => !this.extensiones.includes(extensionDe(f.name)))
      : undefined;
    if (formatoMalo) {
      this.error = `«${formatoMalo.name}» no es un formato admitido. Se aceptan: ${this.extensiones.join(', ')}.`;
      return;
    }

    const pesado = lista.find((f) => f.size > this.pesoMaximoMb * 1024 * 1024);
    if (pesado) {
      this.error = `«${pesado.name}» pasa de ${this.pesoMaximoMb} MB.`;
      return;
    }

    this.error = '';
    // En modo varios se suman a los que ya había; uno con el mismo nombre
    // reemplaza al anterior en vez de quedar repetido.
    this.archivos = this.multiple
      ? [...this.archivos.filter((a) => !lista.some((n) => n.name === a.name)), ...lista]
      : lista;
    this.archivosChange.emit(this.archivos);
  }
}
