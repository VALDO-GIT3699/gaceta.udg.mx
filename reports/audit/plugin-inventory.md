# Inventario de plugins: qué hace cada uno y qué pasa con él

Fecha: 2026-10-02
Requisito que atiende: CLAUDE.md §16, §17, §41.

Confirmado por SQL contra `dc8_options.active_plugins`, no por el panel.

```text
CONFIRMADO: 28 plugins ACTIVOS. En disco hay 38 directorios, asi que 10 estan
instalados y desactivados: esos no se consideran funcionalidad del sitio.
```

## La regla que se aplica

§16 lo dice sin ambigüedad:

```text
NO:  plugin WordPress -> plugin Drupal
SI:  funcionalidad real -> auditoria -> decision -> implementacion Drupal
```

Así que esta tabla no busca un equivalente para cada plugin. Busca qué
**hace** cada uno en el sitio real y si eso tiene que llegar a Drupal.

## Los 28 activos

| Plugin | Qué hace | Destino en Drupal |
|---|---|---|
| `wordpress-seo` (Yoast) | títulos, meta descriptions, canonical | **Migrado como DATOS.** 910 meta descriptions al nodo; las plantillas de título a `metatag`. El plugin no se migra (§23) |
| `contact-form-7` | 4 formularios, 3 en uso en `/contacto` | **Reconstruido** como 3 Webform (D-29) |
| `wpforms-lite` | 2 formularios | Mismos campos que los de CF7. Cubierto por los Webform |
| `the-events-calendar` | 193 lugares, 4 eventos borrador | **No se migra.** 0 referencias, 0 rutas vivas (D-28) |
| `elementor` + `elementor-pro` | maquetación de páginas | De-escalado: 45 424 de 47 896 filas `_elementor_data` están en REVISIONES. Sólo 2 359 artículos publicados lo usan y **ninguno tiene `post_content` vacío** |
| `td-composer`, `td-cloud-library`, `td-standard-pack`, `td-social-counter` | tema Newspaper de tagDiv | **No se migran** (§19). Son presentación; la plantilla institucional la sustituye |
| `smart-slider-3` | carrusel de portada | La plantilla trae `slideshow_principal`. Pendiente de poblarlo: FASE 11 |
| `wp-super-cache` | caché de página | **No es contenido** (§17). Drupal trae su propia caché; medido 0.07 s en caliente |
| `post-views-counter` | contador de vistas | `dc8_post_views` son 241.8 MB de estadísticas, no contenido editorial. Pendiente de decisión |
| `disable-comments-rb` | desactiva comentarios | Equivale al campo de comentarios en modo CERRADO (D-23) |
| `classic-editor` | editor antiguo | Irrelevante: es interfaz de administración |
| `post-duplicator` | duplicar entradas | **Dejó rastro en los datos**: los slugs `-copy-N` que aparecen entre los conflictos de redirección (D-24) |
| `pdf-embedder` | incrusta PDF | 962 usos de `[pdf` en el cuerpo. Pendiente: D-21 (shortcodes) |
| `youtube-embed-plus` | incrusta YouTube | **0 shortcodes `youtube`** en el corpus. Nada que hacer |
| `tabs` (PickPlugins) | pestañas | 0 shortcodes detectados |
| `search-filter` | filtros de búsqueda | Pendiente: Views con filtros expuestos |
| `loco-translate` | traducción de cadenas | Irrelevante: el sitio es monolingüe en es_ES |
| `jetpack` | estadísticas y servicios de Automattic | **No se migra.** Servicio externo |
| `google-analytics-for-wordpress`, `google-site-kit` | analítica | Servicio externo. Pendiente: decidir si se reinstala el seguimiento |
| `wp-mail-smtp` | envío de correo | Equivale a la configuración SMTP del servidor. **No se copian credenciales** (§37) |
| `query-monitor` | depuración | Herramienta de desarrollo. No se migra |
| `templately` | plantillas de Elementor | Presentación. No se migra |
| `wp-downgrade` | fijar versión de WordPress | Irrelevante en Drupal |

## Lo que este inventario cambió respecto a la auditoría inicial

```text
ELEMENTOR estaba declarado "riesgo critico" (§18) y se temia que el contenido
visible viviera en _elementor_data y no en post_content. Medido: el 94.8 % de
esas filas estan en REVISIONES, solo 2 359 articulos publicados lo usan y
NINGUNO tiene post_content vacio. El riesgo baja de "amenaza al proyecto" a
"revisar 2 359 articulos".
```

```text
LOS SHORTCODES: yo reporte que "el 97.6 % de las entradas tienen shortcodes".
Era FALSO: mi expresion regular [a-z_]+ casaba con prosa normal como "[sic]".
Los reales son [caption 3 089, [pdf 962 y [gallery 284, y CERO de vc_, td_,
youtube o smartslider.
```

## Pendientes que salen de aquí

```text
D-21  los shortcodes [caption, [pdf y [gallery del cuerpo
D-xx  si se reinstala la analitica, y con que propiedad
D-xx  que hacer con los 241.8 MB de contadores de visitas
FASE 11  poblar el carrusel de portada con contenido de Gaceta
```
