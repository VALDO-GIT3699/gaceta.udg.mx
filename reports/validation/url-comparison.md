# Comparación de URLs: vieja contra nueva

Fecha: 2026-10-02 01:27
Requisito que atiende: CLAUDE.md §24, §41.

## El reparto

```text
Noticias con slug en el origen           35918
  de ellas, con alias IDENTICO           34146
  desambiguadas (D-24, D-27)              1772

Noticias SIN slug en el origen             748
  se sirven por /node/N. Es una URL que CAMBIA, y queda contabilizada
  como tal: son las mismas entradas que tampoco tienen titulo.

Redirecciones 301 creadas                 1384
ALIAS QUE LLEVAN A DOS CONTENIDOS            1
```

## Por qué 1 772 URLs llevan un número al final

Porque en WordPress **no eran alcanzables**. 347 slugs están repetidos
entre las noticias publicadas, y en cada grupo sólo uno responde: se
verificó contra producción que `?p=ID` no salva a los demás, porque
WordPress lo redirige al permalink y ahí vuelve a ganar el mismo.

```text
Darles un alias unico no pierde una ruta: CREA una que hoy no existe, y
rescata articulos publicados que llevan anos inalcanzables.
```

El sufijo es el id de WordPress y no `-2`, `-3`, porque es la única forma
determinista: un contador depende del orden de proceso y rompería la
reproducibilidad de §31. Ver D-24.

## Las redirecciones

```text
entradas _wp_old_slug sobre post        7 792
  son BUCLES (slug viejo = el actual)   6 286   no son redirecciones
  redirecciones REALES                  1 403
  rutas que reclamaban 2+ destinos          49
```

```text
CORRECCION: en documentos anteriores cite "7 811 redirecciones". Esa cifra
contaba las entradas de postmeta sin descartar los bucles. Son 1 403.
```

## Lo que queda

```text
1 alias llevan a dos contenidos. §24 NO esta cumplido del todo.
```

Es `/inicio`: lo reclaman la portada de la plantilla y la página «Inicio»
de WordPress. Es la decisión **D-25**, pendiente del responsable, y la
última ruta en conflicto de todo el sitio.
