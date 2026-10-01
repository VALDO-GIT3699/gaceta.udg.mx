# Despliegue

Fecha: 2026-10-01
Requisito que atiende: CLAUDE.md §41

Cómo se lleva esta migración a un servidor real, y qué cosas del entorno local
**no** deben copiarse porque son parches a las rarezas de este equipo.

```text
ALCANCE: este documento describe el despliegue del sitio Drupal migrado. NO
describe ninguna operacion sobre el WordPress de produccion, que es de solo
lectura (D-07, §38).
```

## Requisitos del servidor

```text
PHP          8.2 o 8.3        (local: 8.2.12)
Extensiones  gd, pdo_mysql, mbstring, opcache, zip, xml, curl
MariaDB      10.4 o superior  (local: 10.4.32)
             o MySQL 8.0
Composer     2.x              (local: 2.7.8)
Drush        12.x             (local: 12.5.3.0)
Servidor web nginx o Apache con mod_rewrite
```

### GD tiene que estar habilitada de verdad

En este equipo GD está comentada en un `php.ini` compartido con otro proyecto,
así que se carga **por proceso** con `-d extension=gd` (decisión D-09). Eso es
un apaño local.

```text
EN EL SERVIDOR: habilitar gd en php.ini. Drupal la necesita para los Image
Styles, y sin ella las imagenes dan HTTP 500. No replicar el apano.
```

### OPcache: obligatoria, no opcional

```text
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=30000
opcache.validate_timestamps=0     <- en PRODUCCION
```

Medido en local sobre una página de artículo que **no** está en caché de
página:

| Configuración | 1ª petición | 2ª petición |
|---|---:|---:|
| Sin OPcache | muere a los 60 s | — |
| `validate_timestamps=1` | 41.9 s | 39.5 s |
| `validate_timestamps=0` | **3.2 s** | **0.031 s** |

```text
LECCION: medir el rendimiento solo sobre la portada da una lectura FALSA. La
portada respondia en 0.22 s y parecia que todo iba bien, pero eso era la cache
de pagina anonima sirviendo HTML ya generado.
```

En local el cuello de botella es OneDrive: con la revalidación activada, PHP
hace `stat` de miles de archivos en cada petición. En un servidor normal OPcache
viene activada por defecto, así que esto deja de ser un problema; se documenta
para que nadie repita la medición mal.

```text
CONTRAPARTIDA de validate_timestamps=0: el servidor NO detecta cambios en el
codigo. Tras desplegar hay que recargar PHP-FPM o vaciar OPcache. En un entorno
de desarrollo, dejarlo en 1.
```

## Bases de datos

Hacen falta **dos**, y no deben mezclarse (§12):

```text
webPlantillaUDGD10   el sitio Drupal. Escribe aqui.
gaceta_auditoria     el staging de WordPress. SOLO LECTURA.
```

### El usuario de staging es de sólo lectura por diseño

```sql
GRANT SELECT ON gaceta_auditoria.* TO 'gaceta_ro'@'localhost';
```

Verificado: lee, y al intentar `CREATE TABLE` recibe `CREATE command denied`.

```text
NO es una buena intencion, es una garantia estructural: la migracion no puede
corromper el origen ni por un error de programacion.
```

Su contraseña vive sólo en `sites/default/settings.php`, que está **excluido de
Git** (§37). En el servidor hay que crearla a mano o inyectarla por variable de
entorno. Nunca versionarla: el remoto es público (D-06).

### Reconstruir `gaceta_auditoria` desde cero

```bash
python tools/extract-tables.py --tabla dc8_posts | mysql gaceta_auditoria
python tools/extract-tables.py --tabla dc8_postmeta | mysql gaceta_auditoria
# ... y el resto de tablas dc8_*
```

Se extrae por etapas y se canaliza a `mysql` sin escribir el archivo
intermedio, porque `dc8_postmeta` son 2.3 GB y el disco local tiene 14 libres.
El extractor **incluye las 131 sentencias `ALTER TABLE`** del final del volcado:
phpMyAdmin volcó las estructuras sin claves ni índices y las dejó al final del
archivo. Sin ellas las tablas se cargan desnudas y todo va lentísimo.

```text
El dump original NUNCA se modifica. Se abre siempre en modo lectura (§7, §12).
```

## Orden de despliegue

El orden importa: las migraciones tienen dependencias entre sí.

