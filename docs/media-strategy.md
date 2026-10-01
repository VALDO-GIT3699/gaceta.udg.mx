# Estrategia de media

Fecha: 2026-09-30
Decisión que la origina: `docs/decisiones.md`, **D-15**
Fase del roadmap: FASE 3 y FASE 6

## El problema, planteado con precisión

```text
CONFIRMADO: wp-content/uploads NO existe en la copia local. 0 bytes.
CONFIRMADO: producción reporta ~84.23 GB de uploads.
CONFIRMADO: el responsable no los copió por falta de espacio en la máquina.
CONFIRMADO: el responsable decide continuar sin las imágenes.
CONFIRMADO: la condición expresa es que se tiene que salvar TODA la información.
```

Esas dos últimas líneas parecen contradecirse. No lo hacen, y la distinción es
la base de toda esta estrategia:

```text
Los ARCHIVOS binarios       -> están en producción. No los tenemos.
La INFORMACIÓN sobre ellos  -> está en el dump. Sí la tenemos, íntegra.
```

Todo lo que *se sabe* de una imagen de Gaceta no está dentro del JPEG: está en
la base de datos. El nombre del archivo, su ruta, su URL pública, el tipo MIME,
las dimensiones, los derivados generados, el texto alternativo, el pie de foto,
el título, la descripción, la fecha de subida, quién la subió y, lo más
importante, **qué contenidos la usan y en qué lugar del texto**.

```text
CONCLUSIÓN: no migrar los binarios no implica perder información, siempre que
se preserve íntegra cada referencia y cada metadato.
```

Lo que sí implica, y se mantiene sin matices:

```text
La migración de media NO puede declararse completa. CLAUDE.md §33 y §48.
```

## Las dos etapas

### Etapa 1 — ahora, sin archivos

Construir el **manifiesto de media**: un inventario completo y auditable de
cada archivo que el sitio referencia, extraído de la base de datos.

Para cada adjunto se registra:

```text
wp_attachment_id        dc8_posts.ID del post_type = attachment
post_title              título del adjunto
post_excerpt            pie de foto (caption)
post_content            descripción
post_date               fecha de subida
post_author             quién lo subió
guid                    URL original completa
_wp_attached_file       ruta relativa dentro de uploads
_wp_attachment_metadata dimensiones y lista de derivados generados
_wp_attachment_image_alt texto alternativo
post_mime_type          tipo MIME
ruta_destino_drupal     ruta calculada de forma determinista
estado                  PENDIENTE_DE_ARCHIVO
```

Y para cada **referencia** desde el contenido:

```text
wp_post_id              contenido que la referencia
tipo_de_referencia      imagen destacada | HTML del cuerpo | foto1 | Elementor | slider
contexto                atributo o clave exacta donde aparece
url_original            tal como figura en el origen
```

```text
IMPORTANTE: el manifiesto es un ENTREGABLE en sí mismo.
```

Tiene valor propio incluso si los archivos nunca llegan: es la lista exacta de
lo que hay que recuperar, y permite pedir a producción un subconjunto concreto
en lugar de los 84 GB completos.

### Etapa 2 — cuando haya acceso a los archivos

```text
Depositar los binarios en la ruta ya calculada.
Crear las entidades Media que los envuelven.
Verificar hash e integridad.
```

No hay que repetir la migración de contenido. Las rutas de destino se calculan
en la etapa 1 de forma determinista, así que las referencias del HTML ya
apuntan al sitio correcto desde el principio: lo único que falta es que el
archivo exista ahí.

```text
La estrategia admite entrega PARCIAL E INCREMENTAL. Cualquier subconjunto que
llegue (por años, por secciones, por lotes) es aprovechable sin rehacer nada.
```

## Por qué NO se crean entidades Media vacías en la etapa 1

Es la alternativa evidente y es un error. Una entidad `media` de Drupal
envuelve una entidad `file`, y una entidad `file` con un `uri` que no existe
en el disco produce:

```text
Image styles que fallan al generar derivados.
Errores en el log en cada render.
Entidades que parecen migradas y están vacías: el peor estado posible para
una auditoría, porque los conteos saldrían "correctos".
```

Ese último punto es el decisivo. CLAUDE.md §6 prohíbe marcar como terminado lo
que no lo está, y crear 20 000 entidades Media sin archivo haría que la FASE 13
reportara media migrada al 100 %. Sería un conteo verdadero y una conclusión
falsa.

```text
El manifiesto es honesto: cada línea dice PENDIENTE_DE_ARCHIVO.
```

