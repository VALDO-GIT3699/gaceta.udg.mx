#!/usr/bin/env python3
"""
audit-encoding.py

Audita el encoding real del dump SQL de WordPress SIN cargarlo en ninguna base
de datos y SIN modificarlo.

Contexto (CLAUDE.md §11): dc8_posts declara MyISAM/latin1 mientras WordPress
declara utf8mb4. El contrato prohíbe convertir el charset a ciegas. Este script
produce la evidencia que permite decidir.

Método: cuenta SECUENCIAS DE BYTES en streaming y valida UTF-8 de forma
incremental sobre el archivo completo. No interpreta filas SQL, por lo que no
lo afectan las comas, comillas, escapes, HTML ni JSON que contiene
post_content (CLAUDE.md §13, §14).

La prueba discriminante son dos escenarios que dejan huellas distintas:

  Escenario A  La columna latin1 guardaba caracteres latin1 legítimos.
               El dump (hecho con SET NAMES utf8mb4) contiene UTF-8 correcto:
               "á" = c3 a1

  Escenario B  La columna latin1 guardaba bytes UTF-8 metidos a la fuerza.
               El dump contiene doble codificación:
               "Ã¡" = c3 83 c2 a1

Si predomina A, NO hay que convertir nada. Si predomina B, hay que
reinterpretar bytes.

CORRECCIONES aplicadas tras el dictamen del auditor (2026-09-30):

  1. Los conteos se DEDUPLICAN por offset absoluto. La versión anterior
     incrementaba contadores dentro de la ventana de solape entre bloques, lo
     que inflaba ligeramente las cifras y las presentaba como exactas.
  2. Se valida UTF-8 ESTRICTO sobre el archivo COMPLETO con un decodificador
     incremental. La versión anterior sólo validaba una muestra de 2 MB, es
     decir el 0.056 % del archivo, y generalizaba desde ahí.
  3. Las sondas de letras incluyen MAYÚSCULAS acentuadas, diéresis y cedilla.
     La versión anterior sólo probaba seis minúsculas, así que un corpus de
     titulares (rico en mayúsculas) habría sido invisible.
  4. El veredicto normaliza: compara el TOTAL de letras correctas contra el
     TOTAL de letras dobles, sobre el mismo conjunto de caracteres. La versión
     anterior comparaba un solo carácter correcto contra seis dobles.
  5. La firma de puntuación "â€" y la de puntos suspensivos "â€¦" están
     ANIDADAS (la segunda contiene a la primera). Se informa explícitamente
     para no sumarlas como si fueran independientes.
  6. Se verifica el SUPUESTO de atribución: que el volcado intercale
     estructura y datos por tabla. Si no lo hiciera, la atribución por
     CREATE TABLE más cercano sería inválida.

Uso:
    python tools/audit-encoding.py [ruta-del-dump]
"""

import bisect
import codecs
import re
import sys
from collections import Counter

DUMP_POR_DEFECTO = "wp/DB/gaceta (2).sql"
TAMANO_BLOQUE = 8 * 1024 * 1024

# Pares (correcto, doble) para el MISMO carácter, de modo que el veredicto
# compare poblaciones equivalentes.
# Correcto: el carácter en UTF-8 de un solo paso.
# Doble:    cada byte del UTF-8 reinterpretado como cp1252 y recodificado.
LETRAS = [
    ("a-aguda minúscula", "á"),
    ("e-aguda minúscula", "é"),
    ("i-aguda minúscula", "í"),
    ("o-aguda minúscula", "ó"),
    ("u-aguda minúscula", "ú"),
    ("n-tilde minúscula", "ñ"),
    ("u-diéresis minúsc.", "ü"),
    ("c-cedilla minúscula", "ç"),
    ("A-aguda MAYÚSCULA", "Á"),
    ("E-aguda MAYÚSCULA", "É"),
    ("I-aguda MAYÚSCULA", "Í"),
    ("O-aguda MAYÚSCULA", "Ó"),
    ("U-aguda MAYÚSCULA", "Ú"),
    ("N-tilde MAYÚSCULA", "Ñ"),
]

# Puntuación tipográfica: bloque General Punctuation (prefijo e2 80).
PUNT_PREFIJO_DOBLE = b"\xc3\xa2\xe2\x82\xac"          # "â€"
PUNT_PUNTOS_DOBLE = b"\xc3\xa2\xe2\x82\xac\xc2\xa6"   # "â€¦"
PUNT_PUNTOS_OK = b"\xe2\x80\xa6"                      # "…"

RE_CREATE = re.compile(rb"CREATE TABLE `([^`]+)`")
RE_INSERT = re.compile(rb"INSERT INTO `([^`]+)`")