```bash
# 1. Dependencias
composer install --no-dev --optimize-autoloader

# 2. Modelo de contenido. Idempotente: se puede repetir sin dano.
drush php:script tools/setup-content-model.php
drush php:script tools/setup-noticia-display.php

# 3. Taxonomias y creditos PRIMERO. Las noticias los referencian por
#    migration_lookup, asi que si no existen, las referencias quedan vacias.
drush migrate:import gaceta_seccion
drush migrate:import gaceta_subseccion
drush migrate:import gaceta_credito
drush migrate:import gaceta_etiqueta

# 4. Contenido
drush migrate:import gaceta_pagina
bash tools/migrar-noticias-por-lotes.sh 1000 60

# 5. Verificar que no se perdieron rutas en silencio
drush php:script tools/validar-alias-unicos.php

# 6. Cache
drush cache:rebuild
```

```text
EL PASO 5 NO ES OPCIONAL. Drupal no impone unicidad en path_alias: acepta dos
alias identicos y resuelve solo uno. La migracion puede terminar con 0 errores
y los conteos cuadrando mientras cientos de nodos quedan sin ruta accesible.
Es el modo de fallo de §24 y ningun conteo lo detecta. Ver D-24.
```

## Los archivos de media

```text
B-02: a fecha de hoy falta recibir unos 40 GB de /wp-content/uploads. La
cobertura medida es del 31.1 %. Ver reports/audit/media-inventory.md.
```

Cuando lleguen, el árbol se copia **una vez**, preservando la estructura de
carpetas por año y mes:

```text
<donde esten>/uploads/2019/05/foto.jpg
        ->  sites/default/files/migrado/2019/05/foto.jpg
```

Las rutas de destino del manifiesto ya apuntan ahí, así que **no hay que tocar
las fotos donde están** ni volver a migrar contenido: sólo copiar y crear las
entidades Media contra esas rutas.

Al copiar desde un volumen de macOS aparecen archivos `._nombre` de 4096 bytes:
son la bifurcación de recursos del sistema de archivos de origen, no contenido.

```text
Excluirlos al copiar. En el inventario recibido eran 214 426 de 428 566 lineas,
casi la mitad, y hacian parecer que habia 82 GB donde hay 44.
```

## Reversibilidad

Todo paso de esta migración se deshace:

```bash
drush migrate:rollback gaceta_noticia      # deshace el contenido
drush migrate:rollback gaceta_etiqueta     # etc.
```

```text
El dump original          no se modifica nunca.
gaceta_auditoria          se reconstruye con tools/extract-tables.py.
La base de Drupal         se restaura con backup_migrate, ya instalado.
Cada migracion            se revierte con migrate:rollback.
El modelo de contenido    se recrea con setup-content-model.php, idempotente.
Los alias                 se rehacen reimportando. Por eso el esquema de
                          desambiguacion de D-24 sigue siendo reversible
                          mientras no haya cutover.
```

```text
Ninguna operacion de este despliegue escribe en el WordPress de produccion.
```

## Trampa conocida: vaciar la caché no basta

Tras instalar o activar módulos, en este entorno apareció dos veces un
HTTP 500 con clases que **sí existían en disco**:

```text
Class "Drupal\metatag\Plugin\Field\MetatagEntityFieldItemList" not found
```

`class_exists()` devolvía TRUE desde drush. `drush cache:rebuild` **no lo
arreglaba**, y reiniciar el servidor tampoco. Lo arregló vaciar las tablas de
caché a mano:

```sql
TRUNCATE cache_container;   -- la decisiva
TRUNCATE cache_bootstrap;
TRUNCATE cache_config;
TRUNCATE cache_discovery;
-- y el resto de cache_*
```

```text
CAUSA: cache_container guarda el contenedor de servicios compilado, con los
namespaces de los modulos. drush cache:rebuild NO lo purga, asi que la peticion
web seguia usando un contenedor sin el namespace del modulo nuevo.
```

```text
REGLA: tras instalar modulos, vaciar cache_container ademas de cache:rebuild.
Es la leccion operativa de D-20.
```

## Antes del cutover

```text
[ ] Recibir y copiar los ~40 GB de uploads que faltan (B-02).
[ ] Resolver las decisiones abiertas de URLs: D-24 el sufijo, D-25 /inicio.
[ ] Ejecutar tools/validar-alias-unicos.php y obtener PASS.
[ ] Conciliar conteos WordPress contra Drupal (FASE 13).
[ ] Crear las 7 519 redirecciones 301 y los 7 811 slugs historicos.
[ ] Migrar los metadatos SEO desde las plantillas de Yoast.
[ ] Sincronizacion final: WordPress sigue publicando hasta el cutover (FASE 16).
[ ] Dictamen del auditor. El ultimo fue NO AUTORIZADO y requiere uno nuevo.
```

```text
WordPress sigue siendo el sitio editorial hasta el cutover. Una copia migrada
de hace semanas NO es el sitio terminado: hay que sincronizar las diferencias
nuevas antes de cambiar los DNS (§33 FASE 16).
```
