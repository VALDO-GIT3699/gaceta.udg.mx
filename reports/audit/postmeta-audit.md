# Auditoría de `dc8_postmeta` — etapa 2

Fecha: 2026-09-30
Fase del roadmap: FASE 2 (cierre), FASE 3, FASE 10
Fuente: SQL real contra `gaceta_auditoria`
Reproducible con: `tools/audit-queries-postmeta.sql`

```text
CONFIRMADO: dc8_postmeta cargada sin un solo error.
            720 670 filas, 2 103 MB de datos.
```

Con esto la base de auditoría está **completa** salvo `dc8_post_views`
(242 MB, estadísticas de visitas de un plugin, no contenido editorial).

## EL RESULTADO PRINCIPAL: el riesgo de Elementor se desploma

CLAUDE.md §18 marca Elementor como riesgo crítico, y
`reports/audit/elementor-audit.md` lo describía como el mayor riesgo técnico
del proyecto, con hasta 47 900 contenidos potencialmente afectados.

```text
La consulta decisiva cambia el panorama por completo.
```

De las 47 896 filas de `_elementor_data`:

| Clase | Filas |
|---|---:|
| **revisiones** | **45 424** |
| contenido real | **2 472** |

```text
El 94.8 % de los datos de Elementor está en REVISIONES, no en contenido.
```

Y del contenido real, cruzando con el estado de `post_content`:

| `post_type` | Estado | Con Elementor | `post_content` vacío | Casi vacío | Con texto |
|---|---|---:|---:|---:|---:|
| `post` | publish | 2 359 | **0** | **0** | **2 359** |
| `elementor_library` | publish | 68 | 5 | 0 | 63 |
| `page` | publish | 14 | 0 | 0 | 14 |
| `post` | draft | 13 | 0 | 0 | 13 |
| `page` | draft | 7 | 1 | 0 | 6 |
| `post` | trash | 6 | 0 | 0 | 6 |
| `page` | private | 5 | 1 | 0 | 4 |

```text
CONFIRMADO: CERO entradas publicadas tienen post_content vacío teniendo datos
            de Elementor.
CONFIRMADO: sólo 2 359 de las 36 627 entradas publicadas usan Elementor: el
            6.4 % del corpus.
```

### Qué significa

El escenario catastrófico que §18 advertía —que `post_content` no contenga el
contenido visible y haya que extraerlo del JSON para decenas de miles de
registros— **no ocurre en este sitio**.

```text
post_content es una fuente fiable para el corpus completo.
```

Elementor se usó para **maquetar** 2 359 entradas, pero el texto siguió
viviendo en `post_content`. Los 5 `elementor_library` con contenido vacío son
plantillas, no contenido editorial: ahí es lo esperable.

### La reserva que hay que mantener

```text
CUIDADO: "post_content tiene más de 50 caracteres" NO demuestra que esté
COMPLETO.
```

Podría ser una versión degradada o parcial de lo que Elementor renderiza. Lo
que queda descartado es la pérdida **total**; la pérdida **parcial** en esos
2 359 registros sigue sin medir.

```text
PENDIENTE: comparar una muestra de los 2 359 contra cómo los muestra
producción (§46, sólo lectura). Es una muestra manejable, no un replanteo del
proyecto.
```

### Volumen

```text
_elementor_data: 47 896 filas, 1 286.5 MB
                 media 28 165 bytes, máximo 450 978 bytes
                 21 filas vacías o con lista vacía
```

Es el mayor consumidor de la base, pero el 94.8 % es de revisiones. Si no se
migran las revisiones (decisión abierta), el volumen real a tratar baja a unos
**66 MB**.

## HALLAZGO: 573 MB de basura de presentación

```text
tdc_history: 168 filas, 573.4 MB
             media 3.58 MB por fila, máximo 14.2 MB
```

Es el historial de deshacer de tagDiv Composer. **No es contenido**: es estado
interno del editor visual del tema Newspaper, que CLAUDE.md §19 clasifica como
presentación.

```text
168 filas ocupan el 27 % de dc8_postmeta.
```

