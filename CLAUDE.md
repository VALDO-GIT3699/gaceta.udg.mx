# CLAUDE.md --- Migración quirúrgica de Gaceta UDG: WordPress → Drupal 10

> **Documento contractual de operación para Claude Code**
>
> Proyecto: migración de `https://www.gaceta.udg.mx` desde WordPress
> hacia el template institucional Drupal 10 de UDG.
>
> Repositorio objetivo: `git@github.com:VALDO-GIT3699/gaceta.udg.mx.git`
>
> Regla principal: **preservar información y funcionalidad; no
> improvisar; no modificar producción; detenerse y preguntar cuando
> falte un criterio necesario.**

------------------------------------------------------------------------

## 0. MANDATO PRINCIPAL

Actúa como un **Ingeniero en Informática senior especializado en Drupal,
migraciones de CMS, PHP, MySQL/MariaDB, arquitectura de información, SEO
técnico, accesibilidad y preservación de contenido histórico**.

Tu misión no es simplemente "pasar WordPress a Drupal".

Tu misión es construir una migración **repetible, auditable, reversible
y verificable** que permita llevar Gaceta UDG a Drupal 10 sin pérdida
deliberada de:

-   contenido editorial;
-   fechas;
-   autores;
-   categorías/secciones;
-   taxonomías;
-   imágenes y archivos;
-   metadatos relevantes;
-   URLs;
-   SEO;
-   comentarios;
-   usuarios que deban conservarse;
-   relaciones entre contenido y medios;
-   contenido generado por plugins;
-   formularios o funcionalidades que realmente estén en uso;
-   estructura editorial;
-   información histórica;
-   elementos visuales y funcionales importantes.

La implementación final debe utilizar la **plantilla institucional
Drupal 10 entregada**, particularmente `themes/drudg8b3` y
`modules/custom/udg_liston`, salvo que una auditoría demuestre que
alguna modificación es necesaria.

### Regla de oro

**No conviertas una incertidumbre en una decisión.**

Si encuentras algo ambiguo:

1.  documenta el problema;
2.  identifica las alternativas;
3.  indica el impacto de cada una;
4.  detén esa parte de la migración;
5.  pregunta al responsable humano;
6.  no elijas arbitrariamente.

------------------------------------------------------------------------

# 1. CONTEXTO FÍSICO DEL PROYECTO

El usuario trabajará con los archivos locales en una carpeta similar a:

``` text
C:\Users\acer\OneDrive\Documentos\gaceta
```

No asumas que esa ruta existe exactamente en tu entorno.

Al iniciar:

``` powershell
Get-Location
Get-ChildItem
```

y determina cuál es la raíz real del proyecto.

El usuario proporcionará:

1.  el sitio WordPress local;
2.  el dump SQL;
3.  el sitio completo de la plantilla Drupal 10;
4.  archivos de contexto/auditoría;
5.  acceso de lectura al panel administrativo de WordPress;
6.  el repositorio GitHub.

------------------------------------------------------------------------

# 2. REPOSITORIO OFICIAL DEL PROYECTO

Repositorio:

``` text
git@github.com:VALDO-GIT3699/gaceta.udg.mx.git
```

Este repositorio será la fuente de verdad del desarrollo.

## Antes de modificar código

Verifica:

``` bash
git status
git remote -v
git branch --show-current
```

Si el repositorio no está inicializado, inicialízalo correctamente.

Si ya existe contenido remoto, **no lo sobrescribas sin
inspeccionarlo**.

## Regla de commits

Todos los commits deben estar en español y ser descriptivos.

No usar:

``` text
update
fix
changes
stuff
migration
test
```

como mensajes genéricos.

Preferir:

``` text
migración: crea inventario inicial del contenido WordPress

migración: incorpora auditoría de tipos de contenido y estados

migración: implementa transformación de autores editoriales

migración: preserva slugs y rutas históricas de Gaceta

migración: incorpora entidades Media desde adjuntos WordPress

tema: adapta portada de Gaceta al template institucional

validación: agrega reporte de conteos WordPress contra Drupal
```

Cada commit debe representar **un cambio lógico y revisable**.

No mezcles en un mismo commit:

-   migración;
-   cambios visuales;
-   refactor;
-   limpieza;
-   actualización de dependencias;

salvo que exista una razón documentada.

------------------------------------------------------------------------

# 3. PULL REQUESTS Y TRAZABILIDAD

El trabajo debe realizarse mediante ramas y PRs cuando sea apropiado.

Convención sugerida:

``` text
feature/auditoria-wordpress
feature/modelo-contenido-gaceta
feature/migracion-media
feature/migracion-contenido
feature/urls-seo
feature/vistas-gaceta
feature/tema-gaceta
feature/validacion-migracion
```

Cada PR debe explicar en español:

-   objetivo;
-   cambios;
-   archivos afectados;
-   decisiones tomadas;
-   pruebas realizadas;
-   resultados;
-   riesgos conocidos;
-   pendientes;
-   evidencia de validación.

------------------------------------------------------------------------

# 4. REPORTE EN ESPAÑOL POR CADA COMMIT

El responsable quiere recibir una explicación en español de cada cambio
importante.

Cada commit/PR debe producir un reporte con:

``` text
COMMIT:
Título:

QUÉ SE HIZO:
...

POR QUÉ:
...

ARCHIVOS MODIFICADOS:
...

PRUEBAS:
...

RESULTADO:
PASS / PASS CON OBSERVACIONES / BLOCKED

PENDIENTES:
...

RIESGOS:
...

SIGUIENTE PASO:
...
```

## Correo

El objetivo es que los cambios importantes generen una notificación por
correo.

**No inventes credenciales SMTP ni las guardes en Git.**

Primero verifica si el entorno/GitHub ya tiene notificaciones por correo
configuradas.

Si se requiere automatización adicional:

1.  propone GitHub Actions;
2.  usa secretos de GitHub;
3.  no hardcodees credenciales;
4.  no envíes información sensible;
5.  si faltan credenciales/configuración, detente y pregunta.

No afirmes que "se enviará correo" hasta comprobar que el mecanismo
existe y funciona.

------------------------------------------------------------------------

# 5. AGENTE/REVISOR OBLIGATORIO

Debes crear un mecanismo de revisión dedicado para comprobar
continuamente que el proyecto no se desvía.

Si el entorno de Claude Code permite subagentes/agentes, crea un agente
dedicado:

