/**
 * El panel «Portal Web»: lo que se edita del portal del colegio (cata.edu.pe)
 * y cómo se agrupa en la pantalla.
 *
 * Las categorías son las páginas del portal, más «General» (lo que usan todas:
 * el nombre del colegio, sus teléfonos, sus redes). Dentro de cada una, sus
 * partes: una «ficha» es un contenido de una sola vez (la cabecera de una
 * página) y una «lista» es un conjunto de registros (banners, sedes,
 * noticias…), que se ordenan y se publican o pasan a borrador.
 *
 * Las claves de `ficha` y `lista` son las que cuenta el backend en
 * GET /web-portal/summary.
 */

export interface ConteoLista {
  publicados: number;
  borradores: number;
}

export interface ResumenPortal {
  /** Clave de la lista → cuántos hay publicados y en borrador. */
  listas: Record<string, ConteoLista>;
  /** Las fichas que ya están cargadas. */
  fichas: string[];
}

/** Publicado: se ve en el portal. Borrador: guardado, pero no se muestra. */
export type EstadoPortal = 'publicado' | 'borrador';

/** Una ficha del portal tal como la devuelve GET /web-portal/sections/{clave}. */
export interface FichaPortal<T> {
  clave: string;
  nombre: string;
  /** null si todavía no se cargó. Las imágenes van como { imagenId }. */
  contenido: T | null;
  /** Cada imagen del contenido, completa, para la vista previa: id → imagen. */
  imagenes: Record<string, ImagenPortal>;
  /** Se manda de vuelta al guardar: si alguien guardó entre medio, el backend responde 409. */
  version: string | null;
}

export interface TelefonoPortal {
  /** E.164: +51986322833 */
  numero: string;
  /** Como se lee: 986 322 833 */
  visible: string;
}

/** Los datos del colegio (ficha `sitio`, contrato §3.1, sin las redes). */
export interface SitioPortal {
  nombre: string;
  nombreCorto: string;
  subtitulo: string | null;
  ciudad: string;
  anioFundacion: number;
  telefono: TelefonoPortal;
  whatsapp: TelefonoPortal;
  direccion: { linea: string; referencia: string | null; distrito: string | null };
  portalAcademico: { nombre: string; url: string } | null;
  promotora: string | null;
}

/** Cómo va una imagen dentro de una ficha guardada. */
export interface RefImagen {
  imagenId: string;
}

/** La cabecera de Contacto (ficha `contacto`, contrato §3.16). */
export interface ContactoPortal {
  titulo: string;
  bajada: string | null;
  figura: RefImagen | null;
  correo: string | null;
}

/** Lo que se manda al guardarla: la figura va solo con su id. */
export interface ContactoParaGuardar {
  titulo: string;
  bajada: string | null;
  figura: string | null;
  correo: string | null;
}

export type PaginaConFormulario = 'matricula' | 'contacto';

/** Los textos de «Solicitar información» (ficha `solicitud`, contrato §3.15). */
export interface SolicitudPortal {
  titulo: string;
  texto: string | null;
  mensajeWhatsapp: string;
  mensajesPorPagina: { pagina: PaginaConFormulario; mensaje: string }[];
}

/** Una sede como la ve el panel (contrato §3.6, con los nombres de las columnas). */
export interface SedePortal {
  id: string;
  clave: string;
  nombre: string;
  direccion: string;
  referencia: string | null;
  distrito: string | null;
  telefono: TelefonoPortal | null;
  nota: string | null;
  imagen_id: string | null;
  /** La foto completa, para la vista previa. */
  imagen: ImagenPortal | null;
  mapa_url: string | null;
  correo: string | null;
  horario: { dias: string; horas: string }[];
  mapa_embed_url: string | null;
  orden: number;
  estado: EstadoPortal;
}

/** Una página con su propia lista de cifras. */
export type AmbitoDeCifras = 'portada' | 'logros' | 'propuesta';

