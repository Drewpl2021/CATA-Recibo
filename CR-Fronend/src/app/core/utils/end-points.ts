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
    cambiarPassword: 'cambiar-password',
    olvidePassword: 'olvide-password',
    restablecerPassword: 'restablecer-password',
    /** Los términos de uso: el papel que antes se firmaba a mano. */
    terminos: 'terminos',
    aceptarTerminos: 'terminos/aceptar',
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
    /** Quién cambió qué. Solo lectura. */
    auditoria: 'auditoria',
  },

  personal: {
    empleados: 'empleados',
    contratos: 'contratos',
    users: 'users',
  },

  planilla: {
    planilla: 'planilla',
    /** Las planillas con nombre que agrupan a los trabajadores. */
    corridas: 'planilla-corridas',
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
    /** GET — los bytes de la firma que uno mismo registró */
    miFirmaImagen: 'mi-firma-imagen',
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

  /** GET documentos/{id}/descargar — bajárselo; el suyo, solo firmado */
  descargarDocumento: (documentoId: string) => `documentos/${documentoId}/descargar`,
  /** GET documentos/{id}/ver — abrirlo en pantalla; deja anotado que lo revisó */
  verDocumento: (documentoId: string) => `documentos/${documentoId}/ver`,

  /** POST documentos/subir — adjuntar un archivo (hoja de vida, contrato firmado) */
  subirDocumento: 'documentos/subir',

  /** POST y DELETE mi-foto — la foto de perfil del usuario autenticado */
  miFoto: 'mi-foto',

  /** GET users/{id}/foto — los bytes de la imagen, desde el disco privado */
  fotoDeUsuario: (userId: number | string) => `users/${userId}/foto`,

  /** POST documentos/{id}/firmar-empleador */
  firmarComoEmpleador: (documentoId: string) => `documentos/${documentoId}/firmar-empleador`,

  /** PATCH mis-documentos/{id}/visto */
  marcarDocumentoVisto: (documentoId: string) => `mis-documentos/${documentoId}/visto`,

  /** POST mis-documentos/{id}/firmar */
  firmarMiDocumento: (documentoId: string) => `mis-documentos/${documentoId}/firmar`,

  /** POST mis-documentos/hoja-de-vida — el trabajador sube su propio CV */
  subirMiHojaDeVida: 'mis-documentos/hoja-de-vida',

  /** POST empleados/{id}/identidad-firma */
  identidadFirmaEmpleado: (empleadoId: string) => `empleados/${empleadoId}/identidad-firma`,
  /** GET empleados/{id}/firma-imagen — los bytes de su firma (RR.HH. y Administración) */
  firmaImagenDeEmpleado: (empleadoId: string) => `empleados/${empleadoId}/firma-imagen`,

  /** GET vacaciones/saldo?empleado_id=&anio= */
  saldoVacaciones: 'vacaciones/saldo',

  /** POST users/{id}/restablecer-password */
  restablecerPasswordUsuario: (userId: string) => `users/${userId}/restablecer-password`,

  /** POST modulos/{id}/roles */
  asignarRolesModulo: (moduloId: string) => `modulos/${moduloId}/roles`,

  /** GET consulta-dni/{dni} — quién es esa persona, para el alta */
  consultaDni: (dni: string) => `consulta-dni/${dni}`,

  /** GET planilla/exportar?... — el reporte completo de la planilla en Excel */
  exportarPlanilla: 'planilla/exportar',

  /** POST planilla/{id}/conceptos — deja sus líneas como diga la pantalla */
  sincronizarConceptosPlanilla: (planillaId: string) => `planilla/${planillaId}/conceptos`,

  /** GET empleados/exportar?search= — la lista del personal en Excel */
  exportarEmpleados: 'empleados/exportar',

  /** GET expedientes?page&size&search&filtro — Documentos del personal, por trabajador */
  expedientes: 'expedientes',
  /** GET expedientes/{empleadoId} — el expediente de un trabajador */
  expediente: (empleadoId: string) => `expedientes/${empleadoId}`,

  /** POST importacion-conceptos/{paso} — cargar conceptos desde el Excel de RR.HH. */
  importacionConceptos: {
    /** GET ?mes=&anio= — el Excel modelo del mes, para llenar */
    modelo: 'importacion-conceptos/modelo',
    reconocer: 'importacion-conceptos/reconocer',
    previsualizar: 'importacion-conceptos/previsualizar',
    aplicar: 'importacion-conceptos/aplicar',
  },

  /** POST importacion-empleados/{paso} — altas y cambios desde Excel, y los CVs en lote */
  importacionEmpleados: {
    /** GET — el Excel modelo vacío, con listas desplegables e instrucciones */
    modelo: 'importacion-empleados/modelo',
    reconocer: 'importacion-empleados/reconocer',
    previsualizar: 'importacion-empleados/previsualizar',
    aplicar: 'importacion-empleados/aplicar',
    hojaDeVida: 'importacion-empleados/hoja-de-vida',
  },
  /** Boletas y contratos de antes del sistema, en lote: se revisa y se sube de a uno */
  documentosAnteriores: {
    previsualizar: 'documentos-anteriores/previsualizar',
    subir: 'documentos-anteriores',
  },
} as const;
