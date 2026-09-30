# Auditoría de encoding — dump WordPress Gaceta

Fecha: 2026-09-30
Fase del roadmap: FASE 2, *gate* crítico (CLAUDE.md §11, §33, §47)

## Conclusión

```text
RESUELTO: el contenido del dump es UTF-8 válido y correcto.
RESUELTO: el dump debe importarse como UTF-8, SIN ninguna conversión de charset.
CONFIRMADO: existe mojibake preexistente, minoritario y localizado.
CONFIRMADO: ese mojibake NO es un artefacto del dump ni del charset de las tablas.
```

La advertencia crítica de CLAUDE.md §11 queda atendida con evidencia: **no se
debe ejecutar ninguna conversión de charset**, ni `CONVERT ... USING utf8mb4`
ni una reinterpretación de bytes. Cualquiera de las dos **dañaría** contenido
que hoy está correcto.

## Método

Tres pasadas de lectura sobre el dump completo, en streaming, **sin cargarlo en
ninguna base de datos y sin modificarlo**. No se usó ningún parser de filas
(CLAUDE.md §13, §14): se contaron **secuencias de bytes**, que no dependen de
interpretar la estructura del SQL.

Reproducible con `tools/audit-encoding.py`.

```text
Bytes leídos por pasada: 3 544 549 259
Tablas detectadas:       68
```

## Paso 1 — La cabecera del dump explica el conflicto

```text
CONFIRMADO: el dump lo generó phpMyAdmin 4.9.1
CONFIRMADO: servidor de origen 5.5.68-MariaDB, PHP 7.4.33
CONFIRMADO: la cabecera declara  /*!40101 SET NAMES utf8mb4 */;
```

Este dato resuelve la aparente contradicción de CLAUDE.md §11.

`dc8_posts` declara `DEFAULT CHARSET=latin1` **en el esquema**, pero el volcado
se realizó con la conexión en `utf8mb4`. MySQL convierte del charset de la
columna al charset de la conexión al leer. Por lo tanto:

```text
El texto que contiene el archivo .sql YA ESTÁ en UTF-8,
aunque la sentencia CREATE TABLE siga diciendo latin1.
```

La declaración `latin1` describe cómo estaban almacenados los bytes en el
servidor de origen, **no** cómo están escritos en el archivo.

## Paso 2 — Prueba discriminante

Existen dos escenarios posibles y producen resultados **distintos y
distinguibles** en el archivo:

| Escenario | Qué había en la columna latin1 | Qué produce el dump |
|---|---|---|
| **A** | caracteres latin1 legítimos (`á` = `E1`) | UTF-8 correcto: `á` = `C3 A1` |
| **B** | bytes UTF-8 metidos a la fuerza (`á` = `C3 A1`) | doble codificación: `Ã¡` = `C3 83 C2 A1` |

La prueba consiste en contar, en todo el archivo, las firmas de cada
escenario. Si predomina B, hay que reinterpretar bytes. Si predomina A, no hay
que tocar nada.

### Resultado

```text
UTF-8 correcto      "á"  = c3 a1                    1 343 251 ocurrencias
UTF-8 correcto      "…"  = e2 80 a6                     8 818 ocurrencias

Doble codificación de LETRAS (escenario B):
  "Ã¡" c3 83 c2 a1   6      "Ã©" c3 83 c2 a9   2
  "Ã­" c3 83 c2 ad  10      "Ã³" c3 83 c2 b3  10
  "Ãº" c3 83 c2 ba   2      "Ã±" c3 83 c2 b1   2
  ------------------------------------------------
  TOTAL letras dobles:                    32 ocurrencias
```

```text
CONFIRMADO: escenario A. 1 343 251 contra 32.
```

La doble codificación de letras es **estadísticamente inexistente**: 32 casos
en 3.54 GB. El contenido almacenado era latin1 legítimo y phpMyAdmin lo
convirtió correctamente.

Comprobación adicional sobre una muestra de 2 MB de datos de `dc8_posts`:

```text
CONFIRMADO: decodificación UTF-8 estricta VÁLIDA (sin errores)
CONFIRMADO: 0 bytes latin1 crudos aislados (ninguna "á" como 0xE1 suelto)
CONFIRMADO: 35 bytes altos distintos, todos en secuencias UTF-8 bien formadas
```

## Paso 3 — El síntoma `â€¦` existe, pero no es lo que parecía

CLAUDE.md §11 documenta haber observado `â€¦` en el contenido histórico. La
auditoría **lo confirma** y lo dimensiona:

```text
CONFIRMADO: "â€" = c3 a2 e2 82 ac        21 494 ocurrencias
CONFIRMADO: "â€¦" = c3 a2 e2 82 ac c2 a6   3 879 ocurrencias
```

### Por qué esto NO es el escenario B

La clave está en el contraste entre puntuación y letras:

```text
Doble codificación de PUNTUACIÓN:  21 494
Doble codificación de LETRAS:          32
```

Si la causa fuera el charset de la columna, **letras y puntuación se habrían
dañado en la misma proporción**: una conversión errónea no distingue entre
`á` y `…`. La asimetría de 21 494 contra 32 descarta esa causa.

`â€` es la doble codificación del prefijo `E2 80` de la puntuación general
Unicode: puntos suspensivos `…`, comillas tipográficas `" "`, apóstrofos
`' '` y guiones `– —`. Es decir, **sólo se dañó la puntuación tipográfica**.