/** Una cifra destacada (contrato §3.9). */
export interface CifraPortal {
  id: string;
  clave: string;
  ambito: AmbitoDeCifras;
  valor: number;
  sufijo: string | null;
  etiqueta: string;
  orden: number;
  estado: EstadoPortal;
}

/** Una cabecera de solo título y frase (Logros, Proyectos, Nosotros). */
export interface CabeceraSimple {
  titulo: string;
  bajada: string | null;
}

export const CATEGORIAS_DE_LOGRO: { valor: string; nombre: string }[] = [
  { valor: 'academico', nombre: 'Académico' },
  { valor: 'deportivo', nombre: 'Deportivo' },
  { valor: 'artistico', nombre: 'Artístico' },
  { valor: 'institucional', nombre: 'Institucional' },
];

/** Un logro (contrato §3.22), con los nombres de las columnas. */
export interface LogroPortal {
  id: string;
  clave: string;
  titulo: string;
  categoria: string;
  anio: number | null;
  nivel: string | null;
  descripcion: string | null;
  imagen_id: string | null;
  imagen: ImagenPortal | null;
  destacado: boolean;
  fuente: { etiqueta: string; url: string | null } | null;
  aclaracion: string | null;
  orden: number;
  estado: EstadoPortal;
}

// ───────── Inicio ─────────

/** A dónde lleva un botón (contrato §1.8). */
export type DestinoPortal =
  | { tipo: 'seccion'; seccion: string }
  | { tipo: 'pagina'; ruta: string }
  | { tipo: 'url'; url: string; externo: boolean };

/** Un botón: lo que dice y a dónde lleva (`AccionHero`, contrato §3.3). */
export interface AccionPortal {
  etiqueta: string;
  destino: DestinoPortal;
}

export const CATEGORIAS_DE_BANNER: { valor: string; nombre: string }[] = [
  { valor: 'aviso', nombre: 'Aviso' },
  { valor: 'matricula', nombre: 'Matrícula' },
  { valor: 'evento', nombre: 'Evento' },
  { valor: 'noticia', nombre: 'Noticia' },
];

/** Un banner del carrusel (contrato §3.2), con los nombres de las columnas. */
export interface BannerPortal {
  id: string;
  clave: string;
  categoria: string;
  titulo: string;
  /** AAAA-MM-DD. */
  fecha: string | null;
  imagen_id: string;
  imagen: ImagenPortal | null;
  imagen_movil_id: string | null;
  imagen_movil: ImagenPortal | null;
  foco_x: number | null;
  foco_y: number | null;
  texto_en_imagen: boolean;
  lado_accion: 'izquierda' | 'derecha';
  accion: AccionPortal | null;
  orden: number;
  estado: EstadoPortal;
}

/** «Quiénes somos» (ficha `quienes-somos`, contrato §3.4). */
export interface QuienesSomosPortal {
  titulo: string;
  parrafos: string[];
  foto: RefImagen | null;
  simbolos: { nombre: string; significado: string }[];
}

/** La sección Niveles de Inicio (ficha `niveles`, contrato §3.5). */
export interface SeccionNiveles {
  titulo: string;
  propuestaUrl: string | null;
}

/** Un nivel de Inicio (contrato §3.5). */
export interface NivelPortal {
  id: string;
  clave: string;
  nombre: string;
  rango: string | null;
  sede: string | null;
  reconocimiento: string | null;
  imagen_id: string | null;
  imagen: ImagenPortal | null;
  orden: number;
  estado: EstadoPortal;
}

/** Una noticia o comunicado (contrato §3.7). */
export interface NoticiaPortal {
  id: string;
  clave: string;
  slug: string;
  tipo: 'noticia' | 'comunicado';
  titulo: string;
  resumen: string | null;
  /** AAAA-MM-DD. */
  fecha: string;
  imagen_id: string | null;
  imagen: ImagenPortal | null;
  enlace: { etiqueta: string; url: string; externo: boolean } | null;
  orden: number;
  estado: EstadoPortal;
}

// ───────── Proyectos ─────────

