# MIGRATION_CONTRACT.md — Gaceta UDG: WordPress → Drupal 10

Tablero contractual de progreso. Rige sobre cualquier afirmación de avance
hecha en otro lugar.

- Última actualización: **2026-09-30**
- Fase actual: **FASE 1 y FASE 4 en progreso**; FASE 0 cerrada con observaciones
- BLOCKED abiertos: **3** (B-02 media, B-03 base de auditoría, B-04 accesibilidad)
- BLOCKED resueltos: **1** (B-01 encoding, para la estrategia de importación)
- Decisiones abiertas: **9** (D-02, D-03, D-04, D-05, D-08, D-10, D-11, D-12, D-13)
- Último dictamen del auditor: **NO AUTORIZADO** (2026-09-30). Hallazgos
  atendidos en esta revisión; requiere nuevo dictamen.

## Leyenda

```markdown
- [ ] No iniciado
- [~] En progreso
- [x] Completado y validado (exige evidencia verificable)
- [!] Bloqueado
```

Una casilla `[x]` **sólo es válida si cita un archivo de evidencia que exista y
tenga contenido real**. El código existente no es evidencia (CLAUDE.md §6).

## Entorno confirmado

Verificado por ejecución el 2026-09-30:

```text
CONFIRMADO: Workspace          C:\Users\acer\OneDrive\Documentos\gaceta
CONFIRMADO: Drupal core        10.6.9  (core/lib/Drupal.php: const VERSION)
CONFIRMADO: Drupal root        plantilla_drupal/Drudg10.6.9
CONFIRMADO: Bootstrap Drupal   Successful (drush status)
CONFIRMADO: BD Drupal          <BD_DRUPAL> — Connected, 191 tablas
CONFIRMADO: Motor BD           MariaDB 10.4.32 (C:\xampp8.2.12\mysql)
CONFIRMADO: PHP                8.2.12
CONFIRMADO: Composer           2.7.8
CONFIRMADO: Drush              12.5.3.0
CONFIRMADO: Tema por defecto   drudg8b3  (corregido, ver D-01)
CONFIRMADO: Tema admin         claro
CONFIRMADO: Perfil             standard
CONFIRMADO: Dump WordPress     wp/DB/gaceta (2).sql — 3 544 549 259 bytes
CONFIRMADO: Tablas en el dump  68 (56 dc8_, 12 wp_)
CONFIRMADO: Repositorio remoto git@github.com:VALDO-GIT3699/gaceta.udg.mx.git
CONFIRMADO: Estado del remoto  rama main, un solo commit, sólo README.md
CONFIRMADO: Visibilidad remoto PÚBLICO
CONFIRMADO: SSH a GitHub       autenticado como VALDO-GIT3699
```

### Contenido actual del template Drupal

```text
CONFIRMADO: 56 nodos en 13 tipos de contenido
  page 10 · evento_de_agenda 10 · banner 6 · directorio 6 · noticia 4
  enlaces_de_interes 4 · galeria_de_videos 3 · video 3 · _aviso_emergente 3
  slideshow 2 · galeria_de_imagenes 2 · titulo 2 · liston_de_contenido 1
CONFIRMADO: 60 términos en 8 vocabularios
CONFIRMADO: 4 usuarios
```

Este contenido es **contenido de demostración de la plantilla institucional**,
no contenido de Gaceta. Su destino (conservar, ocultar o eliminar) es la
decisión **D-05**, aún abierta.

## FASE 0 — Control del proyecto

- [x] Confirmar raíz del proyecto
  - Evidencia: `reports/audit/environment-inventory.md`
- [x] Confirmar versión y arranque de Drupal 10
  - Evidencia: `reports/audit/environment-inventory.md` (drush status)
- [x] Confirmar base de datos Drupal operativa
  - Evidencia: `reports/audit/environment-inventory.md`
- [x] Crear `MIGRATION_CONTRACT.md`
  - Evidencia: este archivo
