# Colisiones de URL: lo que producción sirve de verdad

Fecha: 2026-10-01
Requisito que atiende: CLAUDE.md §24, §46. Resuelve el diagnóstico de D-16.

Reproducible con `tools/audit-slug-colisiones.php`. La verificación contra
producción se hizo en modo observación y sólo lectura, con cuatro peticiones
GET. No se modificó nada (§38).

## Por qué esto bloqueaba la migración masiva

Drupal **no impone unicidad** en `path_alias`. Acepta dos alias idénticos sin
protestar y después resuelve sólo uno. El piloto lo reprodujo:

```text
/Enfoques  ->  /node/260, /node/264, /node/265
```

Los conteos de la migración habrían dicho 36 666 de 36 666 mientras cientos de
artículos quedaban sin ruta accesible. Es la **pérdida silenciosa de rutas** que
§24 prohíbe de forma expresa, y la que ningún `failed_count` detecta.

## Corrección de dos cifras mías anteriores

Yo venía repitiendo «1 382 slugs duplicados» y «las páginas también están
afectadas, 14 duplicados». Las dos están mal. El 1 382 salía de contar todos los
`post_type`, incluidas las revisiones:

```text
revision      7 668 grupos    no se migran como contenido
post            347 grupos    ESTO es el problema real
attachment        1 grupo
page              0 grupos    las paginas NO tienen ninguna colision
```

```text
CONFIRMADO: 347 grupos y 971 registros, no 1 382. Y page esta limpia:
185 registros, 170 con slug, 170 slugs distintos.
```

## Alcance real

```text
Grupos con slug repetido en post    347
Registros implicados                971
De ellos publicados                 971   (el 100 %)
Perdedores a desambiguar            624

Tamano de los grupos:
   2 publicados      291 grupos
   3-5                48
   6-20                6
  21-100               1
  >100                 1
```

Los peores son columnas periodísticas recurrentes, no duplicados por error:

```text
Enfoques             108      gaseta-fugaz          52
¡A-moverse!           16      Breves                14
Derecho-electoral     13      Aspidiario            11
Fe-de-erratas         10      Se-descubrió-que       8
```

299 de los 347 grupos tienen **título idéntico** en todos sus miembros. Es
coherente: una columna fija que se publica en cada número, con el mismo título
y por tanto el mismo slug. Es contenido distinto, no contenido repetido.

## El hallazgo: esos artículos ya no son accesibles HOY

`dc8_posts.guid` guarda el permalink no bonito, único por registro:

```text
108 registros de Enfoques  ->  108 guid distintos  ->  ?p=30278, ?p=30352, ...
36 568 de 36 851 registros usan el formato ?p=ID
```

Parecía la salida limpia: WordPress honra `?p=ID` siempre, con independencia de
la estructura de permalinks. Se comprobó, y **no es así cuando el slug
colisiona**.

| Petición a producción | Qué devolvió | Esperado |
|---|---|---|
| `/Enfoques/` | artículo del **2016-06-06** (ID 45900) | uno de los 108 |
| `?p=30278` | artículo del **2016-06-06** | el de 2008-09-22 |
| `?p=30352` | artículo del **2016-06-06** | el de 2008-10-06 |
| `?p=29139` *(control, slug único)* | «2008: año de extrema cautela», 2008-01-07 | correcto |

El control demuestra que `?p=ID` funciona perfectamente **cuando el slug es
único**. Con slug repetido, WordPress aplica su redirección canónica: convierte
`?p=ID` en el permalink bonito, y ahí vuelve a ganar el mismo registro.

```text
CONFIRMADO: en cada grupo colisionado solo UN registro es accesible. Gana el de
ID mas alto, que es el mas reciente. Los 624 restantes NO tienen hoy ninguna
URL que funcione: ni la bonita, ni ?p=ID.
```

## Lo que esto cambia

El diagnóstico se invierte:

```text
LO QUE YO CREIA: Drupal perderia 624 rutas vivas. Riesgo critico.
LO QUE OCURRE:   WordPress ya las perdio. Drupal puede recuperarlas.
```

De modo que:

- Las **347 rutas vivas** se preservan exactamente. No es negociable (§24) y se
  cumple conservando el alias del ganador.
- Los **624 perdedores** no tienen ruta que preservar. Darles un alias único no
  pierde una ruta: **crea una que hoy no existe**. Es una mejora estricta sobre
  el sitio actual, y rescata 624 artículos publicados que llevan años
  inalcanzables.

## Regla implementada

En `GacetaNoticia::prepareRow()`, con trazabilidad en el campo de origen
`alias_colision`:

```text
sin slug        ->  sin alias; el nodo se sirve por /node/N      740 casos
slug unico      ->  /slug                                     35 000+
ganador         ->  /slug            EXACTO, preserva la ruta viva     347
desambiguado    ->  /slug-<wp_id>                                      624
```

El ganador se determina por `MAX(ID)` dentro del grupo, que es lo que
producción aplica, verificado arriba. No es un criterio elegido por comodidad.

### Por qué el sufijo es `-<wp_id>` y no `-2`, `-3`

```text
VERIFICADO: 624 perdedores producen 624 alias distintos, y CERO choques contra
los 35 461 slugs reales del corpus.
```

Un esquema `-2`, `-3` depende del **orden en que se procesen las filas**: si la
migración se reejecuta por lotes distintos, el mismo artículo recibe otra URL.
Eso rompe la reproducibilidad que exige §31. `slug-<wp_id>` se deduce del dato,
así que es idéntico en cualquier ejecución y además trazable al origen.

```text
PROVISIONAL Y REVERSIBLE: es la recomendacion tecnica, no una decision cerrada.
Si el responsable prefiere otro esquema se cambia en un sitio y se reimporta;
los alias se rehacen con migrate:rollback antes del cutover. Ver D-16.
```

## Hallazgo adicional: el contenido migrado choca con la plantilla

Al revisar `path_alias` apareció una segunda clase de colisión que no estaba
prevista, entre contenido migrado y contenido **de la plantilla institucional**:

```text
/inicio        ->  /node/1    (de la plantilla)  +  /node/268  (migrado)
/form/contact  ->  4 alias identicos, todos de la propia plantilla
```

```text
PENDIENTE: /inicio es una colision real y hay que resolverla. Las 4 de
/form/contact vienen de la plantilla entregada y son previas a este proyecto:
se documentan, no se tocan sin autorizacion (§44).
```

La comprobación de duplicados debe correrse sobre `path_alias` **completo**, no
sólo sobre lo migrado, porque el destino ya tenía contenido.
