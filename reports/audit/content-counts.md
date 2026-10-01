# Conteos definitivos del contenido WordPress

Fecha: 2026-09-30
Fase del roadmap: FASE 1 y FASE 2
Fuente: SQL real contra `gaceta_auditoria`
Reproducible con: `tools/audit-queries.sql`

```text
Estas son las CIFRAS DEFINITIVAS del proyecto.
```

CLAUDE.md §14 declara **no confiables** los conteos obtenidos con parsers de
texto, y ordena que las cifras definitivas salgan de consultas SQL contra la
base cargada. Este documento las sustituye todas.

## Cómo se obtuvieron

```text
CONFIRMADO: gaceta_auditoria creada vacía; no existía previamente.
CONFIRMADO: 66 tablas cargadas (830.4 MB extraídos del dump) sin un solo error.
CONFIRMADO: el dump original no se modificó: se abrió sólo en lectura.
PENDIENTE:  dc8_postmeta (2.3 GB) y dc8_post_views (242 MB). Etapa 2 de D-02.
```

Tabla principal cargada:

```text
dc8_posts   162 957 filas   731.2 MB datos + 12.7 MB índices   MyISAM / latin1_swedish_ci
```

## VALIDACIÓN DEL ENCODING, DE EXTREMO A EXTREMO

Antes de cualquier cifra, la prueba que cierra B-01 con datos reales y no sólo
con análisis de bytes:

```text
Cliente SIN charset:              Buz?n      M?sica
Cliente --default-character-set=utf8mb4:  Buzón      Música
```

```text
CONFIRMADO: el texto sobrevive el viaje completo, intacto.
```

La cadena demostrada es:

```text
1. El dump contiene UTF-8 correcto         (análisis de bytes, encoding-audit.md)
2. Carga con la conexión en utf8mb4        -> MySQL convierte UTF-8 a latin1
3. Lectura con la conexión en utf8mb4      -> MySQL convierte latin1 a UTF-8
4. Resultado: "Buzón" y "Música" correctos
```

Los caracteres `?` que aparecen sin el parámetro **no son corrupción de datos**:
son el cliente mostrando bytes latin1 en una terminal UTF-8. El dato almacenado
es correcto.

```text
CONSECUENCIA OPERATIVA: toda consulta a gaceta_auditoria debe llevar
--default-character-set=utf8mb4. Sin él, el texto acentuado se lee mal y
cualquier conclusión sobre el contenido sería falsa.
```

Esto confirma por tercera vía independiente lo que ya decía
`reports/audit/encoding-audit.md`: **no hay que convertir nada**.

## 1. Contenido por `post_type`

| `post_type` | Total | Tratamiento previsto |
|---|---:|---|
| `revision` | **77 307** | revisiones de nodo, no nodos |
| `attachment` | **48 369** | entidades Media (diferido, D-15) |
| **`post`** | **36 666** | **corpus editorial → `noticia`** |
| `tribe_venue` | 194 | §21, probablemente huérfanos |
| `page` | 185 | → `page` |
| `oembed_cache` | 89 | caché, no se migra |
| `elementor_library` | 69 | presentación, no se migra |
| `tdb_templates` | 28 | presentación, no se migra |
| `nav_menu_item` | 17 | menús de Drupal |
| `popup` | 8 | → `_aviso_emergente` |
| `popup_theme` | 8 | presentación |
| `wpcf7_contact_form` | 4 | → Webform |
| `tribe_events` | 4 | § 21, todos borrador |
| `tabs` | 3 | presentación |
| `custom_css` | 2 | configuración |
| `wpforms` | 2 | → Webform |
| `tribe_organizer` | 2 | §21 |
| **TOTAL** | **162 957** | |

```text
CONFIRMADO: el corpus editorial a migrar son 36 666 entradas, de las cuales
36 627 están publicadas.
```

Las cifras de §21 se confirman **exactamente**: 4 eventos borrador, 2
organizadores publicados, 193 lugares publicados (más 1 borrador).

