# Estrategia de SEO

Fecha: 2026-09-30
Fase del roadmap: FASE 10
Requisito que atiende: CLAUDE.md §23

## El hallazgo que redefine el alcance

CLAUDE.md §23 pide auditar y migrar títulos SEO, meta descriptions, canonical,
robots, Open Graph, Twitter Cards, sitemap, schema, focus keyphrase, primary
term, breadcrumbs y redirects. Leído sin datos, parece un trabajo enorme sobre
decenas de miles de contenidos.

La auditoría del dump dice otra cosa:

| Clave de Yoast | Apariciones |
|---|---:|
| `_yoast_wpseo_primary_category` | 6 818 |
| `_yoast_wpseo_estimated-reading-time-minutes` | 6 858 |
| `_yoast_wpseo_focuskw` | 920 |
| `_yoast_wpseo_metadesc` | **910** |
| `_yoast_wpseo_title` | **2** |
| `_yoast_wpseo_canonical` | **0** |
| `_yoast_wpseo_meta-robots-noindex` | **0** |
| `_yoast_wpseo_opengraph-title` | **0** |
| `_yoast_wpseo_opengraph-description` | **0** |
| `_yoast_wpseo_opengraph-image` | **0** |
| `_yoast_wpseo_twitter-title` | **0** |

Evidencia: `reports/audit/postmeta-keys.md`

```text
Sólo 2 contenidos tienen título SEO propio.
Sólo 910 tienen meta description propia.
NINGUNO tiene canonical, Open Graph o Twitter Card propios.
```

```text
CONCLUSIÓN: migrar "los datos SEO" no es copiar 48 000 registros. Es
reproducir unas pocas PLANTILLAS, más 912 excepciones manuales.
```

La redacción de Gaceta prácticamente no ha usado los campos SEO por entrada.
Los títulos y descripciones que hoy ve Google los **genera Yoast al vuelo** a
partir de plantillas con tokens, guardadas en `dc8_options`.

## Qué hay que migrar realmente

### 1. Las plantillas de Yoast — lo más importante

```text
Origen:  dc8_options, claves wpseo_titles y similares
Destino: configuración del módulo metatag de Drupal, con tokens equivalentes
```

Son el mecanismo que produce el SEO del 99.8 % del sitio. Si se reproducen bien,
el SEO se preserva para todo el corpus sin tocar un solo nodo.

```text
PENDIENTE: extraer esas opciones. Requiere la etapa 2 de B-03 (dc8_options ya
está cargada, así que esto es ejecutable ya).
```

Equivalencias de token a establecer:

| Yoast | Drupal / metatag |
|---|---|
| `%%title%%` | `[node:title]` |
| `%%sitename%%` | `[site:name]` |
| `%%sep%%` | literal, según lo que use el sitio |
| `%%excerpt%%` | `[node:summary]` |
| `%%primary_category%%` | el campo de sección (D-14 y modelo de contenido) |
| `%%date%%` | `[node:created:custom:...]` |

```text
ADVERTENCIA: la equivalencia no es automática. Cada token hay que verificarlo
comparando el <title> que produce producción contra el que produce Drupal,
sobre los mismos contenidos.
```

### 2. Los 912 valores manuales

```text
910 meta descriptions + 2 títulos -> valores por nodo en metatag
```

Volumen trivial. Se migran como campos de metatag en el nodo, y **prevalecen
sobre la plantilla**, igual que en Yoast.

### 3. `_yoast_wpseo_primary_category` — 6 818 registros

Es el dato más valioso del bloque y el más fácil de pasar por alto.

```text
Define el término PRIMARIO de cada contenido, algo que la taxonomía nativa de
WordPress no tiene.
```

Afecta a tres cosas visibles: el breadcrumb, la URL canónica y el token
`%%primary_category%%` de las plantillas. Si no se migra, los breadcrumbs de
6 818 contenidos cambian.

```text
Destino: se mapea al campo de sección del modelo (field_seccion), o a un campo
"término primario" si un contenido puede tener varias secciones.
```

Esa decisión depende de la consulta 10 y 11 de `tools/audit-queries.sql`, que
determinan si `seccion`/`subseccion` forman jerarquía.

