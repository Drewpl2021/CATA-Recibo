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

  ngOnInit(): void {
    // Datos simulados (mock)
    this.documentos = [
      {
        id: 'DOC-001',
        periodo: 'Enero 2026',
        fechaEmision: '2026-01-15',
        estadoFirma: 'Firmado',
        estadoRevisado: 'Revisado'
      },
      {
        id: 'DOC-002',
        periodo: 'Febrero 2026',
        fechaEmision: '2026-02-15',
        estadoFirma: 'Firmado',
        estadoRevisado: 'Revisado'
      },
      {
        id: 'DOC-003',
        periodo: 'Marzo 2026',
        fechaEmision: '2026-03-15',
        estadoFirma: 'Pendiente',
        estadoRevisado: 'Pendiente'
      }
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

  verAccion(doc: DocumentoMock): void {
    alert(`Acción para documento del periodo: ${doc.periodo}`);
  }
}