## 2. Estados

| `post_status` | Total |
|---|---:|
| `inherit` | 125 676 |
| `publish` | 37 170 |
| `private` | 53 |
| `draft` | 38 |
| `trash` | 11 |
| `auto-draft` | 8 |
| `future` | 1 |

`inherit` son las revisiones y los adjuntos (77 307 + 48 369 = 125 676, cuadra
exactamente).

Matriz relevante:

```text
post / publish        36 627
post / draft              13
post / trash              11
post / auto-draft          8
post / private             6
page / publish           120
page / private            47
page / draft              18
```

```text
DECISIÓN PENDIENTE: los 47 page privados y los 6 post privados. ¿Se migran
como no publicados, o se descartan? No se descarta nada sin autorización.
```

## 3. Alcance temporal: 31 años de historia

```text
CONFIRMADO: del 1995 al 2026.
CONFIRMADO: 6 registros con post_date = '0000-00-00 00:00:00'.
CONFIRMADO: 0 registros con fecha NULL.
```

Entradas publicadas por año:

```text
1995    19      2008  1620      2015  2166      2022  1703
1996     1      2009  1919      2016  2014      2023  1635
2004   101      2010  2198      2017  1733      2024  1750
2005  2090      2011  2207      2018  1513      2025  1888
2006   548      2012  2226      2019  1241      2026  1273
2007   314      2013  1891      2020  1194
                2014  1824      2021  1553
```

```text
Hay un hueco total entre 1996 y 2004.
```

No se sabe si es que no se publicó nada, o si ese contenido se perdió en la
migración anterior. Es una **pregunta para la redacción**, no técnica.

Los 6 registros con fecha cero necesitan tratamiento explícito: Drupal no
acepta `0000-00-00` como fecha de creación.

## 4. Las columnas no estándar: el corazón del modelo

| Columna | Filas con valor | % de los 36 666 `post` |
|---|---:|---:|
| `original_id` | **25 121** | 68.5 % |
| `seccion` | **24 672** | 67.3 % |
| `subseccion` | **24 673** | 67.3 % |
| `term_id` | 16 832 | 45.9 % |
| **`foto1`** | **16 102** | 43.9 % |
| `balazo` | 13 914 | 38.0 % |
| `cita` | 1 876 | 5.1 % |

```text
CONFIRMADO: ninguna de estas siete columnas existe en WordPress estándar.
Ninguna herramienta genérica de migración las conoce.
```

```text
Si se migra con una fuente estándar de WordPress, se pierden:
  24 672 secciones editoriales
  16 102 imágenes principales
  13 914 antetítulos
   1 876 citas destacadas
```

### `original_id` confirma la segunda migración

```text
CONFIRMADO: 25 121 filas tienen original_id distinto de 0.
```

La hipótesis de `docs/content-model.md` queda respaldada: **este contenido ya
fue migrado una vez** hacia WordPress desde otro sistema. Las consecuencias
importan: puede haber pérdidas heredadas que **no son responsabilidad de este
proyecto** y conviene documentarlas para no atribuírselas.

## 5. Secciones editoriales reales

```text
ADVERTENCIA IMPORTANTE: no se parecen al menú del sitio.
```

Secciones principales según `dc8_posts.seccion`:

| Sección | Entradas |
|---|---:|
| Miradas | 5 560 |
| Universidad | 2 996 |
| Buzón | 1 737 |
| Deportes | 1 542 |
| Literatura | 1 342 |
| Música | 881 |
| Teatro | 874 |
| Primer Plano | 787 |
| Artes visuales | 764 |
| ADN | 612 |
| Entrevista | 474 |
| Catalejo | 462 |
| Pasaje cultural | 461 |
| Cine | 317 |
| DVD | 250 |

Mientras que el menú de producción muestra (§46, observación de sólo lectura):

```text
Investigación y Conocimiento · Noti Red · Deporte U · Talento U
02 Cultura · Especiales · Información Oficial · Hemeroteca
```

