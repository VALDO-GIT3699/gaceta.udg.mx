#!/usr/bin/env python3
"""
extract-tables.py

Extrae un subconjunto de tablas del dump SQL de WordPress a un archivo .sql
más pequeño, para poder cargarlo en la base de auditoría cuando el espacio en
disco no permite cargar los 3.54 GB completos.

Motivo (docs/decisiones.md, D-02): el disco tiene 14 GB libres de 476 GB. Tres
tablas concentran el 98.3 % del dump, y dc8_postmeta sola ocupa 2.3 GB. Cargar
por etapas permite empezar a auditar hoy en lugar de esperar a liberar espacio.

GARANTÍAS

  - El dump original NUNCA se modifica. Se abre sólo en lectura.
  - El archivo generado es un .sql válido y cargable por sí mismo: incluye la
    cabecera original del volcado, con su /*!40101 SET NAMES utf8mb4 */, de
    modo que el encoding se preserva tal como demostró
    reports/audit/encoding-audit.md.
  - Incluye las sentencias ALTER TABLE de las tablas seleccionadas. phpMyAdmin
    volcó las estructuras SIN claves y añadió 131 ALTER TABLE al final; sin
    ellas las tablas se cargarían sin índices ni claves primarias.
  - Omitir una tabla aquí NO la elimina del dump original, que sigue siendo la
    fuente de verdad (CLAUDE.md §7, §12).

Uso:
    python tools/extract-tables.py SALIDA.sql tabla1 tabla2 ...
    python tools/extract-tables.py SALIDA.sql --grupo editorial
    python tools/extract-tables.py --listar

Grupos predefinidos:
    editorial  todo menos dc8_postmeta y dc8_post_views (~1 GB)
    postmeta   sólo dc8_postmeta (~2.3 GB)
    minimo     sólo dc8_posts y las tablas de taxonomía y usuarios (~790 MB)
"""

import os
import re
import sys

DUMP_POR_DEFECTO = "wp/DB/gaceta (2).sql"
TAMANO_BLOQUE = 8 * 1024 * 1024

RE_CREATE = re.compile(rb"CREATE TABLE `([^`]+)`")
RE_ALTER = re.compile(rb"^ALTER TABLE `([^`]+)`", re.MULTILINE)

GRUPOS = {
    "minimo": [
        "dc8_posts",
        "dc8_terms", "dc8_term_taxonomy", "dc8_term_relationships", "dc8_termmeta",
        "dc8_users", "dc8_usermeta",
    ],
    "editorial": None,   # se calcula: todo menos los dos excluidos
    "postmeta": ["dc8_postmeta"],
}
EXCLUIR_EN_EDITORIAL = {"dc8_postmeta", "dc8_post_views"}


def mapear(ruta):
    """Devuelve (creates, inicio_alters, total). Una pasada de lectura."""
    total = os.path.getsize(ruta)
    creates = []
    inicio_alters = None
    pos = 0
    cola = b""
    with open(ruta, "rb") as fh:
        while True:
            bloque = fh.read(TAMANO_BLOQUE)
            if not bloque:
                break
            buf = cola + bloque
            base = pos - len(cola)
            for m in RE_CREATE.finditer(buf):
                creates.append((base + m.start(), m.group(1).decode("ascii", "replace")))
            if inicio_alters is None:
                m = RE_ALTER.search(buf)
                if m:
                    inicio_alters = base + m.start()
            pos += len(bloque)
            cola = buf[-95:]
    return sorted(set(creates)), inicio_alters, total


def copiar_region(fh_in, fh_out, inicio, fin):
    fh_in.seek(inicio)
    restante = fin - inicio
    while restante > 0:
        trozo = fh_in.read(min(TAMANO_BLOQUE, restante))
        if not trozo:
            break
        fh_out.write(trozo)
        restante -= len(trozo)


def main():
    args = sys.argv[1:]
    dump = os.environ.get("GACETA_DUMP", DUMP_POR_DEFECTO)

    if not args or args[0] == "--listar":
        creates, ini, total = mapear(dump)
        print("Tablas en %s:" % dump)
        for i, (off, t) in enumerate(creates):
            fin = creates[i + 1][0] if i + 1 < len(creates) else (ini or total)
            print("  %-40s %12d bytes" % (t, fin - off))
        return

    salida = args[0]
    resto = args[1:]

    creates, inicio_alters, total = mapear(dump)
    todas = [t for _, t in creates]

    if len(resto) >= 2 and resto[0] == "--grupo":
        nombre = resto[1]
        if nombre not in GRUPOS:
            raise SystemExit("Grupo desconocido: %s. Opciones: %s"
                             % (nombre, ", ".join(GRUPOS)))
        if nombre == "editorial":
            seleccion = [t for t in todas if t not in EXCLUIR_EN_EDITORIAL]
        else:
            seleccion = [t for t in GRUPOS[nombre] if t in todas]
            faltan = [t for t in GRUPOS[nombre] if t not in todas]
            if faltan:
                print("AVISO: no existen en el dump: %s" % ", ".join(faltan))
    else:
        seleccion = resto
        faltan = [t for t in seleccion if t not in todas]
        if faltan:
            raise SystemExit("No existen en el dump: %s" % ", ".join(faltan))

    if not seleccion:
        raise SystemExit("No se seleccionó ninguna tabla.")

    fin_datos = inicio_alters if inicio_alters is not None else total
    por_nombre = {}
    for i, (off, t) in enumerate(creates):
        fin = creates[i + 1][0] if i + 1 < len(creates) else fin_datos
        por_nombre[t] = (off, min(fin, fin_datos))

    cabecera_fin = creates[0][0]

    print("Dump:    %s" % dump)
    print("Salida:  %s" % salida)
    print("Tablas:  %d de %d" % (len(seleccion), len(todas)))

    escrito = 0
    with open(dump, "rb") as fin_fh, open(salida, "wb") as out:
        # Cabecera original: trae SET NAMES utf8mb4 y los SET de compatibilidad.
        copiar_region(fin_fh, out, 0, cabecera_fin)
        escrito += cabecera_fin

        for t in seleccion:
            ini, fin = por_nombre[t]
            copiar_region(fin_fh, out, ini, fin)
            escrito += fin - ini
            print("  + %-40s %12d bytes" % (t, fin - ini))

        # Sección de ALTER TABLE, filtrada a las tablas seleccionadas.
        if inicio_alters is not None:
            fin_fh.seek(inicio_alters)
            cola = fin_fh.read(total - inicio_alters)
            sel = set(seleccion)
            bloques = cola.split(b"ALTER TABLE ")
            out.write(b"\n\n-- Claves e indices de las tablas extraidas\n\n")
            n = 0
            for b in bloques[1:]:
                m = re.match(rb"`([^`]+)`", b)
                if m and m.group(1).decode("ascii", "replace") in sel:
                    out.write(b"ALTER TABLE " + b)
                    n += 1
            print("  + %d sentencias ALTER TABLE" % n)

    print()
    print("Escrito: %d bytes (%.1f MB)" % (escrito, escrito / 1048576.0))
    print()
    print("El dump original NO fue modificado.")
    print("Cargar con (sin forzar charset, ver reports/audit/encoding-audit.md):")
    print('  mysql -u root gaceta_auditoria < "%s"' % salida)


if __name__ == "__main__":
    main()
