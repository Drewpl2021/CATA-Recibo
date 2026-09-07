import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { forkJoin } from 'rxjs';

import {
  EmpleadoService,
  AreaService,
  CargoService,
  SedeService,
  RolService,
  ToastService,
  ContratoService,
} from '../../../core/services';
import { Area, Cargo, Empleado, EmpleadoPayload, Rol, Sede,
  Contrato,
} from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { WizardComponent } from '../../../shared/components/wizard/wizard.component';
import { PasoWizard } from '../../../shared/components/wizard/wizard.models';

import { SeccionPersonalesComponent } from './secciones/seccion-personales.component';
import { SeccionLaboralesComponent } from './secciones/seccion-laborales.component';
import { SeccionPlanillaComponent } from './secciones/seccion-planilla.component';
import { SeccionComplementariosComponent } from './secciones/seccion-complementarios.component';
import { SeccionAccesoComponent } from './secciones/seccion-acceso.component';

/** Rechaza fechas posteriores a hoy (el backend valida `before_or_equal:today`). */
function noFutura(control: AbstractControl): ValidationErrors | null {
  if (!control.value) return null;
  return control.value > new Date().toISOString().slice(0, 10) ? { fechaFutura: true } : null;
}

/**
 * Alta, edición y consulta de un empleado.
 *
 * El formulario es UNO SOLO (un FormGroup), pero está partido en secciones
 * que viven en ./secciones: cada una pinta un puñado de campos y recibe ese
 * mismo grupo. Así ningún archivo se vuelve inmanejable, el guardado sigue
 * siendo una sola llamada, y el wizard sabe en qué paso está cada error.
 *
 * Lo importante del alta: el backend crea el EMPLEADO y su USUARIO en la
 * misma operación, con el DNI como contraseña inicial. Por eso el correo y
 * el rol son obligatorios al crear; al editar, el rol se cambia en Usuarios.
 */
