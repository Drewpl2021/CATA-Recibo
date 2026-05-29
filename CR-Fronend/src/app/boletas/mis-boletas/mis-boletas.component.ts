import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../core/services/auth.service';
import { BoletasService } from '../../core/services/boletas.service';

const MESES: Record<number, string> = {
  1: 'Enero', 2: 'Febrero', 3: 'Marzo', 4: 'Abril',
  5: 'Mayo', 6: 'Junio', 7: 'Julio', 8: 'Agosto',
  9: 'Setiembre', 10: 'Octubre', 11: 'Noviembre', 12: 'Diciembre'
};

export interface BoletaRow {
  id: string;
  entidad: string;
  mes: string;
  mesNum: number;
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
  imports: [CommonModule, FormsModule],
  templateUrl: './mis-boletas.component.html',
  styleUrl: './mis-boletas.component.scss'
})
export class MisBoletasComponent implements OnInit {
  anios: string[] = ['2026', '2025', '2024', '2023', '2022'];
  selectedAnio: string = new Date().getFullYear().toString();
  boletas: BoletaRow[] = [];
  isLoading = false;
  errorMsg = '';

  constructor(
    private authService: AuthService,
    private boletasService: BoletasService
  ) {}

  ngOnInit(): void {
    this.cargarBoletas();
  }

  cargarBoletas(): void {
    const empleadoId = this.authService.getEmpleadoId();
    if (!empleadoId) {
      this.errorMsg = 'No se pudo identificar al empleado. Por favor vuelve a iniciar sesión.';
      return;
    }

    this.isLoading = true;
    this.errorMsg = '';

    this.boletasService.getPlanilla(empleadoId, this.selectedAnio).subscribe({
      next: (res) => {
        this.isLoading = false;
        const planillas = Array.isArray(res) ? res : (res.data ?? []);
        this.boletas = planillas.map((p: any) => ({
          id: p.id,
          entidad: 'Colegio',
          mes: MESES[p.mes] ?? `Mes ${p.mes}`,
          mesNum: p.mes,
          anio: p.anio,
          firmado: p.created_at ? { fecha: this.formatFecha(p.created_at) } : null,
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
    const empleadoId = this.authService.getEmpleadoId();
    if (empleadoId) {
      this.isLoading = true;
      this.errorMsg = '';
      
      this.boletasService.descargarBoleta(empleadoId, boleta.mesNum, String(boleta.anio)).subscribe({
        next: (blob) => {
          this.isLoading = false;
          // Crear una URL local a partir del Blob descargado y abrirla en nueva pestaña
          const fileURL = URL.createObjectURL(blob);
          window.open(fileURL, '_blank');
        },
        error: () => {
          this.isLoading = false;
          this.errorMsg = `Error al generar la boleta de ${boleta.mes} ${boleta.anio}.`;
        }
      });
    }
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
