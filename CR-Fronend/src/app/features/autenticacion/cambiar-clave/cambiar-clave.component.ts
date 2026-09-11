import { Component, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { FormsModule } from '@angular/forms';
import { AuthService, ToastService } from '../../../core/services';
import { TerminosDeUso } from '../../../core/models';
import { claveConLetrasYNumeros } from '../../../core/utils';

/**
 * El cambio obligatorio del primer ingreso.
 *
 * Cuando RR.HH. da de alta a un empleado, su contraseña es su DNI — que está
 * en su ficha, en el listado y en su boleta, o sea que lo sabe medio colegio.
 * Hasta que ponga una suya, el backend responde 423 a todo lo demás, así que
 * esta pantalla no tiene salida más que cambiarla o cerrar sesión.
 *
 * Va sin el menú lateral a propósito: con la cuenta trabada no hay ninguna
 * otra pantalla a la que ir.
 */
@Component({
  selector: 'app-cambiar-clave',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, FormsModule],
  templateUrl: './cambiar-clave.component.html',
  styleUrl: '../acceso.scss',
})
export class CambiarClaveComponent implements OnInit {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private toast = inject(ToastService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);

  /**
   * Vino solo a firmar (ruta /terminos): ya tiene una contraseña suya, así
   * que después de firmar entra directo en vez de pasar al segundo paso.
   * Es el autorregistrado y la cuenta de antes de que existieran los términos.
   */
  soloTerminos = this.route.snapshot.data['soloTerminos'] === true;

  guardando = false;
  errorMsg = '';
  verPassword = false;

  /**
   * Primero los términos, después la contraseña.
   *
   * Es el orden que tenía en papel: el colegio repartía una hoja impresa,
   * cada trabajador la firmaba al entrar, y recién después empezaba. Ahora
   * la hoja está aquí y la firma queda guardada en su cuenta con la fecha y
   * la versión — el backend no deja cambiar la contraseña sin ella.
   */
  paso: 'terminos' | 'clave' = 'clave';
  terminos: TerminosDeUso | null = null;
  cargandoTerminos = true;
  aceptaTerminos = false;
  firmando = false;

  /** Para saludar por su nombre a quien acaba de entrar. */
  nombre = this.authService.getUser()?.name ?? '';

  ngOnInit(): void {
    this.authService.terminos().subscribe({
      next: (res) => {
        this.cargandoTerminos = false;
        if (!res.success) return;

        this.terminos = res.data;

        // Vino a firmar pero ya está al día (otra pestaña, o recargó): no hay
        // nada que hacer aquí.
        if (this.soloTerminos && res.data.firmados) {
          this.router.navigateByUrl(this.authService.rutaTrasIngresar());
          return;
        }

        this.paso = res.data.firmados && !this.soloTerminos ? 'clave' : 'terminos';
      },
      error: () => {
        // Sin los términos no se puede seguir: el backend va a rechazar el
        // cambio de contraseña igual. Se dice por qué en vez de dejar una
        // pantalla en blanco.
        this.cargandoTerminos = false;
        this.errorMsg = 'No pudimos cargar los términos de uso. Recarga la página o avisa a Recursos Humanos.';
      },
    });
  }

  /** Ya los firmó antes: se le enseña cuándo, sin volver a pedírselo. */
  get firmadosAntes(): boolean {
    return !!this.terminos?.firmados;
  }

  firmar(): void {
    if (!this.aceptaTerminos) {
      this.errorMsg = 'Marca la casilla para dejar constancia de que los leíste y los aceptas.';
      return;
    }

    this.firmando = true;
    this.errorMsg = '';

    this.authService.aceptarTerminos().subscribe({
      next: (res) => {
        this.firmando = false;

        // Se anota aquí también, sin volver a preguntar al backend: lo que
        // acaba de pasar ya se sabe, y así el "volver a leerlos" del pie
        // aparece de una vez.
        if (this.terminos) {
          this.terminos = {
            ...this.terminos,
            firmados: true,
            firmadosEn: res.data?.firmadosEn ?? new Date().toISOString(),
            versionFirmada: res.data?.version ?? this.terminos.version,
            esVersionNueva: false,
          };
        }

        if (this.soloTerminos) {
          this.toast.success('Términos aceptados', 'Queda registrado. Ya puedes usar el sistema.');
          this.router.navigateByUrl(this.authService.rutaTrasIngresar());
          return;
        }

        this.paso = 'clave';
        this.toast.success('Términos aceptados', 'Queda registrado. Ahora pon tu contraseña.');
      },
      error: (err) => {
        this.firmando = false;
        this.errorMsg = err?.error?.message ?? 'No se pudieron registrar los términos. Intenta de nuevo.';
      },
    });
  }

  /** Volver a leerlos desde el paso de la contraseña. */
  verTerminos(): void {
    this.paso = 'terminos';
  }

  form = this.fb.group(
    {
      password_actual: ['', [Validators.required]],
      password_nuevo: ['', [Validators.required, Validators.minLength(8), claveConLetrasYNumeros]],
      password_nuevo_confirmation: ['', [Validators.required]],
    },
    { validators: (grupo: AbstractControl) => coincidenLasClaves(grupo) }
  );

  get actualVacia(): boolean {
    const c = this.form.get('password_actual');
    return !!c && c.touched && c.invalid;
  }

  get nuevaCorta(): boolean {
    const c = this.form.get('password_nuevo');
    return !!c && c.touched && c.hasError('minlength');
  }

  /** Le faltan letras o números: la misma regla que el backend. */
  get claveDebil(): boolean {
    const c = this.form.get('password_nuevo');
    return !!c && c.touched && c.hasError('claveDebil');
  }

  get noCoinciden(): boolean {
    const c = this.form.get('password_nuevo_confirmation');
    return !!c && c.touched && this.form.hasError('noCoinciden');
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    this.errorMsg = '';
    const crudo = this.form.getRawValue();

    this.authService
      .cambiarPassword({
        password_actual: crudo.password_actual!,
        password_nuevo: crudo.password_nuevo!,
        password_nuevo_confirmation: crudo.password_nuevo_confirmation!,
      })
      .subscribe({
        next: () => {
          this.guardando = false;
          this.toast.success('Contraseña cambiada', 'Ya puedes usar el sistema.');
          this.router.navigateByUrl(this.authService.rutaInicioSegunRol());
        },
        error: (err) => {
          this.guardando = false;
          // 409: no firmó los términos (o cambió la versión). Se le lleva
          // al paso que le falta en vez de dejarle un error que no puede
          // resolver desde donde está.
          if (err?.status === 409 && err?.error?.data?.requiereTerminos) {
            this.paso = 'terminos';
            this.aceptaTerminos = false;
          }

          this.errorMsg =
            err?.error?.errors?.password_actual?.[0] ||
            err?.error?.errors?.password_nuevo?.[0] ||
            err?.error?.message ||
            'No se pudo cambiar la contraseña. Intenta de nuevo.';
        },
      });
  }

  /** La única otra salida: irse. */
  salir(): void {
    this.authService.logout();
  }

  togglePassword(): void {
    this.verPassword = !this.verPassword;
  }
}

/** Las dos casillas de la nueva tienen que decir lo mismo. */
function coincidenLasClaves(grupo: AbstractControl): { noCoinciden: true } | null {
  const clave = grupo.get('password_nuevo')?.value;
  const repetida = grupo.get('password_nuevo_confirmation')?.value;
  return clave && repetida && clave !== repetida ? { noCoinciden: true } : null;
}