No se migra. Se documenta porque explica una parte importante del tamaño del
dump y porque conviene no confundirlo con contenido al calcular volúmenes.

Otras claves de tagDiv en la misma categoría: `tdc_content` (174 filas,
4.5 MB), `td_post_theme_settings` (8 333), `tdc_google_fonts` (13 009),
`tdc_icon_fonts` (15 196).

## URLs históricas: las 7 811 confirmadas

```text
CONFIRMADO: 7 811 slugs históricos
CONFIRMADO: sobre 7 789 contenidos distintos
CONFIRMADO: 7 626 slugs distintos
```

Distribución:

```text
7 769 contenidos con 1 slug histórico
   18 contenidos con 2
    2 contenidos con 3
```

### 185 colisiones que exigen decisión

Hay slugs históricos reclamados por **varios** contenidos:

| Slug histórico | Contenidos |
|---|---:|
| `carton-trino-313-copy-3` | **16** |
| `informe-actividades-sems2022-copy` | 14 |
| `Informes-Red-Universitaria-2018` | 14 |
| `Ruth-Padilla-Muntilde;oz` | 6 |
| `La-Red-en-las-regiones` | 5 |
| `Cine-internacional` | 4 |

```text
No se puede crear una redirección 301 desde una URL hacia varios destinos.
```

Dos observaciones sobre la calidad de estos datos:

```text
HALLAZGO: los sufijos "-copy" vienen del plugin Post Duplicator, que está
ACTIVO. Son artefactos de duplicar entradas, no URLs editoriales.
```

```text
HALLAZGO GRAVE: "Ruth-Padilla-Muntilde;oz" contiene una entidad HTML rota.
```

Debería ser `Ruth-Padilla-Muñoz` (que también existe como slug propio, con 3
contenidos). En algún punto `&ntilde;` se escribió literalmente en el slug y
perdió el `&`. Hay más casos: `Festin-de-los-muntilde;ecos`.

```text
Esto afecta a URLs REALES que hoy existen y están indexadas.
```

Y hay slugs con mayúsculas y acentos, impropios de WordPress:
`LA-CAíDA-DE-LOS-GIGANTES`, `A-TRAVÉS-DEL-ESPEJO`, `Yordi-Capó`,
`Comunicación-y-sociedad`. Otra huella de la migración anterior.

```text
DECISIÓN PENDIENTE: para las 185 colisiones, ¿a qué contenido apunta cada URL
histórica? Y para los slugs con entidades HTML roto, ¿se preserva la URL tal
cual (fiel pero fea) o se crea también la versión corregida?
```

Lo recomendable para el segundo caso es **preservar la rota y añadir la
corregida**: ambas resuelven, y no se pierde ninguna ruta indexada.

## Media: la base del manifiesto, y un problema nuevo

```text
CONFIRMADO: 48 358 rutas en _wp_attached_file
CONFIRMADO: 48 358 adjuntos distintos (uno por adjunto, sin duplicar)
CONFIRMADO: 48 332 rutas distintas -> 26 rutas repetidas
CONFIRMADO: 42 003 contenidos con imagen destacada (_thumbnail_id)
```

### Las rutas sí cubren años antiguos

```text
2025  6 398      2021  4 126      2012  1 194      2018    246
2023  6 379      2026  3 575      2011  1 087      2007    175
2022  6 253      2019  1 474      2017    307      2013    140
2020  6 123      2015  1 428      2010    293
2024  6 056      2008  1 229      2009  1 199
                 slider3  480     slider2  145
```

Esto **matiza** un hallazgo anterior. `reports/audit/content-counts.md` observó
que no hay adjuntos con `post_date` anterior a 2019, y concluyó que las
imágenes históricas no estaban en el sistema de adjuntos.

```text
CORRECCIÓN: las RUTAS sí llegan hasta 2007. Lo que es de 2019 en adelante es
la fecha de creación del registro de adjunto, no la del archivo.
```

Es decir: la migración anterior creó los registros de adjunto en 2019-2020
apuntando a archivos que ya estaban en carpetas de años anteriores. El pico de
14 174 adjuntos con `post_date` en 2020 es esa importación masiva.