@Component({
  selector: 'app-empleado-form',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    PageHeaderComponent, WizardComponent,
    SeccionPersonalesComponent, SeccionLaboralesComponent, SeccionPlanillaComponent,
    SeccionComplementariosComponent, SeccionAccesoComponent,
  ],
  templateUrl: './empleado-form.component.html',
})
export class EmpleadoFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private ruta = inject(ActivatedRoute);
  private router = inject(Router);
  private empleadoService = inject(EmpleadoService);
  private areaService = inject(AreaService);
  private cargoService = inject(CargoService);
  private sedeService = inject(SedeService);
  private rolService = inject(RolService);
  private contratoService = inject(ContratoService);
  private toastService = inject(ToastService);

  modo: 'nuevo' | 'editar' | 'ver' = 'nuevo';
  empleadoId: string | null = null;
  cargando = false;
  guardando = false;

  areas: Area[] = [];
  cargos: Cargo[] = [];
  sedes: Sede[] = [];
  roles: Rol[] = [];

  paso = 0;
  pasos: PasoWizard[] = [];

  form = this.fb.group({
    // ── Personales ──
    dni: ['', [Validators.required, Validators.pattern(/^[0-9]{8}$/)]],
    nombre: ['', [Validators.required, Validators.maxLength(100)]],
    apellido: ['', [Validators.required, Validators.maxLength(100)]],
    fecha_nacimiento: ['', [Validators.required, noFutura]],
    telefono: ['', [Validators.required, Validators.pattern(/^[0-9]+$/), Validators.maxLength(15)]],
    direccion: ['', [Validators.required, Validators.maxLength(255)]],

    // ── Laborales ──
    area_id: ['', [Validators.required]],
    cargo_id: ['', [Validators.required]],
    sede_id: ['', [Validators.required]],
    fecha_ingreso: ['', [Validators.required, noFutura]],
    tipo_contrato: ['', [Validators.required]],
    // Obligatoria salvo en contrato indeterminado; el validador se pone y se
    // quita en ajustarFechaFinContrato(), según el tipo elegido.
    fecha_fin_contrato: [''],
    sueldo_base: [null as number | null, [Validators.required, Validators.min(0)]],
    estado: ['activo'],

    // ── Planilla ──
    sistema_pensiones: ['ONP'],
    afp: [''],
    cuspp: [''],
    forma_pago: [''],
    entidad_financiera: [''],
    numero_cuenta: [''],
    tiene_hijos: [false],

    // ── Complementarios ──
    nivel_estudios: [''],
    especialidad: [''],
    institucion_estudios: [''],
    contacto_emergencia_nombre: [''],
    contacto_emergencia_telefono: ['', [Validators.pattern(/^[0-9]*$/), Validators.maxLength(15)]],

    // ── Acceso ──
    email: ['', [Validators.required, Validators.email]],
    rol_id: ['', [Validators.required]],
  });

  get esNuevo(): boolean {
    return this.modo === 'nuevo';
  }

  get soloLectura(): boolean {
    return this.modo === 'ver';
  }

  get titulo(): string {
    if (this.modo === 'nuevo') return 'Nuevo Empleado';
    if (this.modo === 'ver') return 'Ficha del Empleado';
    return 'Editar Empleado';
  }

  get subtitulo(): string {
    if (this.modo === 'nuevo') return 'Se dará de alta al trabajador y se le creará su cuenta de acceso.';
    if (this.modo === 'ver') return 'Datos registrados del trabajador.';
    return 'Actualiza los datos del trabajador.';
  }

  /** Los contratos que ya tiene. En un alta se queda vacío. */
  contratos: Contrato[] = [];
  cargandoContratos = false;

  ngOnInit(): void {
    this.empleadoId = this.ruta.snapshot.paramMap.get('id');
    const url = this.router.url;
    this.modo = !this.empleadoId ? 'nuevo' : url.includes('/ver/') ? 'ver' : 'editar';

    this.armarPasos();
    this.ajustarValidacionSegunModo();
    // El backend solo exige AFP y CUSPP cuando el sistema de pensiones es AFP.
    this.form.get('sistema_pensiones')!.valueChanges.subscribe(() => this.ajustarValidacionAfp());
    this.ajustarValidacionAfp();
    // Y la fecha de término solo se exige si el contrato lleva plazo.
    this.form.get('tipo_contrato')!.valueChanges.subscribe(() => this.ajustarFechaFinContrato());
    this.ajustarFechaFinContrato();

    this.cargarCatalogos();
    if (this.empleadoId) this.cargarContratos();
  }

  /**
   * El historial de contratos de este trabajador. Se pide aparte de la ficha
   * porque el endpoint de empleados no los trae, y solo tiene sentido cuando
   * la ficha ya existe.
   */
  private cargarContratos(): void {
    this.cargandoContratos = true;
    this.contratoService.getAll({ empleado_id: this.empleadoId!, incluir_inactivos: 1 }).subscribe({
      next: (res) => {
        if (res.success) this.contratos = res.data;
        this.cargandoContratos = false;
      },
      error: () => {
        // No es crítico: la ficha se puede ver y editar igual.
        this.cargandoContratos = false;
      },
    });
  }

  private armarPasos(): void {
    this.pasos = [
      {
        id: 'personales', titulo: 'Personales', icono: 'person',
        campos: ['dni', 'nombre', 'apellido', 'fecha_nacimiento', 'telefono', 'direccion'],
      },
      {
        id: 'laborales', titulo: 'Laborales', icono: 'badge',
        campos: ['area_id', 'cargo_id', 'sede_id', 'fecha_ingreso', 'tipo_contrato', 'fecha_fin_contrato', 'sueldo_base'],
      },
      {
        id: 'planilla', titulo: 'Planilla', icono: 'money',
        campos: ['sistema_pensiones', 'afp', 'cuspp'],
      },
      {
        id: 'complementarios', titulo: 'Complementarios', icono: 'description',
        campos: ['contacto_emergencia_telefono'],
      },
      {
        id: 'acceso', titulo: 'Acceso', icono: 'shield',
        campos: this.esNuevo ? ['email', 'rol_id'] : ['email'],
      },
    ];
  }

  /** Al editar, el backend ignora `rol_id` y el correo pasa a ser opcional. */
  private ajustarValidacionSegunModo(): void {
    if (this.esNuevo) return;

    const rol = this.form.get('rol_id')!;
    rol.clearValidators();
    rol.updateValueAndValidity();

    const email = this.form.get('email')!;
    email.setValidators([Validators.email]);
    email.updateValueAndValidity();
  }

  /**
   * La fecha de término solo es obligatoria cuando el contrato tiene plazo.
   * En el indeterminado ni se pide ni se manda: se limpia para que no quede
   * una fecha suelta de cuando el usuario probó otro tipo.
   */
  private ajustarFechaFinContrato(): void {
    const tipo = this.form.get('tipo_contrato')?.value;
    const fechaFin = this.form.get('fecha_fin_contrato')!;

    if (tipo && tipo !== 'indeterminado') {
      fechaFin.setValidators([Validators.required]);
    } else {
      fechaFin.clearValidators();
      fechaFin.setValue('', { emitEvent: false });
    }
    fechaFin.updateValueAndValidity({ emitEvent: false });
  }

  private ajustarValidacionAfp(): void {
    const esAfp = this.form.get('sistema_pensiones')?.value === 'AFP';
    const afp = this.form.get('afp')!;
    const cuspp = this.form.get('cuspp')!;

    if (esAfp) {
      afp.setValidators([Validators.required]);
      cuspp.setValidators([Validators.required, Validators.pattern(/^[0-9]{11}$/)]);
    } else {
      afp.clearValidators();
      cuspp.clearValidators();
      // Quien pasa a ONP no conserva datos de AFP.
      afp.setValue('', { emitEvent: false });
      cuspp.setValue('', { emitEvent: false });
    }
    afp.updateValueAndValidity({ emitEvent: false });
    cuspp.updateValueAndValidity({ emitEvent: false });
  }

  private cargarCatalogos(): void {
    this.cargando = true;
    forkJoin({
      areas: this.areaService.getAll(),
      cargos: this.cargoService.getAll(),
      sedes: this.sedeService.getAll(),
      roles: this.rolService.getAll(),
    }).subscribe({
      next: ({ areas, cargos, sedes, roles }) => {
        if (areas.success) this.areas = areas.data;
        if (cargos.success) this.cargos = cargos.data;
        if (sedes.success) this.sedes = sedes.data;
        if (roles.success) this.roles = roles.data;

        if (this.empleadoId) {
          this.cargarEmpleado(this.empleadoId);
        } else {
          this.cargando = false;
        }
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los catálogos.'));
      },
    });
  }

  private cargarEmpleado(id: string): void {
    this.empleadoService.getById(id).subscribe({
      next: (res) => {
        this.cargando = false;
        if (!res.success) return;
        this.volcarEnFormulario(res.data);
        if (this.soloLectura) this.form.disable();
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar el empleado.'));
      },
    });
  }

  private volcarEnFormulario(e: Empleado): void {
    this.form.patchValue({
      dni: e.dni,
      nombre: e.nombre,
      apellido: e.apellido,
      // <input type="date"> necesita "YYYY-MM-DD" pelado.
      fecha_nacimiento: (e.fecha_nacimiento ?? '').slice(0, 10),
      telefono: e.telefono ?? '',
      direccion: e.direccion ?? '',
      area_id: e.area_id ?? '',
      cargo_id: e.cargo_id ?? '',
      sede_id: e.sede_id ?? '',
      fecha_ingreso: (e.fecha_ingreso ?? '').slice(0, 10),
      tipo_contrato: e.tipo_contrato ?? '',
      sueldo_base: e.sueldo_base ?? null,
      estado: e.estado ?? 'activo',
      sistema_pensiones: e.sistema_pensiones ?? 'ONP',
      afp: e.afp ?? '',
      cuspp: e.cuspp ?? '',
      forma_pago: e.forma_pago ?? '',
      entidad_financiera: e.entidad_financiera ?? '',
      numero_cuenta: e.numero_cuenta ?? '',
      tiene_hijos: !!e.tiene_hijos,
      nivel_estudios: e.nivel_estudios ?? '',
      especialidad: e.especialidad ?? '',
      institucion_estudios: e.institucion_estudios ?? '',
      contacto_emergencia_nombre: e.contacto_emergencia_nombre ?? '',
      contacto_emergencia_telefono: e.contacto_emergencia_telefono ?? '',
      email: e.usuario?.email ?? '',
    });
    this.ajustarValidacionAfp();
    this.ajustarFechaFinContrato();
  }

  /** El paso que se está viendo ahora mismo. */
  get pasoActual(): string {
    return this.pasos[this.paso]?.id ?? '';
  }

  /**
   * Arma el cuerpo tal como lo espera el backend: los opcionales vacíos van
   * como null, porque reglas como `nullable|in:...` rechazan la cadena vacía.
   */
  private construirPayload(): EmpleadoPayload {
    const v = this.form.getRawValue();
    const oNull = (x: string | null | undefined) => (x ? x : null);

    return {
      dni: v.dni!,
      nombre: v.nombre!,
      apellido: v.apellido!,
      fecha_nacimiento: oNull(v.fecha_nacimiento),
      telefono: v.telefono!,
      direccion: v.direccion!,
      area_id: v.area_id!,
      cargo_id: v.cargo_id!,
      sede_id: v.sede_id!,
      fecha_ingreso: v.fecha_ingreso!,
      tipo_contrato: oNull(v.tipo_contrato),
      // Solo viaja cuando el contrato tiene plazo; en el indeterminado el
      // backend la ignora y guarda null.
      fecha_fin_contrato: v.tipo_contrato === 'indeterminado' ? null : oNull(v.fecha_fin_contrato),
      sueldo_base: v.sueldo_base === null ? null : Number(v.sueldo_base),
      estado: v.estado ?? 'activo',
      sistema_pensiones: v.sistema_pensiones ?? 'ONP',
      afp: oNull(v.afp),
      cuspp: oNull(v.cuspp),
      forma_pago: oNull(v.forma_pago),
      entidad_financiera: oNull(v.entidad_financiera),
      numero_cuenta: oNull(v.numero_cuenta),
      tiene_hijos: !!v.tiene_hijos,
      nivel_estudios: oNull(v.nivel_estudios),
      especialidad: oNull(v.especialidad),
      institucion_estudios: oNull(v.institucion_estudios),
      contacto_emergencia_nombre: oNull(v.contacto_emergencia_nombre),
      contacto_emergencia_telefono: oNull(v.contacto_emergencia_telefono),
      email: v.email!,
      rol_id: v.rol_id!,
    };
  }

  /** Lleva al primer paso con errores, para que el usuario no los busque. */
  private irAlPrimerPasoConError(): void {
    const i = this.pasos.findIndex((p) => p.campos?.some((c) => this.form.get(c)?.invalid));
    if (i >= 0) this.paso = i;
  }

  guardar(): void {
    if (this.soloLectura) return;

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.irAlPrimerPasoConError();
      this.toastService.error('Faltan datos', 'Revisa los pasos marcados en rojo.');
      return;
    }

    const payload = this.construirPayload();
    this.guardando = true;

    if (this.esNuevo) {
      this.empleadoService.create(payload).subscribe({
        next: (res) => {
          this.guardando = false;
          if (!res.success) return;
          this.toastService.success(
            'Empleado registrado',
            `Se creó la cuenta ${payload.email}. Su contraseña inicial es su DNI: ${payload.dni}.`
          );
          this.router.navigate(['/inicio/empleados']);
        },
        error: (err) => {
          this.guardando = false;
          this.toastService.error('No se pudo registrar', mensajeErrorApi(err, 'Revisa los datos e inténtalo de nuevo.'));
        },
      });
      return;
    }

    // Al editar, el backend ignora rol_id: el rol se cambia en Usuarios.
    const { rol_id, ...cambios } = payload;
    this.empleadoService.update(this.empleadoId!, cambios).subscribe({
      next: (res) => {
        this.guardando = false;
        if (!res.success) return;
        this.toastService.success('Empleado actualizado', 'Los cambios se guardaron correctamente.');
        this.router.navigate(['/inicio/empleados']);
      },
      error: (err) => {
        this.guardando = false;
        this.toastService.error('No se pudo guardar', mensajeErrorApi(err, 'Revisa los datos e inténtalo de nuevo.'));
      },
    });
  }

  volver(): void {
    this.router.navigate(['/inicio/empleados']);
  }
}
