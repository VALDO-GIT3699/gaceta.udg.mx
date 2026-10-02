# Conciliación de conteos: WordPress contra Drupal

Fecha: 2026-10-02 00:27
Requisito que atiende: CLAUDE.md §34, §41, FASE 13.

Generado con `tools/conciliar-conteos.php`. Reproducible y de sólo lectura.

## Noticias: total y ausencias

```text
En WordPress (post_type = post)    36666
En Drupal (tipo noticia)           36666
AUSENTES en Drupal                     0
En Drupal SIN origen                   0
```

```text
CONCILIADO: ningun registro del origen falta en el destino.
```

## Estado de publicación

```text
publish en WordPress               36627
publicados en Drupal               36627
NO publicados en Drupal               39
Diferencia                             0

Nodos noticia de la PLANTILLA          4  (fuera de esta comparacion)
```

En WordPress `private` significa publicado con acceso restringido y
`future` es programado. Ninguno equivale al publicado de Drupal, así que
sólo `publish` pasa a publicado. Nada se descarta: el resto entra sin
publicar y conserva su estado original en la trazabilidad.

## Reparto por año

| Año | WordPress | Drupal | Diferencia |
|---|---:|---:|---:|
| 0 | 6 | 6 | — |
| 1995 | 19 | 19 | — |
| 1996 | 1 | 1 | — |
| 2004 | 101 | 101 | — |
| 2005 | 2090 | 2090 | — |
| 2006 | 548 | 548 | — |
| 2007 | 314 | 314 | — |
| 2008 | 1620 | 1620 | — |
| 2009 | 1920 | 1920 | — |
| 2010 | 2198 | 2198 | — |
| 2011 | 2207 | 2207 | — |
| 2012 | 2226 | 2226 | — |
| 2013 | 1891 | 1891 | — |
| 2014 | 1824 | 1824 | — |
| 2015 | 2166 | 2166 | — |
| 2016 | 2014 | 2014 | — |
| 2017 | 1733 | 1733 | — |
| 2018 | 1513 | 1513 | — |
| 2019 | 1242 | 1242 | — |
| 2020 | 1194 | 1194 | — |
| 2021 | 1553 | 1553 | — |
| 2022 | 1705 | 1705 | — |
| 2023 | 1635 | 1635 | — |
| 2024 | 1751 | 1751 | — |
| 2025 | 1889 | 1889 | — |
| 2026 | 1306 | 1306 | — |

```text
CONCILIADO: el reparto por anio coincide exactamente.
```

Los 6 registros con `0000-00-00` reciben marca de tiempo 0, que en
`America/Mexico_City` es **1969-12-31 18:00** porque la zona es UTC−6. Se
contabilizan en la fila del año 0, que es de donde vienen. No se les
inventó una fecha: ver `GacetaNoticia::prepareRow()`.

## Campos poblados

Un campo que no se puebla **no produce ningún error** y los conteos de la
migración salen perfectos. Esta tabla es la que lo detecta.

| Campo | Lo tenía en el origen | Poblado en Drupal | Diferencia |
|---|---:|---:|---:|
| `field_balazo` | 13914 | 13914 | — |
| `field_cita` | 1877 | 1877 | — |
| `field_seccion` | 24672 | 24672 | — |
| `field_subseccion` | 24673 | 24673 | — |
| `field_wp_original_id` | 25121 | 25121 | — |
| `field_categoria` | 29485 | 29485 | — |
| `field_tags` | 9054 | 9054 | — |
| `field_titulo_completo` | 21 | 21 | — |

```text
CONCILIADO: todos los campos cuadran con el origen.
```

`field_autor_texto` no entra en la tabla: el crédito se resuelve contra el
vocabulario y no contra una columna del origen (D-14).

## URLs

```text
Noticias con slug en el origen     35918
Noticias con alias en Drupal       35922
Alias desambiguados (D-24, D-27)    1772
ALIAS QUE LLEVAN A DOS SITIOS          1
```

Las 740 entradas sin título son las mismas 740 sin slug: se sirven por
`/node/N`. Es una URL que cambia y queda contabilizada como tal.

```text
1 alias llevan a dos contenidos. §24 NO cumplido. Ver D-25.
```

## Veredicto

```text
PASS CON OBSERVACIONES
1 bloques de comprobacion presentan diferencias. Cada uno queda
detallado arriba con sus cifras.
```
