# Modelo de contenido — análisis y propuesta

Fecha: 2026-09-30
Fase del roadmap: FASE 4 (diseño del modelo Drupal)

```text
ESTADO: PROPUESTA. No aprobada. No implementada.
```

CLAUDE.md §33 exige que el modelo se apruebe **antes** de cualquier migración
masiva, y §29 prohíbe crear campos sin justificación y tipos de contenido sólo
porque exista un `post_type`. Este documento es el análisis previo a esa
aprobación: expone lo que hay, lo que falta y lo que no se puede decidir
todavía.

## Qué está confirmado y qué no

```text
CONFIRMADO: la estructura de tipos de contenido y campos del template Drupal.
CONFIRMADO: las columnas personalizadas de dc8_posts (esquema del dump).
DESCONOCIDO: cuántos registros usan cada columna y con qué valores.
```

Esa asimetría es importante. Se conoce el **continente** de ambos lados, pero
no el **contenido** del lado WordPress: los conteos requieren la base de
auditoría (B-03). Por eso este documento identifica el mapeo y los huecos,
pero **no fija cardinalidades ni decide tipos de campo definitivos**.

## Lado origen: columnas de `dc8_posts`

`dc8_posts` no es una tabla `wp_posts` estándar. CLAUDE.md §11 lista sus
columnas; las que **no** existen en WordPress son:

| Columna | Naturaleza aparente | Relevancia |
|---|---|---|
| `original_id` | identificador heredado de un sistema anterior | trazabilidad |
| `term_id` | término asociado, fuera de `term_relationships` | taxonomía |
| `foto1` | imagen principal, fuera del sistema de adjuntos | media |
| `balazo` | antetítulo o entradilla periodística | editorial |
| `cita` | cita destacada | editorial |
| `seccion` | sección editorial | arquitectura |
| `subseccion` | subsección editorial | arquitectura |

```text
HIPÓTESIS: dc8_posts es el resultado de una migración anterior hacia
WordPress, y esas columnas son el residuo del sistema de origen.
```

`original_id` es la pista más fuerte: una tabla nativa de WordPress no
necesita guardar el identificador de otro sistema. Esto significa que **esta
es la segunda migración** de este contenido, y que puede haber pérdidas
heredadas de la primera.

No se eleva a CONFIRMADO: exige comparar `original_id` con `ID` y ver si hay
rangos, huecos o duplicados. Requiere B-03.

### Consecuencia de diseño

`seccion`, `subseccion`, `term_id` y `foto1` son **campos planos** donde
WordPress esperaría relaciones. Es decir, el contenido no usa los mecanismos
nativos de WordPress para lo más importante de su arquitectura editorial.

```text
CRÍTICO: no se puede asumir que las taxonomías de Gaceta vivan en
dc8_term_relationships. Pueden estar en columnas de texto de dc8_posts.
```

Esto afecta directamente a la FASE 7. Antes de migrar vocabularios hay que
determinar cuál de los dos mecanismos es la fuente de verdad, o si conviven.

## Lado destino: el tipo de contenido `noticia` existente

```text
CONFIRMADO: 9 campos, ninguno obligatorio.
```

| Campo | Tipo | Etiqueta |
|---|---|---|
| `body` | `text_with_summary` | Descripcion |
| `field_sinopsis_` | `string` | Sinopsis |
| `field_imagen_noticia` | `image` | Imagen |
| `field_archivo_noticia` | `file` | Archivo |
| `field_archivo_de_audio` | `file` | Archivo de audio |
| `field_url_de_video` | `video` | URL de video |
| `field_galeria_asociada` | `entity_reference` → `galeria_de_imagenes` | Galería asociada |
| `field_galeria_de_video_asociada_` | `entity_reference` | Galería de video asociada |
| `field_tags` | `entity_reference` → vocabulario `tags` | Etiquetas |

Observaciones sobre el estado del template:

```text
HALLAZGO: tres nombres de campo tienen defectos heredados.
```

`field_sinopsis_` y `field_galeria_de_video_asociada_` terminan en guion bajo,
y dos etiquetas contienen un tabulador literal (`"Sinopsis\t"`,
`"Galería de video asociada\t"`). Son defectos cosméticos del template
institucional, no introducidos por este proyecto. Corregirlos implica cambiar
nombres de campo del template, lo que CLAUDE.md §44 advierte no hacer
arbitrariamente.

```text
RECOMENDACIÓN: no renombrar. Limpiar sólo las etiquetas visibles si se
autoriza, y dejar los nombres de máquina intactos.
```

## Mapeo propuesto y huecos detectados

