# Inventario del template institucional Drupal 10

Fecha: 2026-09-30
Fase del roadmap: FASE 4 (diseño del modelo) — inventario inicial

## Núcleo

```text
CONFIRMADO: Drupal core     10.6.9  (core/lib/Drupal.php -> const VERSION)
CONFIRMADO: Perfil          standard
CONFIRMADO: Tema por defecto drudg8b3  (corregido, ver D-01)
CONFIRMADO: Tema admin      claro
CONFIRMADO: Drupal root     plantilla_drupal/Drudg10.6.9
```

## INCIDENCIA RESUELTA — D-01: tema por defecto inexistente

Al iniciar, el sitio servía HTTP 200 pero **sin tema**: 2 974 bytes de HTML,
sin CSS, sin regiones, sin listón. Drupal había caído al *fallback* de core.

Causa confirmada con cuatro fuentes independientes:

```text
CONFIRMADO: la BD viva declaraba  system.theme:default = udg_institucional
CONFIRMADO: udg_institucional NO existe en el sistema de archivos
            (themes/ sólo contiene: bootstrap, contrib/bootstrap, drudg8b3)
CONFIRMADO: udg_institucional SÍ figuraba en core.extension:theme
            como tema instalado -> entrada fantasma
CONFIRMADO: la configuración exportada del template declara
            system.theme:default = drudg8b3
CONFIRMADO: el dump entregado (Drudg10.6.9.mysql) contiene 105 ocurrencias
            de "drudg8b3" y 0 de "udg_institucional"
```

Conclusión: la base de datos cargada **no corresponde al dump entregado del
template**. Alguien cambió el tema por defecto a `udg_institucional` en la base
viva, o la base proviene de otro sitio.

### Resolución

```text
DECISIÓN: se estableció drudg8b3 como tema por defecto.
```

No es una elección arbitraria. Cuatro fuentes coinciden: la configuración
exportada, el dump entregado, el contenido del disco y la instrucción expresa
del responsable ("respetar `drudg8b3`").

Aplicado mediante la API de configuración de Drupal, **no por SQL directo**
(CLAUDE.md §31):

```bash
drush config:set system.theme default drudg8b3 -y
drush cache:rebuild
```

Resultado verificado: portada de 67 288 bytes, con los activos de `drudg8b3`,
`udg_liston` y Bootstrap presentes.

### Pendiente derivado

```text
PENDIENTE: la entrada fantasma "udg_institucional" sigue en core.extension.
```

No se eliminó. Desinstalar un tema ausente del disco puede fallar, y **no se
sabe** si el nombre corresponde a un renombrado previsto del template
institucional. Eliminarlo sería convertir una incertidumbre en decisión
(CLAUDE.md, regla de oro). Queda documentado para resolución con el
responsable.

## Tema drudg8b3

```text
CONFIRMADO: base theme  bootstrap (Bootstrap 3)
CONFIRMADO: 26 regiones declaradas
CONFIRMADO: librerías   drudg8b3/global-styling, drudg8b3/Smove
```

Regiones: `liston`, `navigation`, `navigation_collapsible`, `header`,
`precontent`, `precontent2`, `slideshow`, `precontent3`, `highlighted`,
`help`, `content`, `sidebar_first`, `sidebar_second`, `footer`, `content2`
… `content11`, `page_top`, `page_bottom`.

La región `liston` es la que consume el módulo `udg_liston`.

```text
NOTA: CLAUDE.md §43 prohíbe actualizar Bootstrap por iniciativa propia.
Bootstrap 3 se mantiene.
```

Activos verificados en la portada renderizada:

```text
/themes/drudg8b3/css/style.css
/themes/drudg8b3/images/BG_escudo-header-01.svg
/themes/drudg8b3/images/logo_udg_web_cads.svg
/themes/drudg8b3/libraries/banner.js
/themes/drudg8b3/libraries/jquery.smoove.min.js
/themes/drudg8b3/libraries/slideshow.js
/themes/drudg8b3/libraries/smoveConf.js
```

