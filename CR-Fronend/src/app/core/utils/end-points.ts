/**
 * Todas las rutas de la API en un solo lugar (espejo de routes/api.php del
 * backend). Si mañana cambia una ruta, se cambia acá y no hay que salir a
 * buscar strings sueltos por todos los servicios.
 */
export const END_POINTS = {
  auth: {
    login: 'login',
    register: 'register',
    logout: 'logout',
    me: 'me',
    cambiarPassword: 'cambiar-password',
  },

  /** Configuración base — RRHH y Admin (ver routes/api.php, grupo rol:rrhh,admin). */
  configuracion: {
    areas: 'areas',
    cargos: 'cargos',
    sedes: 'sedes',
    periodos: 'periodos',
    paymentConcepts: 'payment-concepts',
    roles: 'roles',
  },

  /** Solo Admin. */
  admin: {
    modulos: 'modulos',
    modulosPadre: 'modulos-padre',
  },

  personal: {
    empleados: 'empleados',
    contratos: 'contratos',
    users: 'users',
  },

  planilla: {
    planilla: 'planilla',
    payrollDetalles: 'payroll-detalles',
    documentos: 'documentos',
    vacaciones: 'vacaciones',
  },

  /** Autoservicio del empleado autenticado. */
  autoservicio: {
    miPlanilla: 'mi-planilla',
    misBoletas: 'mis-boletas',
    misDocumentos: 'mis-documentos',
    misModulos: 'mis-modulos',
    miIdentidadFirma: 'mi-identidad-firma',
  },
} as const;

/** Rutas que no son CRUD plano y llevan segmentos extra. */
export const END_POINTS_ACCIONES = {
  /** GET boleta/{empleado_id}/{mes}/{anio} */
  boletaIndividual: (empleadoId: string, mes: number | string, anio: number | string) =>
    `boleta/${empleadoId}/${mes}/${anio}`,

  /** GET mis-boletas/{mes}/{anio} */
  miBoleta: (mes: number | string, anio: number | string) => `mis-boletas/${mes}/${anio}`,

  /** POST boletas/generar-masivo */
  boletasMasivo: 'boletas/generar-masivo',

  /** POST periodos/{id}/generar-planilla */
  generarPlanillaPeriodo: (periodoId: string) => `periodos/${periodoId}/generar-planilla`,

  /** POST payment-concepts/{id}/aplicar-a-grupo */
  aplicarConceptoGrupo: (conceptoId: string) => `payment-concepts/${conceptoId}/aplicar-a-grupo`,

  /** GET documentos/{id}/descargar */
  descargarDocumento: (documentoId: string) => `documentos/${documentoId}/descargar`,

  /** POST documentos/{id}/firmar-empleador */
  firmarComoEmpleador: (documentoId: string) => `documentos/${documentoId}/firmar-empleador`,

  /** PATCH mis-documentos/{id}/visto */
  marcarDocumentoVisto: (documentoId: string) => `mis-documentos/${documentoId}/visto`,

  /** POST mis-documentos/{id}/firmar */
  firmarMiDocumento: (documentoId: string) => `mis-documentos/${documentoId}/firmar`,

  /** POST empleados/{id}/identidad-firma */
  identidadFirmaEmpleado: (empleadoId: string) => `empleados/${empleadoId}/identidad-firma`,

  /** POST modulos/{id}/roles */
  asignarRolesModulo: (moduloId: string) => `modulos/${moduloId}/roles`,
} as const;
