# Arquitectura del proyecto

Fecha: 2026-10-01
Requisito que atiende: CLAUDE.md §41

Describe cómo están dispuestas las piezas y, sobre todo, **por qué**. Las
decisiones que la sostienen están en `docs/decisiones.md`.

## Vista general del flujo de datos

```text
WordPress de PRODUCCIÓN  (https://www.gaceta.udg.mx)
    |
    |  SÓLO LECTURA. Nunca se escribe. Confirmado en D-07.
    |  Se consulta para comparar (§46), nada más.
    v
dump entregado  wp/DB/gaceta (2).sql   3 544 549 259 bytes
    |
    |  FUENTE DE VERDAD. Nunca se modifica (§7, §12).
    |  Se abre siempre en modo lectura.
    v
tools/extract-tables.py   (extracción por etapas)
    |
    v
gaceta_auditoria   (MariaDB local, 67 tablas, 2.89 GB)
    |
    |  STAGING. Aislada de producción y de Drupal.
    |  Acceso desde Drupal con el usuario gaceta_ro: SELECT y nada más.
    v
modules/custom/gaceta_migrate   (Migrate API)
    |
    v
<BD_DRUPAL>   (Drupal 10.6.9, 208 tablas)
    |
    v
validación -> reports/validation/
```

## Por qué hay una base de staging y no se lee el dump directamente

El dump es un archivo de texto de 3.3 GiB. Consultarlo exigiría un parser, y
CLAUDE.md §13 y §14 lo prohíben expresamente: `post_content` contiene comas,
comillas, escapes, HTML, JSON y saltos de línea, de modo que cualquier parser
casero desplaza columnas y produce cifras falsas. El contrato declara **no
confiables** las cifras obtenidas así.

```text
Las cifras del proyecto salen de SQL real contra gaceta_auditoria.
```

`gaceta_auditoria` cumple además la función de *staging* que CLAUDE.md §32
describe en la Solución B, sin necesidad de construir un segundo staging con
CSV o JSON.

## Por qué la carga es por etapas

El disco tiene 14 GB libres de 476 y el dump son 3.54 GB. Al medir qué hay
dentro apareció que tres tablas concentran el 98.3 %:

```text
dc8_postmeta     2.3 GB    68.28 %
dc8_posts      772.3 MB    22.85 %
dc8_post_views 241.8 MB     7.15 %   (estadísticas, no contenido)
```

De ahí `tools/extract-tables.py`, que extrae subconjuntos y admite volcar a
stdout para canalizar a `mysql` sin escribir el archivo intermedio. Con eso se
cargó `dc8_postmeta` sin gastar 2.3 GB de disco adicionales.

El extractor incluye las sentencias `ALTER TABLE` de la sección final del
volcado. Sin ellas las tablas se cargarían sin claves ni índices: phpMyAdmin
volcó las estructuras desnudas y dejó 131 `ALTER TABLE` al final del archivo.

## Por qué el usuario de base de datos es de sólo lectura

```text
gaceta_ro@localhost: GRANT SELECT ON gaceta_auditoria.*
```

Verificado: lee, y al intentar `CREATE TABLE` recibe
`CREATE command denied`.

Es una garantía estructural, no una buena intención: **la migración no puede
corromper el origen ni por error de programación**. Su contraseña vive sólo en
`sites/default/settings.php`, que está excluido de Git (§37).

## Los dos repositorios, y por qué están separados

```text
gaceta/                              repo del PROYECTO  -> remoto público
├── CLAUDE.md, MIGRATION_CONTRACT.md
├── docs/        estrategias y decisiones
├── reports/     evidencia de auditoría y validación
├── tools/       herramientas reproducibles
├── work/        manifiestos con contenido editorial  (EXCLUIDO de Git)
├── wp/          copia de WordPress y el dump          (EXCLUIDO de Git)
└── plantilla_drupal/Drudg10.6.9/    repo del SITIO    (EXCLUIDO de Git)
```