``` text
Agente: auditor-migracion-gaceta
```

Responsabilidad:

-   revisar el roadmap;
-   comprobar el estado de cada fase;
-   detectar tareas omitidas;
-   revisar que las transformaciones sean reversibles;
-   detectar pérdida de contenido;
-   revisar conteos;
-   revisar URLs;
-   revisar media;
-   revisar encoding;
-   revisar SEO;
-   revisar accesibilidad;
-   revisar seguridad;
-   revisar Drupal coding standards;
-   revisar que no se esté modificando producción;
-   revisar que no se introduzcan decisiones no autorizadas.

El agente auditor **no debe limitarse a revisar sintaxis**.

Debe funcionar como un segundo par de ojos del proyecto.

Antes de cerrar cada fase deberá emitir:

``` text
FASE:
ESTADO:
EVIDENCIA:
PROBLEMAS:
RIESGOS:
PENDIENTES:
AUTORIZACIÓN PARA CONTINUAR: SÍ / NO
```

Si el auditor detecta un problema crítico:

``` text
BLOCKED
```

y la ejecución de esa fase debe detenerse.

------------------------------------------------------------------------

# 6. CONTRATO DE PROGRESO

Mantén un archivo:

``` text
MIGRATION_CONTRACT.md
```

Este archivo es el tablero contractual del proyecto.

Usa:

``` markdown
- [ ] No iniciado
- [x] Completado y validado
- [~] En progreso
- [!] Bloqueado
```

Nunca marques una tarea como `[x]` solamente porque el código existe.

Debe existir evidencia.

Ejemplo:

``` markdown
- [x] Inventario de tablas completado
  - Evidencia: reports/audit/db-inventory.md

- [x] Conteo de contenido validado
  - Evidencia: reports/audit/content-counts.md

- [!] Migración completa de media
  - Motivo: falta acceso al uploads completo de producción
```

------------------------------------------------------------------------

# 7. FUENTES DE VERDAD

Usa estas fuentes en este orden:

1.  WordPress real / snapshot entregado.
2.  Dump SQL real.
3.  Archivos de uploads disponibles.
4.  Panel administrativo WordPress en modo lectura.
5.  Template Drupal 10 entregado.
6.  HTML de referencia de la plantilla.
7.  Repositorio Git.
8.  Documentación oficial de Drupal/WordPress.
9.  Investigación externa cuando sea necesaria.

Nunca sustituyas datos del proyecto por ejemplos genéricos de Internet.

------------------------------------------------------------------------

# 8. DATOS CONFIRMADOS DE WORDPRESS

Según la auditoría entregada:

``` text
WordPress: 6.4.3
Idioma: es_ES
Zona horaria: America/Mexico_City
URL: https://www.gaceta.udg.mx
Permalinks: /%postname%/
HTTPS: sí
Multisite: no
Usuarios: 162
Entorno: production
Base de datos: gaceta
Prefijo principal: dc8_
DB declarada: utf8mb4 / utf8mb4_unicode_ci
DB reportada: aproximadamente 4.53 GB
Instalación total reportada: aproximadamente 89.68 GB
wp-content/uploads reportado: aproximadamente 84.23 GB
PHP producción: 7.4.33
MariaDB producción reportada: 5.5.68
Servidor web: nginx/1.25.3
```

El WordPress usa:

``` text
Newspaper 12.6
autor: tagDiv
```

No intentes migrar el tema Newspaper como tema Drupal.

El objetivo es **reconstruir la presentación y funcionalidad necesaria
sobre la plantilla institucional Drupal**.

------------------------------------------------------------------------

# 9. WORDPRESS ES UN SITIO HISTÓRICO

Trata el sitio como un sistema legado.

Eso significa:

-   no asumir que todo sigue estándares modernos;
-   no asumir que todos los registros son contenido editorial;
-   no eliminar registros porque parezcan obsoletos;
-   no eliminar media duplicada sin establecer relaciones;
-   no eliminar usuarios sin criterio;
-   no convertir automáticamente todos los posts en Noticias;
-   no confiar solamente en WordPress UI;
-   revisar SQL;
-   revisar metadatos;
-   revisar taxonomías;
-   revisar adjuntos;
-   revisar revisiones;
-   revisar contenido generado por plugins.

------------------------------------------------------------------------

# 10. ESTRUCTURA LOCAL WORDPRESS ENTREGADA

La copia local tiene aproximadamente esta estructura:

``` text
wp/
├── DB/
│   └── gaceta (2).sql
├── qdb/
├── wp-admin/
├── wp-includes/
├── wp-content/
├── index.html
├── index.php
├── wp-config.php
└── ...
```

El dump principal:

``` text
DB\gaceta (2).sql
```

es de aproximadamente 3.5 GB.

La carpeta:

``` text
qdb/
```

corresponde a una copia antigua de phpMyAdmin y **no debe migrarse**
como funcionalidad del sitio.

------------------------------------------------------------------------

# 11. ADVERTENCIA CRÍTICA: DC8_POSTS

Existe una tabla histórica/custom:

``` text
dc8_posts
```

Su estructura incluye:

``` text
ID
original_id
term_id
foto1
balazo
cita
seccion
subseccion
post_author
post_date
post_date_gmt
post_content
post_title
post_excerpt
post_status
comment_status
ping_status
post_password
post_name
to_ping
pinged
post_modified
post_modified_gmt
post_content_filtered
post_parent
guid
menu_order
post_type
post_mime_type
comment_count
```

Y, de acuerdo con la inspección del dump:

``` text
ENGINE=MyISAM
DEFAULT CHARSET=latin1
```

Esto es **crítico**.

Aunque la información de WordPress reporta:

``` text
DB_CHARSET=utf8mb4
```

la tabla `dc8_posts` declara `latin1`.

Por tanto:

> **NO hagas una conversión ciega de charset.**

Antes de migrar:

1.  carga el dump en una BD de auditoría;
2.  inspecciona `SHOW CREATE TABLE`;
3.  inspecciona `CHARACTER SET`;
4.  inspecciona `COLLATION`;
5.  prueba textos con acentos;
6.  prueba caracteres especiales;
7.  detecta mojibake;
8.  compara texto SQL contra texto mostrado por WordPress;
9.  determina el encoding real;
10. documenta la transformación.

Un ejemplo observado en el contenido histórico presenta síntomas como:

``` text
â€¦
```

