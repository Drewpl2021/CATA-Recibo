import { ImagenPortal, RefImagen } from '../../portal-web.models';

/**
 * Los bloques de una subpágina de Nosotros (contrato §3.20) y cómo se
 * editan. Quien edita no ve el JSON: cada tipo tiene sus campos, y las
 * listas de textos (párrafos, versos) se escriben una por línea.
 *
 * Las reglas de verdad las pone el backend (Bloques::problemas, la misma
 * clase que sirve la página): si algo no cumple, dice qué y en qué bloque.
 */

export type TipoDeBloque =
  | 'texto' | 'destacado' | 'lista' | 'hitos' | 'imagen' | 'personas'
  | 'mensaje' | 'letra' | 'audio' | 'organigrama' | 'enlaces';

export const TIPOS_DE_BLOQUE: { valor: TipoDeBloque; nombre: string; ayuda: string }[] = [
  { valor: 'texto', nombre: 'Texto', ayuda: 'Párrafos, con un título y una firma opcionales.' },
  { valor: 'destacado', nombre: 'Destacado', ayuda: 'Una placa con un rótulo: Misión, Visión, Lema. Los seguidos van lado a lado.' },
  { valor: 'lista', nombre: 'Lista', ayuda: 'Viñetas, o tarjetas (valores, símbolos) con la fuente de la cita.' },
  { valor: 'hitos', nombre: 'Línea de tiempo', ayuda: 'Fechas con lo que pasó, de arriba abajo.' },
  { valor: 'imagen', nombre: 'Imagen', ayuda: 'Una imagen completa, sin recorte, con su pie y su transcripción si tiene texto.' },
  { valor: 'personas', nombre: 'Personas', ayuda: 'Tarjetas con foto redonda. Un bloque por área, cada uno con su título.' },
  { valor: 'mensaje', nombre: 'Carta', ayuda: 'Una carta firmada, con la foto de quien la firma.' },
  { valor: 'letra', nombre: 'Letra', ayuda: 'La letra de una canción. Una parte llamada «Coro» va arriba, destacada.' },
  { valor: 'audio', nombre: 'Audio', ayuda: 'Un reproductor que nunca suena solo.' },
  { valor: 'organigrama', nombre: 'Organigrama', ayuda: 'Los cargos como lista jerárquica, con una imagen opcional encima.' },
  { valor: 'enlaces', nombre: 'Documentos', ayuda: 'Enlaces a documentos, con su formato y su peso.' },
];

export const NOMBRE_DE_TIPO: Record<string, string> = Object.fromEntries(TIPOS_DE_BLOQUE.map((t) => [t.valor, t.nombre]));

export const FORMATOS_DE_AUDIO = [
  { valor: 'audio/mpeg', nombre: 'MP3' },
  { valor: 'audio/mp4', nombre: 'M4A' },
  { valor: 'audio/ogg', nombre: 'OGG' },
  { valor: 'audio/wav', nombre: 'WAV' },
];

export const FORMATOS_DE_DOCUMENTO = [
  { valor: 'pdf', nombre: 'PDF' },
  { valor: 'web', nombre: 'Página web' },
  { valor: 'hoja', nombre: 'Hoja de cálculo' },
  { valor: 'presentacion', nombre: 'Presentación' },
  { valor: 'documento', nombre: 'Documento' },
  { valor: 'canva', nombre: 'Canva' },
];

/** Un bloque mientras se edita: sus campos, con las imágenes completas y los textos «uno por línea». */
export type BloqueEnEdicion = { tipo: TipoDeBloque; clave: number } & Record<string, any>;

/**
 * Un objeto JSON cualquiera: los bloques tienen once formas distintas y se
 * leen campo por campo, con un valor por defecto para lo que falte.
 */
type Json = any;

let siguienteClave = 1;

/** Las líneas no vacías de un texto. */
function lineas(texto: string | null | undefined): string[] {
  return (texto ?? '').split('\n').map((l) => l.trim()).filter(Boolean);
}

function vacio(texto: string | null | undefined): string | null {
  const t = (texto ?? '').trim();
  return t === '' ? null : t;
}

function imagenDe(ref: RefImagen | null | undefined, imagenes: Record<string, ImagenPortal>): ImagenPortal | null {
  return ref?.imagenId ? imagenes[ref.imagenId] ?? null : null;
}

