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
CONFIRMADO: las columnas personalizadas de dc8_posts y CUÁNTAS filas usa cada
            una. Evidencia: reports/audit/content-counts.md
PENDIENTE:  lo que vive en dc8_postmeta (Elementor, media, slugs históricos,
            Yoast por contenido). Etapa 2 de D-02.
```

La etapa 1 de la base de auditoría ya cargó 162 957 filas de `dc8_posts`, así
que las cifras de este documento son SQL real, no estimaciones. Lo que sigue
pendiente es `dc8_postmeta`.

```text
Uso real de las columnas no estándar, sobre 36 666 entradas:
  original_id  25 121     seccion     24 672     subseccion  24 673
  term_id      16 832     foto1       16 102     balazo      13 914
  cita          1 876
```

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
| **Autoría del texto** | `post_author` | — | **FALTA** (D-14: vocabulario) |
| **Fotografía** | sin campo en origen | — | **FALTA** y sin fuente de datos |
| **Otros créditos** | sin campo en origen | — | **FALTA** y sin fuente de datos |
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

### RESUELTO con datos: NO forman jerarquía

La pregunta era si `seccion`/`subseccion` debía ser un vocabulario jerárquico
de dos niveles o dos vocabularios planos. Los datos la responden:

```text
CONFIRMADO: hay subsecciones bajo MÚLTIPLES secciones padre.
  Crónica      7 secciones padre
  Entrevista   7
  Homenaje     6
  Personaje, Exposición, Patrimonio, Conferencia, Opinión, Festival   5 cada una
Evidencia: reports/audit/content-counts.md
```

Un término de taxonomía de Drupal tiene **un solo padre**. "Crónica" no puede
ser hija de siete secciones a la vez.

```text
RESUELTO: dos vocabularios PLANOS e independientes.
  seccion_historica      -> field_seccion
  subseccion_historica   -> field_subseccion
```

Preserva el dato exactamente como está, sin inventar una jerarquía que el
origen no tiene. La relación sección-subsección sigue siendo recuperable
porque ambos campos conviven en el mismo nodo.

### Las secciones reales no son las del menú

```text
CONFIRMADO: dc8_posts.seccion contiene Miradas (5 560), Universidad (2 996),
Buzón (1 737), Deportes (1 542), Literatura (1 342), Música (881), Teatro
(874), Primer Plano (787), Artes visuales (764), ADN (612)...
```

Nada que ver con el menú que se observó en producción (Investigación y
Conocimiento, Noti Red, Deporte U, Talento U, 02 Cultura...).

```text
Son DOS arquitecturas editoriales distintas que conviven: la de las columnas
es la histórica, la del menú es la actual y vive en la taxonomía `category`
(129 términos, 103 con padre, jerárquica).
```

Esto valida la advertencia de CLAUDE.md §46: derivar el modelo de una
observación visual habría producido un vocabulario equivocado y habría dejado
24 672 secciones históricas sin destino.

```text
DECISIÓN ABIERTA: cómo se reconcilian las dos arquitecturas. Conservar ambas
(fiel pero confuso), mapear la histórica a la actual (pierde granularidad), o
conservar la actual y la histórica sólo como metadato.
```

### Calidad de datos: truncamiento heredado a 15 caracteres

```text
CONFIRMADO: 1 066 subsecciones tienen exactamente 15 caracteres, y sólo 12
tienen 16. Un factor de 89. No es distribución natural: es truncamiento.
Ejemplos: "Actividades cul", "Agenda académic", "Aniversario de"
```

Coherente con `original_id`: el sistema anterior tenía un campo de 15
caracteres. **Los caracteres perdidos no son recuperables desde este dump.**

```text
Esta pérdida NO la causa este proyecto (CLAUDE.md §47).
```

```text
DECISIÓN ABIERTA: coexisten "Artes visuales" y "Artes Visuales". ¿Se normalizan
las variantes de mayúsculas? Unificar modifica el dato; no unificar duplica
términos. Es decisión editorial.
```

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

### Créditos editoriales — RESUELTO (D-14)

```text
DECISIÓN TOMADA por el responsable del proyecto y su superior.
```

```text
NO se crean cuentas de usuario en Drupal para los autores de WordPress.
Los créditos se modelan como TÉRMINOS DE UN VOCABULARIO.
Deben admitir combinaciones de autoría de texto, fotografía y otros
colaboradores.
```

Razón de fondo: las cuentas de WordPress existen sólo para asignar el crédito
de los artículos. **Nadie inicia sesión con ellas.** Son identidades de
atribución, no de acceso. Modelarlas como usuarios de Drupal crearía 162
superficies de autenticación que nadie usaría.

#### Diseño

Un **único vocabulario** de personas, con **tres campos** de referencia:

```text
Vocabulario:  credito_editorial          (términos = personas)

Campos nuevos en noticia, todos de valor MÚLTIPLE:
  field_autor_texto    entity_reference -> credito_editorial  "Autoría del texto"
  field_fotografia     entity_reference -> credito_editorial  "Fotografía"
  field_colaboradores  entity_reference -> credito_editorial  "Otros créditos"

Campos de trazabilidad en el término:
  field_wp_user_id     integer   dc8_users.ID de origen
  field_wp_user_login  string    login de origen