```text
CONFIRMADO: son dos arquitecturas editoriales DISTINTAS.
```

La de las columnas es la **histórica**; la del menú es la **actual**, basada en
la taxonomía `category` de WordPress. Conviven.

```text
Esto valida la advertencia de CLAUDE.md §46: derivar el modelo de datos de una
observación visual habría producido un vocabulario equivocado y habría dejado
24 672 secciones históricas sin destino.
```

## 6. `seccion` / `subseccion` NO forman jerarquía — decisión resuelta

`docs/content-model.md` dejaba abierto si debían ser un vocabulario jerárquico
o dos planos. Los datos lo responden:

```text
CONFIRMADO: hay subsecciones que aparecen bajo MÚLTIPLES secciones padre.
```

| Subsección | Secciones padre distintas |
|---|---:|
| Crónica | **7** |
| Entrevista | **7** |
| Homenaje | 6 |
| Personaje | 5 |
| Exposición | 5 |
| Patrimonio | 5 |
| Conferencia | 5 |
| Opinión | 5 |
| Festival | 5 |

```text
RESUELTO: NO puede ser un vocabulario jerárquico de dos niveles.
```

Un término de taxonomía de Drupal tiene un solo padre. "Crónica" no puede ser
hija de siete secciones a la vez. Las opciones reales son dos vocabularios
planos independientes, o un vocabulario de secciones más etiquetas para las
subsecciones.

```text
RECOMENDACIÓN: dos vocabularios planos, seccion_historica y
subseccion_historica, con dos campos de referencia independientes. Preserva el
dato exactamente como está, sin inventar una jerarquía que el origen no tiene.
```

## 7. Calidad de datos: truncamiento heredado

```text
CONFIRMADO: seccion y subseccion son varchar(25).
```

Distribución de longitudes de `subseccion`:

```text
longitud 13:   571      longitud 16:    12
longitud 14:   574      longitud 17:   292
longitud 15: 1 066      longitud 18:    32
```

```text
De 1 066 valores a longitud 15 se cae a 12 a longitud 16. Un factor de 89.
```

Eso no es una distribución natural de longitudes de palabra: es un
**truncamiento a 15 caracteres**. Se observa en valores concretos como
`Actividades cul`, `Agenda académic` y `Aniversario de`, todos exactamente de
15 caracteres y cortados a mitad de palabra.

`seccion` muestra el mismo escalón: 662 valores a longitud 15, 114 a 16.

```text
CONFIRMADO: el truncamiento es PREEXISTENTE y los caracteres perdidos NO son
recuperables desde este dump.
```

Es coherente con `original_id`: el sistema anterior tenía un campo de 15
caracteres, y la primera migración trajo los valores ya cortados.

```text
Esta pérdida NO la causa este proyecto. Se documenta para que no se le
atribuya, conforme a CLAUDE.md §47.
```

### Inconsistencia de mayúsculas

```text
CONFIRMADO: coexisten "Artes visuales" y "Artes Visuales".
```

Al construir el vocabulario, cada variante produciría un término distinto.

```text
DECISIÓN PENDIENTE: ¿se normalizan las variantes de mayúsculas y se unifican?
Es una decisión editorial. Unificar modifica el dato; no unificar duplica
términos.
```

## 8. Autoría: el hallazgo que condiciona D-14

```text
CONFIRMADO: 162 usuarios. 151 con algún contenido. 142 con entradas.
```

Principales atribuciones:

| Usuario | Nombre mostrado | Entradas |
|---|---|---:|
| 1 | **Universidad de Guadalajara** | **23 663** |
| 16 | Laura Sepúlveda Velázquez | 2 416 |
| 87 | Gaceta UdeG | 1 747 |
| 6 | Iván Serrano Jauregui | 984 |
| 76 | Adrián Montiel González | 839 |
| 72 | Pablo Miranda Ramírez | 663 |
| 86 | **Universidad de Guadalajara** | 648 |
| 15 | Mariana González Márquez | 532 |
| 80 | Wendy Aceves | 472 |
| 17 | Martha E. Mata Loera | 469 |
| 55 | Karina Alatorre | 445 |
| 45 | Trino | 315 |
| 44 | Jis | 309 |
| 43 | Falcón | 137 |