/** Una categoría del filtro de Proyectos (contrato §3.17). */
export interface CategoriaProyectoPortal {
  id: string;
  clave: string;
  nombre: string;
  orden: number;
  /** Cuántos proyectos tiene: con alguno, no se elimina. */
  proyectos: number;
}

export const ETAPAS_DE_PROYECTO: { valor: string; nombre: string }[] = [
  { valor: 'en-curso', nombre: 'En curso' },
  { valor: 'proximo', nombre: 'Próximamente' },
  { valor: 'finalizado', nombre: 'Finalizado' },
];

/** Una sección del detalle de un proyecto: viñetas o placas numeradas. */
export interface SeccionProyecto {
  titulo: string;
  tipo: 'lista' | 'numerada';
  items: { titulo: string | null; texto: string }[];
}

/** Un proyecto (contrato §3.17 y §3.18), con los nombres de las columnas. */
export interface ProyectoPortal {
  id: string;
  clave: string;
  slug: string;
  titulo: string;
  lema: string | null;
  resumen: string;
  categoria_id: string;
  niveles: string[];
  etapa: string | null;
  /** AAAA-MM-DD. */
  fecha: string | null;
  imagen_id: string | null;
  imagen: ImagenPortal | null;
  descripcion: string[];
  secciones: SeccionProyecto[];
  galeria: ImagenPortal[];
  orden: number;
  estado: EstadoPortal;
}

// ───────── Propuesta Educativa ─────────

export type TipoDeFigura = 'recorte' | 'foto';

/** La cabecera de Propuesta Educativa (ficha `propuesta-cabecera`, contrato §3.3). */
export interface CabeceraPropuesta {
  titulo: string;
  bajada: string | null;
  figuras: { tipo: TipoDeFigura; imagen: RefImagen }[];
  accionPrincipal: AccionPortal;
  accionSecundaria: AccionPortal | null;
}

/** La introducción de los pilares (ficha `propuesta-pilares`, contrato §3.23). null = sin introducción. */
export interface IntroduccionPilares {
  introduccion: { titulo: string; parrafos: string[] } | null;
}

/** Los textos de la academia (ficha `propuesta-academia`, contrato §3.25). */
export interface AcademiaPortal {
  titulo: string;
  descripcion: string | null;
  aclaracion: string | null;
}

export interface PilarPortal {
  id: string;
  clave: string;
  titulo: string;
  texto: string;
  /** Sus aspectos («Espiritual»…), 0 a 8. */
  puntos: string[];
  orden: number;
  estado: EstadoPortal;
}

/** Un nivel de Propuesta Educativa (contrato §3.24). Aparte de los de Inicio. */
export interface NivelPropuestaPortal {
  id: string;
  clave: string;
  nombre: string;
  rango: string | null;
  descripcion: string[];
  rasgos: string[];
  talleres: { titulo: string; texto: string | null }[];
  imagen_id: string | null;
  imagen: ImagenPortal | null;
  orden: number;
  estado: EstadoPortal;
}

export interface UniversidadPortal {
  id: string;
  /** El id del filtro en la dirección (?universidad=…). No cambia. */
  clave: string;
  nombre: string;
  /** Cuántos ciclos tiene: sin ciclos no se publica. */
  ciclos: number;
  orden: number;
  estado: EstadoPortal;
}

export type ModalidadDeCiclo = 'presencial' | 'virtual';

export const MODALIDADES_DE_CICLO: { valor: ModalidadDeCiclo; nombre: string }[] = [
  { valor: 'presencial', nombre: 'Presencial' },
  { valor: 'virtual', nombre: 'Virtual' },
];

/** Un ciclo de la academia (CicloAcademia, contrato §3.25). Sin borrador: se ve con su universidad. */
export interface CicloPortal {
  id: string;
  clave: string;
  universidad_id: string;
  nombre: string;
  turno: string | null;
  modalidades: ModalidadDeCiclo[];
  /** En soles; null = no vigente, no se muestra. */
  precio: number | null;
  precio_virtual: number | null;
  /** AAAA-MM-DD. */
  inicio: string | null;
  fin: string | null;
  orden: number;
}