```

**Un vocabulario, no tres.** La misma persona puede firmar el texto de una nota
y la fotografía de otra. Con un vocabulario único hay **un término canónico por
persona**: no hay duplicados, una página de autor reúne todo su trabajo, y se
puede preguntar "qué ha publicado esta persona, en cualquier rol". Con tres
vocabularios sería tres términos sin relación entre sí.

**Lo que NO se migra:** contraseñas, hashes, claves de activación ni
direcciones de correo. El correo es dato personal y no aporta nada a la
atribución (CLAUDE.md §27, §37).

**Alternativa evaluada.** Un vocabulario de roles más entidades Paragraph que
emparejen persona y rol. Más flexible si los roles se multiplican o importa su
orden, pero añade el módulo Paragraphs y complejidad. Con tres roles concretos,
tres campos son más simples y auditables. Queda como vía de escape.

#### HALLAZGO: los créditos de fotografía NO están en postmeta

El modelo queda preparado para los tres roles. Pero la auditoría del dump
revela un problema de **origen de datos**:

```text
CONFIRMADO: dc8_posts.post_author existe y es un id numérico.
            De ahí sale field_autor_texto. Migración directa y fiable.

CONFIRMADO: NINGUNA de estas claves aparece en el dump:
            _credito, _autor, _fotografo, _fotografia, _colaborador,
            autor, fotografo, credito
            Evidencia: reports/audit/postmeta-keys.md
```

Es decir: **no existe ningún campo estructurado con el crédito del fotógrafo**.
Las posibilidades que quedan son el texto del propio artículo (patrones del
tipo "Foto:", "Fotografía:", "Texto y fotos:") o el pie de la imagen
(`post_excerpt` del adjunto).

```text
CONSECUENCIA: field_fotografia y field_colaboradores se crean, pero se
quedarán VACÍOS salvo que se autorice extraer los créditos del cuerpo del
texto.
```

Extraerlos exigiría reconocer patrones en prosa, lo cual es inherentemente
inexacto y constituye una transformación de contenido editorial. **No se hará
por iniciativa propia.** Requeriría su propia decisión, con muestras reales y
una tasa de error medida.

```text
No se pierde información: mientras no se extraigan, el crédito original
permanece íntegro dentro del cuerpo del artículo, que es donde está hoy.
```

#### HALLAZGO QUE CONDICIONA D-14: post_author casi no sirve

Los conteos reales revelan un problema que no se podía ver antes:

```text
CONFIRMADO: 162 usuarios, 151 con algún contenido, 142 con entradas.
CONFIRMADO: el usuario 1, con nombre mostrado "Universidad de Guadalajara",
            tiene 23 663 entradas: el 64.5 % del corpus.
CONFIRMADO: el usuario 86 también se llama "Universidad de Guadalajara" (648).
CONFIRMADO: el usuario 87 es "Gaceta UdeG" (1 747).
Evidencia: reports/audit/content-counts.md
```

```text
Casi dos tercios del corpus está atribuido a una cuenta genérica cuyo nombre
es la institución, no una persona.
```

El modelo de vocabulario sigue siendo el correcto, pero `field_autor_texto`
quedaría con el valor "Universidad de Guadalajara" en 23 663 contenidos. El
autor real de esas notas está, con toda probabilidad, **dentro del texto del
artículo**, que es exactamente lo que ya apuntaba la ausencia de claves de
crédito en los metadatos.

```text
Periodistas con atribución real: Laura Sepúlveda Velázquez (2 416), Iván
Serrano Jauregui (984), Adrián Montiel González (839), Pablo Miranda Ramírez
(663), Mariana González Márquez (532), Wendy Aceves (472), Martha E. Mata
Loera (469), Karina Alatorre (445)...
Cartonistas: Trino (315), Jis (309), Falcón (137).
```

El caso de los cartonistas liga con la media diferida: su obra es gráfica.

```text
CONFIRMADO: nombres mostrados duplicados -> "Universidad de Guadalajara" en 2
usuarios (ids 1 y 86) y "Miriam Mairena" en 2.
```

```text
DECISIÓN ABIERTA: ¿un término o dos por cada nombre duplicado?
```

#### Pendiente de investigación (requiere la etapa 2 de D-02)

```text
- [ ] Frecuencia de patrones "Foto:", "Fotografía:", "Texto:" en post_content
- [ ] Contenido de post_excerpt en los adjuntos (pies de foto)
- [ ] Si el autor real de las 23 663 entradas genéricas está en el cuerpo
```

Esa última es editorial, no técnica: si la misma persona aparece como "Juan
Pérez" y "Juan Pérez Gómez", unificarla o no lo decide la redacción.

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
Cardinalidad de los campos que no son de crédito.
Si seccion/subseccion es un vocabulario jerárquico o dos vocabularios.
Qué hacer con los 193 lugares.
Si dc8_posts.term_id o dc8_term_relationships es la fuente de verdad.
Cómo se extrae el contenido de Elementor (D-03).
Si se autoriza extraer créditos de fotografía del cuerpo del texto.
```

Dependen de datos que sólo la base de auditoría puede dar. Fijarlas ahora sería
convertir incertidumbre en decisión, que es exactamente lo que el mandato
prohíbe.

El modelo de créditos **ya no está en esta lista**: lo resolvió el responsable
en D-14.

## Gate de la FASE 4

```text
GATE FASE 4: NO SUPERADO
```

Falta: aprobación del responsable, instalación de los módulos ausentes, y las
seis decisiones que dependen de B-03.
