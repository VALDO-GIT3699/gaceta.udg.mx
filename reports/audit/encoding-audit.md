# Auditoría de encoding — dump WordPress Gaceta

Fecha: 2026-09-30
Fase del roadmap: FASE 2, *gate* crítico (CLAUDE.md §11, §33, §47)

```text
REVISIÓN 2: corregida tras el dictamen del auditor de migración.
```

La revisión 1 de este documento contenía tres defectos metodológicos y una
conclusión falsa. Están corregidos y señalados en la sección "Correcciones
respecto a la revisión 1", al final. Las cifras de esta revisión **sustituyen**
a las anteriores.

## Conclusión

```text
CONFIRMADO: el dump completo es UTF-8 válido. Los 3 544 549 259 bytes.
CONFIRMADO: el dump debe importarse como UTF-8, SIN conversión de charset.
CONFIRMADO: existe mojibake residual, minoritario y localizado.
HIPÓTESIS:  ese mojibake es de origen editorial, preexistente.
```

La advertencia crítica de CLAUDE.md §11 queda atendida: **no se debe ejecutar
ninguna conversión de charset**. Cualquiera de las dos conversiones posibles
dañaría contenido que hoy está correcto.

Nótese la distinción entre las dos últimas líneas. Que el daño **exista** y
esté **acotado** está confirmado. Que su **causa** sea editorial es una
hipótesis razonada, no un hecho demostrado. Esa diferencia importa porque D-11
propone alterar 21 324 posiciones de contenido publicado.

## Método

Una pasada de lectura en streaming sobre el dump completo, **sin cargarlo en
ninguna base de datos y sin modificarlo**. No se usó ningún parser de filas
(CLAUDE.md §13, §14): se cuentan **secuencias de bytes** y se valida UTF-8 con
un decodificador incremental. Ninguna de las dos técnicas depende de
interpretar la estructura del SQL.

Reproducible con `tools/audit-encoding.py`.

```text
Bytes leídos:   3 544 549 259
CREATE TABLE:              68
INSERT INTO:           67 217
```

## Paso 1 — La cabecera del dump explica el conflicto

```text
CONFIRMADO: el dump lo generó phpMyAdmin 4.9.1
CONFIRMADO: servidor de origen 5.5.68-MariaDB, PHP 7.4.33
CONFIRMADO: la cabecera declara  /*!40101 SET NAMES utf8mb4 */;
```

Este dato resuelve la aparente contradicción de CLAUDE.md §11.

`dc8_posts` declara `DEFAULT CHARSET=latin1` **en el esquema**, pero el volcado
se hizo con la conexión en `utf8mb4`. MySQL convierte del charset de la columna
al de la conexión al leer. Por lo tanto:

```text
El texto que contiene el archivo .sql YA ESTÁ en UTF-8,
aunque la sentencia CREATE TABLE siga diciendo latin1.
```

La declaración `latin1` describe cómo estaban almacenados los bytes en el
servidor de origen, **no** cómo están escritos en el archivo.

## Paso 2 — Validación UTF-8 sobre el archivo completo

```text
CONFIRMADO: los 3 544 549 259 bytes decodifican como UTF-8 estricto válido.
```

Se validó con un decodificador incremental que procesa el archivo por bloques y
mantiene el estado entre ellos, de modo que detecta también las secuencias
partidas en la frontera de un bloque. Un solo byte inválido en todo el archivo
habría abortado la validación.

Esto descarta de entrada que haya restos de latin1 crudo o de codificaciones
mezcladas.

## Paso 3 — Prueba discriminante

Existen dos escenarios posibles y producen huellas **distintas y
distinguibles** en el archivo:

| Escenario | Qué había en la columna latin1 | Qué produce el dump |
|---|---|---|
| **A** | caracteres latin1 legítimos (`á` = `E1`) | UTF-8 correcto: `á` = `C3 A1` |
| **B** | bytes UTF-8 metidos a la fuerza (`á` = `C3 A1`) | doble codificación: `Ã¡` = `C3 83 C2 A1` |