```text
CONFIRMADO: el daño es preexistente y de origen editorial.
```

Ése es el patrón característico de texto pegado en el editor desde un
documento de Word, un PDF o una fuente RSS con codificación mal declarada: la
puntuación tipográfica de Windows-1252 llegó ya corrupta al contenido. No lo
causó la base de datos, no lo causó el dump y **no lo causará la migración**.

## Paso 4 — Distribución del daño por tabla

Atribución de cada ocurrencia a su tabla, mediante el mapa de offsets de las
68 sentencias `CREATE TABLE`:

| Tabla | Mojibake | UTF-8 correcto | % dañado |
|---|---:|---:|---:|
| `dc8_posts` | 21 324 | 1 325 959 | **1.58 %** |
| `dc8_yoast_indexable` | 143 | 6 150 | 2.27 % |
| `dc8_postmeta` | 23 | 8 036 | 0.29 % |
| `dc8_yoast_seo_links` | 4 | 38 | 9.52 % |
| `dc8_options` | 0 | 2 448 | **0 %** |
| `dc8_terms` | 0 | 487 | **0 %** |
| `dc8_usermeta` | 0 | 60 | **0 %** |
| `dc8_users` | 0 | 25 | **0 %** |
| `dc8_comments` | 0 | 21 | **0 %** |
| `dc8_revslider_sliders` | 0 | 1 | **0 %** |
| `wp_posts` | 0 | 24 | **0 %** |
| `wp_options` | 0 | 2 | **0 %** |

Lecturas importantes de esta tabla:

1. **El 99.2 % del mojibake está en `dc8_posts`** (21 324 de 21 494). Es
   coherente con un origen editorial: es la tabla donde se escribe el
   contenido.
2. **`dc8_yoast_indexable` y `dc8_yoast_seo_links` son derivadas.** Yoast copia
   títulos y descripciones desde los posts, así que heredan el daño. Su
   porcentaje más alto se explica por su volumen mucho menor de texto.
3. **Taxonomías, comentarios, usuarios y opciones están limpios: 0 casos.**
   Esto es una conclusión operativa fuerte: las migraciones de taxonomías
   (FASE 7), autores (FASE 8) y comentarios no tienen riesgo de encoding.

## Consecuencias para la migración

### Lo que SÍ se debe hacer

```text
Importar el dump con la conexión en utf8mb4, respetando la cabecera del
propio archivo, y sin pasar --default-character-set.
```

Las tablas destino en Drupal son `utf8mb4`. El texto del dump ya es UTF-8
correcto, por lo que la carga es una copia fiel.

### Lo que NO se debe hacer

```text
PROHIBIDO: CONVERT(... USING utf8mb4) sobre las columnas de dc8_posts.
PROHIBIDO: reinterpretar bytes (latin1 -> binary -> utf8mb4).
PROHIBIDO: forzar --default-character-set=latin1 en la importación.
PROHIBIDO: "arreglar" automáticamente las cadenas con mojibake.
```

Las tres primeras corromperían el 98.4 % del contenido que hoy está correcto,
para intentar arreglar el 1.58 % que no lo está. La cuarta es una
transformación de contenido editorial y requiere autorización explícita.

### Decisión abierta derivada

La reparación del mojibake preexistente es una tarea **separada, opcional y
acotada**: 21 494 posiciones, casi exclusivamente puntuación tipográfica en
`dc8_posts`. Se registra como **D-11** en `docs/decisiones.md`.

CLAUDE.md §11 es explícito: *"No corrijas cadenas automáticamente hasta
demostrar cuál es la codificación original."* La codificación original ya está
demostrada. La corrección, si se autoriza, es reversible y verificable porque
el patrón es determinista.

## Verificación contra producción (§46, sólo lectura)

Observación de `https://www.gaceta.udg.mx` en modo lectura, sin ninguna acción
que modifique el sitio:

```text
CONFIRMADO: la portada renderiza acentos y comillas tipográficas correctamente.
CONFIRMADO: no se detectó mojibake en el contenido de portada.
```

Esto es consistente con el hallazgo: el daño afecta al 1.58 % del contenido
acentuado, concentrado en registros concretos, no al contenido corriente.

```text
PENDIENTE: identificar los post_id concretos con mojibake para verificar en
producción cómo se ven esos registros específicos. Requiere la base de
auditoría (D-02).
```

## Estado del gate

```text
GATE CRÍTICO FASE 2 (encoding): SUPERADO

Motivo: ya no existe incertidumbre sobre el encoding. Está demostrado, con
evidencia reproducible sobre los 3 544 549 259 bytes del dump, que el
contenido es UTF-8 correcto y que la estrategia de importación es no
convertir.
```

El resto de la FASE 2 sigue pendiente por otras razones (conteos, post types,
Elementor), que dependen de la base de auditoría, no del encoding.

## Nota sobre utf8mb3

24 tablas `dc8_` declaran `CHARSET=utf8`, que en MySQL/MariaDB es **utf8mb3**
(3 bytes) y no admite caracteres de 4 bytes como los emoji.

```text
CONFIRMADO: es una limitación preexistente del origen, no de la migración.
```

Si el contenido original tuvo emoji, WordPress ya los perdió o los almacenó
como entidades HTML antes de este proyecto. Las tablas destino en Drupal son
`utf8mb4`, así que la migración **no introduce** esta limitación; sólo hereda
sus efectos. Se documenta para no atribuir pérdidas preexistentes al proceso.