- [~] Crear estructura de reportes y documentación
  - Estado: `reports/audit/`, `docs/` y `tools/` tienen contenido real.
    `reports/migration/` y `reports/validation/` están **vacíos** y por tanto
    no existen en Git. De los 8 documentos que CLAUDE.md §41 exige en `docs/`
    existen 3: faltan `architecture.md`, `migration-strategy.md`,
    `media-strategy.md`, `seo-strategy.md`, `url-strategy.md`,
    `accessibility.md`, `deployment.md`.
  - Corregido de `[x]` a `[~]` tras el dictamen del auditor: un directorio
    vacío no es estructura de documentación.
- [x] Crear agente auditor
  - Evidencia: `.claude/agents/auditor-migracion-gaceta.md`
- [x] Confirmar reglas de commits
  - Evidencia: `docs/git-workflow.md`
- [x] Corregir el tema por defecto roto del template
  - Evidencia: `docs/decisiones.md` (D-01)
- [x] Confirmar Git y remote del proyecto
  - Evidencia: `docs/decisiones.md` (D-06). El remoto fue inspeccionado sin
    sobrescribirlo; el repositorio del proyecto se inicializó en la raíz
    tomando como padre el commit existente del remoto.
- [x] Confirmar por escrito que producción no será modificada
  - Evidencia: `docs/decisiones.md` (D-07). El responsable confirmó que el
    acceso al WordPress de producción es únicamente de lectura y que nadie
    ejecutará cambios durante la migración.
- [x] Registrar la actualización de core pendiente del template
  - Evidencia: commit `core: registra la actualización de Drupal de 10.5.3 a
    10.6.9` en el repositorio de `plantilla_drupal/Drudg10.6.9`.
- [x] Retirar credenciales del índice de Git del sitio Drupal
  - Evidencia: commit `seguridad: deja de versionar settings.php y
    services.yml del sitio Drupal`.
- [ ] Integrar el docroot Drupal al repositorio del proyecto
  - Pendiente: el sitio vive en un repositorio Git independiente y quedó
    excluido del repositorio del proyecto. Ver D-06, punto pendiente.
- [ ] Purgar credenciales del historial del repositorio del sitio Drupal
  - Pendiente: requiere reescritura de historial. Ver D-10.

```text
GATE FASE 0: SUPERADO CON OBSERVACIONES
```

Las tareas de control están cumplidas y el entorno es operativo y verificable.
Quedan dos pendientes que **no bloquean** el avance a las fases de auditoría,
pero **sí bloquean** cualquier publicación del árbol Drupal en el remoto
público: la integración del docroot y la purga de credenciales del historial.

## FASE 1 — Inventario del WordPress

- [x] Inventariar el dump (tamaño, tablas, motores, charsets)
  - Evidencia: `reports/audit/database-inventory.md`,
    `reports/audit/wp-tables-engine.txt`
- [x] Inventariar filesystem WordPress
  - Evidencia: `reports/audit/wordpress-inventory.md`
- [x] Inventariar plugins en disco (38 directorios, versiones clave)
  - Evidencia: `reports/audit/wordpress-inventory.md`
- [x] Inventariar tema (Newspaper 12.6 confirmado)
  - Evidencia: `reports/audit/wordpress-inventory.md`
- [!] Inventariar uploads - **B-02**: el directorio NO existe en la copia local
- [ ] Determinar plugins **activos** (`dc8_options.active_plugins`) *(requiere B-03)*
- [ ] Inventariar tipos de contenido *(requiere B-03)*
- [ ] Inventariar estados *(requiere B-03)*
- [ ] Inventariar autores *(requiere B-03)*
- [ ] Inventariar taxonomías *(requiere B-03)*
- [ ] Inventariar comentarios *(requiere B-03)*
- [ ] Inventariar postmeta *(requiere B-03)*
- [ ] Inventariar Elementor *(requiere B-03)*
- [ ] Inventariar SEO *(requiere B-03)*
- [ ] Inventariar formularios *(requiere B-03)*
- [ ] Inventariar eventos *(requiere B-03)*
- [ ] Inventariar sliders *(requiere B-03)*
- [ ] Inventariar configuraciones relevantes *(requiere B-03)*