Las carpetas `slider3` (480) y `slider2` (145) están fuera de la estructura por
año: son media de Smart Slider 3.

### PROBLEMA NUEVO: `foto1` no tiene ruta

```text
CONFIRMADO: 16 102 valores en foto1
CONFIRMADO: 0 contienen una barra "/"
CONFIRMADO: los 16 102 son SÓLO EL NOMBRE del archivo
```

```text
GRAVE para docs/media-strategy.md: de esas 16 102 imágenes no se sabe en qué
carpeta están.
```

`_wp_attached_file` da rutas como `2015/06/archivo.jpg`. `foto1` da sólo
`archivo.jpg`. Sin el directorio, el manifiesto no puede calcular la ruta de
destino de forma determinista para esas 16 102 referencias.

Vías posibles, por orden de preferencia:

```text
1. Cruzar el nombre de foto1 contra los nombres de _wp_attached_file. Si cada
   nombre es único en el árbol, la ruta queda resuelta sin ambigüedad.
2. Buscar el nombre dentro del HTML de post_content del mismo registro, donde
   la etiqueta img suele llevar la URL completa.
3. Pedir a producción un listado recursivo de nombres de archivo, que es
   barato y no requiere transferir los 84 GB.
```

### Resultado: las vías 1 y 2 NO bastan

```text
Vía 1 — cruzar el nombre contra las rutas de _wp_attached_file:
  resueltos de forma única   7 527  (46.9 %)
  ambiguos                      37
  SIN NINGUNA COINCIDENCIA   8 501  (52.9 %)

Vía 2 — buscar el nombre dentro del HTML de post_content:
  CONFIRMADO: "893024.jpg" aparece 0 veces en todo post_content.
  CONFIRMADO: los registros de ejemplo no contienen ninguna etiqueta <img>.
```

```text
CONFIRMADO: 15 646 de 16 102 valores de foto1 (97.2 %) son nombres puramente
numéricos del tipo 893024.jpg, heredados del sistema anterior.
CONFIRMADO: se concentran entre 2008 y 2019, con 1 000 a 1 550 por año.
```

```text
Para unas 8 500 imágenes la ÚNICA información que existe es un nombre de
archivo numérico. Ni carpeta, ni URL, ni registro de adjunto, ni referencia en
el HTML.
```

Queda sólo la vía 3, y pasa a ser prioritaria:

```text
RECOMENDACIÓN: pedir a producción un listado recursivo de /wp-content/uploads
(rutas, nombres y tamaños) en un archivo de texto.
```

Es la petición más barata y de mayor rendimiento del proyecto: no transfiere
archivos, no modifica producción, resuelve D-17 y además permite verificar las
48 358 rutas ya conocidas sin mover un solo byte. Registrado como **D-17**.

### Observación colateral: el contenido antiguo usa entidades HTML

En los registros de muestra de 1995 el texto aparece como
`Tonal&aacute;`, `categor&iacute;as`, `Ren&eacute;`.

```text
CONFIRMADO: el contenido histórico almacena los acentos como entidades HTML,
no como caracteres UTF-8.
```

No requiere acción: las entidades se renderizan igual en Drupal. Pero explica
por qué esos registros aportan pocos bytes acentuados al análisis de encoding,
y hay que tenerlo en cuenta en cualquier búsqueda de texto sobre el corpus
antiguo: buscar "Tonalá" no encontrará "Tonal&aacute;".

### Accesibilidad: el dato definitivo

```text
CONFIRMADO: 661 adjuntos tienen la clave de texto alternativo
CONFIRMADO: de ellos, 645 tienen texto y 16 están vacíos
CONFIRMADO: sobre 48 358 adjuntos, el 1.33 % tiene alt
```

```text
46 713 imágenes sin texto alternativo.
```

CLAUDE.md FASE 12 dice "mantener y mejorar accesibilidad". Aquí no hay nada que
mantener: hay que decidir si se genera, se deja vacío o se marca para revisión
editorial. Es una decisión, no una tarea técnica.

## SEO por contenido: confirmado mínimo

