import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

export interface DocumentoMock {
  id: string;
  periodo: string;
  fechaEmision: string;
  estadoFirma: 'Firmado' | 'Pendiente';
  estadoRevisado: 'Revisado' | 'Pendiente';
}

@Component({
  selector: 'app-documentos-list',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './documentos-list.component.html',
  styleUrl: './documentos-list.component.scss'
})
export class DocumentosListComponent implements OnInit {
  documentos: DocumentoMock[] = [];
  searchTerm = '';
  mostrarConfirmacion = false;
  docAEliminar: DocumentoMock | null = null;

  // Modal de nuevo documento
  mostrarModal = false;
  nuevoPeriodo = '';
  nuevaFechaEmision = '';

  ngOnInit(): void {
    this.documentos = [
      { id: 'DOC-001', periodo: 'Enero 2026',   fechaEmision: '2026-01-15', estadoFirma: 'Firmado',  estadoRevisado: 'Revisado' },
      { id: 'DOC-002', periodo: 'Febrero 2026',  fechaEmision: '2026-02-15', estadoFirma: 'Firmado',  estadoRevisado: 'Revisado' },
      { id: 'DOC-003', periodo: 'Marzo 2026',    fechaEmision: '2026-03-15', estadoFirma: 'Pendiente', estadoRevisado: 'Pendiente' }
    ];
  }

  get filteredDocumentos(): DocumentoMock[] {
    if (!this.searchTerm) return this.documentos;
    const lower = this.searchTerm.toLowerCase();
    return this.documentos.filter(d =>
      d.periodo.toLowerCase().includes(lower) ||
      d.fechaEmision.toLowerCase().includes(lower)
    );
  }

  // Helper to return simple signed status
  firmaIcon(doc: DocumentoMock): string {
    return doc.estadoFirma === 'Firmado' ? '✅' : '❌';
  }


  abrirModalNuevo(): void {
    this.nuevoPeriodo = '';
    this.nuevaFechaEmision = '';
    this.mostrarModal = true;
  }

  cerrarModal(): void {
    this.mostrarModal = false;
  }

  guardarNuevo(): void {
    if (!this.nuevoPeriodo || !this.nuevaFechaEmision) {
      alert('Por favor completa el Periodo y la Fecha de Emisión.');
      return;
    }
    const nuevo: DocumentoMock = {
      id: `DOC-00${this.documentos.length + 1}`,
      periodo: this.nuevoPeriodo,
      fechaEmision: this.nuevaFechaEmision,
      estadoFirma: 'Pendiente',
      estadoRevisado: 'Pendiente'
    };
    this.documentos.unshift(nuevo);
    this.cerrarModal();
  }

  descargarDoc(doc: DocumentoMock): void {
    alert(`Descargando documento: ${doc.periodo}\n(Próximamente conectado al backend)`);
  }

  firmarDoc(doc: DocumentoMock): void {
    doc.estadoFirma = 'Firmado';
  }

  confirmarEliminar(doc: DocumentoMock): void {
    this.docAEliminar = doc;
    this.mostrarConfirmacion = true;
  }

  cancelarEliminar(): void {
    this.docAEliminar = null;
    this.mostrarConfirmacion = false;
  }

  eliminarDoc(): void {
    if (this.docAEliminar) {
      this.documentos = this.documentos.filter(d => d.id !== this.docAEliminar!.id);
      this.docAEliminar = null;
      this.mostrarConfirmacion = false;
    }
  }
}