```text
CRÍTICO: el 64.5 % del corpus (23 663 de 36 666) está atribuido a UNA cuenta
cuyo nombre mostrado es la institución, no una persona.
```

```text
CONSECUENCIA PARA D-14: post_author NO es una fuente fiable de crédito
editorial para la mayoría del corpus.
```

La decisión del responsable (vocabulario de créditos, no cuentas) sigue siendo
correcta, pero `field_autor_texto` quedaría con el valor "Universidad de
Guadalajara" en 23 663 contenidos. El autor real de esas notas está, con toda
probabilidad, **dentro del texto del artículo**, que es justo lo que
`reports/audit/postmeta-keys.md` ya anticipaba al no encontrar ninguna clave de
crédito en los metadatos.

Nota aparte: Trino (315), Jis (309) y Falcón (137) son cartonistas. Su
contenido es gráfico, lo que lo liga a `foto1` y a la media diferida.

### Nombres duplicados

```text
CONFIRMADO: "Universidad de Guadalajara" aparece en 2 usuarios (ids 1 y 86).
CONFIRMADO: "Miriam Mairena" aparece en 2 usuarios.
```

Al crear términos del vocabulario, ambos casos requieren decisión: ¿un término
o dos?

## 9. URLs

```text
CONFIRMADO: permalink_structure = /%postname%/
CONFIRMADO: siteurl = http://www.gaceta.udg.mx/   (HTTP, no HTTPS)
CONFIRMADO: blog_charset = UTF-8
```

```text
37 170 filas publicadas
   740 sin slug
35 788 slugs distintos
```

```text
CONFIRMADO: hay del orden de 1 382 slugs duplicados entre contenido publicado.
```

Los peores casos:

| Slug | Veces |
|---|---:|
| `Enfoques` | **108** |
| `Gaseta-fugaz` | 52 |
| `¡A-moverse!` | 16 |
| `Breves` | 14 |

```text
GRAVE: 108 contenidos publicados reclaman la misma ruta /Enfoques/.
```

En WordPress sólo uno es alcanzable por esa URL; los otros 107 son
**inalcanzables por slug**. Además, esos slugs tienen mayúsculas y signos de
admiración, lo que no es propio de WordPress: es otra huella de la migración
anterior.

```text
DECISIÓN PENDIENTE: ¿qué URL reciben esos contenidos en Drupal? No se puede
reproducir una colisión. Afecta a unos 1 382 contenidos.
```

Y las 740 filas sin slug necesitan uno generado, lo que **cambia su URL**.

## 10. Taxonomías

| Taxonomía | Términos | Usos | Jerarquía |
|---|---:|---:|---|
| `post_tag` | 7 724 | 24 932 | plana |
| `category` | **129** | **43 580** | **103 con padre, 26 raíz** |
| `tribe_events_cat` | 9 | 0 | plana |
| `elementor_library_type` | 3 | 67 | plana |
| `monsterinsights_note_category` | 3 | 0 | plana |
| `post_format` | 2 | 9 | plana |
| `nav_menu` | 1 | 17 | plana |

```text
CONFIRMADO: category SÍ es jerárquica (129 términos, 103 con padre).
```

Es la arquitectura **actual**, la que produce el menú del sitio. Y convive con
las columnas `seccion`/`subseccion`, que son la **histórica**.

```text
Los dos mecanismos coexisten:
  dc8_posts.term_id              16 832 entradas
  dc8_term_relationships         30 692 objetos, 70 257 relaciones
```

```text
DECISIÓN PENDIENTE: ¿cómo se reconcilian las dos arquitecturas en Drupal?
```