La prueba cuenta, sobre **el mismo conjunto de 14 caracteres** y en todo el
archivo, cuántos aparecen en su forma correcta y cuántos en la doble. Comparar
poblaciones equivalentes es esencial: contar un solo carácter correcto frente a
seis dobles daría un resultado sin sentido.

### Resultado

| Carácter | Correcto | Doble |
|---|---:|---:|
| `á` | 1 343 251 | 6 |
| `é` | 942 124 | 2 |
| `í` | 1 508 438 | 10 |
| `ó` | 2 404 154 | 10 |
| `ú` | 345 121 | 2 |
| `ñ` | 517 059 | 2 |
| `ü` | 7 837 | 1 |
| `ç` | 1 036 | 0 |
| `Á` | 85 686 | **0** |
| `É` | 14 968 | **0** |
| `Í` | 13 499 | **0** |
| `Ó` | 27 791 | **0** |
| `Ú` | 19 350 | **0** |
| `Ñ` | 3 696 | **0** |
| **TOTAL** | **7 234 010** | **33** |

```text
CONFIRMADO: ESCENARIO A. Proporción 219 212 a 1.
```

La doble codificación de letras es **residual**: 33 casos en 3.54 GB. El
contenido almacenado era latin1 legítimo y phpMyAdmin lo convirtió
correctamente.

Dato relevante: **las seis mayúsculas acentuadas tienen cero casos** de doble
codificación, sobre un universo de 164 990 apariciones correctas. Si la causa
fuera el charset de la columna, las mayúsculas se habrían dañado igual que las
minúsculas.

## Paso 4 — Validación del método de atribución

Antes de atribuir el daño a cada tabla hay que comprobar el supuesto del que
depende: que el volcado **intercale** estructura y datos por tabla. Si
phpMyAdmin hubiera escrito primero las 68 estructuras y después todos los
datos, atribuir cada byte al `CREATE TABLE` más cercano por la izquierda daría
resultados completamente falsos.

```text
CONFIRMADO: 67 217 sentencias INSERT caen tras el CREATE TABLE de SU tabla.
CONFIRMADO: 0 sentencias INSERT mal atribuidas.
```

Comprobado también a mano sobre un caso concreto:

```text
CREATE TABLE dc8_comments   offset        292 169
INSERT INTO  dc8_comments   offsets  293 324 .. 5 722 191
CREATE TABLE dc8_postmeta   offset     14 931 839
```

```text
El método de atribución es válido.
```

## Paso 5 — El síntoma `â€¦` existe, y está acotado

CLAUDE.md §11 documenta haber observado `â€¦` en el contenido histórico. La
auditoría **lo confirma** y lo dimensiona:

```text
CONFIRMADO: prefijo "â€" = c3 a2 e2 82 ac          21 494 ocurrencias
CONFIRMADO: de las cuales "â€¦"                      3 879 ocurrencias
            (ANIDADAS en la cifra anterior: no se suman)
CONFIRMADO: puntuación correcta "…" = e2 80 a6       8 818 ocurrencias
```

`â€` es la doble codificación del prefijo `E2 80` del bloque General
Punctuation de Unicode: puntos suspensivos `…`, comillas tipográficas `" "`,
apóstrofos `' '` y guiones `– —`.

### Distribución por tabla

Denominador: total de las 14 letras acentuadas correctas atribuidas a cada
tabla.

| Tabla | Mojibake | Letras correctas | % dañado |
|---|---:|---:|---:|
| `dc8_posts` | 21 324 | 7 144 862 | **0.30 %** |
| `dc8_yoast_indexable` | 143 | 36 507 | 0.39 % |
| `dc8_postmeta` | 23 | 40 411 | 0.06 % |
| `dc8_yoast_seo_links` | 4 | 301 | 1.31 % |

Tablas con texto acentuado y **cero** mojibake:

| Tabla | Letras acentuadas | Valor de la evidencia |
|---|---:|---|
| `dc8_options` | 7 267 | denominador suficiente |
| `dc8_terms` | 3 570 | denominador suficiente |
| `dc8_comments` | 633 | denominador aceptable |
| `dc8_usermeta` | 215 | denominador escaso |
| `dc8_users` | 98 | denominador escaso |
| `wp_posts` | 96 | denominador escaso |
| resto | ≤ 28 | **sin valor probatorio** |

```text
ADVERTENCIA: cero mojibake sobre un denominador pequeño no es prueba de
limpieza, es ausencia de medición.
```

Lecturas defendibles de esta tabla:

1. **El 99.2 % del mojibake está en `dc8_posts`** (21 324 de 21 494).
2. **`dc8_terms` está limpio con evidencia suficiente** (3 570 letras, 0 casos).
   La migración de taxonomías (FASE 7) no tiene riesgo de encoding.
3. **`dc8_comments` está limpio con evidencia aceptable** (633 letras, 0 casos).
4. **`dc8_users` y `dc8_usermeta` tienen denominadores escasos.** No se afirma
   que estén limpios: se afirma que no se detectó daño con la medición
   disponible. La FASE 8 debe verificarlo sobre los datos cargados.
5. `dc8_yoast_indexable` y `dc8_yoast_seo_links` son **derivadas**: Yoast copia
   títulos y descripciones de los posts, así que heredan el daño.

## Sobre la CAUSA del daño: hipótesis, no confirmación

```text
HIPÓTESIS: el mojibake es preexistente y de origen editorial.
```

Argumento a favor. El daño afecta a la puntuación tipográfica (21 494 casos) y
casi no afecta a las letras (33 casos). Una conversión errónea de charset no
distingue entre `á` y `…`: dañaría ambas por igual. Que las mayúsculas
acentuadas tengan cero casos refuerza lo mismo.

Ése es el patrón característico de texto pegado en el editor desde un documento
de Word, un PDF o una fuente RSS con codificación mal declarada.

Por qué **no** se eleva a CONFIRMADO. El argumento asume que letras y
puntuación entraron en la columna por la misma vía, y eso no está demostrado.
Demostrar la causa exige identificar los registros dañados y su procedencia, lo
que requiere la base de auditoría (D-02, B-03).

```text
PENDIENTE: identificar los post_id con mojibake y cruzarlos con
dc8_agg_sources (WP RSS Aggregator) para verificar la hipótesis del
contenido agregado.
```

Hay un indicio independiente: `reports/audit/wordpress-inventory.md` confirma
que **WP RSS Aggregator 5.0.6 está instalado**, y la agregación RSS con
encoding mal declarado produce exactamente este patrón.

## Consecuencias para la migración

### Lo que SÍ se debe hacer

```text
Importar el dump con la conexión en utf8mb4, respetando la cabecera del propio
archivo, y sin pasar --default-character-set.
```

### Lo que NO se debe hacer

```text
PROHIBIDO: CONVERT(... USING utf8mb4) sobre las columnas de dc8_posts.
PROHIBIDO: reinterpretar bytes (latin1 -> binary -> utf8mb4).
PROHIBIDO: forzar --default-character-set=latin1 en la importación.
PROHIBIDO: "arreglar" automáticamente las cadenas con mojibake.
```

Las tres primeras corromperían el 99.7 % del contenido que hoy está correcto,
para intentar arreglar el 0.30 % que no lo está. La cuarta es una modificación
de contenido editorial y requiere autorización explícita: **D-11**.

## Estado del gate

```text
GATE CRÍTICO FASE 2 (encoding): SUPERADO, para la estrategia de importación.
```

Lo que está demostrado y basta para decidir cómo importar:

```text
El archivo completo es UTF-8 válido.
La proporción correcto/doble es de 219 212 a 1 sobre 14 caracteres.
El método de atribución por tabla está validado (0 errores en 67 217 INSERT).
La estrategia es NO CONVERTIR.
```

Lo que **no** está demostrado y sigue pendiente:

```text
La CAUSA del daño residual (HIPÓTESIS).
Los registros concretos afectados.
El paso 8 de CLAUDE.md §11: comparar el texto SQL de un registro dañado
contra cómo lo muestra ese mismo registro en producción.
```

