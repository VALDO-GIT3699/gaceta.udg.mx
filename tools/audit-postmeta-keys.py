#!/usr/bin/env python3
"""
audit-postmeta-keys.py

Cuenta la frecuencia de cada `meta_key` conocida dentro del dump SQL, sin
cargarlo en ninguna base de datos y sin modificarlo.

Para qué sirve. `dc8_postmeta` ocupa 2.3 GB, el 68 % del dump. Saber QUÉ hay
dentro dimensiona de golpe tres cosas que el contrato marca como riesgo y que
hasta ahora estaban sin medir:

  - Elementor (CLAUDE.md §18): cuántos contenidos tienen `_elementor_data`.
  - Media (§25, §26): cuántos adjuntos hay y cuántas referencias a archivos.
  - Yoast (§23): qué datos SEO existen realmente y en qué volumen.

Método. Se cuentan apariciones de la cadena literal `'<meta_key>'` tal como
aparece entrecomillada en las sentencias INSERT. Es un conteo de bytes, no un
parser de filas: CLAUDE.md §13 y §14 prohíben interpretar filas con parsers de
texto, y esta técnica no lo hace.

LIMITACIÓN que hay que tener presente al leer la salida: la cifra es el número
de apariciones de esa cadena en el archivo, no necesariamente el número de
filas. Si una clave apareciera también dentro del *valor* de otra fila (por
ejemplo, dentro del JSON de Elementor), se contaría de más. Por eso la salida
distingue entre claves de prefijo seguro y claves que conviene verificar
después con SQL. Trátese como ORDEN DE MAGNITUD y confírmese con la base de
auditoría.

Uso:
    python tools/audit-postmeta-keys.py [ruta-del-dump]
"""

import sys

DUMP_POR_DEFECTO = "wp/DB/gaceta (2).sql"
TAMANO_BLOQUE = 8 * 1024 * 1024

# Claves agrupadas por el asunto del contrato al que pertenecen.
GRUPOS = {
    "Elementor (CLAUDE.md §18)": [
        "_elementor_data",
        "_elementor_edit_mode",
        "_elementor_page_settings",
        "_elementor_template_type",
        "_elementor_version",
        "_elementor_pro_version",
        "_elementor_css",
        "_elementor_controls_usage",
        "_elementor_conditions",
    ],
    "Media y adjuntos (§25, §26)": [
        "_wp_attached_file",
        "_wp_attachment_metadata",
        "_wp_attachment_image_alt",
        "_thumbnail_id",
        "_wp_attachment_backup_sizes",
    ],
    "Yoast SEO (§23)": [
        "_yoast_wpseo_title",
        "_yoast_wpseo_metadesc",
        "_yoast_wpseo_canonical",
        "_yoast_wpseo_focuskw",
        "_yoast_wpseo_meta-robots-noindex",
        "_yoast_wpseo_opengraph-title",
        "_yoast_wpseo_opengraph-description",
        "_yoast_wpseo_opengraph-image",
        "_yoast_wpseo_twitter-title",
        "_yoast_wpseo_primary_category",
        "_yoast_wpseo_estimated-reading-time-minutes",
    ],
    "tagDiv / Newspaper (§19)": [
        "td_post_theme_settings",
        "_td_post_views_count",
        "tdc_css_class",
        "tdc_css_class_style",
    ],
    "The Events Calendar (§21)": [
        "_EventStartDate",
        "_EventEndDate",
        "_EventVenueID",
        "_EventOrganizerID",
        "_EventAllDay",
    ],
    "Créditos editoriales (D-14)": [
        "_credito",
        "_autor",
        "_fotografo",
        "_fotografia",
        "_colaborador",
        "autor",
        "fotografo",
        "credito",
    ],
    "Formularios (§22)": [
        "_wpcf7",
        "_wpforms",
    ],
    "Otros plugins": [
        "_edit_lock",
        "_edit_last",
        "_wp_page_template",
        "_wp_old_slug",
        "_wp_desired_post_slug",
        "_pvc_post_views",
        "_wprss_item_permalink",
        "_wprss_feed_id",
    ],
}


def main():
    ruta = sys.argv[1] if len(sys.argv) > 1 else DUMP_POR_DEFECTO

    claves = []
    for lista in GRUPOS.values():
        claves.extend(lista)
    # Buscar la forma entrecomillada, que es como aparece en el INSERT.
    patrones = {k: ("'" + k + "'").encode("utf-8") for k in claves}
    conteo = {k: 0 for k in claves}

    maxlen = max(len(v) for v in patrones.values())
    pos = 0
    cola = b""
    with open(ruta, "rb") as fh:
        while True:
            bloque = fh.read(TAMANO_BLOQUE)
            if not bloque:
                break
            buf = cola + bloque
            for k, pat in patrones.items():
                conteo[k] += buf.count(pat)
            pos += len(bloque)
            # Restar los solapes contados dos veces es complicado; en su lugar
            # se usa un solape mínimo y se acepta un error de a lo sumo una
            # aparición por frontera de bloque, que es despreciable frente a
            # las magnitudes que se miden.
            cola = buf[-(maxlen - 1):] if maxlen > 1 else b""

    print("Dump: %s" % ruta)
    print("Bytes leídos: %d" % pos)
    print()

    for grupo, lista in GRUPOS.items():
        presentes = [(k, conteo[k]) for k in lista if conteo[k] > 0]
        print("=" * 72)
        print(grupo)
        print("=" * 72)
        if not presentes:
            print("  (ninguna de las claves buscadas aparece en el dump)")
            print()
            continue
        for k, c in sorted(presentes, key=lambda x: -x[1]):
            print("  %-48s %10d" % (k, c))
        ausentes = [k for k in lista if conteo[k] == 0]
        if ausentes:
            print("  --- ausentes: %s" % ", ".join(ausentes))
        print()

    print("=" * 72)
    print("ADVERTENCIA DE INTERPRETACIÓN")
    print("=" * 72)
    print("Estas cifras son apariciones de una cadena, no filas confirmadas.")
    print("Una clave podría aparecer dentro del valor de otra fila, sobre todo")
    print("dentro del JSON de Elementor. Confírmese con SQL real contra la")
    print("base de auditoría antes de usarlas como cifras definitivas")
    print("(CLAUDE.md §14).")


if __name__ == "__main__":
    main()
