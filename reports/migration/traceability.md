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

## El hash de migración (§36)

```text
36 851 hashes SHA-256. TODOS DISTINTOS. 0 registros sin destino.
```

§36 obliga a documentar **exactamente** qué campos entran. Son 11, en este
orden, separados por el ASCII 31:

```text
ID · post_title · post_name · post_content · post_excerpt · post_status
post_date · post_author · seccion · subseccion · balazo
```

El separador es ASCII 31 a propósito: no aparece en texto editorial, así que
dos registros distintos no pueden producir la misma cadena por concatenación
ambigua.

### Se calcula sobre el origen CRUDO, y esa es la decisión importante

El valor entra **tal como está en WordPress**, antes de cualquier
transformación mía: sin decodificar entidades HTML, sin convertir shortcodes.

```text
SI SE CALCULARA SOBRE EL RESULTADO, cambiar mi propio codigo alteraria el hash
de 36 851 registros sin que nadie hubiera tocado WordPress, y la
sincronizacion final creeria que TODO cambio.

EL HASH MIDE EL ORIGEN, NO MI TRABAJO.
```

### Para qué sirve, que no es para adornar la matriz

La migración se hizo sobre un volcado del **2026-09-17 12:39** y WordPress
sigue publicando. La FASE 16 tiene que saber qué cambió.

Sin hash, la única respuesta es confiar en `post_modified`, y no basta:

```text
CONFIRMADO: 12 860 articulos tienen post_modified POSTERIOR a post_date.
```

`post_modified` cambia cuando alguien abre y guarda sin tocar nada, y **no
cambia** si el contenido se altera por SQL. El hash compara el contenido.

```text
EN LA SINCRONIZACION: se recalcula sobre el volcado nuevo y se compara.
  distinto -> reimportar ese articulo
  igual    -> saltarlo AUNQUE post_modified haya cambiado
```

### Qué NO entra, y por qué

```text
foto1, term_id, original_id   identificadores: no cambian nunca
comment_count                 cambia al comentar, sin tocar el articulo
post_modified                 es precisamente lo que el hash viene a no tener
                              que creer
```

```text
CAMBIAR LA LISTA DE CAMPOS INVALIDA TODOS LOS HASHES ANTERIORES. Si se cambia,
hay que recalcularlos.
```

## Dónde está el detalle

```text
work/traceability.csv   una fila por entidad, con las 13 columnas de §35
```

Ese CSV **no entra en Git**: lleva títulos de artículo y rutas, que son
contenido editorial, y el remoto es público (§37, D-06). En Git entra
sólo este resumen agregado.
