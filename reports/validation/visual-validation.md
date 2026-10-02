# Validación visual: producción contra el sitio migrado

Fecha: 2026-10-02
Requisito que atiende: CLAUDE.md FASE 14, §41, §46.

Toda la observación de `https://www.gaceta.udg.mx` se hizo en **modo lectura**,
con peticiones GET puntuales. No se ejecutó ninguna acción que modifique el
sitio ni scraping agresivo (§46, §38).

```text
ALCANCE: esta revision compara ESTRUCTURA y CONTENIDO, no pixeles. La fidelidad
visual fina depende de las fotos, y faltan ~40 GB (B-02). Comparar maquetacion
sin imagenes daria una conclusion falsa.
```

## Navegación principal

Se leyó el menú real de producción y se reprodujo elemento a elemento.

| Producción | Sitio migrado | Estado |
|---|---|---|
| Inicio | Inicio | correcto |
| Investigación y Conocimiento | ídem, término 9049 | correcto |
| → COVID-19 | ídem, término 9139 | correcto |
| → Ciencia y Tecnología | ídem, término 9075 | correcto |
| → Medio Ambiente | ídem, término 9077 | correcto |
| → Opinión | ídem, término 9080 | correcto |
| → Sociedad | ídem, término 9079 | correcto |
| → Economía | ídem, término 9076 | correcto |
| → Salud | ídem, término 9074 | correcto |
| Noti Red | ídem, término 9052 | correcto |
| → Corresponsal Gaceta | ídem, término 9069 | correcto |
| Deporte U | ídem, término 9054 | correcto |
| Talento U | ídem, término 9051 | correcto |
| 02 Cultura | ídem, término 9053 | correcto |
| Especiales | ídem, término 9061 | correcto |
| Información Oficial | ídem, término 9055 | correcto |
| **Hemeroteca** | **AUSENTE** | pendiente |

### Un error mío que esta comparación corrigió

La primera versión del menú se construyó **deduciendo** las secciones por
volumen de contenido, con el supuesto de que las categorías con más artículos
serían las de la navegación. Al leer el menú real no coincidía:

```text
LO QUE YO DEDUJE:  incluia Universidad, Primer Plano, Comunidad UdeG y
                   Cartones, que tienen MUCHO contenido y NO estan en el menu;
                   y le faltaban Talento U, Especiales y Hemeroteca.
```

El menú de producción es **manual**, no un árbol de taxonomía: coloca
`COVID-19` bajo «Investigación y Conocimiento» aunque en la taxonomía ese
término cuelga de «Especiales».

```text
§46 avisa de esto literalmente: "No conviertas una observacion visual en una
decision de modelo de datos sin verificar el origen". Mi error fue el inverso y
igual de malo: DEDUCIR del modelo de datos algo que habia que ir a MIRAR.
```

### «02 Cultura»: se conserva el prefijo

El término lleva un prefijo numérico que parece un artefacto de ordenación de
WordPress, y yo lo había limpiado a «Cultura». Pero producción **muestra
literalmente «02 Cultura»** en su menú, así que no es un residuo invisible: es
lo que el lector ve hoy.

```text
Se conserva por fidelidad. Si el responsable quiere que diga "Cultura", es
cambiar una linea. Lo que no corresponde es que yo "mejore" el sitio del
cliente sin que nadie lo pida.
```

## Identidad

| Elemento | Producción | Migrado |
|---|---|---|
| Nombre del sitio | Gaceta UDG | Gaceta UDG |
| Eslogan | ninguno | ninguno |
| Título de página | `<título> - Gaceta UDG` | ídem, plantilla de Yoast trasladada |

El nombre se tomó **literalmente** del encabezado de producción, no se inventó.

## Plantillas de página verificadas

| Página | Producción | Migrado |
|---|---|---|
| Portada | portada editorial con carrusel y secciones | responde 200; mezcla artículos migrados con la demo de la plantilla (D-05) |
| Listado de noticias | listado paginado | `/noticias` lista las noticias migradas |
| Ficha de artículo | título, fecha, autoría, cuerpo, sección | muestra cuerpo, balazo, autoría, sección, subsección y categoría |
| Página de sección | listado por categoría | `/taxonomy/term/N` lista correctamente |
| Contacto | tres formularios | los tres webforms reconstruidos (D-29) |
| 404 | página de error | responde 404 |

## Accesibilidad (§12)

Verificado en el HTML servido, **por el archivo que carga el navegador** y no
por el nombre de la librería:

```text
accesibilityUdg.js   cargado
Sepia                presente
Grises               presente
skip-link            presente
visually-hidden      presente
```

```text
La primera vez que certifique la accesibilidad lo hice viendo las ETIQUETAS de
los botones, sin comprobar que el script estuviera cargado, y me equivoque. Hay
que mirar las dos cosas, y eso es lo que hace ahora tools/prueba-humo.sh.
```

## Lo que NO se puede validar todavía

```text
IMAGENES. Faltan ~40 GB de /wp-content/uploads (B-02). La cobertura medida es
del 31.1 %. Sin las fotos no tiene sentido comparar maquetacion: un listado sin
imagenes no se parece a uno con imagenes, y la conclusion seria falsa.
```

```text
LA PORTADA. Sigue mezclando contenido de DEMOSTRACION de la plantilla
("Ejemplo con archivos", "Evento de agenda #10 DEMO") con articulos de Gaceta.
Su destino es la decision D-05, abierta. Hasta resolverla, la portada no puede
declararse validada.
```

## Veredicto

```text
PASS CON OBSERVACIONES
```

La estructura, la navegación, las plantillas de página y la accesibilidad se
corresponden con producción. Quedan dos cosas, y ninguna es un defecto de la
migración: las **imágenes**, que son una dependencia externa, y el **contenido
de demostración** de la plantilla, que es una decisión pendiente.