/** Una plataforma digital (contrato §3.26). */
export interface PlataformaPortal {
  id: string;
  clave: string;
  nombre: string;
  descripcion: string | null;
  /** Qué ofrece a quién: «Docentes» y lo que hacen ahí. */
  funciones: { titulo: string; texto: string }[];
  enlace: { etiqueta: string; url: string; externo: boolean } | null;
  aclaracion: string | null;
  orden: number;
  estado: EstadoPortal;
}

// ───────── Nosotros ─────────

/**
 * Las subpáginas que el portal sabe abrir (contrato §3.19), con su grupo y su
 * nombre en el menú. Es la misma lista que PaginasDeNosotros en el backend.
 */
export const PAGINAS_DE_NOSOTROS: { slug: string; grupo: string; nombre: string }[] = [
  { slug: 'mision-y-vision', grupo: 'Institución', nombre: 'Misión y Visión' },
  { slug: 'resena-historica', grupo: 'Institución', nombre: 'Reseña Histórica' },
  { slug: 'emblema', grupo: 'Institución', nombre: 'Emblema' },
  { slug: 'himno', grupo: 'Institución', nombre: 'Himno' },
  { slug: 'consejo-directivo', grupo: 'Institución', nombre: 'Consejo Directivo' },
  { slug: 'administracion', grupo: 'Institución', nombre: 'Administración' },
  { slug: 'personal-docente', grupo: 'Institución', nombre: 'Personal Docente' },
  { slug: 'organigrama', grupo: 'Institución', nombre: 'Organigrama' },
  { slug: 'procesos', grupo: 'Gestión', nombre: 'Procesos' },
  { slug: 'planificacion-anual', grupo: 'Gestión', nombre: 'Planificación Anual' },
  { slug: 'planes-de-mejora', grupo: 'Gestión', nombre: 'Planes de Mejora' },
  { slug: 'evaluacion-docente', grupo: 'Gestión', nombre: 'Evaluación Docente' },
  { slug: 'acreditacion', grupo: 'Gestión', nombre: 'Acreditación' },
  { slug: 'instrumentos-de-gestion', grupo: 'Documentos', nombre: 'Instrumentos de Gestión' },
  { slug: 'manual-de-identidad', grupo: 'Documentos', nombre: 'Manual de Identidad' },
  { slug: 'planificador-eva', grupo: 'Documentos', nombre: 'Planificador Inteligente EVA' },
];

/** Una subpágina de Nosotros, sin sus bloques (solo cuántos tiene). */
export interface PaginaNosotros {
  id: string;
  /** La dirección: /nosotros/{slug}. No cambia. */
  slug: string;
  grupo: string | null;
  nombre: string | null;
  titulo: string;
  bajada: string | null;
  /** La frase de su tarjeta en el índice; null = solo el nombre. */
  resumen: string | null;
  bloques: number;
  orden: number;
  estado: EstadoPortal;
}

/** Una subpágina con sus bloques, para el editor (GET pages/{id}). */
export interface PaginaConBloques extends Omit<PaginaNosotros, 'bloques'> {
  /** Como se guardan: las imágenes como { imagenId }. */
  bloques: Record<string, unknown>[];
  /** Cada imagen de los bloques, completa, para la vista previa. */
  imagenes: Record<string, ImagenPortal>;
  /** Se manda de vuelta al guardar: si alguien guardó entre medio, 409. */
  version: string | null;
}

/** Un nivel (o grupo) de Personal Docente, con su filtro. */
export interface NivelDocentePortal {
  id: string;
  clave: string;
  nombre: string;
  docentes: number;
  orden: number;
  estado: EstadoPortal;
}

export interface DocentePortal {
  id: string;
  clave: string;
  nombre: string;
  /** Cargo o área: «Tutora de 3 años», «Matemática». */
  cargo: string;
  nivel_id: string;
  imagen_id: string | null;
  imagen: ImagenPortal | null;
  orden: number;
  estado: EstadoPortal;
}

