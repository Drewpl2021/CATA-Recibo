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
  /** null si todavía no se cargó. */
  contenido: T | null;
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
    disponible: false,
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
    disponible: false,
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
    disponible: false,
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
    disponible: false,
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
    disponible: false,
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
    disponible: false,
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
    disponible: false,
    partes: [
      { nombre: 'Cabecera', descripcion: 'Título, frase, foto y correo general', ficha: 'contacto' },
      { nombre: 'Sedes', descripcion: 'Dirección, teléfono, horario, foto y mapa de cada sede', lista: 'sedes' },
      { nombre: 'Formulario «Solicitar información»', descripcion: 'Sus textos y el mensaje de WhatsApp de cada página', ficha: 'solicitud' },
    ],
  },
];