Esto puede indicar un problema de interpretación de encoding.

**No corrijas cadenas automáticamente hasta demostrar cuál es la
codificación original.**

------------------------------------------------------------------------

# 12. BASE DE DATOS DE AUDITORÍA

Existe un entorno local MariaDB:

``` text
MariaDB 10.4.32
```

El ejecutable utilizado localmente es:

``` text
C:\Users\Usuario2\xampp8.1.25\mysql\bin\mysql.exe
```

Se creó una base de auditoría:

``` text
gaceta_auditoria
```

Esta base es exclusivamente para análisis.

Reglas:

-   nunca usar la BD de producción;
-   nunca usar la BD Drupal como staging de WordPress;
-   nunca importar el dump sobre una BD existente sin verificar;
-   nunca ejecutar `DROP DATABASE` sin autorización explícita;
-   nunca modificar el dump original.

------------------------------------------------------------------------

# 13. PRIMERA AUDITORÍA SQL OBLIGATORIA

Una vez cargado el dump correctamente, consulta con SQL real.

No utilices parsers caseros de PowerShell para interpretar filas SQL
complejas.

Especialmente NO confíes en un parser basado en:

``` text
split(',')
```

porque `post_content`, títulos, URLs y otros campos pueden contener:

-   comas;
-   comillas;
-   escapes;
-   HTML;
-   JSON;
-   serialización;
-   saltos de línea.

Primero:

``` sql
SHOW TABLES;
```

Después:

``` sql
SELECT post_type, COUNT(*)
FROM dc8_posts
GROUP BY post_type
ORDER BY COUNT(*) DESC;
```

Después:

``` sql
SELECT post_status, COUNT(*)
FROM dc8_posts
GROUP BY post_status
ORDER BY COUNT(*) DESC;
```

Después:

``` sql
SELECT post_type, post_status, COUNT(*)
FROM dc8_posts
GROUP BY post_type, post_status
ORDER BY COUNT(*) DESC;
```

Y posteriormente auditar:

-   autores;
-   secciones;
-   subsecciones;
-   `term_id`;
-   `foto1`;
-   `post_parent`;
-   `guid`;
-   `post_name`;
-   fechas;
-   comentarios;
-   metadatos;
-   attachments;
-   revisiones;
-   Elementor;
-   taxonomías;
-   Yoast;
-   Events Calendar;
-   formularios.

------------------------------------------------------------------------

# 14. NO CONFUNDIR REGISTROS CON CONTENIDO EDITORIAL

La tabla contiene diferentes `post_type`.

En una inspección inicial aparecieron tipos como:

``` text
revision
attachment
post
tribe_venue
page
oembed_cache
elementor_library
tdb_templates
nav_menu_item
popup
popup_theme
wpcf7_contact_form
tribe_events
tabs
tribe_organizer
wpforms
custom_css
```

Los conteos iniciales obtenidos mediante un parser de texto fueron
considerados **NO CONFIABLES** porque el parser desplazaba columnas
cuando encontraba comas/escapes dentro del contenido.

Por tanto:

> **Estos conteos NO deben utilizarse como cifras definitivas.**

La cifra definitiva debe salir de consultas SQL contra la BD cargada.

------------------------------------------------------------------------

# 15. WORDPRESS ESTÁNDAR `wp_*`

El dump contiene también tablas:

``` text
wp_posts
wp_postmeta
wp_users
wp_usermeta
...
```

La inspección inicial indica que parecen corresponder a una instalación
WordPress separada/inicial o de prueba, con contenido como:

``` text
¡Hola mundo!
Página de ejemplo
privacy policy
wp_navigation
```

No asumas que forman parte del corpus editorial principal.

Pero tampoco las ignores automáticamente.

Primero:

1.  documenta;
2.  compara;
3.  determina si contienen información utilizada;
4.  demuestra su relación o falta de relación con `dc8_*`.

La migración editorial principal probablemente estará basada en `dc8_*`,
pero esa conclusión debe quedar respaldada por auditoría.

------------------------------------------------------------------------

# 16. PLUGINS: NO MIGRAR PLUGINS, MIGRAR FUNCIONALIDAD

El sitio tiene múltiples plugins históricos.

Entre los activos auditados aparecen:

``` text
Classic Editor
Contact Form 7
Disable Comments RB
Elementor
Elementor Pro
Google Analytics for WordPress by MonsterInsights
Jetpack
Loco Translate
PDF Embedder
Post Duplicator
Post Views Counter
Query Monitor
Search & Filter
Site Kit by Google
Smart Slider 3
Tabs by PickPlugins
tagDiv Cloud Library
tagDiv Composer
tagDiv Social Counter
tagDiv Standard Pack
Templately
The Events Calendar
WP Downgrade
WPForms Lite
WP Mail SMTP
WP Super Cache
Yoast SEO
YouTube WordPress Plugin by Embed Plus
```

También existen plugins imprescindibles como:

``` text
Endurance Browser Cache
Endurance Page Cache
SSO
```

## Regla

No hagas:

``` text
plugin WordPress → plugin Drupal
```

automáticamente.

Haz:

``` text
funcionalidad real → auditoría → decisión → implementación Drupal
```

------------------------------------------------------------------------

# 17. WP SUPER CACHE

`WP Super Cache` está identificado.

**No debe "migrarse" como contenido.**

Es una capa de cache.

En Drupal:

-   evaluar la caché nativa;
-   render cache;
-   dynamic page cache;
-   BigPipe si corresponde;
-   cache tags;
-   reverse proxy/CDN si existe;
-   configuración del servidor.

Documenta la equivalencia funcional.

No copies archivos de cache de WordPress.

------------------------------------------------------------------------

# 18. ELEMENTOR: RIESGO CRÍTICO

El sitio utiliza:

``` text
Elementor 3.15.3
Elementor Pro 3.15.1
```

Esto es uno de los mayores riesgos de la migración.

No supongas que:

``` text
post_content = contenido visible completo
```

Puede haber contenido y estructura almacenados en:

``` text
dc8_postmeta
```

incluyendo datos estructurados de Elementor.

Debes investigar:

-   `_elementor_data`;
-   `_elementor_edit_mode`;
-   `_elementor_page_settings`;
-   plantillas;
-   widgets;
-   shortcodes;
-   imágenes;
-   enlaces;
-   HTML embebido;
-   CSS;
-   contenido dinámico.

### Prohibido

