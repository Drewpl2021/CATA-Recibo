import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DomSanitizer } from '@angular/platform-browser';
import { PdfViewerModule } from 'ng2-pdf-viewer';
import { GlobalWorkerOptions } from 'pdfjs-dist';
import { AuthService } from '../../core/services/auth.service';
import { BoletasService } from '../../core/services/boletas.service';
import { MisDocumentosService } from '../../core/services/mis-documentos.service';
import { ToastService } from '../../core/services/toast.service';

const MESES: Record<number, string> = {
  1: 'Enero', 2: 'Febrero', 3: 'Marzo', 4: 'Abril',
  5: 'Mayo', 6: 'Junio', 7: 'Julio', 8: 'Agosto',
  9: 'Setiembre', 10: 'Octubre', 11: 'Noviembre', 12: 'Diciembre'
};

export interface BoletaRow {
  id: string;
  tipoDocumento: string;
  numeroDocumento: string;
  fechaEmision: string;
  mes: string;
  mesNum: number;
  montoTotal: number;
  anio: number;
  firmado: { fecha: string } | null;
  avisoEnviado: { fecha: string; correo: string } | null;
  revisado: { fecha: string } | null;
  descargado: { fecha: string } | null;
  correo: string;
  celular: string;
}

@Component({
  selector: 'app-mis-boletas',
  standalone: true,
  imports: [CommonModule, FormsModule, PdfViewerModule],
  templateUrl: './mis-boletas.component.html',
  styleUrl: './mis-boletas.component.scss'
})
export class MisBoletasComponent implements OnInit {
  anios: string[] = ['2026', '2025', '2024', '2023', '2022'];
  selectedAnio: string = new Date().getFullYear().toString();
  boletas: BoletaRow[] = [];
  isLoading = false;
  errorMsg = '';
  isEmpleado = false;
  userName = '';
  
  // Modal states
  showPdfModal = false;
  pdfUrl: string | null = null;
  pdfBoletaName = '';
  private currentPdfBlob: Blob | null = null;

  // Metrics
  boletasPendientes: number = 0;
  ultimoReciboMes: string = '-';
  ultimoReciboDias: string = '';
  vacacionesDisponibles: number = 14;

  // Sign Modal state
  showSignModal = false;
  signPassword = '';
  boletaAFirmar: BoletaRow | null = null;
  signErrorMsg = '';
  isSigning = false;

  constructor(
    private authService: AuthService,
    private boletasService: BoletasService,
    private misDocumentosService: MisDocumentosService,
    private sanitizer: DomSanitizer,
    private toastService: ToastService
  ) {}

  ngOnInit(): void {
    // Configurar el worker de PDF.js apuntando al archivo estático
    GlobalWorkerOptions.workerSrc = '/pdf.worker.min.mjs';

    const user = this.authService.getUser();
    const rol = user?.rol?.toLowerCase() || '';
    const email = user?.email?.toLowerCase() || '';
    
    // Si es administrador o rrhh por correo, NO es empleado (a nivel de vista).
    const isAdmin = rol === 'admin' || rol === 'rrhh' || email === 'admin@colegio.com' || email === 'rrhh@colegio.com';
    this.isEmpleado = !isAdmin;
    
    this.userName = user?.name ? user.name.split(' ')[0] : 'Usuario';
    
    this.cargarBoletas();
  }

  cargarBoletas(): void {
    if (!this.authService.isLoggedIn()) {
      this.errorMsg = 'Sesión expirada. Por favor vuelve a iniciar sesión.';
      return;
    }

    const user = this.authService.getUser();
    if (!user?.empleado_id) {
      this.errorMsg = 'Esta cuenta no tiene un perfil de empleado asociado para mostrar boletas.';
      return;
    }

    this.isLoading = true;
    this.errorMsg = '';

    this.misDocumentosService.getMisDocumentos().subscribe({
      next: (res) => {
        this.isLoading = false;
        if (!res.success) {
          this.boletas = [];
          this.errorMsg = 'No se pudieron cargar las boletas.';
          return;
        }
        
        const todos = res.data ?? [];
        // Filtrar por tipo boleta y el año seleccionado
        const boletasDocs = todos.filter(d => 
          d.tipo === 'boleta' && 
          d.planilla && 
          String(d.planilla.anio) === this.selectedAnio
        );

        this.boletas = boletasDocs.map((d) => ({
          id: d.id, // usamos el id del Documento para poder firmarlo
          tipoDocumento: 'Boleta de Pago',
          numeroDocumento: `BP-${d.planilla?.anio}-${String(d.planilla?.mes).padStart(2, '0')}`,
          fechaEmision: d.created_at ? this.formatFecha(d.created_at).split(' ')[0] : '',
          mes: MESES[d.planilla?.mes!] ?? `Mes ${d.planilla?.mes}`,
          mesNum: d.planilla?.mes || 0,
          montoTotal: (d.planilla as any)?.total ?? 0,
          anio: d.planilla?.anio || 0,
          // Estado de firma real que viene del backend
          firmado: d.estado_firma === 'firmado' ? { fecha: d.fecha_firma ? this.formatFecha(d.fecha_firma) : this.formatFecha(d.created_at) } : null,
          avisoEnviado: null,
          revisado: null,
          descargado: null,
          correo: '',
          celular: ''
        }));
        
        this.calcularMetricas(boletasDocs);
      },
      error: () => {
        this.isLoading = false;
        this.errorMsg = 'Error al cargar las boletas. Verifica tu conexión con el servidor.';
      }
    });
  }

