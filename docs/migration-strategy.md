# Estrategia de migración

Fecha: 2026-09-30
Fase del roadmap: previa a FASE 5 y siguientes
Requisito que atiende: CLAUDE.md §31 y §32

```text
ESTADO: PROPUESTA. No aprobada. No implementada.
```

## Estrategia elegida

CLAUDE.md §32 plantea tres soluciones. La auditoría permite ahora elegir con
datos en lugar de por principios.

```text
ELEGIDA: Solución A — Drupal Migrate API, con un matiz de la Solución B.
```

El matiz es que la base `gaceta_auditoria` ya actúa como el *staging* que
describe la Solución B: es una copia aislada, de sólo lectura para el proceso,
separada del WordPress de producción y de la base de Drupal. No hace falta
construir un segundo staging con CSV o JSON.

```text
WordPress (producción, INTACTO)
   -> dump entregado (fuente de verdad, nunca modificado)
   -> gaceta_auditoria (staging ya existente, sólo lectura)
   -> Migrate API con source plugins SQL
   -> entidades Drupal
   -> validación
```

### Por qué no la Solución C

CLAUDE.md §32 ya la descarta como estrategia principal, y la auditoría añade un
motivo concreto: las columnas no estándar (`seccion`, `balazo`, `cita`,
`foto1`, `original_id`) exigen transformaciones por campo con reglas propias.
Escribir eso como SQL directo contra las tablas internas de Drupal sería
imposible de auditar y de repetir.

## Lo que la auditoría ya resolvió y simplifica el plan

```text
post_content es FIABLE. Las 2 359 entradas con Elementor conservan su texto, y
ninguna publicada lo tiene vacío. No hace falta un extractor de JSON para
migrar el contenido. (reports/audit/postmeta-audit.md)
```

```text
El encoding no requiere transformación. Las columnas son utf8mb3 y el destino
es utf8mb4: ampliación compatible. (reports/audit/encoding-audit.md)
```

```text
La integridad referencial está intacta: 0 comentarios huérfanos, 0 post_parent
colgados, 2 filas de postmeta huérfanas de 720 670.
```

Esas tres cosas eliminan la mayor parte de la complejidad que se temía.

## Orden de las migraciones y sus dependencias

El orden lo imponen las dependencias de referencia, no el roadmap:

```text
1. Vocabularios            (sin dependencias)
2. Términos                 depende de 1
3. Créditos editoriales     depende de 1   (D-14: términos, no usuarios)
4. Media: manifiesto        sin dependencias; archivos DIFERIDOS (D-15)
5. Páginas                  depende de 4
6. Noticias                 depende de 2, 3, 4
7. Eventos de agenda        depende de 2, 4
8. Comentarios              depende de 6
9. Alias de ruta            depende de 5, 6, 7
10. Redirects 301           depende de 9
11. Metadatos SEO           depende de 5, 6, 7
```

Migrate API expresa esto con `migration_dependencies`, lo que permite ejecutar
el conjunto con una sola orden y que respete el orden por sí mismo.

## Volúmenes reales por lote

Con las cifras definitivas de `reports/audit/content-counts.md`:

| Migración | Registros | Nota |
|---|---:|---|
| Vocabularios | 4 nuevos | sección, subsección, créditos, etiquetas |
| Términos: etiquetas | 7 724 | de `post_tag` |
| Términos: categorías | 129 | de `category`, jerárquica |
| Términos: secciones | ~200 | valores distintos de `seccion` |
| Términos: subsecciones | ~1 500 | valores distintos de `subseccion` |
| Créditos editoriales | ~142 | usuarios con entradas |
| Manifiesto de media | 48 358 | sin archivos (D-15) |
| Páginas | 185 | |
| **Noticias** | **36 666** | 36 627 publicadas |
| Eventos | 4 | todos borrador |
| Comentarios | 40 | aprobados; 6 346 pendientes por decidir |
| Alias de ruta | ~37 000 | |
| Redirects | 7 811 | menos 185 colisiones por resolver |

```text
El lote grande es uno solo: 36 666 noticias. Todo lo demás es pequeño.
```

Eso favorece una estrategia de lotes por año, que además es natural para un
medio: `--limit` sobre un rango de `post_date`. Hay 31 años, con un máximo de
2 226 entradas en 2012.

## Trazabilidad

CLAUDE.md §35 exige poder responder *"¿dónde terminó exactamente este registro
WordPress?"*. Dos mecanismos, deliberadamente redundantes:

```text
1. Las tablas de mapeo de Migrate API (migrate_map_*). Automáticas, pero se
   pierden si se hace migrate:reset o se reconstruye.
2. Campos propios en la entidad: field_wp_post_id y field_wp_original_id.
   Sobreviven a cualquier reconstrucción y permiten reconciliar con SQL.
```

El segundo es el que hace la trazabilidad independiente del estado de las
herramientas. `original_id` importa especialmente: 25 121 registros lo tienen, y
es el identificador del sistema anterior a WordPress.

