# Inventario de base de datos — dump WordPress Gaceta

Fecha de auditoría: 2026-09-30
Fase del roadmap: FASE 2 (auditoría de base de datos) — **en progreso, NO cerrada**

## Alcance de esta auditoría

Este inventario se obtuvo **sin cargar el dump en una base de datos**, mediante
lectura en streaming del archivo para extraer exclusivamente las sentencias
`CREATE TABLE` y sus cláusulas `ENGINE` / `DEFAULT CHARSET` / `COLLATE`.

Por lo tanto:

- **CONFIRMADO:** estructura de tablas, motor y charset declarado.
- **DESCONOCIDO:** conteos de filas, post types, estados, autores, taxonomías,
  comentarios y metadatos. Esas cifras exigen SQL real contra una base de
  auditoría cargada (ver sección "Pendiente para cerrar FASE 2").

Conforme a CLAUDE.md §13 y §14, **no se usó ningún parser de texto para
interpretar filas de datos**. La extracción se limitó a líneas de definición de
esquema, que no contienen contenido editorial.

## Archivo auditado

```text
Ruta:    wp/DB/gaceta (2).sql
Tamaño:  3 544 549 259 bytes (~3.30 GiB / ~3.54 GB)
```

El dump original **no fue modificado** (CLAUDE.md §12).

## Resumen de tablas

```text
Total de tablas en el dump:       68
Prefijo dc8_ (corpus principal):  56
Prefijo wp_ (instalación aparte): 12
Otros prefijos:                    0
```

Listado completo tabla -> motor/charset: `reports/audit/wp-tables-engine.txt`

## CONFLICTO CRÍTICO DE ENCODING — confirmado

CLAUDE.md §11 advertía sobre `dc8_posts`. La auditoría lo **confirma y además
amplía el alcance del problema**: no es una sola tabla, son 25.

```text
CONFIRMADO: dc8_posts -> ENGINE=MyISAM DEFAULT CHARSET=latin1 (sin COLLATE)
CONFIRMADO: 24 tablas dc8_ adicionales -> CHARSET=utf8 (utf8mb3, 3 bytes)
CONFLICTO:  WordPress Site Health declara DB_CHARSET=utf8mb4
```

### Tablas que NO son utf8mb4

| Tabla | Motor | Charset declarado | Relevancia para la migración |
|---|---|---|---|
| `dc8_posts` | MyISAM | **latin1** | **Máxima.** Corpus editorial completo. |
| `dc8_postmeta` | MyISAM | utf8 (mb3) | **Máxima.** Elementor, Yoast, adjuntos. |
| `dc8_comments` | MyISAM | utf8 (mb3) | Alta. Comentarios históricos. |
| `dc8_commentmeta` | MyISAM | utf8 (mb3) | Media. |
| `dc8_terms` | MyISAM | utf8 (mb3) | Alta. Taxonomías. |
| `dc8_term_taxonomy` | MyISAM | utf8 (mb3) | Alta. Taxonomías. |
| `dc8_term_relationships` | MyISAM | utf8 (mb3) | Alta. Relación contenido-término. |
| `dc8_termmeta` | MyISAM | utf8 (mb3) | Media. |
| `dc8_users` | MyISAM | utf8 (mb3) | Alta. Autoría editorial. |
| `dc8_usermeta` | MyISAM | utf8 (mb3) | Alta. |
| `dc8_options` | MyISAM | utf8 (mb3) | Media. Configuración del sitio. |
| `dc8_links` | MyISAM | utf8 (mb3) | Baja. |
| `dc8_revslider_*` (12 tablas, incl. `_bkp`) | MyISAM | utf8 (mb3) | Por determinar (§20). |
| `dc8_yoast_seo_links` | InnoDB | utf8 (mb3) | Media (§23). |

### Doble implicación técnica

Hay **dos problemas distintos** que no deben confundirse:

1. **`dc8_posts` declara `latin1`.** Es el caso que CLAUDE.md §11 marca como
   crítico. El síntoma `â€¦` documentado en el contrato es la firma clásica de
   **UTF-8 almacenado en una columna declarada latin1** (doble codificación).
   Si eso se confirma, la transformación correcta **no** es
   `CONVERT ... USING utf8mb4` (eso destruiría los bytes), sino una
   reinterpretación de bytes. Aplicar la conversión equivocada es
   **irreversible sobre los datos convertidos**.

2. **24 tablas declaran `utf8` = utf8mb3 (3 bytes).** utf8mb3 **no puede
   almacenar** caracteres de 4 bytes (emoji, algunos signos). Si el contenido
   original tenía esos caracteres, WordPress ya los perdió o los almacenó como
   entidades HTML *antes* de esta migración. Esto no es un daño causado por la
   migración, pero **debe documentarse** para no atribuirle a nuestro proceso
   pérdidas preexistentes.

### ACCIÓN OBLIGATORIA

```text
GATE FASE 2 (CLAUDE.md §33): NO SUPERADO
```

No se ejecuta ninguna transformación de contenido hasta demostrar, con muestras
reales de `dc8_posts`, cuál es la codificación de bytes real. El procedimiento
exigido por CLAUDE.md §11 (pasos 1-10) está **pendiente** porque requiere la
base de auditoría cargada.

## Instalación WordPress separada (`wp_*`) — §15

```text
CONFIRMADO: 12 tablas wp_, todas InnoDB/utf8mb4_unicode_ci
CONFIRMADO: son exclusivamente tablas del núcleo de WordPress
CONFIRMADO: no existe ninguna tabla wp_ de plugin
```

Tablas: `wp_commentmeta`, `wp_comments`, `wp_links`, `wp_options`,
`wp_postmeta`, `wp_posts`, `wp_termmeta`, `wp_terms`,
`wp_term_relationships`, `wp_term_taxonomy`, `wp_usermeta`, `wp_users`.

```text
HIPÓTESIS: es una instalación limpia o de prueba, no el corpus editorial.
```

Sustento de la hipótesis: ausencia total de tablas de plugin, charset moderno
uniforme, y el contenido de ejemplo que CLAUDE.md §15 reporta ("Hola mundo",
"Página de ejemplo"). **La hipótesis no se eleva a CONFIRMADO** hasta contar
filas y comparar fechas con SQL real. No se descartan estas tablas.

## Tablas dc8_ agrupadas por función

### Corpus editorial (núcleo WordPress)

`dc8_posts`, `dc8_postmeta`, `dc8_comments`, `dc8_commentmeta`, `dc8_terms`,
`dc8_term_taxonomy`, `dc8_term_relationships`, `dc8_termmeta`, `dc8_users`,
`dc8_usermeta`, `dc8_options`, `dc8_links`

### SEO — Yoast (§23)

`dc8_yoast_indexable`, `dc8_yoast_indexable_hierarchy`, `dc8_yoast_migrations`,
`dc8_yoast_primary_term`, `dc8_yoast_seo_links`

`dc8_yoast_primary_term` es especialmente relevante: define el término primario
de cada contenido, dato que **no existe** en la taxonomía nativa de WordPress y
que afecta breadcrumbs y URL canónica.

### Sliders (§20)

- **Smart Slider 3 / Nextend:** `dc8_nextend2_image_storage`,
  `dc8_nextend2_section_storage`, `dc8_nextend2_smartslider3_generators`,
  `dc8_nextend2_smartslider3_sliders`,
  `dc8_nextend2_smartslider3_sliders_xref`,
  `dc8_nextend2_smartslider3_slides`
- **Slider Revolution:** `dc8_revslider_*` (6 tablas + 6 `_bkp`)

```text
HALLAZGO NO PREVISTO: Slider Revolution no aparece en el inventario de plugins
de CLAUDE.md §16, pero sus tablas existen en la base de datos.
```

