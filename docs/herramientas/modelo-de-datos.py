"""
Arma el modelo de datos leyendo las migraciones del proyecto.

    python docs/herramientas/modelo-de-datos.py

Escribe en docs/:
    modelo-datos.dbml   para pegar en https://dbdiagram.io
    modelo-datos.mmd    el diagrama general en Mermaid
    modelo-*.mmd        un diagrama por tema, que se leen mejor
    esquema.json        lo leído, por si se quiere usar en otra cosa

Se lee de las MIGRACIONES y no de una base viva a propósito: las migraciones
son la única fuente que viaja con el código, así que el modelo nunca queda
describiendo la base de otra máquina.

Para convertir los .mmd en imagen: docs/herramientas/dibujar-modelo.js
"""
import json
import re
import sys
from pathlib import Path

sys.stdout.reconfigure(encoding='utf-8')

RAIZ = Path(__file__).resolve().parents[2]
MIGRACIONES = RAIZ / 'CR-Backend' / 'database' / 'migrations'
DOCS = RAIZ / 'docs'

# Tablas de Laravel: no son del negocio y ensucian el gráfico.
DE_LARAVEL = {
    'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions',
    'password_reset_tokens', 'personal_access_tokens',
}

TIPOS = ('uuid|id|string|char|text|longText|mediumText|integer|bigInteger|unsignedBigInteger|'
         'smallInteger|tinyInteger|unsignedInteger|unsignedTinyInteger|unsignedSmallInteger|'
         'boolean|decimal|float|double|date|dateTime|timestamp|time|year|json|enum|'
         'foreignId|foreignUuid|binary')

# Cómo se agrupan para leerlas, y qué es cada una.
GRUPOS = {
    'Personal': ['empleados', 'contratos', 'identidades_firma', 'areas', 'cargos', 'sedes', 'area_cargo'],
    'Planilla': ['periodos', 'planilla_corridas', 'planilla', 'payroll_detalles', 'payment_concepts', 'concepto_alias'],
    'Documentos y avisos': ['documentos', 'notificaciones'],
    'Vacaciones': ['vacaciones'],
    'Seguridad y accesos': ['users', 'roles', 'modulo_padre', 'modulos', 'rol_modulo', 'auditoria'],
}

TEMAS = {
    'modelo-personal': ['empleados', 'contratos', 'identidades_firma', 'areas', 'cargos', 'sedes',
                        'area_cargo', 'vacaciones', 'periodos', 'users'],
    'modelo-planilla': ['planilla', 'payroll_detalles', 'payment_concepts', 'concepto_alias',
                        'planilla_corridas', 'periodos', 'empleados'],
    'modelo-documentos-seguridad': ['documentos', 'notificaciones', 'users', 'roles', 'modulos',
                                    'modulo_padre', 'rol_modulo', 'auditoria', 'empleados',
                                    'contratos', 'planilla'],
}

QUE_ES = {
    'empleados': 'La ficha del trabajador: datos personales, laborales, de planilla y bancarios.',
    'contratos': 'El vinculo laboral. Un trabajador tiene un solo contrato vigente a la vez.',
    'identidades_firma': 'La firma y la huella del trabajador, para estampar en su boleta.',
    'areas': 'Areas academicas y administrativas del colegio.',
    'cargos': 'Puestos que puede ocupar un trabajador.',
    'sedes': 'Los locales del colegio.',
    'area_cargo': 'Que cargos son propios de cada area. Un cargo puede valer en varias.',
    'periodos': 'El tramo de la campana de planillas sobre el que se genera.',
    'planilla_corridas': 'Una corrida agrupa las planillas que se pagan juntas.',
    'planilla': 'La planilla de UN trabajador en un mes: su base, sus sumas y su neto.',
    'payroll_detalles': 'Cada linea de esa planilla: que concepto se aplico y por cuanto.',
    'payment_concepts': 'El catalogo de bonificaciones, descuentos, aportaciones y adelantos.',
    'concepto_alias': 'Los nombres con los que el colegio llama a un concepto en sus Excel.',
    'documentos': 'El expediente: boletas, contratos firmados, hojas de vida y documentos anteriores.',
    'notificaciones': 'Los avisos del trabajador (la campana).',
    'vacaciones': 'Solicitud de vacaciones, su estado y quien la respondio.',
    'users': 'La cuenta de acceso. Puede estar atada a un trabajador o ser solo del sistema.',
    'roles': 'admin, rrhh y empleado: la llave con la que el backend decide que se puede hacer.',
    'modulo_padre': 'Los grupos de la barra lateral.',
    'modulos': 'Los items del menu: a donde llevan y de que grupo cuelgan.',
    'rol_modulo': 'Que modulos ve cada rol.',
    'auditoria': 'Quien cambio que y cuando. Solo se escribe y se lee.',
}

