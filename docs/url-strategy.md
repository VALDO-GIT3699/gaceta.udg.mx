# Estrategia de URLs

Fecha: 2026-09-30
Fase del roadmap: FASE 10
Requisito que atiende: CLAUDE.md §24 (requisito crítico)

## Punto de partida

```text
CONFIRMADO: WordPress usa permalinks /%postname%/
CONFIRMADO: el sitio es https://www.gaceta.udg.mx
CONFIRMADO: Drupal tiene pathauto instalado; redirect NO está instalado.
```

La estructura `/%postname%/` es una ventaja grande: la URL depende sólo del
slug, no de la fecha ni de la categoría. Eso permite reproducirla en Drupal
con un patrón de pathauto trivial y **conservar la URL idéntica** para la
mayoría del contenido.

```text
OBJETIVO: que la inmensa mayoría de las URLs no cambie en absoluto.
```

Cuando la URL no cambia, no hace falta redirección, no se pierde
posicionamiento y los enlaces externos siguen funcionando. Es el mejor
resultado posible y es alcanzable aquí.

## HALLAZGO CRÍTICO — 7 811 URLs históricas adicionales

```text
CONFIRMADO: _wp_old_slug aparece 7 811 veces en el dump.
CONFIRMADO: _wp_desired_post_slug aparece 12 veces.
Evidencia: reports/audit/postmeta-keys.md
```

WordPress guarda el slug anterior cada vez que el de una entrada cambia, y
**sirve una redirección 301 desde él automáticamente**. Es un mecanismo
silencioso: no aparece en ninguna pantalla de administración.

```text
Son 7 811 URLs reales, que hoy funcionan, están indexadas en buscadores y
pueden estar enlazadas desde fuera del sitio.
```

### Por qué esto es grave

Nadie lo había inventariado. Si la migración sólo reproduce el slug **actual**
de cada contenido:

```text
Las 7 811 rutas antiguas devolverían 404 el día del cutover.
Se perderían sin que ningún conteo lo detectara.
```

CLAUDE.md §24 lo prohíbe de forma explícita: *"Nunca borres URLs históricas
simplemente porque Drupal puede generar otras"* y *"No existen pérdidas
silenciosas de rutas"*.

```text
Este hallazgo convierte el módulo redirect de Drupal en OBLIGATORIO, no
opcional.
```

Y `redirect` **no está instalado ni presente en disco**: hay que incorporarlo
con Composer, lo que altera el template institucional y requiere aprobación
(ver `docs/content-model.md`).

## Las tres clases de URL que hay que tratar

### Clase 1 — URL actual del contenido

```text
Origen:  dc8_posts.post_name
Destino: alias de Drupal, idéntico
Acción:  patrón de pathauto que reproduzca /%postname%/
```

No requiere redirección si el slug se conserva literal. El riesgo está en que
pathauto **re-genere** el alias aplicando su propia transliteración y produzca
algo distinto del slug original.

```text
CRÍTICO: el alias debe tomarse del post_name de origen, NO generarse desde el
título con pathauto.
```

Generar desde el título parece equivalente y no lo es: los títulos con acentos,
signos o mayúsculas pueden transliterarse de forma distinta a como WordPress lo
hizo años atrás. La única fuente de verdad del alias es `post_name`.

### Clase 2 — Slugs históricos

```text
Origen:  _wp_postmeta -> _wp_old_slug  (7 811)
Destino: entidad redirect 301 hacia el alias actual
```

Cada `_wp_old_slug` genera una redirección. Hay que tener en cuenta que un
mismo contenido puede tener **varios** slugs antiguos acumulados.

```text
PENDIENTE: contar cuántos contenidos distintos acumulan los 7 811 slugs, y
detectar colisiones (dos contenidos que en algún momento tuvieron el mismo
slug). Requiere B-03.
```

Las colisiones son el caso difícil: si dos contenidos reclaman la misma ruta
antigua, hay que decidir a cuál apunta. No se resuelve por iniciativa propia.

### Clase 3 — URLs que no son de contenido

