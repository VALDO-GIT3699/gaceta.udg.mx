# Inventario del entorno de trabajo

Fecha: 2026-09-30
Fase del roadmap: FASE 0 (control del proyecto)

Todo lo marcado `CONFIRMADO` fue verificado por ejecución en este equipo.

## Workspace

```text
CONFIRMADO: C:\Users\acer\OneDrive\Documentos\gaceta
```

Contenido de primer nivel:

```text
CLAUDE.md              documento contractual
plantilla_drupal/      template institucional Drupal 10
wp/                    copia local del WordPress de Gaceta
```

La ruta coincide con la que anticipa CLAUDE.md §1.

```text
OBSERVACIÓN: el proyecto reside dentro de OneDrive.
```

Esto tiene consecuencias operativas reales, documentadas más abajo en
"Rendimiento del entorno". No afecta la integridad de los datos.

## Herramientas

```text
CONFIRMADO: PHP        8.2.12 (cli, ZTS, VC2019 x64)
CONFIRMADO: php.ini    C:\xampp\php\php.ini
CONFIRMADO: Composer   2.7.8
CONFIRMADO: Drush      12.5.3.0 (vendor/drush/drush)
CONFIRMADO: MariaDB    10.4.32 (cliente y servidor, C:\xampp8.2.12\mysql)
CONFIRMADO: Git        disponible
CONFIRMADO: gh         2.98.0
```

Hay **tres instalaciones de XAMPP** en el equipo: `C:\xampp`,
`C:\xampp8.1.17` y `C:\xampp8.2.12`. Esto es una fuente de error importante:

```text
CONFIRMADO: el PHP en PATH proviene de C:\xampp\php
CONFIRMADO: la base de datos de Drupal reside en C:\xampp8.2.12\mysql\data
```

Es decir, **PHP y MySQL provienen de instalaciones distintas**. Funciona
porque la conexión es por TCP a `localhost:3306`, pero cualquier
procedimiento que se documente debe indicar explícitamente qué binario usar.

### Extensiones PHP relevantes

```text
CONFIRMADO cargadas:    exif, mbstring, pdo_mysql, zip
CONFIRMADO NO cargadas: gd, opcache
```

## INCIDENCIA RESUELTA — GD deshabilitado

```text
CONFIRMADO: en C:\xampp\php\php.ini, línea 931, "extension=gd" está comentada.
```

Consecuencia: el *image toolkit* de Drupal quedaba sin implementación
disponible. `ImageToolkitManager::getDefaultToolkitId()` devolvía `NULL`, y
cualquier página que resolviera un *image style* terminaba en **HTTP 500**:

```text
PluginNotFoundException: The "" plugin does not exist.
Valid plugin IDs for Drupal\Core\ImageToolkit\ImageToolkitManager are: gd
  en ImageFactory->getSupportedExtensions()
  desde ImageStyle.php línea 390
```

Es importante notar que `system.image.toolkit` **sí valía `gd`** tanto en la
configuración exportada como en la base de datos. El problema no era de
configuración de Drupal, sino del intérprete: el plugin `gd` se descubre pero
`isAvailable()` es falso sin la extensión.

### Resolución aplicada

```text
DECISIÓN: no se modificó el php.ini global del equipo.
```

La extensión se habilita **por proceso**, mediante parámetro:

```bash
php -d extension=gd -d max_execution_time=0 -d memory_limit=512M \
    -S 127.0.0.1:8090 .ht.router.php
```

Motivo: `C:\xampp\php\php.ini` es configuración compartida de la máquina y da
servicio a otros proyectos (existe una base `webcsocial` ajena a Gaceta).
Habilitar GD globalmente sería un cambio fuera del alcance del proyecto y con
efectos en software de terceros. El parámetro por proceso es equivalente para
nuestro uso y **completamente reversible**.

```text
PENDIENTE: para desplegar en un servidor real, GD debe habilitarse en el
php.ini de ese servidor. Es requisito de Drupal, no una particularidad local.
```

## Base de datos de Drupal

```text
CONFIRMADO: base      <BD_DRUPAL>
CONFIRMADO: usuario   <BD_DRUPAL>@localhost
CONFIRMADO: host      localhost:3306
CONFIRMADO: driver    mysql (Drupal\mysql\Driver\Database\mysql)
CONFIRMADO: tablas    191
CONFIRMADO: conexión con el usuario de la aplicación: correcta
CONFIRMADO: Drupal bootstrap: Successful
```

La contraseña está en `sites/default/settings.php` y **no se reproduce aquí**
(CLAUDE.md §37). Ese archivo no debe versionarse.

### Por qué el nombre de la base aparece como `<BD_DRUPAL>`

```text
HALLAZGO DE SEGURIDAD: en este entorno el nombre de la base, el nombre del
usuario y la contraseña son la misma cadena de texto.
```

Consecuencia: **publicar el nombre de la base revela la contraseña**. Como el
repositorio del proyecto es público, la cadena se sustituye por `<BD_DRUPAL>`
en todos los archivos versionados. El valor real está en
`sites/default/settings.php`, que no se versiona.