CAMPOS_EN_EL_GRAFICO = 8


# ── Leer las migraciones ─────────────────────────────────────────
def leer():
    tablas, relaciones = {}, []

    for archivo in sorted(MIGRACIONES.glob('*.php')):
        # Solo el up(): el down() deshace lo mismo y descontaria lo que la
        # migracion acaba de agregar.
        texto = archivo.read_text(encoding='utf-8').split('function down')[0]

        for m in re.finditer(r"Schema::(create|table)\('([^']+)'", texto):
            tabla = m.group(2)
            if tabla in DE_LARAVEL:
                continue

            siguiente = texto.find('Schema::', m.end())
            cuerpo = texto[m.end():siguiente if siguiente > 0 else len(texto)]
            registro = tablas.setdefault(tabla, {'columnas': []})
            nombres = [c[0] for c in registro['columnas']]

            for c in re.finditer(r"\$table->(" + TIPOS + r")\('([^']+)'([^;]*)", cuerpo):
                tipo, nombre, resto = c.group(1), c.group(2), c.group(3)
                if nombre in nombres:
                    continue
                detalle = []
                if 'nullable()' in resto:
                    detalle.append('nullable')
                if 'unique()' in resto:
                    detalle.append('unique')
                defecto = re.search(r"default\((?:'([^']*)'|([^)]*))\)", resto)
                if defecto:
                    detalle.append('def ' + (defecto.group(1) or defecto.group(2) or '').strip())
                if tipo == 'enum':
                    valores = re.findall(r"'([^']+)'", resto)
                    if valores:
                        tipo = 'enum(' + ','.join(valores[:6]) + ')'
                if tipo == 'decimal':
                    nums = re.search(r",\s*(\d+)\s*,\s*(\d+)", resto)
                    if nums:
                        tipo = f'decimal({nums.group(1)},{nums.group(2)})'
                registro['columnas'].append((nombre, tipo, ' '.join(detalle)))
                nombres.append(nombre)

            if 'timestamps()' in cuerpo and 'created_at' not in nombres:
                registro['columnas'] += [('created_at', 'timestamp', 'nullable'),
                                         ('updated_at', 'timestamp', 'nullable')]

            # Las dos formas de declarar una llave foranea en este proyecto.
            def anotar(columna, destino, resto):
                al_borrar = ('cascade' if 'cascadeOnDelete' in resto or "onDelete('cascade')" in resto
                             else 'null' if 'nullOnDelete' in resto or "onDelete('set null')" in resto
                             else 'restrict')
                if (tabla, columna, destino) not in [(r[0], r[1], r[2]) for r in relaciones]:
                    relaciones.append((tabla, columna, destino, al_borrar))

            for f in re.finditer(
                r"->foreign\('([^']+)'\)\s*->references\('([^']+)'\)\s*->on\('([^']+)'\)([^;]*)", cuerpo
            ):
                anotar(f.group(1), f.group(3), f.group(4))

            for f in re.finditer(
                r"foreign(?:Id|Uuid)\('([^']+)'\)([^;]*?)constrained\(\s*(?:'([^']+)')?\s*\)([^;]*)", cuerpo
            ):
                columna, antes, destino, despues = f.groups()
                # Sin nombre de tabla, Laravel la deduce: user_id -> users
                anotar(columna, destino or (columna[:-3] + 's' if columna.endswith('_id') else columna),
                       (antes or '') + (despues or ''))

            for d in re.finditer(r"dropColumn\(\[([^\]]+)\]\)|dropColumn\('([^']+)'\)", cuerpo):
                fuera = re.findall(r"'([^']+)'", d.group(0))
                registro['columnas'] = [c for c in registro['columnas'] if c[0] not in fuera]

    return dict(sorted(tablas.items())), relaciones


