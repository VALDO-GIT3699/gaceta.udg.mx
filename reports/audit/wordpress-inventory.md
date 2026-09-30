# Inventario del WordPress entregado

Fecha: 2026-09-30
Fase del roadmap: FASE 1 (inventario del WordPress)

Inventario del **sistema de archivos**. Los conteos de contenido siguen
pendientes de la base de auditoría (B-03).

## Estructura entregada

```text
wp/
├── DB/gaceta (2).sql        3 544 549 259 bytes
├── qdb/                     copia antigua de phpMyAdmin — NO se migra (§10)
├── wp-admin/
├── wp-includes/
├── wp-content/
├── wp-config.php
└── (archivos raíz de WordPress)
```

```text
CONFIRMADO: coincide con la estructura que anticipa CLAUDE.md §10.
```

### Ruido de macOS

```text
CONFIRMADO: el árbol contiene numerosos archivos ._* y un directorio __MACOSX.
```

Son metadatos de recursos que macOS genera al comprimir. No son parte de
WordPress. Ya están excluidos en `.gitignore` y deben ignorarse en todo
inventario; de lo contrario duplican los conteos de archivos.

## HALLAZGO CRÍTICO — `wp-content/uploads` NO EXISTE

```text
CONFIRMADO: el directorio wp-content/uploads no existe en la copia local.
CONFIRMADO: no existe ningún directorio llamado "uploads" en todo el proyecto.
CONFIRMADO: 0 archivos .jpg, .png o .pdf en los tres primeros niveles de
            wp-content.
```

Esto **agrava** lo previsto en CLAUDE.md §25, que describe la copia local como
parcial:

> *"La copia local tiene solamente una parte del árbol de uploads."*

La realidad es más severa:

```text
No hay una parte del árbol de uploads. No hay nada.
```

### Consecuencia

El BLOCKED **B-02** cambia de naturaleza. No es un problema de completitud
sino de **ausencia total**:

- No se puede calcular ningún hash SHA-256 de archivos (§26).
- No se puede relacionar ningún `attachment` con su archivo físico (§25).
- No se puede detectar derivados ni archivos huérfanos (FASE 3).
- No se puede migrar ni un solo archivo a Drupal Media (FASE 6).

Lo único auditable sobre media son las **referencias** en la base de datos
(`guid`, `_wp_attached_file`, `_wp_attachment_metadata`, `foto1`, HTML de
`post_content`, datos de Elementor). Eso permite construir el inventario de
**qué archivos deberían existir**, pero no verificar que existan.

```text
FASE 3 y FASE 6: BLOQUEADAS por dependencia externa.
```

Se requiere acceso al árbol `/wp-content/uploads` de producción
(~84.23 GB según §8). Ver `MIGRATION_CONTRACT.md`, B-02.

## Contenido de `wp-content`

| Directorio | Archivos | Naturaleza |
|---|---:|---|
| `cache` | 12 879 | caché de WP Super Cache — **no se migra** (§17) |
| `td_cache` | 12 | caché de tagDiv — **no se migra** (§19) |
| `languages` | 859 | traducciones es_ES |
| `ai1wm-backups` | 13 | respaldos de All-in-One WP Migration |
| `upgrade` | 0 | vacío |
| `upgrade-temp-backup` | 0 | vacío |
| `uploads` | **no existe** | ver hallazgo crítico |

```text
CONFIRMADO: 12 879 archivos de caché. Son artefactos regenerables.
```

CLAUDE.md §17 lo indica explícitamente: la caché no se migra como contenido.
En Drupal el equivalente son `page_cache`, `dynamic_page_cache` y `big_pipe`,
los tres ya instalados en el template.

`ai1wm-backups` se revisó por si contenía un respaldo completo del sitio con
los uploads dentro, lo que habría aliviado B-02:

```text
CONFIRMADO: 38 KB en total. NO contiene ningún respaldo.
CONFIRMADO: sólo archivos de protección del directorio (.htaccess, index.php,
            robots.txt, web.config).
```

```text
B-02 sigue sin alivio. No existe ninguna copia de los archivos de media.
```

