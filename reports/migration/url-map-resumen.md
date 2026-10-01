# Resumen del mapa de URLs

Generado por `tools/build-url-map.php` el 2026-10-01
Fuente: `gaceta_auditoria` (sólo lectura)

```text
El mapa completo NO se versiona: contiene slugs y títulos, es decir
contenido editorial, y el repositorio es público. Vive en work/.
```

## Totales

```text
URLs en el mapa:                    44662
  Clase 1 (URL actual):             36851
  Clase 2 (slug histórico):          7811
```

## Tipo de resolución

| Resolución | URLs | Qué implica |
|---|---:|---|
| `IDENTICA` | 35233 | la URL no cambia: no hace falta redirección |
| `REDIRECT_301` | 7519 | redirección desde el slug histórico |
| `COLISION_REQUIERE_DECISION` | 1147 | **no se puede resolver sola** |
| `SIN_SLUG_EN_ORIGEN` | 763 | la URL cambia por fuerza |

```text
URLs que se preservan sin tocar nada: 78.9 % del mapa
```

## Problemas de calidad detectados en los slugs

| Problema | URLs |
|---|---:|
| Entidad HTML roto (`ntilde;`, `aacute;`…) | 296 |
| Mayúsculas en el slug | 24260 |
| Caracteres no ASCII (acentos, signos) | 8324 |
| Artefacto de Post Duplicator (`-copy`) | 76 |

Ninguno impide preservar la URL: WordPress las sirve hoy tal cual y
Drupal puede servirlas igual. Se listan porque condicionan la decisión
D-16 y porque conviene crear además la versión corregida de las que
llevan una entidad HTML roto.

## Lo que este mapa NO cubre

```text
Clase 3: taxonomías (/category/, /tag/), archivos por fecha, páginas de
autor (/author/), feeds, paginación y URLs de adjunto. No dependen de
post_name y se tratan por separado en docs/url-strategy.md.
```