// ───────── Matrícula ─────────

/** La cabecera de Matrícula (ficha `matricula-cabecera`, contrato §3.10). */
export interface MatriculaCabecera {
  titulo: string;
  bajada: string | null;
  /** Lo pone el colegio; null = sin placa del año. */
  anio: number | null;
  figura: RefImagen | null;
}

/** Fecha de actualización y nota de las vacantes (ficha `matricula-vacantes`). */
export interface MatriculaVacantesNota {
  /** AAAA-MM-DD. */
  actualizado: string | null;
  nota: string | null;
}

/** Un paso de un proceso, en su orden. */
export interface PasoMatricula {
  titulo: string;
  detalle: string | null;
}

/** Una lista de un proceso (requisitos, documentos). */
export interface ListaMatricula {
  titulo: string;
  items: string[];
}

/** Un proceso de matrícula (contrato §3.11). */
export interface ProcesoPortal {
  id: string;
  clave: string;
  nombre: string;
  resumen: string | null;
  pasos: PasoMatricula[];
  listas: ListaMatricula[];
  orden: number;
  estado: EstadoPortal;
}

export type DisponibilidadVacantes = 'disponible' | 'pocas' | 'agotadas' | 'consultar';

/** Lo que muestra el portal con cada una (contrato §3.12). */
export const DISPONIBILIDADES: { valor: DisponibilidadVacantes; nombre: string; ayuda: string; conNumero: boolean }[] = [
  { valor: 'consultar', nombre: 'Consultar', ayuda: '«Consulta las vacantes», sin número. Mientras no haya cifras confirmadas.', conNumero: false },
  { valor: 'disponible', nombre: 'Disponibles', ayuda: 'El número y «vacantes»; sin número, «Vacantes disponibles».', conNumero: true },
  { valor: 'pocas', nombre: 'Últimas vacantes', ayuda: 'El número y «Últimas vacantes»; sin número, solo «Últimas vacantes».', conNumero: true },
  { valor: 'agotadas', nombre: 'Agotadas', ayuda: '«Vacantes agotadas», sin número.', conNumero: false },
];

/** Las vacantes de un nivel (contrato §3.12). */
export interface VacantePortal {
  id: string;
  clave: string;
  nivel: string;
  detalle: string | null;
  disponibilidad: DisponibilidadVacantes;
  vacantes: number | null;
  orden: number;
  estado: EstadoPortal;
}

/** Una fecha del cronograma (contrato §3.13). */
export interface FechaPortal {
  id: string;
  clave: string;
  titulo: string;
  /** AAAA-MM-DD. */
  inicio: string;
  /** null = un solo día. */
  fin: string | null;
  detalle: string | null;
  orden: number;
  estado: EstadoPortal;
}

/** Una pregunta frecuente (contrato §3.14). */
export interface PreguntaPortal {
  id: string;
  clave: string;
  pregunta: string;
  respuesta: string;
  orden: number;
  estado: EstadoPortal;
}

/** Las opciones de grado de un nivel (contrato §3.8). */
export interface GrupoGradosPortal {
  id: string;
  nivel: string;
  opciones: string[];
  orden: number;
  estado: EstadoPortal;
}

/** Las redes que admite el contrato; el portal pone el icono y el color de cada una. */
export const REDES_DEL_PORTAL: { valor: string; nombre: string }[] = [
  { valor: 'facebook', nombre: 'Facebook' },
  { valor: 'youtube', nombre: 'YouTube' },
  { valor: 'tiktok', nombre: 'TikTok' },
  { valor: 'whatsapp', nombre: 'WhatsApp' },
  { valor: 'instagram', nombre: 'Instagram' },
  { valor: 'x', nombre: 'X' },
  { valor: 'linkedin', nombre: 'LinkedIn' },
];