def doble_codificar(caracter):
    """Devuelve los bytes que resultan de la doble codificación del carácter.

    Toma el UTF-8 del carácter y reinterpreta cada byte como cp1252, luego
    vuelve a codificar en UTF-8. Es exactamente lo que hace MySQL al volcar
    con la conexión en utf8mb4 una columna latin1 que guardaba bytes UTF-8.
    """
    crudo = caracter.encode("utf-8")
    try:
        return crudo.decode("cp1252").encode("utf-8")
    except UnicodeDecodeError:
        return None


def construir_sondas():
    sondas = {}
    for etiqueta, caracter in LETRAS:
        ok = caracter.encode("utf-8")
        doble = doble_codificar(caracter)
        sondas[("letra_ok", etiqueta)] = ok
        if doble:
            sondas[("letra_doble", etiqueta)] = doble
    sondas[("punt_doble", "prefijo â€")] = PUNT_PREFIJO_DOBLE
    sondas[("punt_doble", "puntos â€¦")] = PUNT_PUNTOS_DOBLE
    sondas[("punt_ok", "puntos …")] = PUNT_PUNTOS_OK
    return sondas


def recorrer(ruta, sondas):
    """Una sola pasada. Deduplica por offset y valida UTF-8 incrementalmente."""
    offsets = {k: set() for k in sondas}
    creates = []
    inserts = []
    decodificador = codecs.getincrementaldecoder("utf-8")()
    utf8_error = None

    solape = 96
    pos = 0
    cola = b""

    with open(ruta, "rb") as fh:
        while True:
            bloque = fh.read(TAMANO_BLOQUE)
            if not bloque:
                break

            if utf8_error is None:
                try:
                    decodificador.decode(bloque)
                except UnicodeDecodeError as e:
                    utf8_error = "offset aprox. %d: %s" % (pos, e)

            buf = cola + bloque
            base = pos - len(cola)

            for m in RE_CREATE.finditer(buf):
                creates.append((base + m.start(), m.group(1).decode("ascii", "replace")))
            for m in RE_INSERT.finditer(buf):
                inserts.append((base + m.start(), m.group(1).decode("ascii", "replace")))

            for clave, patron in sondas.items():
                i = buf.find(patron)
                while i != -1:
                    offsets[clave].add(base + i)
                    i = buf.find(patron, i + 1)

            pos += len(bloque)
            cola = buf[-(solape - 1):]

    if utf8_error is None:
        try:
            decodificador.decode(b"", final=True)
        except UnicodeDecodeError as e:
            utf8_error = "final del archivo: %s" % e

    return pos, offsets, sorted(set(creates)), sorted(set(inserts)), utf8_error


def verificar_intercalado(creates, inserts):
    """Comprueba que los INSERT de cada tabla caen tras su propio CREATE TABLE.

    Si el volcado pusiera todas las estructuras primero y luego todos los
    datos, la atribución por CREATE TABLE más cercano sería inválida.
    """
    inicios = [c[0] for c in creates]
    nombres = [c[1] for c in creates]
    ok = 0
    mal = 0
    for off, tabla in inserts:
        i = bisect.bisect_right(inicios, off) - 1
        if i >= 0 and nombres[i] == tabla:
            ok += 1
        else:
            mal += 1
    return ok, mal


def atribuir(offsets, creates):
    inicios = [c[0] for c in creates]
    cuenta = Counter()
    for off in offsets:
        i = bisect.bisect_right(inicios, off) - 1
        cuenta[creates[i][1] if i >= 0 else "(cabecera del dump)"] += 1
    return cuenta