### Archivos de caché y configuración en la raíz de `wp-content`

```text
advanced-cache.php   db.php   db-error.php   maintenance.php
```

`db.php` es un *drop-in* de reemplazo de la capa de base de datos. Debe
revisarse por si altera el comportamiento de lectura/escritura, lo que
afectaría a cualquier supuesto sobre los datos.

## `mu-plugins` — plugins de carga obligatoria

```text
CONFIRMADO: endurance-browser-cache.php
CONFIRMADO: endurance-page-cache.php
CONFIRMADO: sso.php
```

Coinciden con los "plugins imprescindibles" de CLAUDE.md §16. Son *must-use*:
se cargan siempre y no aparecen en la lista de plugins activables.

`sso.php` es el más relevante: implica **autenticación federada**. Si el acceso
al Drupal de Gaceta debe conservar el inicio de sesión institucional, eso es un
requisito funcional que todavía **no está recogido en el roadmap** y que no
tiene equivalente instalado en el template.

```text
PENDIENTE: determinar si el SSO institucional debe replicarse en Drupal.
```

## Tema

```text
CONFIRMADO: Newspaper 12.6 (tagDiv)
```

Coincide con CLAUDE.md §8 y §19. **No se migra como tema** (§19).

Temas presentes en disco: `Newspaper`, `twentysixteen`, `twentyseventeen`,
`twentynineteen`, `twentytwenty`, `twentytwentyone`, `twentytwentytwo`. Los
temas por defecto de WordPress son irrelevantes para la migración.

## Plugins en disco

```text
CONFIRMADO: 38 directorios de plugin.
```

### Reconciliación con CLAUDE.md §16

Versiones verificadas leyendo las cabeceras de los archivos de plugin:

| Plugin | Versión en disco | En §16 |
|---|---|---|
| Elementor | 3.15.3 | sí — coincide |
| Elementor Pro | 3.15.1 | sí — coincide |
| Yoast SEO (`wordpress-seo`) | 22.0 | sí, sin versión |
| The Events Calendar | 6.3.2 | sí, sin versión |
| WPForms Lite | 1.8.6.4 | sí, sin versión |
| Smart Slider 3 | 3.5.1.21 | sí, sin versión |
| tagDiv Cloud Library | 2.9 (15.09.2023) | sí, sin versión |
| tagDiv Standard Pack | 2.0 (15.09.2023) | sí, sin versión |

```text
CONFIRMADO: las versiones de Elementor coinciden exactamente con §8 y §18.
```

### HALLAZGO — plugins en disco que §16 NO inventaría

```text
HALLAZGO NO PREVISTO: 10 plugins presentes en disco y ausentes del contrato.
```

| Plugin | Versión | Por qué importa |
|---|---|---|
| **Slider Revolution** (`revslider`) | 6.0.7 | **Explica las 12 tablas `dc8_revslider_*`** halladas en el dump |
| **WP RSS Aggregator** | 5.0.6 | **Explica `dc8_agg_displays` y `dc8_agg_sources`** |
| **Popup Maker** | 1.19.0 | Explica los post types `popup` y `popup_theme` de §14 |
| **Essential Addons for Elementor** | 5.9.9 | Amplía el riesgo de Elementor (§18): más widgets a interpretar |
| Akismet Anti-spam | 5.6 | Antispam de comentarios (§28) |
| All-in-One WP Migration | 7.79 | Explica `ai1wm-backups`; posible vía para B-02 |
| OptinMonster | 2.15.3 | Captación de correos: posibles datos personales |
| Templately | 3.0.2 | Plantillas de Elementor |
| Enable jQuery Migrate Helper | 1.4.0 | Indicador de dependencias JS antiguas |
| MOJO Marketplace | 1.7.0 | Plugin del proveedor de hosting |

Estos hallazgos **cierran dos incógnitas** que quedaron abiertas en
`database-inventory.md`:

```text
RESUELTO: dc8_revslider_* proviene de Slider Revolution 6.0.7.
RESUELTO: dc8_agg_displays y dc8_agg_sources provienen de WP RSS Aggregator 5.0.6.
```