El sitio Drupal conserva su **propio** repositorio, con el historial del
template institucional de la UDG (ramas `master`, `dev`, `dev2`, `dev3`). Está
excluido del repositorio del proyecto para no crear un enlace de submódulo
roto. Decisión D-06.

```text
PENDIENTE: integrarlo preservando su historial con git subtree. No se hace
antes de purgar las credenciales de su historial (D-10), porque el remoto del
proyecto es público.
```

### Qué NO entra en el repositorio público

El remoto es público por decisión del responsable (D-06). Eso fija una
restricción permanente:

```text
work/      manifiestos con títulos y pies de foto
wp/        el dump completo, con todo el contenido y los usuarios
*.otf      tipografías comerciales sin licencia
API keys   de terceros, embebidas en JS del template
settings.php, services.yml, res-settings.php
```

En Git entran sólo los **resúmenes agregados**, sin texto de artículos.

## El sitio Drupal

```text
Drupal        10.6.9
Perfil        standard
Tema          drudg8b3 (Bootstrap 3, 26 regiones, 39 bloques)
Tema admin    claro
Módulo clave  udg_liston (listón institucional y accesibilidad)
Base          <BD_DRUPAL>, 208 tablas
```

`drudg8b3` y `udg_liston` son el material institucional y **no se sustituyen
por HTML estático** (§44). Bootstrap 3 no se actualiza por iniciativa propia
(§43).

La configuración de un tema inexistente, `udg_institucional`, permanece en la
base como configuración huérfana. Se desprecia por instrucción del responsable
(D-08). No afecta al sitio: Drupal sólo renderiza los bloques del tema activo.

### Módulos añadidos, y qué requisito cubre cada uno

```text
media, media_library   §25  imagen original -> Media -> Image Style
redirect               §24  las 7 519 redirecciones 301 de slugs históricos
metatag (+ OG, Twitter) §23  plantillas de título y los 912 valores manuales
migrate                §31  el motor
migrate_plus                origen SQL externo: el núcleo sólo lee de Drupal
migrate_tools               ejecución por lotes, estado y rollback
```

Los tres primeros de core sólo había que activarlos. Composer sólo bajó cuatro
contrib, de forma **puramente aditiva**: 4 instalaciones, 0 actualizaciones,
0 eliminaciones. Decisión D-20.

## El módulo de migración

```text
modules/custom/gaceta_migrate/
├── gaceta_migrate.info.yml
├── migrations/              definiciones, descubiertas por el núcleo
│   ├── gaceta_seccion.yml
│   ├── gaceta_subseccion.yml
│   ├── gaceta_credito.yml
│   └── gaceta_etiqueta.yml
└── src/Plugin/migrate/source/   orígenes SQL propios
```

Las definiciones viven en `migrations/` y no como entidades de configuración.
Motivo práctico: así se releen al reconstruir la caché, lo que permite iterar
sin importar y exportar configuración en cada cambio.

### Por qué hacen falta orígenes propios

Las columnas que importan de Gaceta **no existen en WordPress estándar**:

```text
seccion, subseccion, balazo, cita, foto1, original_id, term_id
```

Ninguna herramienta genérica de migración de WordPress las conoce. Sin estos
orígenes se perderían 24 672 secciones, 24 673 subsecciones, 13 914
antetítulos y 1 876 citas destacadas.

## Trazabilidad: dos mecanismos a propósito

```text
1. Tablas migrate_map_* de Migrate API. Automáticas, pero desaparecen con un
   migrate:reset o una reconstrucción.
2. Campos propios en la entidad: field_wp_post_id, field_wp_original_id,
   field_wp_user_id, field_wp_user_login.
```

El segundo hace la trazabilidad **independiente del estado de las
herramientas** y permite reconciliar conteos con SQL directo en la FASE 13.
`original_id` importa especialmente: 25 121 registros lo tienen, y es el
identificador del sistema anterior a WordPress.

## Reversibilidad

