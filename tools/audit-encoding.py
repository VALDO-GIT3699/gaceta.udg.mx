#!/usr/bin/env python3
"""
audit-encoding.py

Audita el encoding real del dump SQL de WordPress SIN cargarlo en ninguna base
de datos y SIN modificarlo.

Contexto (CLAUDE.md §11): dc8_posts declara MyISAM/latin1 mientras WordPress
declara utf8mb4. El contrato prohíbe convertir el charset a ciegas. Este script
produce la evidencia que permite decidir.

Método: cuenta SECUENCIAS DE BYTES en streaming. No interpreta filas SQL, por
lo que no lo afectan las comas, comillas, escapes, HTML ni JSON que contiene
post_content (CLAUDE.md §13, §14).

La prueba discriminante son dos escenarios que dejan huellas distintas:

  Escenario A  La columna latin1 guardaba caracteres latin1 legítimos.
               El dump (hecho con SET NAMES utf8mb4) contiene UTF-8 correcto:
               "á" = c3 a1

  Escenario B  La columna latin1 guardaba bytes UTF-8 metidos a la fuerza.
               El dump contiene doble codificación:
               "Ã¡" = c3 83 c2 a1

Si predomina A, NO hay que convertir nada. Si predomina B, hay que
reinterpretar bytes. La proporción entre ambos decide.

Uso:
    python tools/audit-encoding.py [ruta-del-dump]

Salida: un informe por stdout. Redirigir a reports/audit/ si se desea archivar.
"""

import bisect
import re
import sys
from collections import Counter

DUMP_POR_DEFECTO = "wp/DB/gaceta (2).sql"
TAMANO_BLOQUE = 8 * 1024 * 1024

# Firmas de doble codificación de LETRAS acentuadas (escenario B).
LETRAS_DOBLES = {
    "a-aguda  Ã¡  c383c2a1": b"\xc3\x83\xc2\xa1",
    "e-aguda  Ã©  c383c2a9": b"\xc3\x83\xc2\xa9",
    "i-aguda  Ã­  c383c2ad": b"\xc3\x83\xc2\xad",
    "o-aguda  Ã³  c383c2b3": b"\xc3\x83\xc2\xb3",
    "u-aguda  Ãº  c383c2ba": b"\xc3\x83\xc2\xba",
    "n-tilde  Ã±  c383c2b1": b"\xc3\x83\xc2\xb1",
}

# Firmas de doble codificación de PUNTUACIÓN tipográfica.
# "â€" es el prefijo e2 80 de la puntuación general Unicode, doblemente
# codificado. Cubre puntos suspensivos, comillas tipográficas y guiones.
PUNTUACION_DOBLE = b"\xc3\xa2\xe2\x82\xac"
PUNTOS_DOBLES = b"\xc3\xa2\xe2\x82\xac\xc2\xa6"

# Firmas de UTF-8 CORRECTO (escenario A).
OK_A_AGUDA = b"\xc3\xa1"
OK_PUNTOS = b"\xe2\x80\xa6"

RE_CREATE = re.compile(rb"CREATE TABLE `([^`]+)`")


def contar_en_streaming(ruta):
    """Una sola pasada: cuenta firmas y mapea cada ocurrencia a su tabla."""
    firmas = dict(LETRAS_DOBLES)
    firmas["puntuacion  â€    c3a2e282ac"] = PUNTUACION_DOBLE
    firmas["3puntos     â€¦   c3a2e282acc2a6"] = PUNTOS_DOBLES
    firmas["OK a-aguda  á    c3a1"] = OK_A_AGUDA
    firmas["OK 3puntos  …    e280a6"] = OK_PUNTOS

    conteo = {k: 0 for k in firmas}
    primero = {k: None for k in firmas}
    tablas = []
    off_moji = []
    off_ok = []

    # El solape entre bloques debe cubrir la firma más larga y el CREATE TABLE.
    solape = 64
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
                tablas.append((base + m.start(), m.group(1).decode("ascii", "replace")))

            for clave, patron in firmas.items():
                i = buf.find(patron)
                while i != -1:
                    conteo[clave] += 1
                    if primero[clave] is None:
                        primero[clave] = base + i
                    if patron is PUNTUACION_DOBLE:
                        off_moji.append(base + i)
                    elif patron is OK_A_AGUDA:
                        off_ok.append(base + i)
                    i = buf.find(patron, i + 1)

            pos += len(bloque)
            cola = buf[-(solape - 1):]

    return pos, conteo, primero, tablas, off_moji, off_ok