| Necesidad de Gaceta | Origen | Destino en el template | Estado |
|---|---|---|---|
| Título | `post_title` | `title` del nodo | **cubierto** |
| Cuerpo | `post_content` | `body` | **cubierto** |
| Fecha de publicación | `post_date` | `created` | **cubierto** |
| Fecha de modificación | `post_modified` | `changed` | **cubierto** |
| Slug | `post_name` | alias de ruta | **cubierto** (pathauto) |
| Estado | `post_status` | `status` | **cubierto** |
| Imagen principal | `foto1` | `field_imagen_noticia` | cubierto, **pendiente B-02** |
| Archivo adjunto | adjuntos | `field_archivo_noticia` | cubierto, **pendiente B-02** |
| Galería | adjuntos | `field_galeria_asociada` | cubierto, **pendiente B-02** |
| Vídeo | plugin YouTube | `field_url_de_video` | **cubierto** |
| Etiquetas | `dc8_terms` | `field_tags` | **cubierto** |
| **Balazo / antetítulo** | `balazo` | — | **FALTA** |
| **Cita destacada** | `cita` | — | **FALTA** |
| **Sección** | `seccion` | — | **FALTA** |
| **Subsección** | `subseccion` | — | **FALTA** |
| **Autor editorial** | `post_author` | — | **FALTA** |
| **Trazabilidad WP** | `ID`, `original_id` | — | **FALTA** |
| **SEO (título, meta, OG)** | Yoast | — | **FALTA: módulo ausente** |
| **Redirects 301** | URLs históricas | — | **FALTA: módulo ausente** |
| **Entidades Media** | adjuntos | — | **FALTA: módulo ausente** |

### Los cuatro huecos editoriales

`balazo`, `cita`, `seccion` y `subseccion` son **contenido editorial real** de
Gaceta que el template institucional no contempla, porque su tipo `noticia`
fue diseñado para noticias institucionales simples, no para un periódico.

```text
No migrarlos sería pérdida deliberada de contenido, que el mandato prohíbe.
```

Propuesta, sujeta a aprobación:

| Campo nuevo | Tipo | Justificación |
|---|---|---|
| `field_balazo` | `string` | antetítulo; es texto corto sin formato |
| `field_cita` | `string_long` | cita destacada; admite varias líneas |
| `field_seccion` | `entity_reference` → nuevo vocabulario | jerarquía editorial navegable |
| `field_subseccion` | `entity_reference` → mismo vocabulario | jerarquía de segundo nivel |

```text
DECISIÓN ABIERTA: ¿seccion y subseccion como UN vocabulario jerárquico de dos
niveles, o como DOS vocabularios independientes?
```

Un vocabulario jerárquico es más fiel a la relación real y permite que Drupal
genere las rutas y los breadcrumbs por sí solo. Dos vocabularios son más
simples de migrar desde columnas planas, pero pierden la relación
padre-hijo. **No se decide aquí**: depende de si en los datos cada subsección
pertenece siempre a una sola sección, lo que exige B-03.

Las secciones observadas en producción (§46, sólo lectura) son:

```text
Investigación y Conocimiento · Noti Red · Deporte U · Talento U
02 Cultura · Especiales · Información Oficial · Hemeroteca
```

```text
ADVERTENCIA: son una observación visual, no la fuente de verdad.
```

CLAUDE.md §46 lo prohíbe expresamente: no se convierte una observación visual
en decisión de modelo de datos. El vocabulario debe construirse desde
`dc8_posts.seccion` y `dc8_terms`, no desde el menú del sitio.

### Autor editorial

CLAUDE.md §27 es claro: 162 usuarios de WordPress **no** se traducen en 162
cuentas de Drupal. Hay que separar la cuenta técnica del crédito editorial.

Propuesta:

```text
field_autor_editorial (string)  -> nombre del autor tal como debe mostrarse
uid del nodo                    -> cuenta técnica, o usuario de migración
```

Guardar el nombre como campo preserva la atribución histórica sin crear
cuentas activas para personas que ya no participan. Si más adelante se decide
crear un vocabulario de autores o entidades de autor, el campo de texto es
convertible; el camino inverso no lo es.

```text
DECISIÓN ABIERTA: ¿campo de texto, vocabulario de autores, o cuentas reales?
Depende de cuántos autores distintos haya y de si necesitan página propia.
Requiere B-03.
```

### Trazabilidad

CLAUDE.md §35 exige poder responder *"¿dónde terminó exactamente este registro
WordPress?"*. Eso necesita almacenamiento persistente del identificador de
origen.

Migrate API mantiene sus propias tablas de mapeo, que cubren el requisito
mientras las migraciones existan. Pero si se hace `migrate:reset` o se
reconstruye, el rastro se pierde. Por eso se propone además:

```text
field_wp_post_id     (integer)  -> dc8_posts.ID
field_wp_original_id (integer)  -> dc8_posts.original_id
```

Son dos campos ocultos en la visualización. Hacen la trazabilidad
independiente del estado de las tablas de Migrate y permiten reconciliar
conteos con SQL directo en la FASE 13.

## Módulos que hay que instalar

```text
CONFIRMADO: ninguno de estos está instalado en el template.
```

| Módulo | Exigido por | Para qué |
|---|---|---|
| `migrate`, `migrate_drupal` | §31 | núcleo de la estrategia principal |
| `migrate_plus`, `migrate_tools` | §31 | grupos, dependencias, ejecución por lotes |
| `media`, `media_library` | §25 | `imagen original -> Media -> Image Style` |
| `redirect` | §24 | redirects 301 de URLs que cambien |
| `metatag` | §23 | migrar los datos SEO con valor |