```text
Archivos de taxonomía:   /category/<slug>/, /tag/<slug>/
Archivos por fecha:      /2019/05/
Páginas de autor:        /author/<slug>/
Feeds:                   /feed/, /<slug>/feed/
Buscador:                /?s=...
Paginación:              /page/2/
Adjuntos:                /<slug>/<nombre-archivo>/
```

Cada familia necesita una decisión propia. Dos merecen atención particular:

```text
Las páginas de autor (/author/<slug>/) chocan con la decisión D-14.
```

Al no crear usuarios de Drupal, la ruta `/author/x/` de WordPress no tiene
equivalente automático. Si esas URLs están indexadas, hay que decidir si se
reproducen como páginas de término del vocabulario `credito_editorial` (lo cual
es perfectamente posible y además deseable) o si se redirigen a otro sitio.

```text
RECOMENDACIÓN: reproducirlas con una vista de Drupal sobre el término del
vocabulario de créditos, conservando el slug. Así la decisión de seguridad de
D-14 no cuesta ninguna URL.
```

```text
Los feeds RSS son contenido funcional, no decorativo.
```

Hay lectores y agregadores suscritos a `/feed/`. Drupal puede servir feeds con
Views. Debe inventariarse qué feeds existen hoy.

## El mapa de trazabilidad

CLAUDE.md §24 y §35 exigen una matriz. Campos mínimos:

```text
wp_post_id
wp_post_type
wp_post_status
old_slug
old_url
es_slug_historico      (sí/no, para distinguir clase 1 de clase 2)
drupal_entity_type
drupal_entity_id
new_url
tipo_de_resolucion     (idéntica | redirect 301 | sin equivalente)
migration_batch
migration_status
validation_status
validation_date
notas
```

```text
La columna "tipo_de_resolucion" es la que permite responder a la pregunta que
importa: cuántas URLs cambiaron y cuántas se perdieron.
```

El objetivo declarado es que la tercera categoría, "sin equivalente", quede
**vacía**, y que cualquier entrada que caiga ahí esté justificada por escrito.

## Orden de trabajo propuesto

```text
PASO 1  Extraer todos los post_name actuales (clase 1).
PASO 2  Extraer los 7 811 _wp_old_slug con su post_id (clase 2).
PASO 3  Detectar colisiones entre ambas clases.
PASO 4  Inventariar las URLs de clase 3 que existan y estén indexadas.
PASO 5  Configurar pathauto para reproducir /%postname%/ tomando post_name.
PASO 6  Instalar redirect y generar las 301 de clase 2.
PASO 7  Validar con peticiones reales contra el Drupal local.
PASO 8  Comparar contra producción en modo lectura (§46).
```

Los pasos 1 a 4 requieren B-03. Los pasos 5 y 6 requieren aprobación para
instalar `redirect`.

## Verificación

La FASE 10 no se cierra sin una comprobación ejecutable:

```text
Para cada URL del mapa, una petición al Drupal local debe devolver:
  200 si la resolución es "idéntica"
  301 hacia el destino correcto si la resolución es "redirect"
y NINGUNA debe devolver 404.
```

Eso es un script, no una revisión manual, y debe vivir en `tools/`.

## Riesgos

1. **Transliteración de pathauto.** Si se genera el alias desde el título en
   lugar de tomar `post_name`, las URLs cambian de forma masiva y silenciosa.
   Es el riesgo más probable y el más fácil de evitar.
2. **Colisiones de slugs históricos.** Cantidad DESCONOCIDA.
3. **`redirect` no instalado.** Bloquea la clase 2 por completo.
4. **URLs de adjuntos.** Con la media diferida (D-15), las rutas de adjunto
   (`/<slug>/<archivo>/`) quedan sin destino hasta la etapa 2 de
   `docs/media-strategy.md`.
5. **Sitemap y robots.** Yoast genera el sitemap actual. Drupal necesitará su
   propio mecanismo, y el `robots.txt` del template apunta a rutas de Drupal,
   no de WordPress.
