#!/usr/bin/env bash
#
# audit-dump-schema.sh
#
# Extrae el esquema (tabla -> motor / charset / collation) del dump SQL de
# WordPress SIN cargarlo en una base de datos y SIN modificar el dump.
#
# Sólo lee líneas de definición de esquema (CREATE TABLE / ) ENGINE=...).
# Nunca interpreta filas de datos: CLAUDE.md §13 y §14 prohíben usar parsers
# de texto para leer contenido, porque post_content, títulos y URLs contienen
# comas, comillas, escapes, HTML, JSON y saltos de línea.
#
# Uso:
#   tools/audit-dump-schema.sh [ruta-del-dump] [directorio-de-salida]
#
# Salida:
#   <out>/wp-tables-engine.txt   una línea por tabla: nombre|ENGINE=...
#
set -euo pipefail

DUMP="${1:-wp/DB/gaceta (2).sql}"
OUT_DIR="${2:-reports/audit}"
OUT="$OUT_DIR/wp-tables-engine.txt"

if [[ ! -f "$DUMP" ]]; then
  echo "ERROR: no se encontró el dump: $DUMP" >&2
  exit 1
fi

mkdir -p "$OUT_DIR"

echo "Dump:   $DUMP"
echo "Bytes:  $(wc -c < "$DUMP")"
echo "Leyendo esquema (sólo lectura)..."

# -a trata el archivo como texto aunque contenga bytes binarios (blobs).
# tr -d '\r' normaliza los finales de línea CRLF del dump.
grep -aoE "^CREATE TABLE \`[^\`]+\`|^\) ENGINE=[A-Za-z]+ (AUTO_INCREMENT=[0-9]+ )?DEFAULT CHARSET=[A-Za-z0-9_]+( COLLATE=[A-Za-z0-9_]+)?" "$DUMP" \
  | tr -d '\r' \
  | awk '
      /^CREATE TABLE/ {
        match($0, /`[^`]+`/)
        tabla = substr($0, RSTART + 1, RLENGTH - 2)
        next
      }
      /^\) ENGINE/ {
        sub(/^\) /, "")
        print tabla "|" $0
      }
    ' > "$OUT"

echo "Tablas encontradas: $(wc -l < "$OUT")"
echo
echo "Por prefijo:"
cut -d"|" -f1 "$OUT" | sed -E 's/^(dc8|wp)_.*/\1_/' | sort | uniq -c

echo
echo "Tablas que NO son utf8mb4 (riesgo de encoding, CLAUDE.md §11):"
grep -v "utf8mb4" "$OUT" || echo "  (ninguna)"

echo
echo "Escrito en: $OUT"