Tres opciones, todas con coste: conservar ambas como vocabularios separados
(fiel pero confuso), mapear la histórica a la actual (pierde granularidad), o
conservar la actual y la histórica sólo como metadato. No se decide aquí.

## 11. Comentarios: la cifra de §28, confirmada exactamente

| `comment_approved` | Total |
|---|---:|
| `0` (pendientes) | **6 346** |
| `1` (aprobados) | **40** |
| `spam` | 2 |
| **TOTAL** | **6 388** |

```text
CONFIRMADO: los 6 346 de CLAUDE.md §28 son exactos.
CONFIRMADO: sólo 40 comentarios aprobados en toda la historia del sitio.
CONFIRMADO: 0 comentarios huérfanos. Integridad referencial intacta.
```

Y el análisis de bytes de `reports/audit/encoding-audit.md` ya mostró que esa
cola de 6 346 es **spam en inglés** (`the ` 16 615 veces frente a ` de ` 76;
`casino` 98; `viagra` 16).

```text
CONCLUSIÓN: migrar "los comentarios" significa migrar 40 comentarios reales.
```

Eso cambia por completo el peso de §28. La decisión sobre los 6 346 pendientes
pasa de ser un problema de volumen a una limpieza trivial, pero **sigue siendo
una decisión**: no se descartan sin autorización.

El plugin `disable-comments-rb` está **activo**, lo que explica que no haya
comentarios nuevos.

## 12. Plugins realmente activos: 28 de 38

```text
CONFIRMADO: 28 plugins activos, frente a 38 directorios en disco.
```

Activos: `classic-editor`, `contact-form-7`, `disable-comments-rb`,
`elementor`, `elementor-pro`, `google-analytics-for-wordpress`,
`google-site-kit`, `jetpack`, `loco-translate`, `pdf-embedder`,
`post-duplicator`, `post-views-counter`, `query-monitor`, `search-filter`,
`smart-slider-3`, `tabs`, `td-cloud-library`, `td-composer`,
`td-social-counter`, `td-standard-pack`, `templately`, `the-events-calendar`,
`wordpress-seo`, `wp-downgrade`, `wp-mail-smtp`, `wp-super-cache`,
`wpforms-lite`, `youtube-embed-plus`.

```text
NO activos, aunque estén en disco: revslider (Slider Revolution),
wp-rss-aggregator, popup-maker, essential-addons-for-elementor-lite, akismet,
all-in-one-wp-migration, optinmonster, enable-jquery-migrate-helper,
mojo-marketplace-wp-plugin-
```

Esto resuelve tres incógnitas abiertas:

```text
RESUELTO: Slider Revolution NO está activo. Sus 12 tablas son residuo
          histórico. No hay funcionalidad que migrar.
RESUELTO: WP RSS Aggregator NO está activo (y tiene sólo 2 fuentes
          configuradas). Se descarta como origen del mojibake.
RESUELTO: Essential Addons for Elementor NO está activo. El riesgo de widgets
          de terceros dentro del JSON de Elementor es menor de lo temido.
```

```text
CONFIRMADO: template = stylesheet = Newspaper. §19 confirmado.
```

## 13. Adjuntos: un hallazgo que reorienta la estrategia de media

Por tipo MIME:

| MIME | Total |
|---|---:|
| `image/jpeg` | 40 644 |
| `image/png` | 4 259 |
| `application/pdf` | **3 033** |
| `video/mp4` | 217 |
| `audio/mpeg` | 67 |
| `image/webp` | 66 |
| `image/gif` | 32 |
| `image/heic` | 22 |
| vacío | 11 |
| `audio/wav` | 7 |
| `video/quicktime` | 5 |
| `image/tiff` | 2 |
| `.docx` | 2 |

```text
48 369 adjuntos: 45 025 imágenes, 3 033 PDF, 222 vídeos, 74 audios.
```

Los 3 033 PDF son significativos: el plugin `pdf-embedder` está activo, así que
hay PDF incrustados en el contenido. Probablemente son las ediciones impresas
de la Gaceta, lo que los convierte en **patrimonio documental**, no en adjuntos
secundarios.