def dedup(offsets):
    """Elimina duplicados producidos por el solape entre bloques."""
    salida = []
    ultimo = -1
    for x in sorted(offsets):
        if x != ultimo:
            salida.append(x)
            ultimo = x
    return salida


def atribuir(offsets, tablas):
    """Asigna cada offset a la tabla cuyo CREATE TABLE lo precede."""
    inicios = [t[0] for t in tablas]
    cuenta = Counter()
    for off in offsets:
        i = bisect.bisect_right(inicios, off) - 1
        cuenta[tablas[i][1] if i >= 0 else "(cabecera del dump)"] += 1
    return cuenta


def main():
    ruta = sys.argv[1] if len(sys.argv) > 1 else DUMP_POR_DEFECTO

    print("Dump auditado: %s" % ruta)
    leidos, conteo, primero, tablas, off_moji, off_ok = contar_en_streaming(ruta)

    tablas = sorted(set(tablas))
    off_moji = dedup(off_moji)
    off_ok = dedup(off_ok)

    print("Bytes leídos:  %d" % leidos)
    print("Tablas:        %d" % len(tablas))
    print()
    print("%-36s %14s  %s" % ("FIRMA", "OCURRENCIAS", "PRIMER OFFSET"))
    print("-" * 72)
    for clave in sorted(conteo):
        p = primero[clave]
        print("%-36s %14d  %s" % (clave, conteo[clave], p if p is not None else "-"))

    letras = sum(conteo[k] for k in LETRAS_DOBLES)
    correctas = conteo["OK a-aguda  á    c3a1"]

    print()
    print("PRUEBA DISCRIMINANTE")
    print("-" * 72)
    print("UTF-8 correcto (letras):        %d" % correctas)
    print("Doble codificación (letras):    %d" % letras)
    if letras == 0 and correctas == 0:
        print("VEREDICTO: DESCONOCIDO. No se hallaron letras acentuadas.")
    elif correctas > letras * 100:
        print("VEREDICTO: ESCENARIO A. El dump ya es UTF-8 correcto.")
        print("           NO convertir el charset. Importar tal cual.")
    elif letras > correctas:
        print("VEREDICTO: ESCENARIO B. Hay doble codificación sistemática.")
        print("           Requiere reinterpretación de bytes. NO importar sin más.")
    else:
        print("VEREDICTO: CONFLICTO. Proporción ambigua, revisar manualmente.")

    print()
    print("DISTRIBUCIÓN DEL MOJIBAKE DE PUNTUACIÓN POR TABLA")
    print("-" * 72)
    cm = atribuir(off_moji, tablas)
    co = atribuir(off_ok, tablas)
    print("%-34s %10s %12s %9s" % ("TABLA", "MOJIBAKE", "UTF8_OK", "% DAÑADO"))
    for t, c in cm.most_common():
        o = co.get(t, 0)
        pct = (100.0 * c / (c + o)) if (c + o) else 0.0
        print("%-34s %10d %12d %8.2f%%" % (t, c, o, pct))
    print()
    print("Tablas con texto acentuado y SIN mojibake:")
    limpias = [(t, o) for t, o in co.most_common() if cm.get(t, 0) == 0]
    if limpias:
        for t, o in limpias:
            print("  %-32s %d" % (t, o))
    else:
        print("  (ninguna)")


if __name__ == "__main__":
    main()