```text
GATE FASE 1: NO SUPERADO
```

## FASE 2 — Auditoría de base de datos

- [!] Cargar dump en base de auditoría - **B-03**
- [x] Confirmar tablas, motores y charsets declarados
  - Evidencia: `reports/audit/database-inventory.md`
- [x] Confirmar encoding real de `dc8_posts` - **B-01 RESUELTO**
  - Evidencia: `reports/audit/encoding-audit.md`
  - Reproducible con: `tools/audit-encoding.py`
- [ ] Todo lo demás *(requiere B-03)*

```text
GATE CRÍTICO FASE 2 (encoding): SUPERADO
```

La incertidumbre sobre el encoding que CLAUDE.md §33 señalaba como bloqueante
**ya no existe**. Está demostrado sobre los 3 544 549 259 bytes del dump que el
contenido es UTF-8 correcto y que la estrategia es **no convertir**.

```text
GATE FASE 2 (resto): NO SUPERADO
Motivo: conteos, post types, Elementor, Yoast y formularios siguen requiriendo
la base de auditoría (B-03).
```

## FASE 3 — Auditoría de media

- [x] Determinar si el `uploads` local está completo
  - Evidencia: `reports/audit/wordpress-inventory.md`
  - Resultado: **no existe**. No hay copia parcial: no hay copia alguna.
- [!] Todo lo demás - **B-02**, por dependencia externa

```text
GATE FASE 3: NO SUPERADO - BLOQUEADO POR DEPENDENCIA EXTERNA
```

Sin archivos no se puede calcular hashes (§26), relacionar attachments con
archivos físicos (§25), detectar derivados ni identificar huérfanos. Lo único
auditable son las **referencias** en la base de datos, que permiten inventariar
qué archivos *deberían* existir, no verificar que existan.

## FASE 4 — Diseño del modelo Drupal

- [x] Inventariar módulos y temas instalados en el template
  - Evidencia: `reports/audit/drupal-template-inventory.md`
- [x] Revisar `drudg8b3` (26 regiones, 24 plantillas Twig, 2 librerías)
  - Evidencia: `docs/content-model.md`
- [x] Revisar `udg_liston` (6 clases PHP, 4 librerías, 5 bloques)
  - Evidencia: `docs/content-model.md`
- [x] Revisar Views y bloques (26 vistas, 116 bloques)
  - Evidencia: `docs/content-model.md`
- [x] Inventariar campos de los tipos de contenido existentes
  - Evidencia: `docs/content-model.md` (`noticia` con 9 campos,
    `evento_de_agenda` con 7)
- [~] Diseñar content types, fields, taxonomías, media, autores, eventos
  - Estado: propuesta redactada con el mapeo origen-destino y 10 huecos
    identificados. **Seis decisiones de diseño no se pueden cerrar sin B-03.**
  - Evidencia: `docs/content-model.md`
- [ ] Instalar los módulos ausentes (`migrate*`, `media`, `redirect`, `metatag`)
  - Requiere aprobación: altera el template institucional
- [ ] Diseñar SEO, redirects y comentarios
- [ ] Revisar menús

```text
GATE FASE 4: NO SUPERADO
```

Falta la aprobación del responsable, la instalación de los módulos ausentes y
las seis decisiones de diseño que dependen de la base de auditoría. El modelo
debe aprobarse antes de migrar en masa (CLAUDE.md §33).

### Huecos del modelo detectados

Contenido editorial de Gaceta sin destino en el template:

```text
balazo      -> no existe campo
cita        -> no existe campo
seccion     -> no existe campo ni vocabulario
subseccion  -> no existe campo ni vocabulario
autor editorial -> no existe campo
trazabilidad WP -> no existe campo
```