Ese paso 8 exige localizar los registros dañados, lo que requiere la base de
auditoría. La verificación contra producción que sí se hizo fue sobre la
portada, que por la distribución del daño es precisamente la muestra con menor
probabilidad de estar afectada: **es una comprobación consistente, no
discriminante**.

```text
GATE FASE 2 (resto): NO SUPERADO. Requiere B-03.
```

## Verificación contra producción (§46, sólo lectura)

```text
Fecha:  2026-09-30
URL:    https://www.gaceta.udg.mx  (portada)
Método: observación, sin ninguna acción que modifique el sitio.
```

```text
CONFIRMADO: la portada renderiza acentos y comillas tipográficas correctamente.
CONFIRMADO: no se detectó mojibake en el contenido de portada.
```

Titulares observados con acentos correctos, entre otros: *"Historia y Geografía
se transforman…"*, *"Educación dual…"*, *"La deuda histórica de Los Altos de
Jalisco…"*, *"Laura Puentes representará a México…"*.

Consistente con el hallazgo: el daño afecta al 0.30 % del texto acentuado de
`dc8_posts`, concentrado en registros concretos, no al contenido corriente.

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

## Hallazgo colateral — los comentarios son spam en inglés

Al resolver un CONFLICTO señalado por el auditor (¿por qué `dc8_comments` tenía
tan pocas letras acentuadas si §28 reporta ~6 346 comentarios?) se midió la
región completa de esa tabla: 5.5 MB, 111 sentencias INSERT.

```text
CONFIRMADO: token "the "   16 615 ocurrencias
CONFIRMADO: token " de "        76 ocurrencias
CONFIRMADO: token " que "       20 ocurrencias
CONFIRMADO: token "http"     7 973 ocurrencias
CONFIRMADO: token "casino"      98 ocurrencias
CONFIRMADO: token "viagra"      16 ocurrencias
```

```text
CONFIRMADO: la cola de moderación es abrumadoramente spam en inglés.
```

No era un fallo de medición: es una característica real de los datos. Tiene
consecuencias directas para CLAUDE.md §28: la decisión sobre migrar
comentarios cambia sustancialmente si la mayoría de los ~6 346 son spam y no
contenido de lectores. Debe cuantificarse con `comment_approved` cuando haya
base de auditoría.

## Correcciones respecto a la revisión 1

Detectadas por el auditor de migración y corregidas aquí.

| Defecto de la revisión 1 | Corrección |
|---|---|
| Validación UTF-8 sólo sobre 2 MB (0.056 % del archivo) y generalizada al total | Validación incremental sobre los 3 544 549 259 bytes |
| Sondas de letras: 6 minúsculas. Omitía mayúsculas, `ü`, `ç` | 14 caracteres, incluidas las 6 mayúsculas acentuadas |
| Veredicto comparaba 1 carácter correcto contra 6 dobles | Compara los mismos 14 caracteres en ambas formas |
| Contadores inflados por la ventana de solape entre bloques | Deduplicación por offset absoluto |
| `â€` y `â€¦` presentadas como cifras independientes | Se declara explícitamente que están anidadas |
| Supuesto de atribución por tabla no verificado | Verificado: 67 217 INSERT, 0 mal atribuidos |
| Denominador = ocurrencias de `á`. Daño de `dc8_posts` cifrado en 1.58 % | Denominador = 14 letras. El daño real es **0.30 %** |
| `CONFIRMADO: el daño es de origen editorial` | Reetiquetado como **HIPÓTESIS** |
| *"taxonomías, autores y comentarios no tienen riesgo de encoding"*, sobre denominadores de 21 y 25 | Afirmación retirada. Sólo `dc8_terms` (3 570) y `dc8_comments` (633) tienen denominador suficiente. `dc8_users` (98) y `dc8_usermeta` (215) quedan por verificar |
| Gate declarado SUPERADO sin distinguir alcance | Superado **para la estrategia de importación**; el paso 8 de §11 sigue pendiente |