def corto(tipo):
    return (tipo.replace('unsignedSmallInteger', 'int').replace('unsignedTinyInteger', 'int')
            .replace('unsignedInteger', 'int').replace('bigInteger', 'bigint')
            .replace('foreignId', 'bigint').replace('longText', 'text')
            .replace('mediumText', 'text').split('(')[0])


def escribir_dbml(tablas, fks):
    salida = ['// CATA-Recibo - modelo de datos',
              '// Pegalo en https://dbdiagram.io para ver y exportar el grafico.',
              '// Generado desde las migraciones: python docs/herramientas/modelo-de-datos.py', '']

    for grupo, lista in GRUPOS.items():
        salida.append(f'// -- {grupo} --')
        for tabla in lista:
            if tabla not in tablas:
                continue
            salida.append(f'Table {tabla} {{')
            columnas = tablas[tabla]['columnas']
            if not any(c[0] == 'id' for c in columnas):
                salida.append('  id uuid [pk]')
            for nombre, tipo, detalle in columnas:
                marcas = []
                if nombre == 'id':
                    marcas.append('pk')
                if nombre in fks.get(tabla, {}):
                    marcas.append('ref: > ' + fks[tabla][nombre] + '.id')
                if 'unique' in detalle:
                    marcas.append('unique')
                if 'nullable' not in detalle and nombre != 'id':
                    marcas.append('not null')
                salida.append(f'  {nombre} {corto(tipo)}' + (' [' + ', '.join(marcas) + ']' if marcas else ''))
            salida.append(f"  Note: '{QUE_ES.get(tabla, '')}'")
            salida.append('}')
            salida.append('')

    (DOCS / 'modelo-datos.dbml').write_text('\n'.join(salida), encoding='utf-8', newline='\n')


def escribir_mermaid(nombre, tablas, relaciones, fks, lista):
    salida = ['erDiagram']

    for tabla in lista:
        if tabla not in tablas:
            continue
        columnas, propias = tablas[tabla]['columnas'], fks.get(tabla, {})
        elegidas = [(corto(t), n, 'PK') for n, t, _ in columnas if n == 'id']
        elegidas += [(corto(t), n, 'FK') for n, t, _ in columnas if n in propias]
        for n, t, _ in columnas:
            if n in propias or n in ('id', 'created_at', 'updated_at', 'estado_registro'):
                continue
            if len(elegidas) >= CAMPOS_EN_EL_GRAFICO:
                break
            elegidas.append((corto(t), n, ''))
        salida.append(f'    {tabla} {{')
        for tipo, campo, marca in elegidas:
            salida.append(f'        {tipo} {campo} {marca}'.rstrip())
        salida.append('    }')

    for origen, columna, destino, _ in relaciones:
        if origen in lista and destino in lista:
            salida.append(f'    {destino} ||--o{{ {origen} : "{columna}"')

    (DOCS / (nombre + '.mmd')).write_text('\n'.join(salida), encoding='utf-8', newline='\n')


tablas, relaciones = leer()
fks = {}
for origen, columna, destino, _ in relaciones:
    fks.setdefault(origen, {})[columna] = destino

(DOCS / 'esquema.json').write_text(
    json.dumps({'tablas': tablas, 'relaciones': relaciones}, ensure_ascii=False, indent=1),
    encoding='utf-8', newline='\n')

escribir_dbml(tablas, fks)
escribir_mermaid('modelo-datos', tablas, relaciones, fks, [t for lista in GRUPOS.values() for t in lista])
for nombre, lista in TEMAS.items():
    escribir_mermaid(nombre, tablas, relaciones, fks, lista)

print(f'{len(tablas)} tablas y {len(relaciones)} relaciones')
print('escritos: modelo-datos.dbml, modelo-datos.mmd y ' + str(len(TEMAS)) + ' diagramas por tema')
