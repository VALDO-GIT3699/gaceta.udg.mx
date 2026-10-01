# Auditoría de Elementor

Fecha: 2026-09-30
Fase del roadmap: FASE 2
Riesgo que atiende: CLAUDE.md §18 (riesgo crítico)
Decisión que alimenta: **D-03**

## Resumen

```text
CONFIRMADO: Elementor 3.15.3 y Elementor Pro 3.15.1 instalados.
HIPÓTESIS:  del orden de 47 900 contenidos tienen datos de Elementor.
HIPÓTESIS:  esos datos ocupan la mayor parte de los 2.3 GB de dc8_postmeta,
            es decir, la mayor masa de datos de todo el proyecto.
```

```text
El riesgo que CLAUDE.md §18 anticipaba no sólo se confirma: es mayor de lo
que el contrato suponía.
```

## Las cifras

Conteo de apariciones de cada clave en el dump completo
(`tools/audit-postmeta-keys.py`):

| Clave | Apariciones | Qué almacena |
|---|---:|---|
| `_elementor_template_type` | 48 171 | tipo de plantilla |
| `_elementor_version` | 48 120 | versión que generó el diseño |
| `_elementor_pro_version` | 48 119 | versión de Pro |
| `_elementor_edit_mode` | 48 108 | marca de "editado con Elementor" |
| **`_elementor_data`** | **47 896** | **la estructura y el contenido, en JSON** |
| `_elementor_page_settings` | 29 631 | ajustes de página |
| `_elementor_css` | 13 423 | CSS generado |
| `_elementor_controls_usage` | 4 458 | estadística de uso de widgets |
| `_elementor_conditions` | 5 | condiciones de plantilla global |

La clave decisiva es `_elementor_data`: es la que contiene el **contenido
real**, no sólo metadatos de configuración.

### Por qué se cree que ocupan casi todo `dc8_postmeta`

```text
dc8_postmeta mide 2.3 GB en el dump.
Las demás claves medidas son valores cortos: rutas, versiones, enteros.
_elementor_data son blobs JSON de estructura de página.
```

Si unas 47 900 filas de `_elementor_data` promediaran unos 40 KB cada una, eso
da aproximadamente 1.9 GB, que encaja con los 2.3 GB medidos dejando margen
para el resto de claves.

```text
HIPÓTESIS: _elementor_data es del orden del 80 % de dc8_postmeta, y por tanto
más de la mitad de todo el dump.
```

No es CONFIRMADO porque exige `SUM(LENGTH(meta_value))` agrupado por
`meta_key`, lo que requiere la base de auditoría (B-03). Es la **primera
consulta** que debe ejecutarse cuando esté disponible.

## La advertencia central de §18, confirmada

```text
post_content NO es necesariamente el contenido visible.
```

Cuando una entrada se edita con Elementor, WordPress deja en `post_content` una
versión degradada o incluso vacía, y el contenido real vive en el JSON de
`_elementor_data`. Elementor lo renderiza en la portada sustituyendo el
contenido nativo.

```text
CONSECUENCIA: migrar post_content y nada más produciría hasta 47 900
contenidos vacíos o mutilados, con los conteos saliendo correctos.
```

Ése es el peor modo de fallo posible para esta migración: un éxito aparente.
La FASE 13 compararía 47 900 contra 47 900 y daría todo por bueno.

```text
PRIMERA VERIFICACIÓN OBLIGATORIA al cargar la base de auditoría:
cuántos de esos contenidos tienen post_content vacío o casi vacío mientras
_elementor_data tiene datos.
```

Esa única consulta dimensiona la pérdida potencial. Si la cifra es alta, la
migración de contenido **no puede ejecutarse** sin resolver D-03 antes.

## Qué hay dentro de `_elementor_data`

Es un árbol JSON de secciones, columnas y widgets. Cada widget tiene su tipo y
sus ajustes. Lo relevante para una migración de preservación:

```text
Contenido semántico:  texto, encabezados, listas, citas, tablas
Media:                ids de adjunto y URLs de imagen
Enlaces:              internos y externos
HTML embebido:        widgets de HTML crudo
Shortcodes:           de otros plugins, anidados dentro de widgets
Presentación:         márgenes, colores, tipografías, animaciones, responsive
```