| Clave | Filas |
|---|---:|
| `_yoast_wpseo_estimated-reading-time-minutes` | 6 858 |
| `_yoast_wpseo_wordproof_timestamp` | 6 848 |
| `_yoast_wpseo_primary_category` | **6 818** |
| `_yoast_wpseo_content_score` | 5 915 |
| `_yoast_wpseo_focuskw` | 920 |
| `_yoast_wpseo_linkdex` | 920 |
| `_yoast_wpseo_metadesc` | **910** |
| `_yoast_wpseo_title` | **2** |

Confirma lo que `docs/seo-strategy.md` anticipó: el SEO por contenido es
mínimo. Sólo **912 valores con auténtico valor editorial** (910 descripciones
y 2 títulos) más los 6 818 términos primarios.

`_yoast_wpseo_content_score` y `_yoast_wpseo_linkdex` son puntuaciones internas
de la herramienta de análisis de Yoast: no se emiten en el HTML y no se migran.

## Créditos editoriales: resultado definitivo para D-14

```text
CONFIRMADO: la consulta de claves con "autor", "foto", "credit" o "colabora"
            en dc8_postmeta devuelve CERO resultados.
```

```text
No existe ningún campo estructurado de crédito en ninguna parte de la base.
```

Queda cerrada la cuestión: la decisión del jefe (vocabulario de créditos) es el
modelo correcto, pero `field_fotografia` y `field_colaboradores` **no tienen
fuente de datos estructurada**.

### Lo que sí hay: créditos dentro del texto

Búsqueda de patrones en `post_content` de las 36 666 entradas:

| Patrón | Entradas |
|---|---:|
| `Fotografía:` | 1 614 |
| `Foto:` | 1 562 |
| `Texto:` | 436 |
| `Redacción` | 218 |
| `Fotos:` | 141 |
| `Por:` | 83 |
| **Al menos uno de ellos** | **3 514** |

```text
CONFIRMADO: 3 514 entradas (9.6 % del corpus) contienen algún patrón de
crédito en el cuerpo del texto.
```

```text
El 90.4 % del corpus no registra el crédito de fotografía en ninguna parte.
```

Eso acota la decisión. Extraer los créditos del texto **no** llenaría los
campos del vocabulario para el corpus: cubriría como máximo 3 514 entradas, y
con una tasa de error que habría que medir porque depende de reconocer prosa.

```text
RECOMENDACIÓN: no extraer automáticamente. El crédito permanece visible dentro
del artículo, donde está hoy, así que no se pierde información. Si la redacción
quiere los campos poblados, lo razonable es un proceso editorial asistido sobre
esas 3 514 entradas, no una transformación automática sobre 36 666.
```

## Integridad

```text
CONFIRMADO: 2 filas de postmeta huérfanas de 720 670.
```

Prácticamente intacta. Las 2 se documentan y se descartan con justificación en
la migración, no en silencio.

## Estado de los gates

```text
GATE FASE 2: SUPERADO.
```

Encoding demostrado y validado, conteos definitivos, metadatos inventariados,
Elementor dimensionado, Yoast inventariado, formularios y eventos confirmados
vacíos.

```text
GATE FASE 9 (migración de contenido): DESBLOQUEADO en lo que depende de D-03.
```

El riesgo que lo bloqueaba era que `post_content` no fuera fiable. Está
demostrado que lo es para el corpus publicado. Queda la verificación de muestra
sobre los 2 359 con Elementor, que es acotada.

## Decisiones que estos datos abren o cierran

```text
CIERRA  D-03 en su forma catastrófica: no hay 47 900 contenidos en riesgo,
        hay 2 359 que conviene verificar por muestra.
CIERRA  la cuestión de la fuente de créditos de D-14: no existe, y el texto
        sólo cubre el 9.6 %.
ABRE    las 185 colisiones de slug histórico.
ABRE    los slugs con entidades HTML roto (Muntilde;).
ABRE    la resolución de ruta de las 16 102 referencias de foto1.
ABRE    el tratamiento de 46 713 imágenes sin texto alternativo.
ABRE    si se migran las revisiones (45 424 de ellas con datos de Elementor).
```