### 4. Open Graph y Twitter Cards

```text
CERO valores propios en el origen.
```

No hay nada que migrar. Pero eso **no** significa que el sitio no los emita:
Yoast los genera desde las plantillas y la imagen destacada. En Drupal hay que
configurar `metatag_open_graph` y `metatag_twitter_cards` para producir lo
equivalente.

```text
RIESGO ligado a D-15: Open Graph necesita una imagen absoluta. Mientras la
media esté diferida, las tarjetas de redes sociales no tendrán imagen.
```

Es una consecuencia directa de la decisión de continuar sin los archivos, y
conviene que esté escrita: al compartir un enlace de Gaceta en redes, no
aparecerá la foto hasta la etapa 2 de `docs/media-strategy.md`.

### 5. Sitemap

```text
Origen:  Yoast genera /sitemap_index.xml y los sub-sitemaps
Destino: módulo simple_sitemap de Drupal, NO instalado
```

```text
PENDIENTE: decidir si se instala simple_sitemap. No está en el template.
```

La URL del sitemap debe conservarse o redirigirse, porque está declarada en
`robots.txt` y registrada en Google Search Console.

### 6. Schema / datos estructurados

Yoast emite JSON-LD (`Article`, `Organization`, `BreadcrumbList`).

```text
DESCONOCIDO: qué piezas de schema emite hoy el sitio y cuáles importan.
```

Se determina observando el HTML de producción (§46, sólo lectura), no
suponiendo. No se ha hecho todavía.

### 7. Robots

```text
CONFIRMADO: 0 contenidos con _yoast_wpseo_meta-robots-noindex.
```

Nada que excluir por contenido. El `robots.txt` del template Drupal existe pero
apunta a rutas de Drupal, no de WordPress: hay que revisarlo antes de publicar.

## Lo que NO se migra

```text
El plugin Yoast. §23 lo dice expresamente: se migran los DATOS con valor.
_yoast_wpseo_estimated-reading-time-minutes (6 858): es un cálculo derivado.
  Drupal lo puede recalcular. No es un dato de origen.
_yoast_wpseo_focuskw (920): es una nota de trabajo del redactor para la
  herramienta de análisis de Yoast. No se emite en el HTML y no afecta al
  posicionamiento. Migrarlo sólo tiene sentido si la redacción quiere
  conservar ese historial de trabajo.
dc8_yoast_indexable (30.2 MB) y dc8_yoast_seo_links (9.3 MB): son tablas de
  CACHÉ e índice interno que Yoast reconstruye. No son contenido.
```

```text
DECISIÓN MENOR PENDIENTE: ¿se conserva el focus keyphrase de 920 contenidos
como nota editorial, o se descarta?
```

## Dependencia bloqueante

```text
El módulo metatag NO está instalado ni presente en disco.
```

Igual que `redirect` (ver `docs/url-strategy.md`), hay que incorporarlo con
Composer, lo que modifica `composer.json` y `composer.lock` del template
institucional y requiere aprobación.

```text
Sin metatag no hay ninguna forma de cumplir §23.
```

## Relación con las URLs

El SEO y las URLs no se pueden separar. `docs/url-strategy.md` documenta el
hallazgo de los **7 811 slugs históricos**, que es el riesgo de SEO más grande
del proyecto: perder 7 811 URLs indexadas pesa mucho más que cualquier meta
description.

```text
Prioridad dentro de la FASE 10:
  1. Preservar las URLs actuales (clase 1)
  2. Redirigir los 7 811 slugs históricos (clase 2)
  3. Reproducir las plantillas de título y descripción
  4. Migrar los 912 valores manuales y los 6 818 términos primarios
  5. Sitemap, Open Graph, schema
```

## Verificación

La FASE 10 no se cierra sin comparación ejecutable contra producción, en modo
lectura (§46):

```text
Para una muestra representativa de URLs:
  comparar <title> de producción contra el de Drupal
  comparar <meta name="description">
  comparar <link rel="canonical">
  comparar las etiquetas og: y twitter:
  comparar el breadcrumb
```

Debe ser un script en `tools/`, no una revisión manual, y su salida va a
`reports/validation/`.