## El puente temporal: URLs absolutas a producción

Hay una opción pragmática que conviene poner sobre la mesa con sus riesgos,
porque la decisión no es técnica.

```text
CONFIRMADO: el HTML del contenido contiene URLs absolutas del tipo
https://www.gaceta.udg.mx/wp-content/uploads/...
```

Si esas URLs se dejan intactas, **las imágenes se verán** en el Drupal nuevo,
servidas todavía por el servidor de producción.

Ventajas: el sitio es presentable y validable visualmente (FASE 14) desde el
primer día, sin tener los archivos.

Riesgos, que son serios:

```text
El día del cutover, producción deja de servir y TODAS las imágenes se rompen.
El sitio nuevo depende del viejo: no es autónomo.
Puede ocultar el problema y dar la falsa impresión de que la media está migrada.
Genera tráfico desde el sitio nuevo hacia producción.
```

```text
RECOMENDACIÓN: usar el puente SÓLO para la validación visual, y mantener en
paralelo la reescritura a rutas locales como el estado de destino real.
```

Técnicamente ambas cosas conviven: se migra el HTML con las rutas finales de
Drupal y se configura, sólo en el entorno de desarrollo, una regla que sirva
las imágenes ausentes desde producción como respaldo. Así el entorno de
validación muestra imágenes y el contenido migrado ya es el definitivo.

```text
DECISIÓN PENDIENTE: ¿se activa ese respaldo en desarrollo? Afecta a la FASE 14.
```

## Derivados: no crear una Media por miniatura

CLAUDE.md §25 ya fija el criterio y la auditoría lo respalda:

```text
imagen original -> Drupal Media -> Drupal Image Style
```

El origen generó múltiples tamaños del mismo archivo (`68x200`, `100x120`,
`120x150` y otros). Son **derivados**, no imágenes distintas.

```text
En el manifiesto: una línea por ORIGINAL, con la lista de sus derivados como
metadato, no como líneas independientes.
```

La fuente de verdad para distinguir original de derivado es
`_wp_attachment_metadata`, que contiene la lista de tamaños generados por
WordPress. No se decide por el patrón del nombre del archivo: eso daría falsos
positivos con imágenes cuyo nombre contenga dígitos que parezcan dimensiones.

```text
PENDIENTE: medir cuántos derivados hay por original. Requiere B-03.
```

Los `image styles` de Drupal se diseñarán para cubrir los tamaños que el tema
`drudg8b3` realmente necesita, no para reproducir los de WordPress.

## Hashes de integridad

CLAUDE.md §26 pide SHA-256 por archivo.

```text
IMPOSIBLE en la etapa 1: no hay archivos que hashear.
```

Queda explícitamente diferido a la etapa 2, donde cumple su función real:
verificar que el archivo depositado en Drupal es byte a byte el mismo que
estaba en producción. Calcularlo antes no tendría objeto.

```text
Lo que SÍ se puede verificar en la etapa 1: que cada referencia del contenido
apunte a una entrada del manifiesto, y que cada entrada del manifiesto tenga
al menos una referencia o quede marcada como huérfana.
```

Ese cruce detecta dos problemas sin necesidad de un solo archivo:

```text
Referencias rotas: el contenido apunta a un adjunto que no existe en la BD.
Adjuntos huérfanos: existen en la BD y ningún contenido los usa.
```

## Fuentes de referencias de media que hay que cubrir

No basta con `_wp_attached_file`. Las imágenes de Gaceta están referenciadas
desde sitios heterogéneos, y omitir uno es perder información:

| Fuente | Dónde | Estado |
|---|---|---|
| Imagen destacada | `_thumbnail_id` en postmeta | por auditar |
| **`foto1`** | columna propia de `dc8_posts` | **por auditar, prioritaria** |
| HTML del cuerpo | `<img src>` en `post_content` | por auditar |
| Enlaces a archivos | `<a href>` a PDF y otros | por auditar |
| Elementor | JSON de `_elementor_data` | por auditar (D-03) |
| Smart Slider 3 | `dc8_nextend2_*` | por auditar |
| Slider Revolution | `dc8_revslider_slides` | por auditar |
| Yoast Open Graph | `_yoast_wpseo_opengraph-image` | por auditar |
| Galerías | shortcodes en `post_content` | por auditar |

`foto1` es prioritaria porque es una columna **no estándar** de `dc8_posts`
(ver `docs/content-model.md`): WordPress no la conoce, así que ninguna
herramienta genérica de migración la tendrá en cuenta. Si se omite, se pierde
la imagen principal de todo el corpus histórico.