export interface RedSocialPortal {
  id: string;
  red: string;
  cuenta: string;
  url: string;
  aclaracion: string | null;
  orden: number;
  estado: EstadoPortal;
}

/** Para qué se sube una imagen y qué espera el portal (lo dice el backend: GET /web-portal/images/uses). */
export interface UsoDeImagen {
  nombre: string;
  /** Cómo la recorta el portal; null = se muestra completa. */
  proporcion: [number, number] | null;
  /** Tamaño mínimo recomendado [ancho, alto]. */
  minimo: [number, number];
  /** Un recorte sin fondo: conserva la transparencia. */
  transparente: boolean;
  /** Si se elige el punto que no se recorta (los banners). */
  foco: boolean;
}

/** Una imagen ya subida, como la devuelve el panel. */
export interface ImagenPortal {
  id: string;
  url: string;
  /** "" = decorativa. */
  alt: string;
  ancho: number | null;
  alto: number | null;
  bytes: number | null;
}

export interface ImagenSubida {
  imagen: ImagenPortal;
  /** Lo que no impidió guardarla pero conviene leer (más chica, otra proporción…). */
  avisos: string[];
}

/** El punto que queda a la vista al recortar, en % desde arriba a la izquierda. */
export interface PuntoFocal {
  x: number;
  y: number;
}

export interface ParteDelPortal {
  nombre: string;
  /** Qué se edita ahí, en palabras de quien lo usa. */
  descripcion: string;
  ficha?: string;
  lista?: string;
}

export type IdCategoria =
  | 'general'
  | 'inicio'
  | 'proyectos'
  | 'nosotros'
  | 'propuesta'
  | 'matricula'
  | 'logros'
  | 'contacto';

export interface CategoriaDelPortal {
  id: IdCategoria;
  nombre: string;
  icono: string;
  descripcion: string;
  partes: ParteDelPortal[];
  /** Si ya se puede editar desde el panel. Se van habilitando por etapas. */
  disponible: boolean;
}