No destruyas los datos Elementor antes de haber creado una estrategia de
extracción.

### Estrategia

Para cada contenido relevante:

``` text
WordPress ID
↓
contenido principal
↓
metadatos
↓
Elementor
↓
estructura visual
↓
contenido semántico
↓
Drupal
```

El objetivo no es reproducir Elementor dentro de Drupal.

El objetivo es **preservar el contenido y su significado**,
reconstruyendo la presentación con Drupal.

------------------------------------------------------------------------

# 19. NEWSPAPER / TAGDIV

El tema:

``` text
Newspaper 12.6
```

y componentes tagDiv no deben copiarse literalmente a Drupal.

Investiga qué información editorial depende de:

-   Newspaper;
-   tagDiv Composer;
-   tagDiv Standard Pack;
-   tagDiv Cloud Library;
-   plantillas;
-   shortcodes;
-   widgets.

Distingue:

``` text
contenido
```

de:

``` text
presentación
```

y:

``` text
cache/configuración del tema
```

Solo el contenido y comportamiento necesario debe llegar a Drupal.

------------------------------------------------------------------------

# 20. SMART SLIDER / REVSLIDER / BANNERS

Audita:

-   sliders;
-   slides;
-   banners;
-   imágenes;
-   enlaces;
-   textos;
-   orden;
-   relaciones.

No migres simplemente las tablas internas del plugin.

Determina qué elementos son:

``` text
contenido editorial
```

y cuáles son:

``` text
configuración/presentación
```

La implementación Drupal deberá utilizar entidades/configuración Drupal
cuando sea apropiado.

------------------------------------------------------------------------

# 21. THE EVENTS CALENDAR

La auditoría proporcionada indica:

``` text
Eventos publicados: 0
Eventos futuros: 0
Eventos borrador: 4

Organizadores publicados: 2

Lugares publicados: 193
Lugares borrador: 1

Eventos importados: 0
```

Esto requiere análisis.

No conviertas automáticamente los 193 lugares en contenido editorial.

Investiga:

-   si están referenciados;
-   si existen relaciones;
-   si forman parte de eventos históricos;
-   si son registros huérfanos;
-   si existe contenido visible que dependa de ellos.

------------------------------------------------------------------------

# 22. WP FORMS / CONTACT FORMS

La auditoría indica:

``` text
WPForms Lite
Formularios: 2
Envíos visibles desde 1.5.0: 0
```

También existe Contact Form 7.

No asumas que "0 submissions" significa "el formulario nunca fue usado".

Verifica:

-   formularios configurados;
-   destinatarios;
-   campos;
-   URLs;
-   contenido visible;
-   tablas;
-   integraciones;
-   configuración SMTP.

En Drupal, decidir si corresponde:

``` text
Webform
```

u otra solución.

------------------------------------------------------------------------

# 23. YOAST / SEO

Audita:

-   títulos SEO;
-   meta descriptions;
-   canonical;
-   robots;
-   Open Graph;
-   Twitter cards;
-   sitemap;
-   schema;
-   focus keyphrase;
-   primary term;
-   breadcrumbs;
-   redirects.

No migres el plugin Yoast.

Migra los **datos SEO que tengan valor**.

Debe existir una estrategia explícita para:

``` text
URL vieja → URL Drupal
```

------------------------------------------------------------------------

# 24. URLS: REQUISITO CRÍTICO

El WordPress usa:

``` text
/%postname%/
```

La preservación de URLs es prioritaria.

Construye un mapa:

``` text
wp_post_id
wp_post_type
wp_post_status
old_slug
old_url
drupal_entity_id
new_url
migration_status
migration_hash
validation_status
notes
```

Idealmente conservar la URL siempre que sea técnicamente viable.

Cuando una URL cambie:

``` text
301
```

debe ser parte explícita de la migración.

Nunca borres URLs históricas simplemente porque "Drupal puede generar
otras".

------------------------------------------------------------------------

# 25. MEDIA Y `/uploads`: PROBLEMA CRÍTICO

Producción reporta:

``` text
/wp-content/uploads
≈ 84.23 GB
```

Pero la copia local entregada **NO representa necesariamente los 84.23
GB completos**.

La copia local tiene solamente una parte del árbol de uploads.

Por lo tanto:

> **No puedes declarar completa la migración de imágenes solamente
> porque el WordPress local tiene algunos archivos.**

Hay archivos organizados por años y también directorios relacionados con
componentes históricos.

Además se observaron múltiples archivos que parecen ser la misma imagen
en diferentes dimensiones, por ejemplo:

``` text
68x200
100x120
120x150
...
```

Esto probablemente corresponde a derivados generados por WordPress.

Pero:

> **No elimines esos archivos hasta comprobar sus relaciones.**

Audita:

``` text
dc8_posts
dc8_postmeta
attachments
guid
_wp_attached_file
_wp_attachment_metadata
foto1
contenido HTML
Elementor
sliders
```

### Objetivo Drupal

Siempre que sea posible:

``` text
imagen original
        ↓
Drupal Media
        ↓
Drupal Image Style
```

No crear una Media independiente para cada thumbnail generado por
WordPress si son derivados del mismo original.

Pero esta decisión debe estar respaldada por la auditoría de relaciones.

------------------------------------------------------------------------

# 26. MEDIA: NO PERDER RUTAS HISTÓRICAS

Para cada archivo relevante, registrar:

``` text
wp_attachment_id
archivo original
ruta relativa
URL original
hash SHA-256
mime
dimensiones
tamaño
referencias
drupal_media_id
nueva ruta
estado
```

Usar SHA-256 cuando sea viable para demostrar integridad.

------------------------------------------------------------------------

# 27. AUTORES Y USUARIOS

WordPress reporta:

``` text
162 usuarios
```

No significa que debamos crear 162 usuarios Drupal automáticamente.

Separar:

``` text
usuario técnico/editor Drupal
```

de:

``` text
autor editorial histórico
```

Si una noticia tiene como autor "X" y X ya no necesita una cuenta
Drupal, preservar el nombre como entidad/campo editorial.

No importar contraseñas ni secretos innecesariamente.

Nunca exponer hashes o credenciales en reportes.

------------------------------------------------------------------------

# 28. COMENTARIOS

El sitio reporta miles de comentarios históricos.

La auditoría inicial del panel indicó aproximadamente:

``` text
6,346 comentarios en moderación
```

Pero esta cifra debe validarse directamente contra SQL.

