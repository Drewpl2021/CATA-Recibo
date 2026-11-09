import { Component, OnDestroy, OnInit, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, Router } from '@angular/router';
import { environment } from '../../../../environments/environment';

/** Lo que dice la pantalla según lo que pasó. */
const TEXTOS: Record<string, { titulo: string; mensajes: string[] }> = {
  mantenimiento: {
    titulo: 'En mantenimiento',
    mensajes: ['CATA-Recibo se está actualizando y vuelve en unos minutos.', 'Tus boletas, hojas y documentos están a salvo.'],
  },
  sin_conexion: {
    titulo: 'Sin conexión',
    mensajes: ['No se pudo llegar al sistema. Revisa tu conexión a internet.', 'Apenas vuelva, te llevamos de regreso.'],
  },
};

/**
 * «En mantenimiento»: la pantalla que se ve cuando el sistema está de baja
 * (el API responde 502, 503 o 504) o no hay conexión, con el diseño de las
 * páginas de error del colegio (como las del inventario). La abre el
 * interceptor (ver auth.interceptor).
 *
 * Pregunta sola cada 30 segundos si el sistema ya volvió (GET /api/status)
 * y, cuando vuelve, regresa a donde estaba la persona.
 */
@Component({
  selector: 'app-mantenimiento',
  standalone: true,
  templateUrl: './mantenimiento.component.html',
  styleUrl: './mantenimiento.component.scss',
})
export class MantenimientoComponent implements OnInit, OnDestroy {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);
  private readonly ruta = inject(ActivatedRoute);

  codigo = '503';
  titulo = TEXTOS['mantenimiento'].titulo;
  mensajes = TEXTOS['mantenimiento'].mensajes;
  probando = false;
  todaviaNo = false;
  private volver = '/inicio';
  private reloj?: ReturnType<typeof setInterval>;

  ngOnInit(): void {
    const params = this.ruta.snapshot.queryParamMap;
    const volver = params.get('volver');
    // Solo una dirección de la propia app: nada de mandar a otro sitio.
    if (volver && volver.startsWith('/') && !volver.startsWith('//') && !volver.startsWith('/mantenimiento')) this.volver = volver;
    const sinConexion = params.get('motivo') === 'sin_conexion';
    const textos = TEXTOS[sinConexion ? 'sin_conexion' : 'mantenimiento'];
    this.codigo = sinConexion ? 'Sin red' : params.get('codigo') || '503';
    this.titulo = textos.titulo;
    this.mensajes = textos.mensajes;
    this.reloj = setInterval(() => this.probar(true), 30_000);
  }

  ngOnDestroy(): void {
    if (this.reloj) clearInterval(this.reloj);
  }

  /** ¿Ya volvió? Si sí, a donde estaba; si no, se queda y lo dice. */
  probar(solo = false): void {
    if (this.probando) return;
    this.probando = true;
    this.todaviaNo = false;
    this.http.get<{ success: boolean }>(`${environment.apiUrl}/status`).subscribe({
      next: () => {
        this.probando = false;
        this.router.navigateByUrl(this.volver);
      },
      error: () => {
        this.probando = false;
        if (!solo) this.todaviaNo = true;
      },
    });
  }
}
