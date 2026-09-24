/**
 * Todas las rutas de la API en un solo lugar (espejo de routes/api.php del
 * backend). Si mañana cambia una ruta, se cambia acá y no hay que salir a
 * buscar strings sueltos por todos los servicios.
 */
export const END_POINTS = {
  auth: {
    login: 'login',
    logout: 'logout',
    me: 'me',
    cambiarPassword: 'change-password',
    olvidePassword: 'forgot-password',
    restablecerPassword: 'reset-password',
    /** Los términos de uso: el papel que antes se firmaba a mano. */
    terminos: 'terms',
    aceptarTerminos: 'terms/accept',
  },

  /** Configuración base — RRHH y Admin (ver routes/api.php, grupo rol:rrhh,admin). */
  configuracion: {
    areas: 'areas',
    cargos: 'positions',
    sedes: 'campuses',
    periodos: 'periods',
    paymentConcepts: 'payment-concepts',
    roles: 'roles',
  },

  /** Solo Admin. */
  admin: {
    modulos: 'modules',
    modulosPadre: 'module-groups',
    /** Quién cambió qué. Solo lectura. */
    auditoria: 'audit-log',
  },

  personal: {
    empleados: 'employees',
    contratos: 'contracts',
    users: 'users',
  },

  planilla: {
    planilla: 'payrolls',
    /** Las planillas con nombre que agrupan a los trabajadores. */
    corridas: 'payroll-runs',
    payrollDetalles: 'payroll-details',
    documentos: 'documents',
    vacaciones: 'vacations',
  },

  /** Autoservicio del empleado autenticado. */
  autoservicio: {
    miPlanilla: 'my-payroll',
    misBoletas: 'my-payslips',
    misDocumentos: 'my-documents',
    misModulos: 'my-modules',
    miIdentidadFirma: 'my-signature',
    /** GET — los bytes de la firma que uno mismo registró */
    miFirmaImagen: 'my-signature-image',
  },
} as const;

/** Rutas que no son CRUD plano y llevan segmentos extra. */
export const END_POINTS_ACCIONES = {
  /** GET payslips/{employee_id}/{month}/{year} */
  boletaIndividual: (empleadoId: string, mes: number | string, anio: number | string) =>
    `payslips/${empleadoId}/${mes}/${anio}`,

  /** GET my-payslips/{month}/{year} */
  miBoleta: (mes: number | string, anio: number | string) => `my-payslips/${mes}/${anio}`,

  /** POST payslips/generate-bulk */
  boletasMasivo: 'payslips/generate-bulk',

  /** POST periods/{id}/generate-payroll */
  generarPlanillaPeriodo: (periodoId: string) => `periods/${periodoId}/generate-payroll`,

  /** POST payment-concepts/{id}/apply-to-group */
  aplicarConceptoGrupo: (conceptoId: string) => `payment-concepts/${conceptoId}/apply-to-group`,

  /** GET documents/{id}/download — bajárselo; el suyo, solo firmado */
  descargarDocumento: (documentoId: string) => `documents/${documentoId}/download`,
  /** GET documents/{id}/view — abrirlo en pantalla; deja anotado que lo revisó */
  verDocumento: (documentoId: string) => `documents/${documentoId}/view`,

  /** POST documents/upload — adjuntar un archivo (hoja de vida, contrato firmado) */
  subirDocumento: 'documents/upload',

  /** POST y DELETE my-photo — la foto de perfil del usuario autenticado */
  miFoto: 'my-photo',

  /** GET users/{id}/photo — los bytes de la imagen, desde el disco privado */
  fotoDeUsuario: (userId: number | string) => `users/${userId}/photo`,

  /** POST documents/{id}/sign-as-employer */
  firmarComoEmpleador: (documentoId: string) => `documents/${documentoId}/sign-as-employer`,

  /** PATCH my-documents/{id}/viewed */
  marcarDocumentoVisto: (documentoId: string) => `my-documents/${documentoId}/viewed`,

  /** POST my-documents/{id}/sign */
  firmarMiDocumento: (documentoId: string) => `my-documents/${documentoId}/sign`,

  /** POST my-documents/resume — el trabajador sube su propio CV */
  subirMiHojaDeVida: 'my-documents/resume',

  /** POST employees/{id}/signature */
  identidadFirmaEmpleado: (empleadoId: string) => `employees/${empleadoId}/signature`,
  /** GET employees/{id}/signature-image — los bytes de su firma (RR.HH. y Administración) */
  firmaImagenDeEmpleado: (empleadoId: string) => `employees/${empleadoId}/signature-image`,

  /** GET vacations/balance?empleado_id=&anio= */
  saldoVacaciones: 'vacations/balance',

  /** POST users/{id}/reset-password */
  restablecerPasswordUsuario: (userId: string) => `users/${userId}/reset-password`,

  /** POST modules/{id}/roles */
  asignarRolesModulo: (moduloId: string) => `modules/${moduloId}/roles`,

  /** GET dni-lookup/{dni} — quién es esa persona, para el alta */
  consultaDni: (dni: string) => `dni-lookup/${dni}`,

  /** GET payrolls/export?... — el reporte completo de la planilla en Excel */
  exportarPlanilla: 'payrolls/export',

  /** POST payrolls/{id}/concepts — deja sus líneas como diga la pantalla */
  sincronizarConceptosPlanilla: (planillaId: string) => `payrolls/${planillaId}/concepts`,

  /** GET employees/export?search= — la lista del personal en Excel */
  exportarEmpleados: 'employees/export',

  /** GET employee-files?page&size&search&filtro — Documentos del personal, por trabajador */
  expedientes: 'employee-files',
  /** GET employee-files/{employeeId} — el expediente de un trabajador */
  expediente: (empleadoId: string) => `employee-files/${empleadoId}`,

  /** POST concept-import/{step} — cargar conceptos desde el Excel de RR.HH. */
  importacionConceptos: {
    /** GET ?mes=&anio= — el Excel modelo del mes, para llenar */
    modelo: 'concept-import/template',
    reconocer: 'concept-import/recognize',
    previsualizar: 'concept-import/preview',
    aplicar: 'concept-import/apply',
  },

  /** POST employee-import/{step} — altas y cambios desde Excel, y los CVs en lote */
  importacionEmpleados: {
    /** GET — el Excel modelo vacío, con listas desplegables e instrucciones */
    modelo: 'employee-import/template',
    reconocer: 'employee-import/recognize',
    previsualizar: 'employee-import/preview',
    aplicar: 'employee-import/apply',
    hojaDeVida: 'employee-import/resume',
  },
  /** Boletas y contratos de antes del sistema, en lote: se revisa y se sube de a uno */
  documentosAnteriores: {
    previsualizar: 'legacy-documents/preview',
    subir: 'legacy-documents',
  },
} as const;