## Módulo udg_liston

```text
CONFIRMADO: único módulo en modules/custom
CONFIRMADO: dependencias declaradas: block, entity_print
CONFIRMADO: ambas dependencias están instaladas
```

Activos verificados en la portada renderizada:

```text
/modules/custom/udg_liston/css/udg_banner.css
/modules/custom/udg_liston/css/udg_liston.css
/modules/custom/udg_liston/css/udg_slideshow.css
/modules/custom/udg_liston/js/udg_slideshow.js
```

## Accesibilidad — verificada en el HTML servido (§12)

```text
CONFIRMADO presentes: Sepia, Grises, Invertir de color
CONFIRMADO presentes: skip-link, visually-hidden
CONFIRMADO presentes: aria-current, aria-hidden, aria-labelledby
```

Los mecanismos de accesibilidad del template **funcionan** y no deben
eliminarse.

```text
HALLAZGO MENOR: el HTML contiene el atributo "aria-democratizando", que no es
un atributo ARIA válido.
```

Es preexistente en el template, no introducido por este proyecto. Se registra
para la FASE 12; corregirlo toca el template institucional y requiere acuerdo.

## Módulos instalados

Según `core.extension` (configuración exportada), 74 módulos. Los relevantes
para la migración:

| Módulo | Relevancia |
|---|---|
| `comment` | §28 — comentarios históricos |
| `path`, `path_alias`, `pathauto` | §24 — URLs |
| `webform`, `webform_ui`, `webform_bootstrap` | §22 — formularios |
| `entity_print`, `entity_print_views` | dependencia de `udg_liston` |
| `taxonomy`, `taxonomy_term_depth` | §30 — taxonomías con jerarquía |
| `image`, `file` | media |
| `views`, `views_ui`, `views_bootstrap` | listados |
| `google_analytics` | equivalente a MonsterInsights |
| `easy_breadcrumb` | §23 — breadcrumbs |
| `smtp`, `phpmailer_smtp` | equivalente a WP Mail SMTP |
| `backup_migrate` | respaldos |
| `locale`, `language` | es_ES |
| `transliterate_filenames` | nombres de archivo con acentos |
| `big_pipe`, `page_cache`, `dynamic_page_cache` | §17 — caché |
| `devel`, `devel_generate` | desarrollo — **no debe ir a producción** |

### AUSENCIAS CRÍTICAS para la migración

```text
CONFIRMADO: no existen tablas de entidad Media en la base de datos.
CONFIRMADO: los módulos media y media_library NO están instalados.
CONFIRMADO: el módulo redirect NO está presente ni en disco ni instalado.
CONFIRMADO: el módulo metatag NO está presente ni en disco ni instalado.
CONFIRMADO: los módulos migrate* NO están instalados.
```

Esto choca frontalmente con tres requisitos del contrato:

| Requisito de CLAUDE.md | Módulo necesario | Estado |
|---|---|---|
| §25 — `imagen original -> Drupal Media -> Image Style` | `media`, `media_library` | **ausente** |
| §24 — redirects 301 para URLs que cambien | `redirect` | **ausente** |
| §23 — migrar datos SEO de Yoast | `metatag` | **ausente** |
| §31 — Migrate API como estrategia principal | `migrate`, `migrate_drupal`, `migrate_plus`, `migrate_tools` | **ausente** |

Instalar estos módulos **es imprescindible** y además altera el template
institucional (añade dependencias de Composer y configuración). Por eso **no
se instaló nada todavía**: corresponde a la FASE 4, cuyo *gate* exige
aprobación del modelo antes de migrar en masa.

```text
CONFIRMADO: presente en disco pero sin instalar -> upgrade_status
```

## Contenido actual (contenido de demostración del template)

```text
CONFIRMADO: 56 nodos, 13 tipos de contenido
```