Funcionalidad exigida por el contrato sin módulo instalado:

```text
Entidades Media (§25)  -> media, media_library      AUSENTES
Redirects 301 (§24)    -> redirect                  AUSENTE (ni en disco)
Datos SEO (§23)        -> metatag                   AUSENTE (ni en disco)
Migrate API (§31)      -> migrate*                  AUSENTES
```

## FASES 5 a 16

Sin iniciar. No se abren mientras existan BLOCKED en fases previas.

- [ ] FASE 5 — Piloto
- [ ] FASE 6 — Migración de media
- [ ] FASE 7 — Migración de taxonomías
- [ ] FASE 8 — Migración de autores
- [ ] FASE 9 — Migración de contenido
- [ ] FASE 10 — SEO y URLs
- [ ] FASE 11 — Reconstrucción visual
- [ ] FASE 12 — Accesibilidad
- [ ] FASE 13 — Validación automática
- [ ] FASE 14 — Validación visual
- [ ] FASE 15 — Pruebas
- [ ] FASE 16 — Sincronización final

## BLOCKED abiertos

### B-01 - Encoding real de `dc8_posts` - **RESUELTO**

```text
FASE:      2
ESTADO:    RESUELTO (2026-09-30)
EVIDENCIA: reports/audit/encoding-audit.md
```

Resuelto **sin necesidad de la base de auditoría**. La cabecera del dump
declara `SET NAMES utf8mb4`, por lo que phpMyAdmin ya convirtió el contenido a
UTF-8 al volcarlo: la declaración `latin1` del `CREATE TABLE` describe el
almacenamiento en origen, no el archivo.

```text
UTF-8 correcto:                1 343 251 ocurrencias
Doble codificación de letras:         32 ocurrencias
```

La proporción no deja ambigüedad. **Prohibida cualquier conversión de
charset**: dañaría el 98.4 % del contenido que hoy está correcto.

El síntoma de mojibake que documenta CLAUDE.md §11 existe (21 494 casos) pero
es **daño preexistente de puntuación tipográfica**, de origen editorial,
concentrado en `dc8_posts` (1.58 % de su texto acentuado). Taxonomías,
comentarios, usuarios y opciones están limpios. Su reparación es la decisión
**D-11**.

### Planteamiento original del bloqueo

```text
ESTADO HISTÓRICO: BLOCKED
```

`dc8_posts` declara `ENGINE=MyISAM DEFAULT CHARSET=latin1`, mientras WordPress
declara `utf8mb4`. Además hay **24 tablas `dc8_` en `utf8` (utf8mb3)**, un
hallazgo que amplía lo previsto en CLAUDE.md §11.

Aplicar la conversión de charset equivocada destruye los bytes originales de
forma irreversible. Se desbloquea únicamente con muestras reales de contenido
acentuado comparadas contra lo que muestra el WordPress de producción.

Evidencia: `reports/audit/database-inventory.md`
Depende de: B-03

### B-04 - El mecanismo de accesibilidad del template estaba ROTO

```text
FASE:      0, 12
ESTADO:    PARCIALMENTE CORREGIDO. Queda una decisión abierta (D-12).
EVIDENCIA: docs/decisiones.md (D-12)
```

Detectado por el auditor de migración. **Confirmado y grave**, porque
CLAUDE.md FASE 12 ordena expresamente conservar estos mecanismos.

```text
CONFIRMADO: el docroot había perdido las librerías accesibilidadUdg y
            udg_media de udg_liston.libraries.yml.
CONFIRMADO: el commit anterior del template (0153cb7d) SÍ las declaraba.
CONFIRMADO: tres bloques seguían adjuntando librerías inexistentes:
            ListonBlock.php:121, ListonContenidoBlock.php:121  -> accesibilidadUdg
            SocialMediaBlock.php:32                            -> udg_media
CONFIRMADO: js/accesibilityUdg.js existía en disco (2 373 bytes) pero
            ninguna librería lo cargaba.
CONFIRMADO: la portada servía los controles visibles (Sepia, Grises,
            Invertir de color) SIN el JavaScript que los implementa.
```