### El hallazgo: no hay adjuntos anteriores a 2019

| Año | Adjuntos |
|---|---:|
| 2019 | 579 |
| 2020 | **14 174** |
| 2021 | 4 125 |
| 2022 | 6 265 |
| 2023 | 6 677 |
| 2024 | 6 248 |
| 2025 | 6 653 |
| 2026 | 3 648 |

```text
CONFIRMADO: el sistema de adjuntos empieza en 2019, pero el contenido empieza
en 1995.
```

```text
CONSECUENCIA: las imágenes del contenido anterior a 2019 NO están en el
sistema de adjuntos. Están en la columna foto1 (16 102 filas), con rutas
heredadas del sistema anterior.
```

Esto reorienta `docs/media-strategy.md`: el manifiesto de media necesita **dos
fuentes distintas y con formatos distintos de ruta**, y `foto1` cubre
precisamente el contenido histórico, que es el más difícil de recuperar.

El pico de 14 174 en 2020 sugiere además una carga masiva, probablemente la
migración al tema Newspaper o una reindexación.

### Valor práctico para D-15

La tabla por año convierte la petición a producción en algo concreto y
troceable: en lugar de "los 84 GB", se puede pedir `uploads/2020/` (14 174
archivos) o avanzar año por año.

## 14. Revisiones e integridad

```text
CONFIRMADO: 77 307 revisiones sobre 19 400 contenidos distintos.
CONFIRMADO: 0 comentarios huérfanos.
CONFIRMADO: 0 registros con post_parent apuntando a un ID inexistente.
```

```text
La integridad referencial de dc8_posts está intacta.
```

Es una buena noticia poco frecuente en un sistema legado, y simplifica la
migración: no hay que decidir qué hacer con registros colgados.

```text
DECISIÓN PENDIENTE: ¿se migran las 77 307 revisiones? Drupal las soporta, pero
multiplicaría por 3 el volumen. No aportan al sitio público.
```

## 15. Datos SEO de Yoast en `dc8_options`

```text
CONFIRMADO presentes: wpseo_titles (10 644 bytes), wpseo (5 429),
                      wpseo_taxonomy_meta (2 112), wpseo_social (651)
```

`wpseo_titles` es la pieza clave que `docs/seo-strategy.md` identificaba:
contiene las plantillas de título y descripción que generan el SEO del 99.8 %
del sitio. Ya está disponible para extraer.

## Qué sigue

```text
- [ ] Etapa 2 de D-02: cargar dc8_postmeta (Elementor, media, slugs, Yoast)
- [ ] PRIMERA consulta obligatoria: cuántos contenidos tienen _elementor_data
      con post_content vacío. Dimensiona D-03.
- [ ] Extraer y traducir las plantillas de wpseo_titles
- [ ] Decidir las variantes de mayúsculas en secciones
- [ ] Decidir las 1 382 colisiones de slug
- [ ] Decidir el destino de las 77 307 revisiones
- [ ] Decidir el destino de los 6 346 comentarios en moderación
- [ ] Decidir el tratamiento de los 6 registros con fecha cero
- [ ] Preguntar a la redacción por el hueco 1996-2004
```

## Nota sobre una cifra previa corregida

`reports/audit/postmeta-keys.md` estimó ~47 896 contenidos con
`_elementor_data`, contando apariciones de la cadena en el dump. Con los
conteos reales a la vista:

```text
post 36 666 + page 185 + elementor_library 69 = 36 920 contenidos posibles
```

```text
CONFLICTO: 47 896 apariciones frente a 36 920 contenidos posibles.
```

La explicación más probable es que las revisiones también guarden copias de
`_elementor_data`, o que la cadena aparezca dentro de otros valores. Era
exactamente el riesgo que ese reporte advertía al declararse orden de magnitud
y no cifra. **Se resolverá en la etapa 2 con `GROUP BY meta_key`.**
