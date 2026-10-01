# Inventario de claves de `dc8_postmeta`

Fecha: 2026-09-30
Fase del roadmap: FASE 1 y FASE 2
Reproducible con: `tools/audit-postmeta-keys.py`

## Por qué este inventario

`dc8_postmeta` ocupa **2.3 GB, el 68.28 % del dump**. Es la mayor masa de datos
del proyecto y hasta ahora era una caja negra.

Medición del tamaño de cada tabla dentro del dump
(`tools/audit-table-sizes.py`):

| Tabla | En el dump | INSERTs | % del dump |
|---|---:|---:|---:|
| `dc8_postmeta` | **2.3 GB** | 43 856 | **68.28 %** |
| `dc8_posts` | 772.3 MB | 17 557 | 22.85 % |
| `dc8_post_views` | 241.8 MB | 4 676 | 7.15 % |
| `dc8_yoast_indexable` | 30.2 MB | 637 | 0.89 % |
| `dc8_yoast_seo_links` | 9.3 MB | 193 | 0.28 % |
| `dc8_options` | 8.5 MB | 64 | 0.25 % |
| `dc8_comments` | 5.2 MB | 111 | 0.15 % |
| `dc8_term_relationships` | 1.2 MB | 22 | 0.03 % |
| resto (60 tablas) | < 1 MB cada una | | < 0.1 % |

```text
Tres tablas concentran el 98.3 % del dump.
```

## Método y su limitación

Se cuentan apariciones de la cadena literal `'<meta_key>'` tal como aparece
entrecomillada en las sentencias `INSERT`. Es un conteo de bytes en streaming,
no un parser de filas: CLAUDE.md §13 y §14 prohíben interpretar filas con
parsers de texto, y esta técnica no lo hace.

```text
ADVERTENCIA: la cifra es el número de apariciones de una cadena, no de filas
confirmadas. Si una clave apareciera dentro del VALOR de otra fila (por
ejemplo dentro del JSON de Elementor) se contaría de más.
```

Trátese como **orden de magnitud**. Las cifras definitivas exigen SQL contra la
base de auditoría (B-03).

## Elementor — ver análisis dedicado

| Clave | Apariciones |
|---|---:|
| `_elementor_template_type` | 48 171 |
| `_elementor_version` | 48 120 |
| `_elementor_pro_version` | 48 119 |
| `_elementor_edit_mode` | 48 108 |
| **`_elementor_data`** | **47 896** |
| `_elementor_page_settings` | 29 631 |
| `_elementor_css` | 13 423 |
| `_elementor_controls_usage` | 4 458 |
| `_elementor_conditions` | 5 |

Análisis completo en `reports/audit/elementor-audit.md`.

## Media y adjuntos (§25, §26)

| Clave | Apariciones | Qué significa |
|---|---:|---|
| `_wp_attached_file` | **48 358** | ruta relativa de cada adjunto |
| `_wp_attachment_metadata` | 46 415 | dimensiones y derivados generados |
| `_thumbnail_id` | **42 003** | contenidos con imagen destacada |
| `_wp_attachment_backup_sizes` | 2 379 | derivados de ediciones previas |
| `_wp_attachment_image_alt` | **661** | texto alternativo |

### Lecturas

```text
HIPÓTESIS: hay del orden de 48 000 archivos adjuntos originales.
```

Si se confirma, el manifiesto de media (`docs/media-strategy.md`) tendrá unas
48 000 entradas. Con ~84.23 GB en producción, eso da un promedio aproximado de
1.75 MB por original **incluidos sus derivados**, que es un orden de magnitud
plausible para fotografía de prensa.

La diferencia entre `_wp_attached_file` (48 358) y `_wp_attachment_metadata`
(46 415) son unos 1 943 adjuntos sin metadatos de imagen: típicamente PDF y
otros documentos, que WordPress no redimensiona.

```text
HALLAZGO GRAVE DE ACCESIBILIDAD: sólo 661 adjuntos tienen texto alternativo.
```

Sobre ~48 000 adjuntos, eso es el **1.4 %**. El 98.6 % de las imágenes no
tiene `alt`. La FASE 12 no puede "preservar" una accesibilidad que no existe en
el origen: hay que decidir si se genera, si se deja vacío o si se marca para
revisión editorial. Es una decisión, no una tarea técnica, y se registra como
pendiente.

## Yoast SEO (§23)

| Clave | Apariciones |
|---|---:|
| `_yoast_wpseo_estimated-reading-time-minutes` | 6 858 |
| `_yoast_wpseo_primary_category` | **6 818** |
| `_yoast_wpseo_focuskw` | 920 |
| `_yoast_wpseo_metadesc` | **910** |
| `_yoast_wpseo_title` | **2** |
| `_yoast_wpseo_canonical` | **0** |
| `_yoast_wpseo_meta-robots-noindex` | **0** |
| `_yoast_wpseo_opengraph-title` | **0** |
| `_yoast_wpseo_opengraph-description` | **0** |
| `_yoast_wpseo_opengraph-image` | **0** |
| `_yoast_wpseo_twitter-title` | **0** |

### Lecturas — el SEO por contenido es MÍNIMO