function refDe(imagen: ImagenPortal | null | undefined): RefImagen | null {
  return imagen ? { imagenId: imagen.id } : null;
}

// ───────── El organigrama, como texto con sangría ─────────

export interface Cargo {
  cargo: string;
  detalle: string | null;
  hijos: Cargo[];
}

/**
 * El árbol como texto: un cargo por línea, dos espacios de sangría por nivel
 * y el detalle después de « — »:
 *
 *   Dirección General — Mg. Ana Pérez
 *     Subdirección
 *       Coordinación de Primaria
 */
export function organigramaATexto(raiz: Cargo | null | undefined, nivel = 0): string {
  if (!raiz) return '';
  const linea = '  '.repeat(nivel) + raiz.cargo + (raiz.detalle ? ` — ${raiz.detalle}` : '');
  return [linea, ...(raiz.hijos ?? []).map((h) => organigramaATexto(h, nivel + 1))].join('\n');
}

/** El texto con sangría → el árbol. La primera línea es el cargo de arriba; si hay más de una sin sangría, cuelgan de ella. */
export function textoAOrganigrama(texto: string): Cargo | null {
  const filas = (texto ?? '').split('\n').filter((l) => l.trim() !== '').map((l) => {
    const sangria = (l.match(/^[ \t]*/)?.[0] ?? '').replace(/\t/g, '  ').length;
    const [cargo, ...detalle] = l.trim().split(/\s+—\s+/);
    return { nivel: Math.floor(sangria / 2), cargo: { cargo: cargo.trim(), detalle: vacio(detalle.join(' — ')), hijos: [] as Cargo[] } };
  });
  if (!filas.length) return null;

  const raiz = filas[0].cargo;
  // La pila de padres: el último cargo visto en cada nivel.
  const pila: { nivel: number; cargo: Cargo }[] = [{ nivel: filas[0].nivel, cargo: raiz }];
  for (const fila of filas.slice(1)) {
    while (pila.length > 1 && pila[pila.length - 1].nivel >= fila.nivel) pila.pop();
    pila[pila.length - 1].cargo.hijos.push(fila.cargo);
    pila.push(fila);
  }
  return raiz;
}

// ───────── De lo guardado al editor, y de vuelta ─────────

/** Un bloque nuevo, vacío, de ese tipo. */
export function bloqueNuevo(tipo: TipoDeBloque): BloqueEnEdicion {
  return aEditor({ tipo }, {});
}

/** Lo guardado (con {imagenId}) → lo que edita el formulario. */
export function aEditor(b: Json, imagenes: Record<string, ImagenPortal>): BloqueEnEdicion {
  const base = { tipo: b.tipo as TipoDeBloque, clave: siguienteClave++, titulo: b.titulo ?? '' };
  const firma = { firmaNombre: b.firma?.nombre ?? '', firmaDetalle: b.firma?.detalle ?? '' };

  switch (b.tipo as TipoDeBloque) {
    case 'texto':
      return { ...base, parrafos: (b.parrafos ?? []).join('\n'), ...firma };
    case 'destacado':
      return { ...base, rotulo: b.rotulo ?? '', texto: b.texto ?? '' };
    case 'lista':
      return {
        ...base, estilo: b.estilo ?? 'vinetas',
        items: (b.items ?? []).map((i: Json) => ({ titulo: i.titulo ?? '', texto: i.texto ?? '', fuente: i.fuente ?? '' })),
      };
    case 'hitos':
      return { ...base, items: (b.items ?? []).map((i: Json) => ({ fecha: i.fecha ?? '', texto: i.texto ?? '' })) };
    case 'imagen':
      return { ...base, imagen: imagenDe(b.imagen, imagenes), pie: b.pie ?? '', transcripcion: (b.transcripcion ?? []).join('\n') };
    case 'personas':
      return {
        ...base,
        personas: (b.personas ?? []).map((p: Json) => ({ id: p.id ?? null, nombre: p.nombre ?? '', cargo: p.cargo ?? '', foto: imagenDe(p.foto, imagenes), clave: siguienteClave++ })),
      };
    case 'mensaje':
      return { ...base, parrafos: (b.parrafos ?? []).join('\n'), ...firma, foto: imagenDe(b.foto, imagenes) };
    case 'letra':
      return { ...base, partes: (b.partes ?? []).map((p: Json) => ({ titulo: p.titulo ?? '', versos: (p.versos ?? []).join('\n') })) };
    case 'audio':
      return { ...base, url: b.url ?? '', formato: b.formato ?? 'audio/mpeg', descripcion: b.descripcion ?? '' };
    case 'organigrama':
      return {
        ...base, imagen: imagenDe(b.imagen, imagenes), arbol: organigramaATexto(b.raiz),
        conEnlace: !!b.enlace, enlaceEtiqueta: b.enlace?.etiqueta ?? '', enlaceUrl: b.enlace?.url ?? '',
      };
    case 'enlaces':
      return {
        ...base,
        enlaces: (b.enlaces ?? []).map((e: Json) => ({
          etiqueta: e.etiqueta ?? '', url: e.url ?? '', descripcion: e.descripcion ?? '', formato: e.formato ?? 'pdf',
          // En el panel, en KB: nadie piensa en bytes.
          pesoKb: e.peso ? Math.round((e.peso / 1024) * 10) / 10 : null,
        })),
      };
    default:
      // Un tipo que el panel no conoce: se conserva tal cual (y el backend dirá qué le pasa).
      return { ...b, clave: siguienteClave++ };
  }
}

