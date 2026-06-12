import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DomSanitizer } from '@angular/platform-browser';
import { PdfViewerModule } from 'ng2-pdf-viewer';
import { GlobalWorkerOptions } from 'pdfjs-dist';
import { AuthService } from '../../core/services/auth.service';
import { BoletasService } from '../../core/services/boletas.service';

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

  // Sign Modal state
  showSignModal = false;
  signPassword = '';
  boletaAFirmar: BoletaRow | null = null;
  signErrorMsg = '';
  isSigning = false;

  constructor(
    private authService: AuthService,
    private boletasService: BoletasService,
    private sanitizer: DomSanitizer
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

    this.boletasService.getMiPlanilla(this.selectedAnio).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (!res.success) {
          this.boletas = [];
          this.errorMsg = 'No se pudieron cargar las boletas.';
          return;
        }
        const planillas = res.data ?? [];
        this.boletas = planillas.map((p) => ({
          id: p.id,
          tipoDocumento: 'Boleta de Pago',
          numeroDocumento: `BP-${p.anio}-${String(p.mes).padStart(2, '0')}`,
          fechaEmision: p.created_at ? this.formatFecha(p.created_at).split(' ')[0] : '',
          mes: MESES[p.mes] ?? `Mes ${p.mes}`,
          mesNum: p.mes,
          montoTotal: p.total ?? 0,
          anio: p.anio,
          // Temporal para pruebas: Solo los primeros 3 meses están firmados, el resto pendiente
          firmado: (p.created_at && p.mes <= 3) ? { fecha: this.formatFecha(p.created_at) } : null,
          avisoEnviado: null,
          revisado: null,
          descargado: null,
          correo: '',
          celular: ''
        }));
      },
      error: () => {
        this.isLoading = false;
        this.errorMsg = 'Error al cargar las boletas. Verifica tu conexión con el servidor.';
      }
    });
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
      alert(`Esta boleta ya fue firmada el ${boleta.firmado.fecha}.`);
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

    // Simular llamada a API para verificar contraseña y firmar
    setTimeout(() => {
      this.isSigning = false;
      // Aquí se validaría contra el backend real. Por ahora simulamos éxito:
      if (this.signPassword.length < 4) {
        this.signErrorMsg = 'Contraseña incorrecta. (Simulado: usa más de 3 caracteres)';
        return;
      }
      
      if (this.boletaAFirmar) {
        this.boletaAFirmar.firmado = { 
          fecha: new Date().toLocaleDateString('es-PE') + ' ' + new Date().toLocaleTimeString('es-PE') 
        };
        alert(`¡Éxito! Boleta de ${this.boletaAFirmar.mes} firmada correctamente.`);
      }
      this.closeSignModal();
    }, 1000);
  }

  private formatFecha(isoDate: string): string {
    const d = new Date(isoDate);
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    const hh = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${dd}/${mm}/${yyyy} ${hh}:${min}`;
  }
}