Esto significa que hay un plugin histórico **desinstalado o inactivo cuyas
tablas persisten**. Debe auditarse si alguno de esos sliders sigue referenciado
por contenido visible antes de decidir su destino.

### The Events Calendar (§21)

`dc8_tec_events`, `dc8_tec_occurrences`

### Formularios (§22)

- **WPForms:** `dc8_wpforms_payments`, `dc8_wpforms_payment_meta`,
  `dc8_wpforms_tasks_meta`
- **Elementor Forms:** `dc8_e_submissions`, `dc8_e_submissions_values`,
  `dc8_e_submissions_actions_log`

```text
HALLAZGO NO PREVISTO: existen tablas de envíos de formularios de Elementor.
```

CLAUDE.md §22 solamente contempla WPForms (2 formularios, 0 envíos visibles) y
Contact Form 7. Las tablas `dc8_e_submissions*` pertenecen a **Elementor Pro
Forms**, un canal de formularios adicional no inventariado. Si contienen
envíos, son **datos personales de terceros** y su tratamiento requiere decisión
explícita (ver `MIGRATION_CONTRACT.md`, decisión D-04).

### Correo y tareas

`dc8_wpmailsmtp_debug_events`, `dc8_wpmailsmtp_tasks_meta`,
`dc8_actionscheduler_actions`, `dc8_actionscheduler_claims`,
`dc8_actionscheduler_groups`, `dc8_actionscheduler_logs`

`dc8_wpmailsmtp_debug_events` puede contener **credenciales o destinatarios de
correo**. Si se audita, todo dato sensible va **REDACTED** en los reportes
(CLAUDE.md §37).

### Otros / por clasificar

`dc8_post_views` (Post Views Counter), `dc8_e_notes`,
`dc8_e_notes_users_relations` (Elementor Notes), `dc8_mclean_refs`,
`dc8_mclean_scan` (Media Cleaner — relevante para §25), `dc8_agg_displays`,
`dc8_agg_sources`

```text
DESCONOCIDO: el origen de dc8_agg_displays y dc8_agg_sources.
```

No corresponden a ningún plugin del inventario de CLAUDE.md §16.

## Pendiente para cerrar FASE 2

La base `gaceta_auditoria` que CLAUDE.md §12 describe **no existe en este
entorno**. Ver `MIGRATION_CONTRACT.md` (decisión D-02).

```text
CONFIRMADO: MariaDB local disponible -> 10.4.32 (C:\xampp8.2.12\mysql)
CONFIRMADO: bases existentes -> information_schema, mysql, performance_schema,
            phpmyadmin, test, webcsocial, <BD_DRUPAL>
CONFIRMADO: gaceta_auditoria NO existe
```

La ruta `C:\Users\Usuario2\xampp8.1.25\mysql\bin\mysql.exe` que cita CLAUDE.md
§12 **no corresponde a este equipo** (usuario actual: `acer`). La auditoría
previa se realizó en otra máquina y sus resultados no son verificables aquí.

Tareas bloqueadas hasta cargar la base de auditoría:

- [ ] Conteos por `post_type` (SQL real, no parser)
- [ ] Conteos por `post_status`
- [ ] Matriz `post_type` x `post_status`
- [ ] Autores y su relación con `dc8_users`
- [ ] `seccion`, `subseccion`, `term_id`, `foto1`, `balazo`, `cita`
- [ ] Taxonomías y jerarquías
- [ ] Comentarios (validar la cifra de ~6 346 de §28)
- [ ] Attachments y `_wp_attached_file`
- [ ] Claves `_elementor_*` en `dc8_postmeta`
- [ ] Datos Yoast con valor real
- [ ] Eventos y lugares (§21)
- [ ] **Demostración del encoding real de `dc8_posts`**

## Reproducibilidad

El inventario se regenera con `tools/audit-dump-schema.sh`, que sólo lee el
dump y nunca lo escribe.