Gravedad real: baja en local, porque el usuario está restringido a `localhost`
y la base no es accesible desde la red. Pero el mismo patrón en un servidor
expuesto sería una credencial trivialmente adivinable.

```text
RECOMENDACIÓN: usar credenciales distintas entre sí en el entorno de
despliegue. No se cambia nada en local: alterarlas rompería settings.php sin
beneficio de seguridad real en un entorno cerrado.
```

Bases presentes en el servidor:

```text
information_schema, mysql, performance_schema, phpmyadmin, test,
webcsocial, <BD_DRUPAL>
```

```text
CONFIRMADO: gaceta_auditoria NO existe en este equipo.
```

`webcsocial` es una base ajena a este proyecto. **No se toca.**

## Estado del arranque de MariaDB

El servicio no estaba en ejecución al iniciar. Se levantó con:

```bash
"C:/xampp8.2.12/mysql/bin/mysqld.exe" \
  --defaults-file="C:/xampp8.2.12/mysql/bin/my.ini" --standalone
```

Fue necesario eliminar un `mysql.pid` obsoleto del directorio de datos. Esa
es la **única** escritura realizada en el directorio de datos, y no afecta a
ninguna tabla.

## Repositorio remoto

```text
CONFIRMADO: git@github.com:VALDO-GIT3699/gaceta.udg.mx.git existe
CONFIRMADO: autenticación SSH correcta como VALDO-GIT3699
CONFIRMADO: rama por defecto: main
CONFIRMADO: historial: un único commit ("Initial commit")
CONFIRMADO: contenido: únicamente README.md (107 bytes)
CONFIRMADO: visibilidad: PÚBLICO
```

Se inspeccionó mediante un clon superficial en un directorio temporal, sin
escribir nada en el remoto (CLAUDE.md §2).

```text
RIESGO: el repositorio es público. Ver MIGRATION_CONTRACT.md, decisión D-06.
```

## Repositorio Git preexistente del template

```text
CONFIRMADO: existe .git en plantilla_drupal/Drudg10.6.9
CONFIRMADO: sin remotos configurados
CONFIRMADO: ramas: master, dev, dev2, dev3 (HEAD en dev3)
CONFIRMADO: 3 609 rutas con cambios sin registrar
CONFIRMADO: no existe .gitignore
```

Últimos commits (historial del template institucional, en español):

```text
0153cb7d Se actualizo el core a la version 10.5.3 se exporto la config
7b8187ee antes de hacer ajustes de mejora
d4a81bba Se actualizo el core a version 10.3.7 se exporto la config
```

Los 3 609 cambios corresponden a la actualización del core **de 10.5.3 a
10.6.9**, que fue aplicada en el sistema de archivos pero nunca registrada.

Dos consecuencias serias de la ausencia de `.gitignore`:

1. `sites/default/files/` **está versionado**, incluidos archivos subidos por
   usuarios (PDF e imágenes de demostración).
2. Sin reglas de exclusión, un `git add` accidental versionaría
   `sites/default/settings.php`, que contiene el `hash_salt` y la contraseña
   de la base de datos. En un repositorio **público** eso es una fuga.

```text
CONFIRMADO: settings.php y services.yml NO están versionados actualmente.
```

## Rendimiento del entorno

```text
CONFIRMADO: la compilación en frío de las plantillas Twig excede los 120 s
            de max_execution_time por defecto.
```

Con caché frío, la primera petición abortaba con:

```text
PHP Fatal error: Maximum execution time of 120 seconds exceeded
  en vendor/twig/twig/src/Node/Expression/ReturnStringInterface.php
```

Tiempos medidos tras habilitar GD y levantar el límite:

```text
Primera petición (compilando Twig):  22.2 s
```

Causa probable: latencia de E/S por la sincronización de OneDrive sobre
`vendor/` y `core/`, que contienen decenas de miles de archivos pequeños.

```text
HIPÓTESIS: mover el proyecto fuera de OneDrive reduciría estos tiempos de
forma sustancial.
```

No se propone como acción todavía: es un cambio de ubicación del proyecto y
corresponde al responsable humano decidirlo. **No afecta la integridad de los
datos**, sólo la velocidad de las validaciones.

## Servicio del sitio

```text
CONFIRMADO: el sitio responde HTTP 200 en http://127.0.0.1:8090/
CONFIRMADO: tamaño de la portada renderizada: 67 288 bytes
CONFIRMADO: renderiza con el tema drudg8b3
```

Servidor usado: el servidor de desarrollo de PHP con el router oficial de
Drupal (`.ht.router.php`). Apache de XAMPP no se configuró porque no es
necesario para validar, y hacerlo implicaría modificar configuración
compartida del equipo.

## Producción

```text
CONFIRMADO: no se realizó ninguna conexión al WordPress de producción.
CONFIRMADO: no se ejecutó ninguna escritura sobre producción.
```

Todo el trabajo se hizo contra la copia local y el dump entregado.