```text
CONFIRMADO: sólo 2 contenidos tienen título SEO propio.
CONFIRMADO: sólo 910 tienen meta description propia.
CONFIRMADO: CERO tienen canonical, Open Graph o Twitter Card propios.
```

Esto **reduce mucho** el alcance de la FASE 10 respecto a lo que §23 anticipaba.
La inmensa mayoría de los títulos y descripciones no están almacenados por
contenido: los genera Yoast a partir de **plantillas** guardadas en
`dc8_options` (claves `wpseo_titles` y similares).

```text
CONSECUENCIA: migrar "los datos SEO" no es copiar 48 000 registros. Es
reproducir unas pocas PLANTILLAS de título y descripción, más 910 overrides
manuales y 2 títulos manuales.
```

Eso cambia el diseño: el módulo `metatag` de Drupal se configura con patrones
de token equivalentes a las plantillas de Yoast, y sólo los 912 overrides se
migran como valores por nodo.

```text
PENDIENTE: extraer las plantillas de dc8_options (wpseo_titles). Requiere B-03.
```

`_yoast_wpseo_primary_category` (6 818) es el dato más valioso de este bloque:
define el término primario de cada contenido, que no existe en la taxonomía
nativa de WordPress y que determina breadcrumbs y URL canónica.

## URLs — hallazgo crítico

| Clave | Apariciones | Qué significa |
|---|---:|---|
| **`_wp_old_slug`** | **7 811** | slugs históricos de contenidos renombrados |
| `_wp_desired_post_slug` | 12 | slug solicitado y no concedido |

```text
CRÍTICO para CLAUDE.md §24: hay 7 811 URLs históricas que hoy funcionan por
el mecanismo de redirección de WordPress.
```

WordPress guarda el slug anterior cada vez que cambia el de una entrada, y
sirve una redirección 301 desde él. Son URLs reales, indexadas y enlazadas
desde fuera.

```text
Si no se migran, se pierden 7 811 rutas silenciosamente. §24 lo prohíbe
expresamente.
```

Análisis en `docs/url-strategy.md`.

## The Events Calendar (§21)

```text
CONFIRMADO: CERO apariciones de _EventStartDate, _EventEndDate, _EventVenueID,
_EventOrganizerID y _EventAllDay.
```

Coherente con lo que §21 reporta (0 eventos publicados, 0 futuros, 4 borradores)
y **refuerza** la sospecha de que los 193 lugares son registros huérfanos: sin
`_EventVenueID` no hay ningún evento que los referencie.

```text
HIPÓTESIS reforzada: los 193 tribe_venue no están referenciados por eventos.
```

Confirmarlo exige contar filas, pero la ausencia total de la clave de
vinculación es un indicio fuerte.

## tagDiv / Newspaper (§19)

| Clave | Apariciones |
|---|---:|
| `td_post_theme_settings` | 8 333 |

Son ajustes de presentación por contenido del tema Newspaper. CLAUDE.md §19
los clasifica como presentación, no contenido: **no se migran**.

## Créditos editoriales — hallazgo para D-14

```text
CONFIRMADO: CERO apariciones de _credito, _autor, _fotografo, _fotografia,
_colaborador, autor, fotografo, credito.
```

```text
No existe ningún campo estructurado con el crédito del fotógrafo.
```

Es el hallazgo que condiciona la decisión D-14 del responsable. El modelo de
vocabulario de créditos se construye igual, pero `field_fotografia` y
`field_colaboradores` **no tienen fuente de datos** en el origen. Detalle en
`docs/content-model.md`.

## WP RSS Aggregator — hipótesis debilitada

```text
CONFIRMADO: CERO apariciones de _wprss_item_permalink y _wprss_feed_id.
```

`reports/audit/encoding-audit.md` planteaba como hipótesis que el contenido
agregado por RSS fuera el origen del mojibake de puntuación.

```text
HIPÓTESIS DEBILITADA: no hay señal de contenido agregado en postmeta.
```

El plugin está instalado y sus tablas existen, pero no se ve que haya producido
entradas con sus claves de metadatos. La causa del mojibake sigue siendo
DESCONOCIDA y la hipótesis editorial se mantiene sin confirmar.

## Formularios (§22)

```text
CONFIRMADO: CERO apariciones de _wpcf7 y _wpforms en postmeta.
```

Coherente con §22 (0 envíos visibles). Los envíos de Elementor Forms viven en
tablas propias (`dc8_e_submissions`), no en postmeta.

## Otros

| Clave | Apariciones |
|---|---:|
| `_wp_page_template` | 58 099 |
| `_edit_lock` | 19 622 |
| `_edit_last` | 19 390 |

`_edit_lock` y `_edit_last` son artefactos del editor (quién editaba y cuándo).
No son contenido y no se migran, aunque `_edit_last` podría servir como pista
secundaria de autoría si `post_author` resultara poco fiable.

## Qué sigue

```text
- [ ] Confirmar todas estas cifras con SQL real (B-03)
- [ ] Extraer las plantillas de título de Yoast desde dc8_options
- [ ] Decidir el tratamiento de las ~47 300 imágenes sin texto alternativo
- [ ] Construir el manifiesto de media con las 48 358 rutas
- [ ] Construir el mapa de las 7 811 URLs históricas
```