/** En el orden del menú del portal, con «General» primero. */
export const CATEGORIAS_DEL_PORTAL: CategoriaDelPortal[] = [
  {
    id: 'general',
    nombre: 'General',
    icono: 'settings',
    descripcion: 'Lo que aparece en todas las páginas: la cabecera, el pie y el botón de WhatsApp.',
    disponible: true,
    partes: [
      { nombre: 'Datos del colegio', descripcion: 'Nombre, teléfono, WhatsApp, dirección y plataforma académica', ficha: 'sitio' },
      { nombre: 'Redes sociales', descripcion: 'Las cuentas oficiales, en el orden en que se muestran', lista: 'redes' },
    ],
  },
  {
    id: 'inicio',
    nombre: 'Inicio',
    icono: 'dashboard',
    descripcion: 'La portada del portal: el carrusel, las cifras y lo que presenta al colegio.',
    disponible: true,
    partes: [
      { nombre: 'Carrusel', descripcion: 'Los afiches de la portada, con su botón', lista: 'banners' },
      { nombre: 'Cifras', descripcion: 'Los números destacados bajo el carrusel', lista: 'cifras-portada' },
      { nombre: 'Quiénes somos', descripcion: 'Título, párrafos, foto y el significado del emblema', ficha: 'quienes-somos' },
      { nombre: 'Niveles', descripcion: 'Inicial, primaria y secundaria, con su foto', lista: 'niveles' },
      { nombre: 'Noticias y comunicados', descripcion: 'Lo que publica el colegio, de lo más reciente a lo más antiguo', lista: 'noticias' },
    ],
  },
  {
    id: 'proyectos',
    nombre: 'Proyectos',
    icono: 'lightbulb',
    descripcion: 'Los proyectos del colegio, con su página de detalle y su galería.',
    disponible: true,
    partes: [
      { nombre: 'Cabecera', descripcion: 'El título y la frase de la página', ficha: 'proyectos' },
      { nombre: 'Proyectos', descripcion: 'Cada proyecto con su categoría, secciones y fotos', lista: 'proyectos' },
    ],
  },
  {
    id: 'nosotros',
    nombre: 'Nosotros',
    icono: 'people',
    descripcion: 'Misión, historia, emblema, himno, autoridades, documentos y el personal docente.',
    disponible: true,
    partes: [
      { nombre: 'Índice', descripcion: 'El título de Nosotros y la frase de cada subpágina', ficha: 'nosotros' },
      { nombre: 'Subpáginas', descripcion: 'Cada subpágina, armada con bloques de texto, fotos y listas', lista: 'paginas' },
      { nombre: 'Personal docente', descripcion: 'Los docentes por nivel, con su foto', lista: 'docentes' },
    ],
  },
  {
    id: 'propuesta',
    nombre: 'Propuesta Educativa',
    icono: 'graduation_cap',
    descripcion: 'Cómo enseña el colegio: pilares, niveles, academia y plataformas.',
    disponible: true,
    partes: [
      { nombre: 'Cabecera', descripcion: 'Título, frase, botones y fotos de estudiantes', ficha: 'propuesta-cabecera' },
      { nombre: 'Cifras', descripcion: 'Los números de la franja bajo la cabecera', lista: 'cifras-propuesta' },
      { nombre: 'Pilares', descripcion: 'La introducción y cada pilar', lista: 'pilares' },
      { nombre: 'Niveles', descripcion: 'Cada nivel con sus rasgos y talleres', lista: 'nivelesPropuesta' },
      { nombre: 'Academia preuniversitaria', descripcion: 'Las universidades y sus ciclos', lista: 'universidades' },
      { nombre: 'Plataformas digitales', descripcion: 'Cubicol y las demás, con lo que ofrece cada una', lista: 'plataformas' },
    ],
  },
  {
    id: 'matricula',
    nombre: 'Matrícula',
    icono: 'clipboard_check',
    descripcion: 'El proceso de matrícula: requisitos, vacantes, fechas y preguntas.',
    disponible: true,
    partes: [
      { nombre: 'Cabecera', descripcion: 'Título, frase, año del proceso y foto', ficha: 'matricula-cabecera' },
      { nombre: 'Procesos', descripcion: 'Pasos, requisitos y documentos de cada tipo de estudiante', lista: 'procesos' },
      { nombre: 'Vacantes', descripcion: 'La disponibilidad por nivel', lista: 'vacantes' },
      { nombre: 'Cronograma', descripcion: 'Las fechas del proceso', lista: 'fechas' },
      { nombre: 'Preguntas frecuentes', descripcion: 'Las dudas de las familias, con su respuesta', lista: 'preguntas' },
      { nombre: 'Grados', descripcion: 'Las opciones del formulario «Solicitar información»', lista: 'grados' },
    ],
  },
  {
    id: 'logros',
    nombre: 'Logros',
    icono: 'trending_up',
    descripcion: 'Los logros confirmados del colegio, cada uno con su fuente.',
    disponible: true,
    partes: [
      { nombre: 'Cabecera', descripcion: 'El título y la frase de la página', ficha: 'logros' },
      { nombre: 'Cifras', descripcion: 'Los números destacados', lista: 'cifras-logros' },
      { nombre: 'Logros', descripcion: 'Cada logro con su año, su fuente y si es destacado', lista: 'logros' },
    ],
  },
  {
    id: 'contacto',
    nombre: 'Contacto',
    icono: 'phone',
    descripcion: 'Cómo encontrar al colegio: sedes, mapas, correo y el formulario de WhatsApp.',
    disponible: true,
    partes: [
      { nombre: 'Cabecera', descripcion: 'Título, frase, foto y correo general', ficha: 'contacto' },
      { nombre: 'Sedes', descripcion: 'Dirección, teléfono, horario, foto y mapa de cada sede', lista: 'sedes' },
      { nombre: 'Formulario «Solicitar información»', descripcion: 'Sus textos y el mensaje de WhatsApp de cada página', ficha: 'solicitud' },
    ],
  },
];
