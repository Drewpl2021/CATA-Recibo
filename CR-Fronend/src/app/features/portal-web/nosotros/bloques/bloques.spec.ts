import { Cargo, aEditor, aGuardar, organigramaATexto, textoAOrganigrama } from './bloques';

describe('Bloques de Nosotros en el panel', () => {
  const cargo = (c: string, hijos: Cargo[] = [], detalle: string | null = null): Cargo => ({ cargo: c, detalle, hijos });

  it('el organigrama va y vuelve como texto con sangría', () => {
    const raiz = cargo('Dirección General', [
      cargo('Subdirección', [cargo('Coordinación de Primaria'), cargo('Coordinación de Secundaria')]),
      cargo('Administración', [], 'Lic. Ana Pérez'),
    ], 'Mg. Juan Quispe');

    const texto = organigramaATexto(raiz);
    expect(texto).toBe(
      'Dirección General — Mg. Juan Quispe\n  Subdirección\n    Coordinación de Primaria\n    Coordinación de Secundaria\n  Administración — Lic. Ana Pérez',
    );
    expect(textoAOrganigrama(texto)).toEqual(raiz);
  });

  it('el organigrama acepta tabulaciones y líneas en blanco', () => {
    expect(textoAOrganigrama('Dirección\n\n\tSecretaría\n')).toEqual(cargo('Dirección', [cargo('Secretaría')]));
    expect(textoAOrganigrama('   ')).toBeNull();
  });

  it('cada tipo vuelve a guardarse igual que se leyó', () => {
    const imagen = { id: 'img-1', url: 'https://x.pe/a.webp', alt: 'A', ancho: 10, alto: 10, bytes: 1 };
    const imagenes = { 'img-1': imagen };
    const ref = { imagenId: 'img-1' };

    const guardados = [
      { tipo: 'texto', titulo: 'T', parrafos: ['Uno.', 'Dos.'], firma: { nombre: 'Dirección', detalle: null } },
      { tipo: 'destacado', rotulo: 'Misión', texto: 'Formar.' },
      { tipo: 'lista', titulo: null, estilo: 'tarjetas', items: [{ titulo: 'Integridad', texto: '«…»', fuente: 'p. 57' }] },
      { tipo: 'hitos', titulo: null, items: [{ fecha: '1971', texto: 'Se funda.' }] },
      { tipo: 'imagen', imagen: ref, pie: null, transcripcion: ['Línea.'] },
      { tipo: 'personas', titulo: 'Consejo', personas: [{ id: 'ana', nombre: 'Ana', cargo: 'Presidenta', foto: ref }] },
      { tipo: 'mensaje', titulo: null, parrafos: ['Hola.'], firma: { nombre: 'La directora', detalle: 'Directora' }, foto: null },
      { tipo: 'letra', titulo: 'Himno', partes: [{ titulo: 'Coro', versos: ['Uno', 'Dos'] }] },
      { tipo: 'audio', titulo: 'Himno', url: 'https://x.pe/h.mp3', formato: 'audio/mpeg', descripcion: null },
      { tipo: 'organigrama', titulo: null, imagen: null, enlace: { etiqueta: 'Ver', url: 'https://x.pe', externo: true }, raiz: cargo('Dirección') },
      { tipo: 'enlaces', titulo: null, enlaces: [{ etiqueta: 'PEI', url: 'https://x.pe/pei.pdf', descripcion: null, formato: 'pdf', peso: 1024 }] },
    ];

    for (const b of guardados) {
      expect(aGuardar(aEditor(b, imagenes))).withContext(b.tipo).toEqual(b);
    }
  });
});