```text
El dump original nunca se modifica.
gaceta_auditoria se reconstruye con tools/extract-tables.py.
La base de Drupal se restaura con backup_migrate, ya instalado, o con el
  volcado previo que se tomó antes de instalar los módulos.
Cada migración se revierte con migrate:rollback.
El modelo de contenido se recrea con tools/setup-content-model.php, que es
  idempotente.
```

```text
Ninguna operación de esta arquitectura escribe en el WordPress de producción.
```

## Entorno local, y sus rarezas

```text
PHP        8.2.12   desde C:\xampp\php
MariaDB    10.4.32  desde C:\xampp8.2.12\mysql
Composer   2.7.8
Drush      12.5.3.0
```

Hay **tres** instalaciones de XAMPP y el proyecto usa dos: el PHP de una y el
MariaDB de otra. Funciona porque la conexión es por TCP, pero cualquier
procedimiento debe indicar qué binario usar.

GD está deshabilitada en el `php.ini` compartido del equipo. En lugar de
modificar esa configuración, que da servicio a otros proyectos, se carga **por
proceso** con `-d extension=gd`. Decisión D-09. En un servidor real GD debe
habilitarse de verdad: es requisito de Drupal.

El sitio se sirve con el servidor de desarrollo de PHP y el router oficial de
Drupal, no con Apache, para no tocar configuración compartida. **Con OPcache
activada**, que en este entorno no es opcional:

```bash
php -d extension=gd \
    -d zend_extension=opcache -d opcache.enable=1 \
    -d opcache.memory_consumption=256 -d opcache.max_accelerated_files=30000 \
    -d opcache.validate_timestamps=0 \
    -d realpath_cache_size=4096k -d realpath_cache_ttl=600 \
    -d max_execution_time=300 -d memory_limit=512M \
    -S 127.0.0.1:8093 .ht.router.php
```

```text
validate_timestamps=0 es la pieza decisiva, no la activación de OPcache.
```

Medido sobre la misma página de artículo, que NO está en caché de página:

| Configuración | 1ª petición | 2ª petición |
|---|---:|---:|
| Sin OPcache | muere a los 60 s | — |
| OPcache con `validate_timestamps=1` | 41.9 s | 39.5 s |
| OPcache con `validate_timestamps=0` | **3.2 s** | **0.031 s** |

Activar OPcache sola apenas mejoraba, porque con la revalidación activada
sigue haciendo `stat` de miles de archivos en cada petición, y eso es
precisamente lo que OneDrive hace lento.

```text
El cuello de botella no era parsear PHP: era el sistema de archivos.
```

```text
CONTRAPARTIDA: con validate_timestamps=0 el servidor NO detecta cambios en el
código y hay que reiniciarlo tras editar PHP. Es irrelevante para drush, que
corre en otro proceso.
```

```text
POR QUÉ OPCACHE ES OBLIGATORIA AQUÍ, y no una optimización.
```

Sin ella, cada petición vuelve a leer y parsear todos los archivos PHP de
Drupal desde OneDrive. Una página de nodo sin caché agotaba el límite de
ejecución:

```text
PHP Fatal error: Maximum execution time of 60 seconds exceeded
  en vendor/composer/ClassLoader.php
```

El síntoma era engañoso. La portada respondía en 0.22 s y parecía que todo iba
bien, pero eso era la **caché de página anónima** de Drupal sirviendo HTML ya
generado. En cuanto se pidió una página que no estaba en caché, el arranque
completo tardó más de 60 segundos y murió.

```text
LECCIÓN: medir el rendimiento sólo sobre la portada da una lectura falsa.
Hay que medir sobre una página NO cacheada.
```

En un servidor de producción OPcache está activada por defecto, así que esto es
una particularidad del entorno local y no un problema del proyecto.

```text
LECCIÓN OPERATIVA (D-20): tras instalar módulos, drush cache:rebuild NO basta
en este entorno. Hay que vaciar cache_container, o la petición web falla con
clases que sí existen en disco.
```

El proyecto reside en OneDrive, lo que hace lenta la compilación en frío de
Twig. No afecta a la integridad de los datos, sólo a los tiempos.