/** Lo que edita el formulario → lo que se guarda: los campos del contrato, en su orden, con {imagenId}. */
export function aGuardar(b: BloqueEnEdicion): object {
  const titulo = vacio(b['titulo']);
  const firma = vacio(b['firmaNombre']) ? { nombre: vacio(b['firmaNombre']), detalle: vacio(b['firmaDetalle']) } : null;

  switch (b.tipo) {
    case 'texto':
      return { tipo: 'texto', titulo, parrafos: lineas(b['parrafos']), firma };
    case 'destacado':
      return { tipo: 'destacado', rotulo: (b['rotulo'] ?? '').trim(), texto: (b['texto'] ?? '').trim() };
    case 'lista':
      return {
        tipo: 'lista', titulo, estilo: b['estilo'],
        items: b['items'].map((i: Json) => ({ titulo: vacio(i.titulo), texto: (i.texto ?? '').trim(), fuente: vacio(i.fuente) })),
      };
    case 'hitos':
      return { tipo: 'hitos', titulo, items: b['items'].map((i: Json) => ({ fecha: (i.fecha ?? '').trim(), texto: (i.texto ?? '').trim() })) };
    case 'imagen': {
      const transcripcion = lineas(b['transcripcion']);
      return { tipo: 'imagen', imagen: refDe(b['imagen']), pie: vacio(b['pie']), transcripcion: transcripcion.length ? transcripcion : null };
    }
    case 'personas':
      return {
        tipo: 'personas', titulo,
        personas: b['personas'].map((p: Json) => ({ id: p.id, nombre: (p.nombre ?? '').trim(), cargo: (p.cargo ?? '').trim(), foto: refDe(p.foto) })),
      };
    case 'mensaje':
      return { tipo: 'mensaje', titulo, parrafos: lineas(b['parrafos']), firma, foto: refDe(b['foto']) };
    case 'letra':
      return { tipo: 'letra', titulo, partes: b['partes'].map((p: Json) => ({ titulo: (p.titulo ?? '').trim(), versos: lineas(p.versos) })) };
    case 'audio':
      return { tipo: 'audio', titulo: (b['titulo'] ?? '').trim(), url: (b['url'] ?? '').trim(), formato: b['formato'], descripcion: vacio(b['descripcion']) };
    case 'organigrama':
      return {
        tipo: 'organigrama', titulo, imagen: refDe(b['imagen']),
        enlace: b['conEnlace'] ? { etiqueta: (b['enlaceEtiqueta'] ?? '').trim(), url: (b['enlaceUrl'] ?? '').trim(), externo: true } : null,
        raiz: textoAOrganigrama(b['arbol']),
      };
    case 'enlaces':
      return {
        tipo: 'enlaces', titulo,
        enlaces: b['enlaces'].map((e: Json) => ({
          etiqueta: (e.etiqueta ?? '').trim(), url: (e.url ?? '').trim(), descripcion: vacio(e.descripcion), formato: e.formato,
          peso: e.pesoKb ? Math.round(Number(e.pesoKb) * 1024) : null,
        })),
      };
    default: {
      const { clave, ...resto } = b;
      return resto;
    }
  }
}