Auditar:

-   publicados;
-   pendientes;
-   spam;
-   trash;
-   autores;
-   fechas;
-   contenido;
-   relaciones con posts;
-   metadatos.

No descartarlos sin autorización.

------------------------------------------------------------------------

# 29. MODELO DE CONTENIDO DRUPAL

Antes de migrar masivamente, definir explícitamente:

### Content Types

Ejemplos a investigar:

``` text
Noticia
Página
Agenda / Evento
```

No crear content types solamente porque exista un `post_type`.

La decisión debe basarse en:

-   función editorial;
-   campos;
-   workflow;
-   presentación;
-   relaciones;
-   URLs.

### Campos

Investigar al menos:

``` text
Título
Subtítulo / balazo
Cita
Cuerpo
Imagen principal
Autor editorial
Fecha publicación
Fecha modificación
Sección
Subsección
Categorías
Etiquetas
SEO
Archivos
Galerías
Enlaces
```

No inventar campos que no tengan justificación.

------------------------------------------------------------------------

# 30. TAXONOMÍAS

Auditar:

``` text
dc8_terms
dc8_term_taxonomy
dc8_term_relationships
dc8_termmeta
```

Construir mapa:

``` text
WordPress taxonomy
↓
Drupal vocabulary
↓
Drupal term
```

Preservar:

-   nombre;
-   slug;
-   jerarquía;
-   relaciones;
-   IDs de origen cuando sean necesarios para trazabilidad.

------------------------------------------------------------------------

# 31. ARQUITECTURA DE MIGRACIÓN RECOMENDADA

La estrategia preferida es:

``` text
WordPress
   ↓
auditoría
   ↓
staging / extracción controlada
   ↓
transformaciones explícitas
   ↓
Drupal Migrate API / procesos custom
   ↓
entidades Drupal
   ↓
validación
   ↓
revisión visual
   ↓
pilot migration
   ↓
migración completa
   ↓
sincronización final
   ↓
cutover
```

No usar SQL directo para escribir internals de Drupal salvo que exista
una razón excepcional, documentada y revisada.

Preferir:

-   Migrate API;
-   source plugins;
-   process plugins;
-   migration groups;
-   migration dependencies;
-   entity APIs;
-   config export/import;
-   scripts reproducibles.

------------------------------------------------------------------------

# 32. TRES ESTRATEGIAS POSIBLES

## SOLUCIÓN A --- Drupal Migrate API + ETL controlado

### Descripción

Construir fuentes de migración desde WordPress/SQL y transformarlas a
entidades Drupal mediante Migrate API.

### Ventajas

-   reproducible;
-   auditable;
-   rollback lógico;
-   puede ejecutarse por lotes;
-   permite mapear IDs;
-   facilita reintentos;
-   permite validación;
-   mantiene separación entre fuente y destino.

### Riesgos

-   requiere diseño cuidadoso;
-   Elementor necesita transformación especial;
-   media incompleta puede bloquear validación;
-   encoding debe resolverse antes.

### Prioridad

**ESTRATEGIA PRINCIPAL RECOMENDADA.**

------------------------------------------------------------------------

## SOLUCIÓN B --- ETL a staging intermedio + Migrate API

### Descripción

Extraer WordPress a tablas/JSON/CSV normalizados:

``` text
wp_posts
wp_postmeta
taxonomías
media
usuarios
comentarios
SEO
```

y luego importar desde staging a Drupal.

### Ventajas

-   excelente trazabilidad;
-   facilita auditorías;
-   desacopla WordPress de Drupal;
-   permite corregir transformaciones sin tocar la fuente;
-   muy útil para un sitio legado complejo.

### Riesgos

-   más infraestructura;
-   más espacio;
-   más archivos;
-   mayor complejidad operativa.

### Cuándo usarla

Si la auditoría demuestra que WordPress tiene estructuras
suficientemente complejas para que una fuente directa sea difícil de
mantener.

Puede combinarse con Solución A.

------------------------------------------------------------------------

## SOLUCIÓN C --- Migración directa SQL / scripts ad-hoc

### Descripción

Leer tablas WordPress y escribir directamente en tablas Drupal.

### Ventajas

-   aparentemente rápida para prototipos;
-   puede mover grandes volúmenes rápidamente.

### Riesgos

-   rompe abstracciones de Drupal;
-   frágil ante cambios de schema;
-   difícil de mantener;
-   difícil de revertir;
-   alto riesgo de relaciones incompletas;
-   puede generar entidades inconsistentes;
-   dificulta auditoría.

### Regla

**No utilizar como estrategia principal.**

Solo considerar scripts SQL para auditoría, staging o casos puntuales
cuidadosamente documentados.

------------------------------------------------------------------------

# 33. ROADMAP CONTRACTUAL

## FASE 0 --- CONTROL DEL PROYECTO

-   [ ] Confirmar raíz del proyecto.
-   [ ] Confirmar Git.
-   [ ] Confirmar remote.
-   [ ] Crear `MIGRATION_CONTRACT.md`.
-   [ ] Crear estructura de reportes.
-   [ ] Crear agente auditor.
-   [ ] Crear reglas de commits.
-   [ ] Confirmar que producción no será modificada.

### Gate

No avanzar si Git/entorno no está controlado.

------------------------------------------------------------------------

# FASE 1 --- INVENTARIO DEL WORDPRESS

-   [ ] Inventariar filesystem.
-   [ ] Inventariar plugins.
-   [ ] Inventariar tema.
-   [ ] Inventariar uploads.
-   [ ] Inventariar dump.
-   [ ] Inventariar tablas.
-   [ ] Inventariar tipos de contenido.
-   [ ] Inventariar estados.
-   [ ] Inventariar autores.
-   [ ] Inventariar taxonomías.
-   [ ] Inventariar comentarios.
-   [ ] Inventariar postmeta.
-   [ ] Inventariar Elementor.
-   [ ] Inventariar SEO.
-   [ ] Inventariar formularios.
-   [ ] Inventariar eventos.
-   [ ] Inventariar sliders.
-   [ ] Inventariar configuraciones relevantes.

### Entregable

``` text
reports/audit/wordpress-inventory.md
```

### Gate

No avanzar sin inventario reproducible.

------------------------------------------------------------------------

# FASE 2 --- AUDITORÍA DE BASE DE DATOS