El origen del daño es anterior a este proyecto: los archivos del docroot tienen
fecha 29-sep 21:28, previa a toda intervención. **Pero este proyecto lo
consolidó** en el commit `7dc80c045` y, peor, lo negó por escrito: el mensaje
de ese commit afirma *"No se modificó ningún archivo del tema drudg8b3 ni del
módulo udg_liston"*, lo cual es falso.

```text
RECTIFICACIÓN: el commit 7dc80c045 SÍ modificó
  modules/custom/udg_liston/udg_liston.libraries.yml
  themes/drudg8b3/css/style.css
  themes/drudg8b3/libraries/smoveConf.js
y AÑADIÓ themes/drudg8b3/fonts/Montserrat/ (6 archivos) y
  themes/drudg8b3/templates/input--button.html.twig
```

Además, `reports/audit/drupal-template-inventory.md` había concluido que *"los
mecanismos de accesibilidad funcionan"*, apoyándose en encontrar las
**etiquetas** de los botones en el HTML, no el JavaScript. El propio reporte
listaba los activos cargados de `udg_liston` y `accesibilityUdg.js` no estaba
entre ellos: contenía la prueba de lo contrario.

### Corrección aplicada

```text
CONFIRMADO: accesibilidadUdg restaurada en el docroot y verificada.
CONFIRMADO: la portada ahora carga /modules/custom/udg_liston/js/accesibilityUdg.js
CONFIRMADO: portada HTTP 200, 67 368 bytes (antes 67 288).
```

`udg_media` **no** se restauró: sus scripts llevan API keys de terceros
embebidas y restaurarla los activaría. Es la decisión **D-12**.

### B-02 - Ausencia total de `/wp-content/uploads`

```text
FASE:      3, 6
ESTADO:    BLOCKED - AGRAVADO
EVIDENCIA: reports/audit/wordpress-inventory.md
```

CLAUDE.md §25 describía la copia local como parcial. **La realidad es peor:**

```text
CONFIRMADO: wp-content/uploads NO EXISTE en la copia local.
CONFIRMADO: no hay ningún directorio "uploads" en todo el proyecto.
CONFIRMADO: 0 archivos de imagen o PDF en los tres primeros niveles.
CONFIRMADO: ai1wm-backups son 38 KB y NO contiene ningún respaldo.
```

Producción reporta ~84.23 GB. Disponible localmente: **0 bytes**.

No es un problema de completitud sino de ausencia. Bloquea por completo las
FASES 3 y 6. **Requiere acceso al árbol de uploads de producción**; no hay
forma de resolverlo con el material entregado.

### B-03 — Base de datos de auditoría inexistente

```text
FASE:     1, 2
ESTADO:   BLOCKED
```

`gaceta_auditoria` no existe en este equipo. La ruta de MariaDB que cita
CLAUDE.md §12 (`C:\Users\Usuario2\xampp8.1.25\...`) pertenece a otra máquina;
sus resultados no son verificables aquí.

Sin esta base no hay cifras confiables: CLAUDE.md §14 declara **no confiables**
los conteos obtenidos por parser de texto. Cargar 3.54 GB en MariaDB es una
operación de larga duración que requiere decisión previa (**D-02**).

## Decisiones requeridas (abiertas)

Formato completo en `docs/decisiones.md`.

| ID | Asunto | Bloquea |
|---|---|---|
| D-02 | Nombre, ubicación y parámetros de la base de auditoría | B-03, FASE 1, FASE 2 |
| D-03 | Estrategia de extracción de Elementor | FASE 4, FASE 9 |
| D-04 | Tratamiento de envíos de formularios (datos personales) | FASE 9 |
| D-05 | Destino del contenido de demostración de la plantilla | FASE 4 |
| D-08 | Entrada fantasma `udg_institucional` en `core.extension` | nada |
| D-10 | Purga de credenciales del historial del repositorio Drupal | publicación del docroot |
| D-11 | Reparación del mojibake preexistente en `dc8_posts` | FASE 9 (no bloquea la carga) |
| D-12 | Restaurar o no la librería `udg_media` (lleva API keys de terceros) | B-04, FASE 12 |
| D-13 | Qué copia de `drudg8b3`/`udg_liston` es la autoritativa | FASE 11 |

