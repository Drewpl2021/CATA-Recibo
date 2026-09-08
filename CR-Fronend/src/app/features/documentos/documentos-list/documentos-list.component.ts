import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MisDocumentosService } from '../../../core/services';
import { Documento } from '../../../core/models';
import { ToastService } from '../../../core/services';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PistaDirective } from '../../../shared/directives/pista.directive';

@Component({
  selector: 'app-documentos-list',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent, IconComponent, PistaDirective],
  templateUrl: './documentos-list.component.html',
  styleUrl: './documentos-list.component.scss'
})
export class DocumentosListComponent implements OnInit {
  documentos: Documento[] = [];
  searchTerm = '';
  cargando = false;

  // Modal de firmar
  mostrarModalFirmar = false;
  docAFirmar: Documento | null = null;
  passwordFirma = '';
  firmando = false;

  constructor(
    private misDocumentosService: MisDocumentosService,
    private toastService: ToastService
  ) {}

  ngOnInit(): void {
    this.cargarDocumentos();
  }

  cargarDocumentos(): void {
    this.cargando = true;
    this.misDocumentosService.getMisDocumentos().subscribe({
      next: (res) => {
        if (res.success) this.documentos = res.data;
        this.cargando = false;
      },
      error: (err) => {
        console.error('Error cargando documentos', err);
        this.cargando = false;
      }
    });
  }

  get filteredDocumentos(): Documento[] {
    if (!this.searchTerm) return this.documentos;
    const lower = this.searchTerm.toLowerCase();
    return this.documentos.filter(d => {
      const periodo = this.getPeriodo(d).toLowerCase();
      return periodo.includes(lower);
    });
  }

  getPeriodo(doc: Documento): string {
    if (!doc.planilla) return doc.tipo;
    const meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
      'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return `${meses[doc.planilla.mes] || doc.planilla.mes} ${doc.planilla.anio}`;
  }

  getEstadoLabel(doc: Documento): string {
    switch (doc.estado_firma) {
      case 'firmado':  return 'Firmado';
      case 'visto':    return 'Visto';
      default:         return 'Pendiente';
    }
  }

  getEstadoClass(doc: Documento): string {
    switch (doc.estado_firma) {
      case 'firmado':  return 'badge-active';
      case 'visto':    return 'badge-visto';
      default:         return 'badge-vacaciones';
    }
  }

  marcarVisto(doc: Documento): void {
    if (doc.estado_firma !== 'pendiente') return;
    this.misDocumentosService.marcarVisto(doc.id).subscribe({
      next: (res) => {
        if (res.success) {
          doc.estado_firma = 'visto';
          doc.fecha_visto = res.data.fecha_visto;
        }
      },
      error: (err) => {
        console.error('Error marcando como visto', err);
        this.toastService.error('Error', err?.error?.message || 'Error al marcar como visto.');
      }
    });
  }

  abrirModalFirmar(doc: Documento): void {
    this.docAFirmar = doc;
    this.passwordFirma = '';
    this.mostrarModalFirmar = true;
  }

  cerrarModalFirmar(): void {
    this.docAFirmar = null;
    this.passwordFirma = '';
    this.mostrarModalFirmar = false;
  }

  confirmarFirma(): void {
    if (!this.docAFirmar || !this.passwordFirma) {
      this.toastService.warning('Atención', 'Por favor, ingresa tu contraseña para firmar.');
      return;
    }
    this.firmando = true;
    this.misDocumentosService.firmar(this.docAFirmar.id, this.passwordFirma).subscribe({
      next: (res) => {
        if (res.success) {
          const idx = this.documentos.findIndex(d => d.id === this.docAFirmar!.id);
          if (idx !== -1) this.documentos[idx] = res.data;
          this.toastService.success('¡Firma Exitosa!', 'Documento firmado correctamente.');
          this.cerrarModalFirmar();
        }
        this.firmando = false;
      },
      error: (err) => {
        console.error('Error firmando documento', err);
        this.toastService.error('Error de Firma', err?.error?.message || 'Error al firmar el documento.');
        this.firmando = false;
      }
    });
  }
}