| Tipo de contenido | Nodos | Correspondencia probable con Gaceta |
|---|---|---|
| `page` | 10 | Páginas |
| `evento_de_agenda` | 10 | Agenda / The Events Calendar (§21) |
| `banner` | 6 | Banners (§20) |
| `directorio` | 6 | Sin correspondencia evidente |
| `noticia` | 4 | **Corpus editorial principal de Gaceta** |
| `enlaces_de_interes` | 4 | Sin correspondencia evidente |
| `galeria_de_videos` | 3 | Contenido multimedia |
| `video` | 3 | Embeds de YouTube (§16) |
| `_aviso_emergente` | 3 | Popups (§16) |
| `slideshow` | 2 | Sliders (§20) |
| `galeria_de_imagenes` | 2 | Galerías |
| `titulo` | 2 | Elemento de presentación |
| `liston_de_contenido` | 1 | Listón institucional |

```text
IMPORTANTE: estos 56 nodos son contenido de demostración de la plantilla,
NO contenido de Gaceta.
```

Su destino (conservar como referencia, despublicar o eliminar) es la decisión
**D-05**. No se toca ninguno.

El tipo `noticia` ya existe: es el candidato natural para el corpus editorial,
pero CLAUDE.md §29 exige no crear ni reutilizar tipos de contenido sin
auditoría de campos previa. Sus campos **aún no se han inventariado**.

## Taxonomías actuales

```text
CONFIRMADO: 60 términos en 8 vocabularios
```

`centro_sede` 15 · `modalidad` 12 · `categoria_de_directorio` 8 · `puesto` 8 ·
`area_disciplinar` 6 · `nivel` 5 · `pnpc` 4 · `orientacion` 2

```text
OBSERVACIÓN: ninguno corresponde a las secciones editoriales de Gaceta.
```

Los vocabularios del template son de oferta académica. Las secciones y
subsecciones de Gaceta (`dc8_posts.seccion`, `dc8_posts.subseccion`) exigirán
vocabularios nuevos, diseñados en FASE 4 y migrados en FASE 7.

## Usuarios

```text
CONFIRMADO: 4 usuarios en el template
```

Frente a los 162 de WordPress (§27). La clasificación entre cuenta técnica y
autor editorial histórico es tarea de la FASE 8.

## Views existentes

```text
CONFIRMADO: 26 vistas configuradas
```

Reutilizables para Gaceta: `noticia`, `noticias_2`,
`noticias_pagina_principal`, `agenda_contenido_`, `banner`,
`slideshow_principal`, `galeria_de_imagenes`, `videos`, `directorio`,
`aviso_emergente`, `comments_recent`, `content_recent`, `frontpage`.

De administración o core: `archive`, `block_content`, `comment`, `content`,
`files`, `glossary`, `taxonomy_term`, `user_admin_people`,
`webform_submissions`, `watchdog`, `who_s_new`, `who_s_online`.

```text
CONFIRMADO: 116 bloques configurados
```

La infraestructura de presentación de noticias y agenda **ya existe** en el
template. Esto favorece la FASE 11 (reconstrucción visual): se adaptan Views
y bloques de Drupal en lugar de replicar Newspaper/tagDiv.

## Módulos contrib en disco

`animated_gif`, `backup_migrate`, `better_social_sharing_buttons`,
`bootstrap_library`, `captcha`, `ctools`, `date_popup`, `devel`,
`easy_breadcrumb`, `entity_print`, `fieldblock`, `google_analytics`,
`jquery_ui`, `jquery_ui_draggable`, `jquery_ui_resizable`, `menu_block`,
`pathauto`, `phpmailer_smtp`, `smtp`, `taxonomy_term_depth`, `token`,
`transliterate_filenames`, `twig_tweak`, `upgrade_status`, `video`,
`views_bootstrap`, `webform`

```text
CONFIRMADO: better_social_sharing_buttons y bootstrap_library están en disco
            pero NO en core.extension -> no instalados.
```