```text
RIESGO ESPECÍFICO: foto1 puede contener rutas con un formato distinto al de
_wp_attached_file, heredado del sistema anterior a WordPress (ver la hipótesis
de la segunda migración en docs/content-model.md).
```

## Qué pasa con la FASE 3 y la FASE 6

```text
FASE 3 (auditoría de media): se EJECUTA, sobre referencias en lugar de
archivos. Entregable: el manifiesto. Requiere B-03.

FASE 6 (migración de media): se ejecuta la parte de metadatos y referencias.
La creación de entidades Media con archivo queda DIFERIDA a la etapa 2.
```

En `MIGRATION_CONTRACT.md`, la FASE 6 no podrá marcarse `[x]` nunca mientras
no existan los archivos. Su estado correcto es `[~]` con la nota de
dependencia externa, y en los criterios de aceptación (CLAUDE.md §34) la
casilla de media conciliada permanece abierta.

```text
Eso no es un fallo del proyecto: es la consecuencia documentada de una
decisión informada del responsable.
```

## Lo que haría falta para cerrar la etapa 2

```text
- [ ] Acceso al árbol /wp-content/uploads de producción, completo o por lotes
- [ ] Espacio en disco suficiente en el destino
- [ ] Decisión sobre migrar o no los derivados ya generados
- [ ] Verificación de hashes
- [ ] Revalidación de las referencias del HTML
```

El manifiesto de la etapa 1 convierte la primera línea en una petición
concreta: una lista de archivos, no "los 84 GB".


---

## Cómo se mueven los 82 GB (actualización 2026-10-01)

El responsable informa de que ha recibido **datos parciales de uploads, 82 GB**,
descargados en el equipo de su trabajo, y pregunta qué hacer con ellos.

```text
Los 82 GB NO tienen que pasar por el equipo de desarrollo ni por el
repositorio. Nunca.
```

### El modelo, en dos fases

```text
FASE A — AHORA. Sólo viaja un listado de texto.

  equipo del trabajo
      |  tools/inventario-uploads.ps1
      |  (no copia, no mueve, no abre archivos: lee nombres y tamanos)
      v
  uploads-inventario.txt        unos pocos MB
      |
      v
  equipo de desarrollo
      |  tools/cruzar-inventario-uploads.php
      v
  cobertura: qué existe, qué falta, y dónde está cada foto1
```

```text
FASE B — AL DESPLEGAR. Los archivos se copian UNA vez, directo.

  equipo del trabajo  ----------->  servidor de Drupal
       (82 GB)                      sites/default/files/migrado/

  Sin pasar por el equipo de desarrollo. Sin renombrar nada. Preservando la
  estructura de carpetas por año tal como está.
```

### Por qué no hay que tocar las fotos

Ésta era la duda de fondo del responsable, y la respuesta es que **no hay que
reorganizar nada**.

Las rutas de destino del manifiesto se calcularon de forma determinista a
partir de la ruta de origen:

```text
_wp_attached_file:      2015/06/foto.jpg
ruta de destino:        public://migrado/2015/06/foto.jpg
se sirve desde:         sites/default/files/migrado/2015/06/foto.jpg
```

```text
Copiar el árbol tal cual, una vez, al directorio de archivos de Drupal es
TODO lo que hay que hacer. Las referencias ya apuntan ahí.
```

Y el contenido migrado no depende de que eso ocurra para existir: las 36 666
noticias se migran igual, con sus referencias ya escritas. Cuando los archivos
aparezcan en esa ruta, las imágenes simplemente empiezan a verse.

### Que sean parciales no es un problema

```text
El inventario dirá exactamente cuáles de las 48 358 referencias están
cubiertas y cuáles no, con cobertura desglosada por carpeta y por año.
```

Eso convierte "nos faltan fotos" en una lista concreta: se puede pedir
únicamente los años que falten, en lugar de volver a mover 82 GB.

### Lo que el inventario resuelve de D-17

De las 16 102 referencias de `foto1`, 8 501 no tenían ruta conocida porque la
columna sólo guarda el nombre del archivo. Con el inventario se buscan esos
nombres en el árbol real:

```text
nombre único en el árbol  ->  RESUELTA, ruta conocida
nombre en varias carpetas ->  AMBIGUA, se desempata por el año del contenido
nombre que no aparece     ->  NO ESTÁ, y queda documentado
```

El desempate por año es fiable porque WordPress organiza los uploads por año y
mes, y la fecha del contenido es conocida.
