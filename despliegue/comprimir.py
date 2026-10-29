# Comprime una carpeta en un zip con su contenido en la raíz, para descomprimirlo
# dentro de la carpeta de destino en el hosting.
#
# Las rutas van siempre con «/»: un zip con «\» (lo que deja Compress-Archive o
# un robocopy comprimido en Windows) se descomprime mal en Linux, con archivos
# llamados «app\Http\…» en vez de carpetas.
import os
import sys
import zipfile

origen, destino = sys.argv[1], sys.argv[2]
# Rutas largas de Windows (vendor/ pasa de 260 caracteres): con el prefijo \\?\ no hay límite.
if os.name == 'nt':
    origen = '\\\\?\\' + os.path.abspath(origen)

n = 0
with zipfile.ZipFile(destino, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for raiz, carpetas, archivos in os.walk(origen):
        carpetas.sort()
        rel = os.path.relpath(raiz, origen).replace(os.sep, '/')
        # Carpetas vacías (storage/…): se guardan para que existan al descomprimir.
        if rel != '.' and not archivos and not carpetas:
            z.writestr(rel + '/', '')
        for a in sorted(archivos):
            nombre = a if rel == '.' else f'{rel}/{a}'
            z.write(os.path.join(raiz, a), nombre)
            n += 1
print(f'{destino}: {n} archivos, {os.path.getsize(destino) / 1024 / 1024:.1f} MB')