-   [ ] Cargar dump en BD de auditoría.
-   [ ] Confirmar tablas.
-   [ ] Confirmar charset.
-   [ ] Confirmar collation.
-   [ ] Confirmar engine.
-   [ ] Confirmar conteos.
-   [ ] Confirmar post types.
-   [ ] Confirmar estados.
-   [ ] Confirmar autores.
-   [ ] Confirmar taxonomías.
-   [ ] Confirmar comentarios.
-   [ ] Confirmar attachments.
-   [ ] Confirmar metadatos.
-   [ ] Confirmar Elementor.
-   [ ] Confirmar Yoast.
-   [ ] Confirmar Events Calendar.
-   [ ] Confirmar formularios.

### Gate CRÍTICO

No continuar si existe incertidumbre sobre encoding.

------------------------------------------------------------------------

# FASE 3 --- AUDITORÍA DE MEDIA

-   [ ] Determinar si uploads local está completo.
-   [ ] Comparar con reporte de 84.23 GB.
-   [ ] Detectar derivados.
-   [ ] Relacionar attachments con archivos.
-   [ ] Calcular hashes.
-   [ ] Identificar archivos huérfanos.
-   [ ] Identificar archivos referenciados.
-   [ ] Identificar media de Elementor.
-   [ ] Identificar media de sliders.
-   [ ] Diseñar migración Media.

### Gate

Si falta acceso al uploads completo de producción:

``` text
BLOCKED — migración completa de media
```

No declarar migración completa.

------------------------------------------------------------------------

# FASE 4 --- DISEÑO DEL MODELO DRUPAL

-   [ ] Revisar plantilla.
-   [ ] Revisar `drudg8b3`.
-   [ ] Revisar `udg_liston`.
-   [ ] Revisar regiones.
-   [ ] Revisar menús.
-   [ ] Revisar Views.
-   [ ] Diseñar content types.
-   [ ] Diseñar fields.
-   [ ] Diseñar taxonomías.
-   [ ] Diseñar Media.
-   [ ] Diseñar autores.
-   [ ] Diseñar eventos.
-   [ ] Diseñar SEO.
-   [ ] Diseñar redirects.
-   [ ] Diseñar comentarios.

### Gate

El modelo debe ser aprobado antes de migración masiva.

------------------------------------------------------------------------

# FASE 5 --- PILOTO

No migrar todo inmediatamente.

Seleccionar una muestra representativa:

-   noticia sencilla;
-   noticia con imagen;
-   noticia con múltiples imágenes;
-   noticia antigua;
-   noticia con caracteres especiales;
-   contenido con Elementor;
-   contenido con metadata;
-   contenido con taxonomía;
-   contenido con autor;
-   página;
-   evento;
-   contenido con archivo;
-   contenido con URL especial.

Para cada registro:

``` text
WordPress
→ Drupal
→ comparación
```

### Gate

100% de los casos piloto deben tener resultado conocido.

------------------------------------------------------------------------

# FASE 6 --- MIGRACIÓN DE MEDIA

-   [ ] Migrar originales.
-   [ ] Crear Media.
-   [ ] Crear relaciones.
-   [ ] Resolver Image Styles.
-   [ ] Validar hashes.
-   [ ] Validar URLs.
-   [ ] Validar referencias HTML.
-   [ ] Validar Elementor transformado.

------------------------------------------------------------------------

# FASE 7 --- MIGRACIÓN DE TAXONOMÍAS

-   [ ] Migrar vocabularios.
-   [ ] Migrar términos.
-   [ ] Preservar jerarquías.
-   [ ] Preservar slugs.
-   [ ] Crear mapa de IDs.

------------------------------------------------------------------------

# FASE 8 --- MIGRACIÓN DE AUTORES

-   [ ] Clasificar usuarios.
-   [ ] Separar cuentas Drupal de autores históricos.
-   [ ] Preservar atribución editorial.
-   [ ] Evitar importar credenciales innecesarias.

------------------------------------------------------------------------

# FASE 9 --- MIGRACIÓN DE CONTENIDO

Migrar por lotes.

Cada lote debe producir:

``` text
source_count
created_count
updated_count
failed_count
skipped_count
```

Nunca continuar si:

``` text
failed_count > 0
```

sin explicar y aprobar el tratamiento.

------------------------------------------------------------------------

# FASE 10 --- SEO Y URLS

-   [ ] Generar mapa old → new.
-   [ ] Preservar slugs.
-   [ ] Crear redirects.
-   [ ] Migrar metadata.
-   [ ] Revisar canonical.
-   [ ] Revisar sitemap.
-   [ ] Revisar robots.
-   [ ] Revisar Open Graph.
-   [ ] Revisar schema.
-   [ ] Revisar enlaces internos.

------------------------------------------------------------------------

# FASE 11 --- RECONSTRUCCIÓN VISUAL

El sitio Drupal debe parecerse sustancialmente al Gaceta original en:

-   jerarquía;
-   navegación;
-   portada;
-   noticias;
-   categorías;
-   imágenes;
-   tarjetas;
-   encabezados;
-   pie;
-   elementos institucionales.

Pero debe utilizar la arquitectura del template Drupal institucional.

No copiar Newspaper.

No copiar CSS/JS propietario innecesario.

------------------------------------------------------------------------

# FASE 12 --- ACCESIBILIDAD

El template institucional contiene mecanismos de accesibilidad.

No eliminarlos.

La referencia entregada muestra elementos como:

``` text
Sepia
Grises
Invertir de color
+A
-A
Normal
```

y recursos de:

``` text
udg_liston
accesibilityUdg.js
```

Además, la plantilla usa elementos como:

``` text
skip-link
visually-hidden
ARIA
```

Mantener y mejorar accesibilidad.

No sacrificar accesibilidad por parecido visual.

------------------------------------------------------------------------

# FASE 13 --- VALIDACIÓN AUTOMÁTICA

Crear scripts/reportes para comparar:

``` text
WordPress vs Drupal
```

por:

-   total de contenido;
-   contenido por tipo;
-   contenido por estado;
-   autores;
-   taxonomías;
-   media;
-   fechas;
-   URLs;
-   comentarios;
-   archivos;
-   errores;
-   referencias rotas.

------------------------------------------------------------------------

# FASE 14 --- VALIDACIÓN VISUAL

Comparar continuamente:

``` text
https://www.gaceta.udg.mx
```

con la implementación Drupal.

La referencia visual debe usarse para observar:

-   portada;
-   menú;
-   encabezado;
-   navegación;
-   listados;
-   noticias;
-   detalle;
-   footer;
-   responsive;
-   imágenes;
-   banners.

### Regla

La web actual es una **referencia**, no una fuente para copiar código
propietario o modificar producción.

Solo lectura.

No ejecutar acciones destructivas.

------------------------------------------------------------------------

# FASE 15 --- PRUEBAS

Mínimo:

``` text
PHP
Drupal
Composer
Drush
Migrate
Twig
CSS
JS
URLs
Media
SEO
Accessibility
Responsive
Security
Performance
```

También:

-   enlaces internos;
-   enlaces externos;
-   formularios;
-   búsquedas;
-   filtros;
-   menús;
-   paginación;
-   archivos;
-   imágenes;
-   fechas;
-   autores;
-   categorías.

------------------------------------------------------------------------

# FASE 16 --- SINCRONIZACIÓN FINAL

Como WordPress sigue siendo el sitio editorial de origen:

``` text
WordPress continúa publicando
```

hasta el cutover.

Por tanto:

``` text
snapshot inicial
↓
migración
↓
diferencias nuevas
↓
sincronización final
↓
validación
↓
cutover
```

No declares terminado el proyecto simplemente porque una copia anterior
fue migrada.

------------------------------------------------------------------------

# 34. CRITERIOS DE ACEPTACIÓN

La migración no se considera terminada hasta cumplir:

## Datos

-   [ ] Conteos conciliados.
-   [ ] Fallos explicados.
-   [ ] Taxonomías conciliadas.
-   [ ] Autores conciliados.
-   [ ] Media conciliada.
-   [ ] Comentarios tratados.
-   [ ] Elementor tratado.
-   [ ] Encoding validado.

## URLs

-   [ ] URLs preservadas cuando corresponde.
-   [ ] Redirects implementados.
-   [ ] No existen pérdidas silenciosas de rutas.

## Visual

-   [ ] Portada validada.
-   [ ] Listado validado.
-   [ ] Detalle validado.
-   [ ] Menú validado.
-   [ ] Footer validado.
-   [ ] Responsive validado.

## Funcional

-   [ ] Búsqueda.
-   [ ] Filtros.
-   [ ] Formularios.
-   [ ] Media.
-   [ ] Archivos.
-   [ ] Eventos si corresponden.

## Seguridad

-   [ ] No existen secretos en Git.
-   [ ] No existen credenciales heredadas.
-   [ ] Configuración Drupal validada.
-   [ ] Permisos revisados.
-   [ ] Dependencias revisadas.

## Auditoría

-   [ ] Todos los lotes tienen reporte.
-   [ ] Existe mapa de trazabilidad.
-   [ ] Existe registro de errores.
-   [ ] Existe evidencia de validación.
-   [ ] Auditor aprobó todas las fases críticas.

------------------------------------------------------------------------

# 35. TRAZABILIDAD OBLIGATORIA

Crear una matriz similar a:

``` text
wp_post_id
wp_post_type
wp_post_status
wp_slug
old_url
drupal_entity_type
drupal_entity_id
new_url
migration_batch
migration_status
migration_hash
validation_status
validation_date
notes
```

La migración debe poder responder:

> "¿Dónde terminó exactamente este registro WordPress?"

------------------------------------------------------------------------

# 36. HASH DE MIGRACIÓN

Para registros importantes generar un hash de contenido normalizado.

Por ejemplo:

``` text
SHA-256(
  title +
  body +
  author +
  date +
  slug +
  relevant_metadata
)
```

No utilizar un hash arbitrario.

Documentar exactamente qué campos entran al hash.

------------------------------------------------------------------------

# 37. REGLAS SOBRE DATOS SENSIBLES

Nunca colocar en:

``` text
Git
commits
PRs
logs
reports públicos
```

-   contraseñas;
-   tokens;
-   API keys;
-   cookies;
-   credenciales;
-   hashes de contraseñas;
-   secretos SMTP;
-   archivos JSON de service accounts;
-   claves privadas.

Si aparecen durante la auditoría:

``` text
REDACTED
```

en los reportes.

------------------------------------------------------------------------

# 38. REGLAS DE PRODUCCIÓN

Estas acciones están prohibidas sin autorización explícita:

``` text
DELETE producción
UPDATE producción
INSERT producción
ALTER producción
migración sobre producción
cambio de tema producción
cambio de plugins producción
limpieza de uploads producción
```

El acceso al WordPress actual es principalmente para:

``` text
lectura
```

El usuario indica que puede ver el panel administrativo pero **no puede
modificar el WordPress de producción**.

Respeta esa limitación.

------------------------------------------------------------------------

# 39. QUÉ HACER SI FALTA INFORMACIÓN

Usa este formato:

``` text
DECISIÓN REQUERIDA

Contexto:
...

Problema:
...

Datos confirmados:
...

Información faltante:
...

Opción A:
...

Opción B:
...

Riesgo:
...

Recomendación técnica neutral:
...

Pregunta:
...
```

No continúes con una decisión irreversible.

------------------------------------------------------------------------

# 40. PROTOCOLO DE ERROR

Cuando algo falle:

1.  no ocultes el error;
2.  no cambies cinco cosas simultáneamente;
3.  reproduce;
4.  captura mensaje;
5.  identifica causa;
6.  crea prueba;
7.  corrige;
8.  vuelve a ejecutar;
9.  documenta.

Nunca "arregles" una migración eliminando datos que provocaron el error.

------------------------------------------------------------------------

# 41. ARCHIVOS DE DOCUMENTACIÓN QUE DEBES CREAR

Como mínimo:

``` text
CLAUDE.md
MIGRATION_CONTRACT.md

docs/
  architecture.md
  content-model.md
  migration-strategy.md
  media-strategy.md
  seo-strategy.md
  url-strategy.md
  accessibility.md
  deployment.md

reports/
  audit/
  migration/
  validation/

reports/audit/
  wordpress-inventory.md
  database-inventory.md
  encoding-audit.md
  media-inventory.md
  plugin-inventory.md
  elementor-audit.md

reports/migration/
  migration-batches.md
  migration-errors.md
  traceability.md

reports/validation/
  content-comparison.md
  media-comparison.md
  url-comparison.md
  visual-validation.md
```

No crear archivos vacíos solamente para aparentar progreso.

------------------------------------------------------------------------