def main():
    ruta = sys.argv[1] if len(sys.argv) > 1 else DUMP_POR_DEFECTO
    sondas = construir_sondas()

    print("Dump auditado: %s" % ruta)
    leidos, offsets, creates, inserts, utf8_error = recorrer(ruta, sondas)

    cuenta = {k: len(v) for k, v in offsets.items()}

    print("Bytes leídos:  %d" % leidos)
    print("CREATE TABLE:  %d" % len(creates))
    print("INSERT INTO:   %d" % len(inserts))
    print()

    print("VALIDACIÓN UTF-8 ESTRICTA SOBRE EL ARCHIVO COMPLETO")
    print("-" * 72)
    if utf8_error is None:
        print("CONFIRMADO: los %d bytes decodifican como UTF-8 válido." % leidos)
    else:
        print("CONFLICTO: el archivo NO es UTF-8 válido -> %s" % utf8_error)
    print()

    print("SUPUESTO DE ATRIBUCIÓN (estructura y datos intercalados por tabla)")
    print("-" * 72)
    ok, mal = verificar_intercalado(creates, inserts)
    print("INSERT que caen tras el CREATE TABLE de SU tabla: %d" % ok)
    print("INSERT que NO:                                    %d" % mal)
    if mal == 0:
        print("CONFIRMADO: el volcado intercala. La atribución por tabla es válida.")
    else:
        print("CONFLICTO: la atribución por tabla NO es fiable. No usar esas cifras.")
    print()

    print("LETRAS ACENTUADAS: correcto contra doble codificación")
    print("-" * 72)
    print("%-22s %14s %14s" % ("CARÁCTER", "CORRECTO", "DOBLE"))
    tot_ok = 0
    tot_doble = 0
    for etiqueta, _ in LETRAS:
        c_ok = cuenta.get(("letra_ok", etiqueta), 0)
        c_db = cuenta.get(("letra_doble", etiqueta), 0)
        tot_ok += c_ok
        tot_doble += c_db
        print("%-22s %14d %14d" % (etiqueta, c_ok, c_db))
    print("-" * 72)
    print("%-22s %14d %14d" % ("TOTAL", tot_ok, tot_doble))
    print()

    print("PUNTUACIÓN TIPOGRÁFICA")
    print("-" * 72)
    pref = cuenta.get(("punt_doble", "prefijo â€"), 0)
    pts = cuenta.get(("punt_doble", "puntos â€¦"), 0)
    pts_ok = cuenta.get(("punt_ok", "puntos …"), 0)
    print("Prefijo doble  â€   : %d" % pref)
    print("  de los cuales â€¦ : %d   (ANIDADO en el anterior, no sumar)" % pts)
    print("Puntos correctos …  : %d" % pts_ok)
    print()

    print("VEREDICTO")
    print("-" * 72)
    if tot_ok == 0 and tot_doble == 0:
        print("DESCONOCIDO: no se hallaron letras acentuadas.")
    elif tot_doble == 0:
        print("ESCENARIO A puro. Cero doble codificación de letras.")
        print("El dump ya es UTF-8 correcto. NO convertir el charset.")
    elif tot_ok > tot_doble * 1000:
        print("ESCENARIO A. Proporción correcto/doble = %.0f a 1." % (tot_ok / tot_doble))
        print("El dump ya es UTF-8 correcto. NO convertir el charset.")
        print("El residuo de doble codificación es daño puntual, no sistemático.")
    elif tot_ok > tot_doble:
        print("ESCENARIO A con reservas. Proporción = %.1f a 1." % (tot_ok / tot_doble))
        print("Predomina el UTF-8 correcto, pero el residuo NO es despreciable.")
        print("Revisar manualmente antes de decidir.")
    else:
        print("ESCENARIO B. Hay doble codificación sistemática.")
        print("Requiere reinterpretación de bytes. NO importar sin más.")
    print()
    print("NOTA: el veredicto se refiere a la ESTRATEGIA DE IMPORTACIÓN.")
    print("No dictamina la CAUSA del daño residual. Atribuir la causa exige")
    print("cruzar los registros dañados con su origen, lo que requiere la base")
    print("de auditoría.")
    print()

    if mal == 0:
        print("DISTRIBUCIÓN DEL MOJIBAKE DE PUNTUACIÓN POR TABLA")
        print("-" * 72)
        cm = atribuir(offsets[("punt_doble", "prefijo â€")], creates)
        # Denominador: total de letras correctas por tabla, no un solo carácter.
        todas_ok = set()
        for etiqueta, _ in LETRAS:
            todas_ok |= offsets.get(("letra_ok", etiqueta), set())
        co = atribuir(todas_ok, creates)
        print("%-34s %10s %12s %9s" % ("TABLA", "MOJIBAKE", "LETRAS_OK", "% DAÑADO"))
        for t, c in cm.most_common():
            o = co.get(t, 0)
            pct = (100.0 * c / (c + o)) if (c + o) else 0.0
            print("%-34s %10d %12d %8.2f%%" % (t, c, o, pct))
        print()
        print("Tablas con texto acentuado y CERO mojibake:")
        limpias = [(t, o) for t, o in co.most_common() if cm.get(t, 0) == 0]
        if limpias:
            for t, o in limpias:
                print("  %-32s %d letras acentuadas" % (t, o))
        else:
            print("  (ninguna)")
        print()
        print("ADVERTENCIA: cero mojibake sobre un denominador pequeño NO es")
        print("prueba de limpieza, es ausencia de medición. Interpretar sólo")
        print("las tablas con denominador suficiente.")


if __name__ == "__main__":
    main()
