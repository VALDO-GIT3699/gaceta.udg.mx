# Matriz de trazabilidad

Fecha: 2026-10-02 01:27
Requisito que atiende: CLAUDE.md §35, §41.

Generado con `tools/generar-reportes-trazabilidad.php`. Reproducible.

## Qué pregunta responde

> «¿Dónde terminó exactamente este registro WordPress?»

Y la inversa, que es la que de verdad encuentra problemas: «¿de dónde
viene este nodo de Drupal?». Esa segunda pregunta fue la que detectó los
4 nodos de demostración de la plantilla que yo estaba contando como
contenido migrado.

## Los dos mecanismos, a propósito

```text
1. Tablas migrate_map_*      automaticas, pero desaparecen con un
                             migrate:reset o una reconstruccion
2. Campos en la entidad      field_wp_post_id, field_wp_original_id,
                             field_wp_user_id, field_wp_user_login,
                             field_wp_term_id
```

El segundo hace la trazabilidad **independiente del estado de las
herramientas**, y permite reconciliar con SQL directo.

## Cobertura

```text
Entidades con trazabilidad al origen     36851
  tipo noticia                             36666
  tipo page                                  185

Con URL preservada                       36088
Sin slug en el origen: /node/N             763
Con original_id del sistema anterior     25121
```

`original_id` merece una nota: **este contenido ya fue migrado una vez
antes**, desde el sistema que Gaceta usaba antes de WordPress. Conservar
ese identificador mantiene viva la cadena completa de procedencia.

## Dónde está el detalle

```text
work/traceability.csv   una fila por entidad, con las 13 columnas de §35
```

Ese CSV **no entra en Git**: lleva títulos de artículo y rutas, que son
contenido editorial, y el remoto es público (§37, D-06). En Git entra
sólo este resumen agregado.
