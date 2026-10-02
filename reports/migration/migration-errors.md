# Registro de errores de migración

Fecha: 2026-10-02 01:27
Requisito que atiende: CLAUDE.md §41, §40, FASE 9.

## Estado actual de los mensajes de Migrate

```text
gaceta_noticia           36666 creados       0 descartados     0 mensajes
gaceta_pagina              185 creados       0 descartados     0 mensajes
gaceta_seccion             447 creados       0 descartados     0 mensajes
gaceta_subseccion          654 creados       0 descartados     0 mensajes
gaceta_credito             142 creados       0 descartados     0 mensajes
gaceta_etiqueta           7724 creados       0 descartados     0 mensajes
gaceta_categoria           129 creados       0 descartados     0 mensajes
gaceta_redireccion        1406 creados    6386 descartados     0 mensajes
gaceta_comentario           40 creados       0 descartados     0 mensajes
```

## Los errores que SÍ ocurrieron, y cómo se trataron

§40 obliga a no ocultar ningún error. Éstos se reprodujeron, se
diagnosticaron y se corrigieron; ninguno se «arregló» eliminando datos.

| Error | Causa | Tratamiento |
|---|---|---|
| `Data too long for column field_balazo_value` | Diseñé el campo como `string(255)`; el máximo real es 973 | Recreado como `text_long`. 0 pérdidas |
| `Column title cannot be null` | 740 entradas sin título en el origen | Marcador explícito. No se inventó ningún título ni se descartó ningún registro |
| Títulos como `Qu&eacute; bien` | Entidades HTML en 2 948 títulos | Decodificadas en títulos y nombres de término. El cuerpo NO se toca: es HTML |
| `Data too long for column title` | 21 títulos pasan de 255 caracteres, el mayor con 867 | Corte en frontera de palabra + `field_titulo_completo` con el original. 0 pérdidas |
| `Duplicate entry` en `redirect` | 49 rutas viejas reclamaban 2+ destinos | Gana el más reciente, el mismo criterio verificado en D-24. Los descartados quedan marcados |
| `Unknown column COUNT in having clause` | `havingCondition()` entrecomilla su primer argumento | Expresión con alias |

## Los errores SILENCIOSOS, que son los peligrosos

Ninguno de éstos produjo un solo mensaje de error. Todos daban
`failed_count = 0` y conteos correctos.

| Fallo silencioso | Qué lo detectó |
|---|---|
| 9 campos creados y poblados que no se renderizaban | Mirar una ficha, no contar |
| `/Enfoques` apuntando a 3 nodos a la vez | El piloto |
| Mapa de colisiones vacío por leer `$fila->campo` con FETCH_ASSOC | Un aviso de PHP |
| 66 rutas perdidas: MySQL agrupaba sin acentos y PHP comparaba byte a byte | `validar-alias-unicos.php` |
| Colisión entre una noticia y una PÁGINA | `validar-alias-unicos.php` |
| 1 350 secciones y 978 subsecciones sin referencia, por la misma causa | `conciliar-conteos.php`, campo a campo |
| 13 749 filas de alias redundantes tras reprocesar | `validar-alias-unicos.php` |

```text
LECCION: los seis primeros fallos reales se arreglaron en minutos. Los
siete silenciosos costaron mucho mas, y tres de ellos tenian la MISMA
causa: comparar con criterios distintos a los que se habia agrupado.
Por eso la normalizacion vive ahora en un sitio UNICO, NormalizaTexto.
```

## Veredicto

```text
0 mensajes de error pendientes en las 9 migraciones.
```