```text
El objetivo NO es reproducir Elementor dentro de Drupal (§18).
El objetivo es extraer el contenido semántico y la media, y reconstruir la
presentación con la arquitectura del template institucional.
```

## Riesgos específicos detectados

### 1. Referencias de media dentro del JSON

```text
Los widgets de imagen guardan el id del adjunto y su URL dentro del JSON.
```

El manifiesto de media (`docs/media-strategy.md`) **tiene que recorrer el JSON
de Elementor**, no sólo `_wp_attached_file` y `_thumbnail_id`. Si se omite,
se pierden las referencias de imagen de hasta 47 900 contenidos.

### 2. `_elementor_css` es caché, no contenido

```text
13 423 apariciones. Es CSS generado automáticamente.
```

No se migra. Es el equivalente de la caché de WP Super Cache (§17): un
artefacto regenerable. Confundirlo con contenido inflaría el trabajo sin
aportar nada.

### 3. Las 5 condiciones de plantilla global

```text
_elementor_conditions aparece 5 veces.
```

Son plantillas de Elementor Pro Theme Builder que se aplican por condición
(por ejemplo, "todas las entradas de esta categoría"). Son **presentación
global**, equivalente a lo que en Drupal hacen el tema y las Views. Cinco es
una cifra manejable: conviene identificarlas y documentar qué hacía cada una,
porque definen cómo se ve el sitio.

### 4. Elementor Pro y los widgets no estándar

Essential Addons for Elementor 5.9.9 también está instalado
(`reports/audit/wordpress-inventory.md`), lo que añade widgets de terceros al
JSON.

```text
RIESGO: widgets cuyo contenido no sigue la estructura estándar de Elementor.
```

Un extractor que sólo entienda los widgets del núcleo de Elementor perdería el
contenido de los de Pro y los de Essential Addons.

```text
PENDIENTE: inventariar qué TIPOS de widget aparecen realmente y con qué
frecuencia. _elementor_controls_usage (4 458) puede contener ese resumen ya
calculado por el propio plugin.
```

Esa clave es una oportunidad: Elementor guarda ahí su propia estadística de uso
de controles. Leerla daría el inventario de widgets sin recorrer 2 GB de JSON.

## Estrategia propuesta para D-03

No se decide aquí. Se propone el orden de trabajo, que es lo que falta para
poder decidir con datos.

```text
PASO 1  Medir. SUM(LENGTH(meta_value)) por meta_key.
        Cuántos contenidos tienen _elementor_data con post_content vacío.
        Qué post_type son esos contenidos.

PASO 2  Clasificar. Separar los contenidos en:
          a) sin Elementor -> migración directa de post_content
          b) con Elementor pero post_content íntegro -> migración directa
          c) con Elementor y post_content vacío -> REQUIEREN extracción
        Sólo el grupo (c) es el problema real, y su tamaño es DESCONOCIDO.

PASO 3  Inventariar widgets. Desde _elementor_controls_usage si sirve, o
        recorriendo una muestra del JSON.

PASO 4  Prototipar el extractor sobre una muestra del grupo (c), con
        comparación visual contra producción (§46, sólo lectura).

PASO 5  Medir la tasa de pérdida del extractor y presentarla para decisión.
```

```text
El paso 2 es el que convierte D-03 de pregunta abierta en decisión informada.
Puede que el grupo (c) sea pequeño y el problema casi desaparezca; puede que
sean 47 900 y haya que replantear el proyecto. Hoy no se sabe.
```

## Lo que está prohibido mientras D-03 esté abierta

CLAUDE.md §18 lo dice y la auditoría lo respalda:

```text
PROHIBIDO: destruir o descartar los datos de Elementor.
PROHIBIDO: migrar contenido asumiendo que post_content está completo.
PROHIBIDO: declarar migrado un contenido del grupo (c) sin extracción.
```

## Estado

```text
D-03: ABIERTA. No se puede formular con alternativas reales sin el paso 1.
GATE FASE 9 (migración de contenido): NO SUPERABLE mientras D-03 esté abierta.
```

Este es, con diferencia, el mayor riesgo técnico del proyecto: afecta
potencialmente a 47 900 contenidos y a más de la mitad del volumen de datos.