## Hash de migración

CLAUDE.md §36 pide un hash de contenido normalizado y exige documentar qué
campos entran. Propuesta:

```text
SHA-256 de la concatenación, con separador \x1F, de:
  post_title
  post_content
  post_date          (formato ISO 8601, UTC)
  post_name
  seccion
  subseccion
  balazo
  cita
  post_author
```

```text
NO entran: post_modified (cambia por sí solo), guid (contiene el dominio),
comment_count (derivado), foto1 (se valida aparte con el manifiesto).
```

El hash se calcula sobre el **origen** y se guarda junto al nodo. Permite
detectar en la FASE 16 qué contenido cambió en WordPress desde el snapshot,
sin comparar campo por campo.

## Idempotencia y reejecución

```text
Toda migración debe poder ejecutarse dos veces sin duplicar nada.
```

Migrate API lo garantiza por el mapa de identificadores, siempre que la clave
de origen esté bien declarada. Para `dc8_posts` la clave es `ID`.

```text
PROHIBIDO usar post_name como clave de origen: hay 1 382 slugs duplicados.
```

Es un error fácil de cometer y produciría colisiones silenciosas.

## Qué NO se migra, y por qué

Decisiones ya respaldadas por la auditoría:

| Origen | Motivo |
|---|---|
| `revision` (77 307) | pendiente D-19; no aportan al sitio público |
| `oembed_cache` (89) | caché |
| `elementor_library` (69) | presentación |
| `tdb_templates` (28) | presentación |
| `custom_css` (2) | configuración |
| `nav_menu_item` (17) | se reconstruye como menús de Drupal |
| `tdc_history` (573 MB) | historial de deshacer del editor visual |
| `_elementor_css` | CSS generado |
| `_edit_lock`, `_edit_last` | artefactos del editor |
| `_yoast_wpseo_linkdex`, `_content_score` | puntuaciones internas de Yoast |
| `dc8_yoast_indexable`, `_seo_links` | caché e índice interno de Yoast |
| `dc8_post_views` (242 MB) | estadísticas de visitas |
| `dc8_revslider_*` | plugin **no activo**; residuo |
| `dc8_actionscheduler_*` | cola de tareas |
| `dc8_wpmailsmtp_debug_events` | log de correo; puede contener datos personales |
| contraseñas, hashes, correos | §27, §37 |

```text
Cada línea de esta tabla es una pérdida DELIBERADA y justificada, no un
descuido. Ninguna se ejecuta sin que esté escrita aquí.
```

## Lo que falta para poder empezar

```text
- [ ] Aprobación del modelo de contenido (FASE 4)
- [ ] Instalar migrate, migrate_plus, migrate_tools, media, redirect, metatag
      (altera el template: requiere aprobación)
- [ ] Crear los 4 vocabularios y los campos nuevos
- [ ] Resolver D-16 (colisiones de slug) y D-17 (rutas de foto1)
- [ ] Decidir D-19 (revisiones), D-11 (mojibake), D-04 (envíos de formulario)
```

## Piloto (FASE 5)

Antes de cualquier lote masivo, la muestra que CLAUDE.md FASE 5 exige. Con los
datos ya conocidos, la selección deja de ser genérica:

```text
1. Noticia simple, reciente, sin Elementor
2. Noticia de 1995 o 1996 (el extremo antiguo del corpus)
3. Noticia con foto1 y SIN adjunto (contenido histórico)
4. Noticia con _thumbnail_id (imagen destacada moderna)
5. Noticia con Elementor, de las 2 359
6. Noticia con mojibake de puntuación, de las 21 324
7. Noticia con subsección truncada a 15 caracteres
8. Noticia con slug duplicado (de las que comparten /Enfoques/)
9. Noticia con slug histórico (_wp_old_slug)
10. Noticia con uno de los 185 slugs en colisión
11. Noticia del autor genérico "Universidad de Guadalajara"
12. Noticia con crédito dentro del texto ("Fotografía: ...")
13. Noticia con comentario aprobado (de los 40)
14. Noticia con meta description propia (de las 910)
15. Página privada (de las 47)
16. Evento de agenda (de los 4 borradores)
17. Contenido con fecha 0000-00-00 (de los 6)
```

```text
Cada caso de esta lista existe y es localizable por SQL. El piloto deja de ser
una muestra al azar y pasa a ser una batería de casos límite conocidos.
```

CLAUDE.md FASE 5 exige que el 100 % de los casos piloto tenga resultado
conocido antes de avanzar.

## Reversibilidad

```text
El dump original nunca se modifica.
gaceta_auditoria se puede reconstruir desde el dump con tools/extract-tables.py.
La base de Drupal se puede restaurar con backup_migrate, que ya está instalado.
Cada migración se puede revertir con migrate:rollback.
```

```text
Ninguna operación de esta estrategia toca el WordPress de producción.
```