WP RSS Aggregator es especialmente significativo: **importa contenido desde
fuentes RSS externas**. Eso abre dos preguntas que afectan al modelo de
contenido:

1. ¿Parte del corpus editorial es contenido agregado de terceros y no obra
   propia de Gaceta?
2. ¿Ese contenido agregado es el origen del mojibake de puntuación detectado
   en `dc8_posts`? Las fuentes RSS con encoding mal declarado son una causa
   clásica de ese patrón exacto.

```text
HIPÓTESIS: la agregación RSS podría explicar el mojibake de
reports/audit/encoding-audit.md.
```

No se eleva a CONFIRMADO: exige cruzar los `post_id` dañados con los registros
de `dc8_agg_sources`, lo cual requiere la base de auditoría (B-03).

### Plugins del inventario §16 sin verificar en disco

`Disable Comments RB`, `Jetpack`, `Loco Translate`, `PDF Embedder`,
`Post Duplicator`, `Post Views Counter`, `Query Monitor`, `Search & Filter`,
`Site Kit by Google`, `Tabs by PickPlugins`, `tagDiv Composer`,
`tagDiv Social Counter`, `WP Downgrade`, `WP Mail SMTP`, `WP Super Cache`,
`Classic Editor`, `Contact Form 7`, `Google Analytics for WordPress`,
`YouTube WordPress Plugin by Embed Plus` están presentes como directorios;
sus versiones no se leyeron en esta pasada.

```text
PENDIENTE: no se ha determinado qué plugins están ACTIVOS.
```

La lista de plugins activos vive en `dc8_options` (clave `active_plugins`,
valor serializado). Requiere la base de auditoría. **Un plugin en disco no es
un plugin activo**, y CLAUDE.md §16 es claro: se migra funcionalidad real, no
plugins.

## `wp-config.php`

```text
CONFIRMADO: presente.
```

No se reproduce su contenido: contiene credenciales y claves de sal
(CLAUDE.md §37). Está excluido por `.gitignore` junto con todo `wp/`.

## `qdb/` — copia de phpMyAdmin

```text
CONFIRMADO: presente, con su propio directorio sql/.
```

CLAUDE.md §10 lo excluye explícitamente de la migración. Sus archivos `.sql`
(`create_tables.sql`, `upgrade_*.sql`) son el esquema interno de phpMyAdmin y
**no deben confundirse con datos de Gaceta**.

## Secciones editoriales observadas en producción (§46, sólo lectura)

Observación del menú de navegación de `https://www.gaceta.udg.mx`:

```text
Inicio · Investigación y Conocimiento · Noti Red · Deporte U · Talento U
02 Cultura · Especiales · Información Oficial · Hemeroteca
```

```text
CONFIRMADO: son las secciones visibles en el menú principal.
```

Este dato es la primera evidencia real de la **arquitectura editorial** de
Gaceta, y contrasta con los vocabularios del template Drupal, que son de
oferta académica (`centro_sede`, `modalidad`, `nivel`, `pnpc`…).

```text
HIPÓTESIS: estas secciones corresponden a dc8_posts.seccion / .subseccion,
o a la taxonomía category de WordPress.
```

No se eleva a CONFIRMADO. CLAUDE.md §46 advierte expresamente: *"No conviertas
una observación visual en una decisión de modelo de datos sin verificar el
origen de la información."* El diseño de vocabularios (FASE 4, FASE 7) debe
partir de `dc8_terms` y `dc8_term_taxonomy`, no del menú.

## Tareas de FASE 1 aún abiertas

- [ ] Determinar plugins **activos** (`dc8_options.active_plugins`) — B-03
- [ ] Revisar `wp-content/db.php` (drop-in de base de datos)
- [ ] Revisar `ai1wm-backups` por si contiene uploads — puede aliviar B-02
- [ ] Decidir si el SSO institucional debe replicarse
- [ ] Versiones del resto de plugins
- [ ] Inventario de contenido, taxonomías, autores, comentarios — B-03
