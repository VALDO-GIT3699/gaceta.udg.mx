#!/usr/bin/env python3
"""
audit-table-sizes.py

Mide cuánto ocupa cada tabla DENTRO del dump SQL, sin cargarlo en ninguna base
de datos y sin modificarlo.

Para qué sirve: permite planificar la carga de la base de auditoría cuando el
espacio en disco es limitado (ver docs/decisiones.md, D-02). Saber que una
tabla de caché ocupa cientos de megabytes y el corpus editorial otros tantos
convierte "no cabe" en "carguemos estas tablas y no estas otras", con cifras.

Método: el volcado de phpMyAdmin intercala estructura y datos por tabla, hecho
verificado por tools/audit-encoding.py. Por tanto la región de cada tabla va
desde su `CREATE TABLE` hasta el `CREATE TABLE` de la siguiente. El tamaño de
esa región es una buena aproximación del volumen de datos de la tabla.

ADVERTENCIA sobre la interpretación: el tamaño en el dump NO es el tamaño que
ocupará cargada. El SQL de texto incluye nombres de columna repetidos en cada
INSERT y escapes, lo que infla; los índices, en cambio, añaden volumen al
cargar. Trátese como orden de magnitud, no como cifra exacta.

Uso:
    python tools/audit-table-sizes.py [ruta-del-dump]
"""

import os
import re
import sys

DUMP_POR_DEFECTO = "wp/DB/gaceta (2).sql"
TAMANO_BLOQUE = 8 * 1024 * 1024

RE_CREATE = re.compile(rb"CREATE TABLE `([^`]+)`")
RE_INSERT = re.compile(rb"INSERT INTO `([^`]+)`")

# Tablas que son caché, logs o respaldos internos de plugin: candidatas a
# omitirse en una carga parcial. No son contenido editorial.
PRESCINDIBLES = (
    "actionscheduler_",
    "revslider_css",
    "_bkp",
    "wpmailsmtp_debug",
    "mclean_scan",
    "post_views",
)


def humano(n):
    for unidad in ("B", "KB", "MB", "GB"):
        if abs(n) < 1024.0 or unidad == "GB":
            return "%7.1f %s" % (n, unidad)
        n /= 1024.0


def prescindible(tabla):
    return any(p in tabla for p in PRESCINDIBLES)


def main():
    ruta = sys.argv[1] if len(sys.argv) > 1 else DUMP_POR_DEFECTO
    total = os.path.getsize(ruta)

    creates = []
    inserts = {}

    solape = 96
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
            for m in RE_INSERT.finditer(buf):
                t = m.group(1).decode("ascii", "replace")
                inserts[t] = inserts.get(t, 0) + 1
            pos += len(bloque)
            cola = buf[-(solape - 1):]

    creates = sorted(set(creates))

    filas = []
    for i, (off, tabla) in enumerate(creates):
        fin = creates[i + 1][0] if i + 1 < len(creates) else total
        filas.append((fin - off, tabla, inserts.get(tabla, 0)))
    filas.sort(reverse=True)

    print("Dump:  %s" % ruta)
    print("Total: %s (%d bytes)" % (humano(total).strip(), total))
    print("Tablas: %d" % len(creates))
    print()
    print("%-38s %12s %10s %8s  %s" % ("TABLA", "EN EL DUMP", "INSERTs", "% DUMP", "CLASE"))
    print("-" * 92)

    suma_presc = 0
    suma_nucleo = 0
    for tam, tabla, ins in filas:
        pct = 100.0 * tam / total
        clase = "prescindible" if prescindible(tabla) else ""
        if prescindible(tabla):
            suma_presc += tam
        else:
            suma_nucleo += tam
        if tam > 1024 * 1024 or ins:
            print("%-38s %12s %10d %7.2f%%  %s" % (tabla, humano(tam), ins, pct, clase))

    print("-" * 92)
    print("%-38s %12s" % ("SUBTOTAL candidatas a omitir", humano(suma_presc)))
    print("%-38s %12s" % ("SUBTOTAL a cargar", humano(suma_nucleo)))
    print()
    print("NOTA: 'prescindible' marca caché, logs y respaldos internos de")
    print("plugin. NO marca contenido editorial. Omitir una tabla en la carga")
    print("de auditoría no la elimina del dump original, que permanece intacto")
    print("y es la fuente de verdad (CLAUDE.md §7, §12).")


if __name__ == "__main__":
    main()