```text
ADVERTENCIA: instalar estos módulos altera el template institucional.
```

Añade dependencias de Composer y configuración exportada. CLAUDE.md §44
prohíbe destruir la funcionalidad del template, no prohíbe extenderlo, pero la
extensión debe ser deliberada y aprobada. Por eso **no se ha instalado nada**.

`redirect` y `metatag` no están ni siquiera presentes en disco: hay que
descargarlos con Composer, lo que modifica `composer.json` y `composer.lock`.

## Infraestructura de presentación ya disponible

Esto es una buena noticia para la FASE 11: el template **ya trae** la
maquinaria para presentar un sitio de noticias.

```text
CONFIRMADO: 26 vistas, 116 bloques, 24 plantillas Twig en drudg8b3.
```

Vistas reutilizables para Gaceta:

```text
noticia · noticias_2 · noticias_pagina_principal · agenda_contenido_
banner · slideshow_principal · galeria_de_imagenes · videos
directorio · aviso_emergente · comments_recent · content_recent · frontpage
```

Plantillas Twig específicas ya escritas:

```text
views-bootstrap-carousel--noticias_pagina_principal--block-2.html.twig
views-view-fields--noticias_pagina_principal--block-1.html.twig
views-bootstrap-carousel--slideshow_principal--block-1.html.twig
views-bootstrap-carousel--galeria_de_imagenes--block-3.html.twig
views-view-unformatted--agenda_contenido_--block-1.html.twig
views-view--banner--block-1.html.twig
block--listonudg.html.twig · block--bannerudg.html.twig
block--redessociales.html.twig · block--drudg8b3-footer1.html.twig
page.html.twig · node.html.twig · html.html.twig
```

```text
CONCLUSIÓN: la FASE 11 debe adaptar Views y bloques existentes, no reescribir
la presentación. Coincide con lo que exige §44.
```

### Módulo `udg_liston`

```text
CONFIRMADO: 6 clases PHP, 4 librerías de activos.
```

Bloques que aporta: `ListonBlock`, `ListonContenidoBlock`, `BannerBlock`,
`SliderBlock`, `SocialMediaBlock`, más un plugin de campo de Views.

Librerías: `udg_liston`, `udg_slideshow`, `boton_share`, `udg_banner`.

```text
No se modifica. Es el componente institucional que §43 y §44 protegen.
```

## Tipos de contenido: qué NO crear

Aplicando §29 y §14, los `post_type` de WordPress **no** se traducen uno a uno:

| `post_type` de WordPress | Tratamiento propuesto |
|---|---|
| `post` | → `noticia` (con los campos nuevos) |
| `page` | → `page` (ya existe) |
| `attachment` | → entidades Media, **no** nodos |
| `revision` | → revisiones de nodo, **no** nodos |
| `tribe_events` | → `evento_de_agenda` (ya existe) |
| `tribe_venue` | **no migrar como contenido** sin verificar referencias (§21) |
| `tribe_organizer` | idem |
| `elementor_library`, `tdb_templates` | presentación, **no** contenido (§18, §19) |
| `oembed_cache`, `custom_css` | caché y configuración, **no** contenido |
| `nav_menu_item` | menús de Drupal, **no** nodos |
| `popup`, `popup_theme` | `_aviso_emergente` ya existe; verificar equivalencia |
| `wpcf7_contact_form`, `wpforms` | Webform, **no** nodos (§22) |
| `tabs` | presentación; evaluar |

```text
Los 193 "lugares publicados" de §21 NO se convierten automáticamente en
contenido editorial.
```

`evento_de_agenda` resuelve el lugar con `field_lugar`, que es un campo de
enlace, no una entidad. Si los 193 lugares no están referenciados por ningún
evento visible, son registros huérfanos del plugin. Determinarlo exige B-03.

### Campos de `evento_de_agenda` existentes

```text
body · field_fecha_agenda (daterange) · field_cartel (image)
field_liston (image) · field_lugar (link) · field_sitio_de_interes (link)
field_archivo_evento (file)
```

Cubre lo esencial de un evento. `field_fecha_agenda` como `daterange` es
adecuado para `dc8_tec_events` y `dc8_tec_occurrences`.

## Lo que este documento no decide

```text
Cardinalidad de cada campo (uno o varios valores).
Si seccion/subseccion es un vocabulario jerárquico o dos vocabularios.
Si el autor editorial es texto, vocabulario o cuenta.
Qué hacer con los 193 lugares.
Si dc8_posts.term_id o dc8_term_relationships es la fuente de verdad.
Cómo se extrae el contenido de Elementor (D-03).
```

Las seis dependen de datos que sólo la base de auditoría puede dar. Fijarlas
ahora sería convertir incertidumbre en decisión, que es exactamente lo que el
mandato prohíbe.

## Gate de la FASE 4

```text
GATE FASE 4: NO SUPERADO
```

Falta: aprobación del responsable, instalación de los módulos ausentes, y las
seis decisiones que dependen de B-03.