## Decisiones resueltas

| ID | Asunto | Resultado |
|---|---|---|
| D-01 | Tema por defecto apuntaba a `udg_institucional`, inexistente en disco | Corregido a `drudg8b3` |
| D-06 | Estructura del repositorio | Repositorio en la raíz del proyecto; el docroot Drupal queda excluido por ahora |
| D-06b | Visibilidad del remoto | Se mantiene **PÚBLICO** por decisión del responsable |
| D-07 | No modificar producción | Confirmado: acceso de sólo lectura |
| D-09 | Extensión GD | Habilitada por proceso, sin tocar el `php.ini` global |

## Riesgos críticos vigentes

1. **Encoding (B-01): resuelto, y el riesgo se invierte.** El peligro ya no es
   importar sin convertir, sino **convertir**. Cualquier conversión de charset
   dañaría el 98.4 % del contenido que está correcto.
2. **Media ausente (B-02).** Agravado: no existe ninguna copia de los archivos.
   Las FASES 3 y 6 están bloqueadas por dependencia externa.
3. **Elementor (D-03).** `post_content` puede no contener el contenido visible.
3b. **Fiabilidad de la propia documentación.** El auditor halló tres
   afirmaciones verificables de este proyecto que eran falsas: «no se modificó
   udg_liston», «0 credenciales en el índice» y «los mecanismos de
   accesibilidad funcionan». Las tres están rectificadas. Consecuencia
   operativa: **ninguna cifra ni cierre de gate debe aceptarse sin
   re-verificación independiente**, y el auditor debe ejecutarse antes de cada
   cierre de fase, no después.
3c. **Dos copias divergentes del tema (D-13).** `plantilla_drupal/drudg8b3` y
   `Drudg10.6.9/themes/drudg8b3` difieren: `style.css` y `smoveConf.js`
   cambian, la copia entregada tiene `css/res-10062025.css` y el docroot tiene
   `css/style_v2.css` y `fonts/Montserrat/`. Se audita una y se despliega otra.
4. **Repositorio remoto público.** Decisión tomada: el remoto **se mantiene
   público**. Por lo tanto queda como restricción permanente del proyecto:
   ningún reporte versionado puede contener muestras de contenido editorial,
   comentarios de lectores, envíos de formularios ni credenciales. En este
   entorno el nombre de la base, el usuario y la contraseña son la misma
   cadena, por lo que ese valor se publica como `<BD_DRUPAL>`.
5. **Cifras previas no confiables.** Los conteos de CLAUDE.md §14 provienen de
   un parser de texto y están explícitamente invalidados por el contrato.
6. **Rendimiento del entorno.** El proyecto vive en OneDrive; la compilación de
   Twig excede los 120 s de `max_execution_time` por defecto. No afecta la
   integridad de los datos, pero sí los tiempos de validación.
7. **Espacio en disco insuficiente.** `C:` tiene **14 GB libres de 476 GB
   (98 % ocupado)**. Cargar el dump de 3.54 GB en la base de auditoría dejaría
   el equipo al borde de su capacidad. Bloquea D-02 por una razón nueva y
   material.
8. **Contenido agregado de terceros.** WP RSS Aggregator 5.0.6 está instalado:
   parte del corpus de `dc8_posts` podría no ser obra propia de Gaceta, con
   implicaciones de atribución y de derechos.
9. **SSO institucional.** `wp-content/mu-plugins/sso.php` implica autenticación
   federada. No hay equivalente instalado en el template y el roadmap no lo
   contempla.