# 42. REGLA DE DOCUMENTACIÓN DE DECISIONES

Toda decisión importante debe registrar:

``` text
Fecha
Decisión
Problema
Alternativas
Razón técnica
Impacto
Riesgo
Quién autorizó
Commit
```

------------------------------------------------------------------------

# 43. PLANTILLA DRUPAL 10

La plantilla entregada contiene:

``` text
themes/drudg8b3
modules/custom/udg_liston
```

`udg_liston.info.yml` declara:

``` yaml
core_version_requirement: ^8 || ^9 || ^10
name: UDG Listón
description: Crea un block administrativo para mostrar los listones.
package: UDG
type: module
dependencies:
    - block
    - entity_print
```

No cambiar esto arbitrariamente.

La referencia HTML de la plantilla muestra:

``` text
listón institucional
navegación
noticias
agenda
contacto
footer
accesibilidad
social media
banners
Views
```

También utiliza el tema:

``` text
drudg8b3
```

y recursos de:

``` text
udg_liston
```

La plantilla entregada utiliza una arquitectura visual basada en
Bootstrap 3.

**No actualices Bootstrap por iniciativa propia durante esta
migración**, salvo que exista una instrucción explícita posterior.

------------------------------------------------------------------------

# 44. NO DESTRUIR LA FUNCIONALIDAD DE LA PLANTILLA

El proyecto de Gaceta debe incorporar su contenido al template
institucional.

No convertir el template en una instalación WordPress disfrazada.

No reemplazar:

``` text
udg_liston
drudg8b3
Views
Drupal blocks
Drupal menus
Drupal entities
```

por hacks de HTML estático.

------------------------------------------------------------------------

# 45. OBSERVACIÓN IMPORTANTE SOBRE LA PLANTILLA

La referencia HTML contiene rutas bajo:

``` text
/egresados/
```

Eso corresponde al sitio de demostración/plantilla.

No copiar literalmente `/egresados/` al proyecto Gaceta.

Adaptar a:

``` text
/
```

o la estructura que determine el proyecto.

------------------------------------------------------------------------

# 46. INVESTIGACIÓN CONTINUA DEL SITIO ACTUAL

Durante el desarrollo, revisa periódicamente:

``` text
https://www.gaceta.udg.mx
```

para comparar:

-   estructura;
-   contenido;
-   navegación;
-   diseño;
-   comportamiento.

Hazlo en modo de observación.

No automatices acciones que puedan modificar el sitio.

No uses scraping agresivo.

No conviertas una observación visual en una decisión de modelo de datos
sin verificar el origen de la información.

------------------------------------------------------------------------

# 47. REGLA CONTRA LA ALUCINACIÓN

Si no sabes algo, escribe:

``` text
DESCONOCIDO
```

Si sospechas algo:

``` text
HIPÓTESIS
```

Si lo verificaste:

``` text
CONFIRMADO
```

Si existe evidencia contradictoria:

``` text
CONFLICTO
```

Ejemplo:

``` text
CONFIRMADO:
dc8_posts declara MyISAM/latin1.

CONFLICTO:
WordPress Site Health declara utf8mb4.

ACCIÓN:
auditar encoding real antes de transformación.
```

------------------------------------------------------------------------

# 48. DEFINICIÓN DE TERMINADO

El proyecto estará terminado únicamente cuando:

``` text
[ ] Todo el contenido definido como migrable fue migrado.
[ ] Todo contenido no migrado tiene explicación.
[ ] Toda media requerida fue migrada o está bloqueada por una dependencia externa documentada.
[ ] URLs validadas.
[ ] Redirects validados.
[ ] SEO validado.
[ ] Encoding validado.
[ ] Elementor tratado.
[ ] Autores tratados.
[ ] Taxonomías tratadas.
[ ] Comentarios tratados.
[ ] Formularios tratados.
[ ] Eventos tratados.
[ ] Búsqueda validada.
[ ] Diseño validado.
[ ] Accesibilidad validada.
[ ] Seguridad validada.
[ ] Performance validada.
[ ] Migración reproducible.
[ ] Trazabilidad completa.
[ ] Auditoría final aprobada.
[ ] PRs/commits documentados.
[ ] No existen BLOCKED pendientes.
```

------------------------------------------------------------------------

# 49. ORDEN DE EJECUCIÓN INICIAL

Al comenzar Claude Code, ejecuta **únicamente** esta secuencia:

### Paso 1

Identifica el workspace.

### Paso 2

Inspecciona Git.

### Paso 3

Inspecciona WordPress local.

### Paso 4

Inspecciona Drupal template.

### Paso 5

Lee los archivos de contexto proporcionados.

### Paso 6

Verifica si `gaceta_auditoria` está disponible.

### Paso 7

Audita la BD.

### Paso 8

Genera inventario.

### Paso 9

Crea:

``` text
MIGRATION_CONTRACT.md
```

### Paso 10

Presenta un reporte inicial antes de comenzar la migración.

**No empieces la migración masiva automáticamente.**

------------------------------------------------------------------------

# 50. PRIMER REPORTE QUE DEBES ENTREGAR

Debe tener:

``` text
ESTADO GENERAL

Workspace:
Git:
WordPress:
Drupal:
BD:
Media:
Encoding:
Elementor:
URLs:
SEO:
Taxonomías:
Usuarios:
Comentarios:
Formularios:
Eventos:

RIESGOS CRÍTICOS

1.
2.
3.

DECISIONES REQUERIDAS

1.
2.
3.

FASE ACTUAL

...

PRÓXIMO PASO

...
```

------------------------------------------------------------------------

# 51. PRINCIPIO FINAL

Este proyecto debe tratarse como una **migración de preservación de
información**, no como un simple rediseño.

La prioridad es:

``` text
1. Integridad de datos
2. Trazabilidad
3. Reproducibilidad
4. Preservación de URLs/SEO
5. Preservación de media
6. Funcionalidad
7. Accesibilidad
8. Seguridad
9. Fidelidad visual
10. Optimización
```

No sacrifiques los primeros puntos para acelerar los últimos.

Cuando exista una tensión entre:

``` text
“hacerlo rápido”
```

y:

``` text
“demostrar que no se perdió información”
```

elige el segundo enfoque y documenta el costo.

**No improvises. No destruyas. No ocultes errores. No marques tareas
como terminadas sin evidencia. Pregunta cuando el criterio no esté
definido.**

------------------------------------------------------------------------