  calcularMetricas(planillas: any[]): void {
    this.boletasPendientes = this.boletas.filter(b => !b.firmado).length;
    
    if (this.boletas.length > 0) {
      const ultima = [...this.boletas].sort((a, b) => b.mesNum - a.mesNum)[0];
      this.ultimoReciboMes = ultima.mes;
      
      const planillaOriginal = planillas.find(p => p.id === ultima.id);
      if (planillaOriginal && planillaOriginal.created_at) {
        const createdDate = new Date(planillaOriginal.created_at);
        const diffTime = Math.abs(new Date().getTime() - createdDate.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        this.ultimoReciboDias = `Disponible hace ${diffDays} día(s)`;
      } else {
        this.ultimoReciboDias = 'Disponible recientemente';
      }
    } else {
      this.ultimoReciboMes = '-';
      this.ultimoReciboDias = '';
    }
  }

  visualizar(): void {
    this.cargarBoletas();
  }

  verBoleta(boleta: BoletaRow): void {
    if (!this.authService.isLoggedIn()) {
      this.errorMsg = 'Sesión expirada. Por favor vuelve a iniciar sesión.';
      return;
    }

    this.isLoading = true;
    this.errorMsg = '';

    this.boletasService.descargarBoleta(boleta.mesNum, String(boleta.anio)).subscribe({
      next: (blob) => {
        this.isLoading = false;
        this.currentPdfBlob = blob;
        // Para ng2-pdf-viewer pasamos el object URL directo (como string)
        this.pdfUrl = URL.createObjectURL(blob);
        this.pdfBoletaName = `Boleta de ${boleta.mes} ${boleta.anio}`;
        this.showPdfModal = true;
      },
      error: () => {
        this.isLoading = false;
        this.errorMsg = `Error al generar la boleta de ${boleta.mes} ${boleta.anio}.`;
      }
    });
  }

  closePdfModal(): void {
    this.showPdfModal = false;
    if (this.pdfUrl) {
      URL.revokeObjectURL(this.pdfUrl); // Liberar memoria
    }
    this.pdfUrl = null;
    this.currentPdfBlob = null;
  }

  descargarPdfDirecto(): void {
    if (this.currentPdfBlob && this.pdfUrl) {
      const a = document.createElement('a');
      a.href = this.pdfUrl;
      a.download = `${this.pdfBoletaName.replace(/ /g, '_')}.pdf`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    }
  }

  firmarBoleta(boleta: BoletaRow): void {
    if (boleta.firmado) {
      this.toastService.info('Información', `Esta boleta ya fue firmada el ${boleta.firmado.fecha}.`);
      return;
    }
    this.boletaAFirmar = boleta;
    this.signPassword = '';
    this.signErrorMsg = '';
    this.showSignModal = true;
    document.body.style.overflow = 'hidden';
  }

  closeSignModal(): void {
    this.showSignModal = false;
    this.boletaAFirmar = null;
    this.signPassword = '';
    this.signErrorMsg = '';
    this.isSigning = false;
    document.body.style.overflow = '';
  }

  confirmarFirma(): void {
    if (!this.signPassword) {
      this.signErrorMsg = 'Por favor, ingresa tu contraseña para firmar.';
      return;
    }

    this.isSigning = true;
    this.signErrorMsg = '';

    if (!this.boletaAFirmar) return;

    this.misDocumentosService.firmar(this.boletaAFirmar.id, this.signPassword).subscribe({
      next: (res) => {
        this.isSigning = false;
        if (res.success) {
          this.toastService.success('¡Firma Exitosa!', `Boleta de ${this.boletaAFirmar!.mes} firmada correctamente.`);
          this.closeSignModal();
          this.cargarBoletas(); // Recargar para actualizar estados
        } else {
          this.signErrorMsg = res.message || 'Error al firmar.';
        }
      },
      error: (err) => {
        this.isSigning = false;
        this.signErrorMsg = err.error?.message || 'Contraseña incorrecta o error del servidor.';
      }
    });
  }

  private formatFecha(isoDate: string): string {
    const d = new Date(isoDate);
    if (isNaN(d.getTime())) return '';
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    const hh = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${dd}/${mm}/${yyyy} ${hh}:${min}`;
  }
}
