# Registro de decisiones

Formato obligatorio por CLAUDE.md §42. Toda decisión importante se registra
aquí antes de ejecutarse, o inmediatamente después cuando era necesaria para
dejar el entorno operativo.

Las decisiones **abiertas** usan el formato de CLAUDE.md §39 y **no se
ejecutan** hasta contar con respuesta del responsable humano.

---

## D-01 — Tema por defecto apuntaba a un tema inexistente

```text
Fecha:        2026-09-30
Estado:       RESUELTA Y APLICADA
```

**Problema.** El sitio servía HTTP 200 pero sin tema: 2 974 bytes de HTML, sin
CSS, sin regiones, sin listón. La base de datos declaraba
`system.theme:default = udg_institucional`, tema que no existe en disco.

**Alternativas.**

- A) Establecer `drudg8b3` como tema por defecto.
- B) Localizar y obtener el tema `udg_institucional` ausente.
- C) Dejarlo roto y escalar la decisión.

**Razón técnica.** Se eligió A porque cuatro fuentes independientes coinciden:

1. La configuración exportada del template declara `default: drudg8b3`.
2. El dump entregado `Drudg10.6.9.mysql` tiene 105 ocurrencias de `drudg8b3`
   y **0** de `udg_institucional`.
3. En disco sólo existen `bootstrap`, `contrib/bootstrap` y `drudg8b3`.
4. Instrucción expresa del responsable: "Recuerda respetar `drudg8b3`".

La opción B se descartó porque `udg_institucional` no existe en ninguna parte
del material entregado. No es una elección entre alternativas válidas: es la
corrección de un estado inconsistente.

**Impacto.** El sitio pasa de no renderizar a renderizar completo (67 288
bytes) con los activos de `drudg8b3` y `udg_liston`. No se modificó ningún
archivo del tema.

**Riesgo.** Bajo. Aplicado por API de configuración, reversible con
`drush config:set system.theme default <valor>`. No se tocó `drudg8b3`.

**Quién autorizó.** Instrucción expresa del responsable en la solicitud
inicial, más la evidencia documental concordante.

**Pendiente derivado.** La entrada fantasma `udg_institucional` permanece en
`core.extension`. No se eliminó: podría corresponder a un renombrado previsto
del template institucional. Ver D-08.

**Evidencia.** `reports/audit/drupal-template-inventory.md`

---

## D-09 — Extensión GD habilitada por proceso, no globalmente

```text
Fecha:        2026-09-30
Estado:       RESUELTA Y APLICADA
```

**Problema.** `extension=gd` está comentada en `C:\xampp\php\php.ini` (línea
931). Sin GD, el *image toolkit* de Drupal no tiene implementación disponible
y toda página que resuelva un *image style* responde HTTP 500.

**Alternativas.**

- A) Habilitar GD por parámetro en cada invocación de PHP.
- B) Descomentar `extension=gd` en el `php.ini` global del equipo.

**Razón técnica.** Se eligió A. `C:\xampp\php\php.ini` es configuración
compartida del equipo y da servicio a otros proyectos (existe una base
`webcsocial` ajena a Gaceta). Modificarla es un cambio fuera del alcance del
proyecto con efectos sobre software de terceros. El parámetro por proceso es
funcionalmente equivalente para nuestro uso y completamente reversible.

**Impacto.** Todas las invocaciones de PHP de este proyecto deben incluir
`-d extension=gd`.

**Riesgo.** Bajo, pero con una consecuencia que debe recordarse: **en el
servidor de despliegue GD tendrá que habilitarse en el `php.ini` real**. Es
un requisito de Drupal, no una particularidad local.

**Evidencia.** `reports/audit/environment-inventory.md`

---

# Decisiones abiertas

---

## D-02 — Base de datos de auditoría

```text
DECISIÓN REQUERIDA
Bloquea: B-03, FASE 1, FASE 2
```

**Contexto.** CLAUDE.md §12 describe una base `gaceta_auditoria` en MariaDB
10.4.32, creada con el binario
`C:\Users\Usuario2\xampp8.1.25\mysql\bin\mysql.exe`.

**Problema.** Esa base no existe en este equipo, y esa ruta pertenece a otro
usuario de Windows (`Usuario2`, no `acer`). La auditoría previa se hizo en
otra máquina y sus resultados no son verificables aquí.

**Datos confirmados.**

```text
MariaDB local disponible: 10.4.32 en C:\xampp8.2.12\mysql
Bases existentes: information_schema, mysql, performance_schema,
                  phpmyadmin, test, webcsocial, <BD_DRUPAL>
gaceta_auditoria: NO existe
Dump a cargar: 3 544 549 259 bytes
```

**Información faltante.** Autorización para crear la base y cargar 3.54 GB en
este equipo, y con qué parámetros de charset.

### ACTUALIZACIÓN 2026-09-30 — la pregunta de charset ya está resuelta

La auditoría de encoding (`reports/audit/encoding-audit.md`) resolvió **B-01
sin necesidad de esta base de datos**. Por tanto la duda original sobre qué
charset usar en la carga **ya no existe**:

```text
RESUELTO: cargar con la conexión en utf8mb4, respetando la cabecera del propio
dump (/*!40101 SET NAMES utf8mb4 */), y sin pasar --default-character-set.
PROHIBIDO: forzar latin1 en la importación. Dañaría el 98.4 % del contenido.
```

### ACTUALIZACIÓN 2026-09-30 — bloqueo nuevo y material: espacio en disco

```text
CONFIRMADO: C: tiene 14 GB libres de 476 GB. Ocupación: 98 %.
CONFIRMADO: el dump ocupa 3.54 GB en texto SQL.
```

Una carga de MyISAM con sus índices ocupa típicamente entre una y dos veces el
tamaño del volcado. La estimación razonable es **entre 4 y 7 GB**, lo que
dejaría el disco entre 7 y 10 GB libres sobre un sistema que ya está al 98 %.

Riesgos concretos de proceder así:

- Windows y OneDrive necesitan espacio libre para operar; por debajo de unos
  pocos GB el equipo se vuelve inestable.
- MyISAM **no es transaccional**. Si la carga agota el disco a mitad, la base
  queda incompleta y hay que empezar de cero, tras liberar espacio.
- La carga es de varias horas. Un fallo por disco al final del proceso
  desperdicia toda la ventana.

```text
RECOMENDACIÓN: no iniciar la carga hasta liberar espacio o disponer de otro
volumen. Se necesitan al menos 20 GB libres para trabajar con holgura.
```

Opciones para liberar o reubicar, en orden de preferencia técnica:

1. **Usar otro volumen o un disco externo** para el directorio de datos de
   MariaDB. Es la opción más limpia y no toca nada del equipo.
2. **Liberar espacio en `C:`**. Hay candidatos evidentes fuera del proyecto:
   tres instalaciones de XAMPP (`C:\xampp`, `C:\xampp8.1.17`,
   `C:\xampp8.2.12`) de las que el proyecto sólo usa dos. **No se toca
   ninguna sin autorización**: `C:\xampp8.2.12` contiene la base de datos de
   Drupal y también `webcsocial`, que es de otro proyecto.
3. **Cargar sólo un subconjunto de tablas.** Técnicamente posible (cargar
   `dc8_posts`, `dc8_postmeta`, `dc8_terms`, `dc8_term_taxonomy`,
   `dc8_term_relationships`, `dc8_users`, `dc8_comments` y omitir caché,
   sliders y logs). Reduce mucho el espacio, pero **deja partes del corpus sin
   auditar**, lo que contradice el mandato de preservación. Sólo como último
   recurso y documentando exactamente qué quedó fuera.

**Pregunta actualizada.** ¿Hay otro volumen disponible para el directorio de
datos, o se autoriza liberar espacio en `C:`? Si ninguna es posible, ¿se
autoriza la carga parcial de la opción 3, sabiendo qué tablas quedarían sin
auditar?

### Planteamiento original

**Opción A.** Crear `gaceta_auditoria` en el MariaDB de `C:\xampp8.2.12` y
cargar el dump tal cual, **sin forzar charset de conexión**, preservando los
bytes tal como están declarados por tabla.

**Opción B.** Cargar forzando `--default-character-set`, homogeneizando el
charset en la importación.

**Riesgo.** La opción B **contamina la evidencia de encoding**: forzar el
charset de conexión reinterpreta los bytes durante la carga y haría imposible
demostrar cuál era la codificación original de `dc8_posts` (B-01). La opción A
preserva la evidencia, pero puede mostrar mojibake en las consultas, que es
precisamente lo que hay que diagnosticar.

Consideraciones operativas de cualquiera de las dos:

- Es una operación larga (varias horas, probablemente) sobre disco OneDrive.
- Requiere espacio libre considerable, más que el tamaño del dump.
- Las tablas MyISAM no son transaccionales: una carga interrumpida deja la
  base a medias y hay que reiniciarla desde cero.

**Recomendación técnica neutral.** Opción A. Es la única compatible con el
*gate* crítico de la FASE 2: para demostrar el encoding real hay que
observar los bytes sin reinterpretarlos.

**Pregunta.** ¿Se autoriza crear `gaceta_auditoria` en el MariaDB de
`C:\xampp8.2.12` y cargar el dump sin forzar charset de conexión? ¿Hay
espacio en disco suficiente, y es aceptable que la carga tarde horas?

---

## D-03 — Estrategia de extracción de Elementor

```text
DECISIÓN REQUERIDA
Bloquea: FASE 4, FASE 9
```

**Contexto.** CLAUDE.md §18 marca Elementor como riesgo crítico: el contenido
visible puede no estar en `post_content`, sino en `dc8_postmeta` bajo
`_elementor_data` (JSON de estructura de widgets).

**Problema.** No se puede decidir la estrategia sin saber cuántos contenidos
dependen realmente de Elementor y de qué tipo son. Esa medición requiere la
base de auditoría.

**Información faltante.** Todo el dimensionamiento del problema.

**Recomendación técnica neutral.** No decidir todavía. Esta decisión debe
tomarse **después** de cuantificar, no antes. Se mantiene abierta y depende
de D-02.

**Pregunta.** Se reformulará con cifras una vez cargada la base de auditoría.

---

## D-04 — Tratamiento de envíos de formularios

```text
DECISIÓN REQUERIDA
Bloquea: FASE 9
```

**Contexto.** CLAUDE.md §22 contempla WPForms (2 formularios, 0 envíos
visibles) y Contact Form 7.

**Problema.** La auditoría del dump encontró un canal de formularios **no
inventariado**: `dc8_e_submissions`, `dc8_e_submissions_values` y
`dc8_e_submissions_actions_log`, de Elementor Pro Forms. También existen
`dc8_wpforms_payments` y `dc8_wpforms_payment_meta`, cuyo nombre sugiere
**datos de pago**.

**Datos confirmados.** Las tablas existen. Su contenido es DESCONOCIDO
(requiere D-02).

**Opción A.** No migrar ningún envío. Migrar sólo la definición de los
formularios a Webform.

**Opción B.** Migrar los envíos históricos a `webform_submission`.

**Riesgo.** Los envíos de formularios son **datos personales de terceros**.
Migrarlos a una plataforma nueva sin base legal, y a un proyecto cuyo
repositorio es **público**, es un riesgo de protección de datos, no sólo
técnico. La opción B exige además verificar que ningún reporte o volcado los
exponga.

**Recomendación técnica neutral.** Opción A por defecto, salvo que exista una
obligación de conservación documental que obligue a conservarlos. Si se opta
por B, los envíos deben tratarse como datos sensibles: nunca en Git, nunca en
reportes, `REDACTED` en cualquier evidencia (CLAUDE.md §37).

**Pregunta.** ¿Los envíos históricos de formularios deben conservarse? Si sí,
¿bajo qué base legal y con qué política de retención?

---

## D-05 — Destino del contenido de demostración del template

```text
DECISIÓN REQUERIDA
Bloquea: FASE 4
```

**Contexto.** El template trae 56 nodos, 60 términos y 4 usuarios de
demostración institucional.

**Problema.** Al migrar Gaceta, ese contenido convive con el real. No se sabe
si sirve de referencia visual o si debe desaparecer.

**Opción A.** Conservarlo mientras dure el desarrollo y eliminarlo antes del
*cutover*.

**Opción B.** Despublicarlo ya, conservando los nodos.

**Opción C.** Eliminarlo ahora.

**Riesgo.** La opción C destruye la referencia visual que la FASE 11 necesita
para saber cómo el template presenta cada tipo de contenido, y es
irreversible sin recargar el dump. La opción A arrastra el riesgo de que
contenido de prueba llegue a producción si se olvida.

**Recomendación técnica neutral.** Opción A, con una tarea explícita de
limpieza como requisito del *cutover* en la FASE 16.

**Pregunta.** ¿Se conserva el contenido de demostración durante el
desarrollo, con eliminación obligatoria antes del *cutover*?

---

## D-06 — Estructura del repositorio y visibilidad del remoto

```text
Fecha:   2026-09-30
Estado:  RESUELTA Y APLICADA
```

**Resolución del responsable.**

```text
Estructura:  repositorio en la RAÍZ del proyecto (opción B).
Visibilidad: el remoto SE MANTIENE PÚBLICO.
Core:        la actualización 10.5.3 -> 10.6.9 se registra en commit propio.
```

**Cómo se aplicó.** Se inicializó el repositorio en la raíz del proyecto y se
tomó como padre el commit `c855e3b` que ya existía en el remoto, de modo que
**no se sobrescribió nada** (CLAUDE.md §2). `wp/` queda excluido por completo.

**Consecuencia asumida y pendiente.** El repositorio Git del sitio Drupal
(`plantilla_drupal/Drudg10.6.9`) quedó **excluido** del repositorio del
proyecto, para no crear un enlace de submódulo roto. Conserva su propio
historial del template institucional, ahora con dos commits nuevos.

Esto deja el artefacto desplegable fuera del repositorio del proyecto, lo cual
no es un estado final aceptable. La vía para resolverlo **preservando el
historial** es `git subtree add`, que incorpora el árbol y su historia bajo un
prefijo. No se ejecutó todavía por dos razones: es una operación larga sobre
disco OneDrive, y **no debe hacerse antes de purgar las credenciales del
historial** de ese repositorio (D-10), porque el remoto es público.

**Restricción permanente derivada de mantener el remoto público.** Ningún
archivo versionado puede contener muestras de contenido editorial, comentarios
de lectores, envíos de formularios ni credenciales. Por eso el nombre de la
base de datos aparece como `<BD_DRUPAL>` en todos los reportes: en este entorno
el nombre de la base, el usuario y la contraseña son la misma cadena.

### Planteamiento original

```text
Se conserva como registro de las alternativas evaluadas.
```

**Contexto.** CLAUDE.md §2 fija como fuente de verdad
`git@github.com:VALDO-GIT3699/gaceta.udg.mx.git`.

**Datos confirmados.**

```text
El remoto existe, rama main, un solo commit, sólo README.md (107 bytes).
El remoto es PÚBLICO.
Existe un repositorio Git preexistente en plantilla_drupal/Drudg10.6.9,
  sin remotos, con ramas master/dev/dev2/dev3 (HEAD en dev3) y el historial
  del template institucional de la UDG.
Ese repositorio tiene 3 609 rutas modificadas (la actualización de core de
  10.5.3 a 10.6.9, aplicada pero nunca registrada).
No tiene .gitignore. sites/default/files/ está versionado.
wp/ contiene un dump de 3.54 GB que nunca debe versionarse.
```

**Problema.** Hay dos estructuras posibles y no son equivalentes.

**Opción A — el repositorio es el docroot de Drupal.**
Se adopta el repositorio existente en `plantilla_drupal/Drudg10.6.9`, se le
añade `origin` apuntando al remoto, y `MIGRATION_CONTRACT.md`, `docs/`,
`reports/` y `tools/` se mueven dentro del docroot.

- Conserva el historial del template institucional (trazabilidad, §44).
- El repositorio es exactamente el artefacto desplegable.
- En contra: los reportes de auditoría quedan dentro del árbol servido por
  web, y en un repositorio público.

**Opción B — el repositorio es la raíz del proyecto.**
Se inicializa un repositorio en `C:\Users\acer\OneDrive\Documentos\gaceta`,
con `docs/`, `reports/`, `tools/` y el sitio Drupal como subdirectorio;
`wp/` queda excluido.

- Separa documentación de código desplegable.
- En contra: **se pierde el historial del template institucional**, porque el
  `.git` de `Drudg10.6.9` quedaría anidado y Git no versionaría su contenido.

**Riesgo.** Elegir mal es costoso de revertir una vez publicado el historial
en un remoto público. Y hay un riesgo independiente de la estructura:

```text
El repositorio es PÚBLICO.
```

Este proyecto va a manejar contenido editorial, comentarios de lectores
(~6 346 según §28), posiblemente envíos de formularios con datos personales
(D-04) y reportes de auditoría con muestras de contenido. CLAUDE.md §37
prohíbe secretos en Git, pero un repositorio público expone además
**contenido**, de forma indexable por buscadores.

**Recomendación técnica neutral.** Opción A para la estructura, porque
conserva el historial del template y porque el contrato designa ese
repositorio como fuente de verdad del desarrollo. Y, con independencia de la
estructura, **cambiar el repositorio a privado** antes del primer *push* con
contenido del proyecto. Un repositorio privado no impide nada de lo que el
contrato exige y elimina la exposición.

**Pregunta.**

1. ¿Estructura A (repositorio = docroot, conservando el historial del
   template) o estructura B (repositorio = raíz del proyecto)?
2. ¿Se autoriza cambiar `gaceta.udg.mx` a repositorio privado?
3. Los 3 609 cambios del core 10.5.3 -> 10.6.9, ¿se registran en un commit
   propio de actualización de core antes de empezar el trabajo de migración?

---

## D-07 — Confirmación escrita de no modificar producción

```text
Fecha:   2026-09-30
Estado:  RESUELTA
```

**Resolución del responsable.**

```text
CONFIRMADO: el acceso al WordPress de producción es únicamente de lectura.
CONFIRMADO: nadie ejecutará cambios (tema, plugins, contenido, base de datos)
            durante la migración.
```

Queda cumplido el requisito de CLAUDE.md §33 para la FASE 0. La prohibición de
CLAUDE.md §38 sigue vigente en todo momento.

### Planteamiento original

**Contexto.** CLAUDE.md §38 prohíbe toda escritura sobre producción y §33
exige confirmarlo explícitamente para cerrar la FASE 0.

**Datos confirmados.**

```text
No se realizó ninguna conexión al WordPress de producción.
No se ejecutó ninguna escritura sobre producción.
Todo el trabajo se hizo contra la copia local y el dump entregado.
```

**Información faltante.** La confirmación del responsable de que el acceso al
panel de WordPress es de **sólo lectura** y que nadie más ejecutará cambios
durante la migración.

**Pregunta.** ¿Se confirma que el WordPress de producción no será modificado
durante el proyecto, y que el acceso disponible es únicamente de lectura?

---

## D-08 — Entrada fantasma `udg_institucional` en core.extension

```text
DECISIÓN REQUERIDA
Bloquea: nada (cosmético, pero conviene resolverlo pronto)
```

**Contexto.** Tras corregir D-01, `core.extension:theme` sigue listando
`udg_institucional`, un tema que no existe en disco.

**Problema.** No se sabe si el nombre corresponde a un renombrado previsto
del template institucional o a un residuo.

**Opción A.** Dejarlo como está y documentarlo.

**Opción B.** Desinstalarlo de `core.extension`.

**Riesgo.** La opción B puede fallar (Drupal intenta cargar el tema para
desinstalarlo) y, si el renombrado estaba previsto, borra la única pista de
esa intención. La opción A deja una inconsistencia que puede generar avisos.

**Recomendación técnica neutral.** Opción A por ahora. Se eligió no eliminarlo
precisamente para no convertir una incertidumbre en decisión.

**Pregunta.** ¿Existió o se planea un tema `udg_institucional`? Si es un
residuo, se limpia; si es un renombrado previsto, hay que saberlo antes de
diseñar el tema de Gaceta.

---

## D-10 — Purga de credenciales del historial del repositorio Drupal

```text
DECISIÓN REQUERIDA
Bloquea: publicar el docroot Drupal en el remoto público; D-06 (integración)
```

**Contexto.** El repositorio de `plantilla_drupal/Drudg10.6.9` versionaba
`sites/default/settings.php`, que contiene el `hash_salt` del sitio y la
contraseña de la base de datos.

**Datos confirmados.**

```text
CONFIRMADO: settings.php y services.yml fueron retirados del índice y ya no se
            versionarán (commit bd4dda7c5, más .gitignore).
CONFIRMADO: los archivos siguen intactos en el disco.
CONFIRMADO: las credenciales SIGUEN presentes en el historial: settings.php
            aparece en 2 commits previos (173f0c0ce, 69e68a1c7).
CONFIRMADO: ese repositorio no tiene remotos, por lo que nada se ha filtrado.
```

### AMPLIACIÓN 2026-09-30 — falta un tercer archivo

El auditor de migración detectó que el inventario estaba **incompleto**:

```text
CONFIRMADO: sites/default/res-settings.php también contiene credenciales.
CONFIRMADO: fue añadido en el commit 69e68a1c7.
CONFIRMADO: contiene 8 líneas con hash_salt, database, username y password.
CONFIRMADO: fue ELIMINADO por el commit 7dc80c045 de este proyecto, que por
            tanto tocó esa ruta sin inventariarla como exposición.
CONFIRMADO: el .gitignore creado en bd4dda7c5 excluye settings*.php, patrón
            que NO cubre res-settings.php.
```

Consecuencia: cualquier purga o rotación planificada con el alcance anterior de
esta decisión **habría dejado fuera esas credenciales**. El alcance correcto
son **tres** archivos-commit, no dos.

```text
CORREGIDO: el .gitignore del docroot ahora excluye también res-settings.php.
```

**Problema.** Mientras el historial contenga esas credenciales, ese árbol no
puede publicarse en un repositorio público. Y el remoto del proyecto es
público por decisión tomada (D-06).

**Opción A.** Rotar las credenciales (nuevo `hash_salt`, nueva contraseña de
base de datos) y dejar el historial intacto. Lo que quede en el historial deja
de ser válido.

**Opción B.** Reescribir el historial con `git filter-repo` para eliminar
`settings.php` de todos los commits.

**Riesgo.** La opción B **altera commits ya existentes del template
institucional de la UDG**, que es material compartido y posiblemente usado por
otros sitios de la Universidad. Reescribir ese historial cambia todos los
hashes y rompe cualquier clon ajeno. La opción A no altera nada, pero exige
cambiar la contraseña de la base de datos y el `hash_salt`; cambiar el
`hash_salt` invalida todas las sesiones y los tokens de formulario, lo cual en
local es irrelevante y en producción no lo es.

**Recomendación técnica neutral.** Opción A. Es la práctica habitual ante una
credencial expuesta: **rotar es más seguro y menos destructivo que borrar el
rastro**, y no toca el historial compartido del template. La opción B sólo se
justifica si el responsable del template institucional la autoriza
expresamente.

**Pregunta.** ¿Se rotan las credenciales del entorno (opción A) o se autoriza
reescribir el historial del repositorio del template (opción B)? ¿Quién es el
responsable del template institucional que debería aprobarlo?

---

## D-11 — Reparación del mojibake preexistente en `dc8_posts`

```text
DECISIÓN REQUERIDA
Bloquea: nada de la carga. Afecta a FASE 9 (migración de contenido).
```

**Contexto.** La auditoría de encoding demostró que el contenido del dump es
UTF-8 correcto, pero detectó **daño preexistente** en la puntuación
tipográfica.

**Datos confirmados.**

```text
CONFIRMADO: 21 494 ocurrencias de doble codificación de puntuación.
CONFIRMADO: 21 324 de ellas (99.2 %) están en dc8_posts.
CONFIRMADO: representan el 1.58 % del texto acentuado de dc8_posts.
CONFIRMADO: 3 879 son puntos suspensivos; el resto, comillas tipográficas,
            apóstrofos y guiones largos.
CONFIRMADO: sólo 32 casos afectan a LETRAS acentuadas en todo el dump.
CONFIRMADO: dc8_terms, dc8_comments, dc8_users y dc8_options: 0 casos.
CONFIRMADO: el daño NO lo causa la base de datos ni el dump. Es de origen
            editorial (texto pegado desde Word, PDF o fuentes RSS).
```

**Problema.** ¿Se migra el contenido tal cual, conservando el daño, o se
repara durante la migración?

**Opción A — migrar tal cual.** Fidelidad absoluta al origen. Drupal mostrará
exactamente lo que muestra WordPress hoy, defectos incluidos.

**Opción B — reparar durante la migración.** Aplicar la transformación inversa
sobre las cadenas afectadas, con registro de cada cambio.

**Riesgo.** La opción A traslada a un sitio nuevo un defecto conocido y
medible, en 21 324 posiciones de contenido publicado. La opción B **modifica
contenido editorial**, lo que excede el mandato de preservación si no está
autorizado; además, aunque el patrón es determinista, cualquier regla de
reparación puede producir falsos positivos si algún texto contiene esas
secuencias de forma legítima.

Factores a favor de la viabilidad de B:

```text
El patrón es determinista y acotado: la doble codificación de e2 80 xx.
La reparación es verificable: se puede contar antes y después.
Es reversible: el origen permanece intacto y la migración es repetible.
Se puede aplicar como paso explícito y auditable, con su propio reporte de
cuántas cadenas cambiaron y en qué registros.
```

**Recomendación técnica neutral.** Opción B, **pero como paso separado y
explícito**, nunca como efecto colateral de la migración. Es decir: migrar
primero con fidelidad, y aplicar la reparación como una transformación
documentada, con reporte de cada registro alterado y posibilidad de repetir la
migración sin ella. Así se cumple a la vez la preservación (el origen y la
migración fiel existen) y la calidad (el sitio nuevo no hereda el defecto).

CLAUDE.md §11 exige demostrar la codificación original antes de corregir. Esa
demostración ya existe: `reports/audit/encoding-audit.md`.

**Pregunta.** ¿Se autoriza reparar el mojibake como paso explícito y auditable
posterior a la migración fiel? ¿O se prefiere migrar tal cual y tratar la
limpieza como tarea editorial fuera de este proyecto?

---

## D-12 — Restaurar o no la librería `udg_media`

```text
DECISIÓN REQUERIDA
Bloquea: cierre de B-04 y de la FASE 12
```

**Contexto.** El docroot había perdido dos librerías de `udg_liston` respecto
al commit anterior del template: `accesibilidadUdg` y `udg_media`. Tres bloques
seguían adjuntándolas.

**Lo ya aplicado, y por qué no se consideró una decisión unilateral.**

```text
APLICADO: accesibilidadUdg restaurada y verificada en la portada.
```

CLAUDE.md FASE 12 es una instrucción explícita del responsable: *"El template
institucional contiene mecanismos de accesibilidad. No eliminarlos."* Restaurar
un mecanismo que el contrato ordena conservar es **cumplir el contrato**, no
resolver una incertidumbre. El cambio es un bloque YAML, reversible, y quedó
verificado por ejecución: la portada carga `accesibilityUdg.js`.

Copia de seguridad del archivo previo en el directorio de trabajo temporal de
la sesión, y el estado anterior es recuperable con
`git show 7dc80c045:modules/custom/udg_liston/udg_liston.libraries.yml`.

**Lo que NO se aplicó, y por qué.**

```text
NO APLICADO: udg_media sigue sin declararse.
```

`udg_media` carga `js/jquery.social.stream.1.6.2.js`,
`js/jquery.social.stream.wall.1.8.js` y `js/runmedia.js`, más
`css/dcsns_wall.css`. Los dos primeros contienen **API keys de terceros
embebidas** (Google, YouTube y un token de Dribbble). Restaurar la librería las
pondría en ejecución en el navegador de cada visitante.

**Alternativas.**

- **A.** Dejar `udg_media` sin declarar y quitar el `attach` de
  `SocialMediaBlock.php:32`, para que no apunte a una librería inexistente.
- **B.** Restaurar `udg_media` tal cual, con las claves embebidas.
- **C.** Restaurarla sustituyendo las claves por credenciales propias de la UDG
  gestionadas fuera del código.

**Riesgo.** La opción B publica claves de terceros en un sitio institucional y
las expone en el código fuente servido; además, si esas claves son de la cuenta
de demostración del proveedor, el muro social probablemente ya no funcione. La
opción A deja el sitio sin muro de redes sociales, que puede ser una
funcionalidad esperada. La opción C es la correcta a largo plazo pero requiere
que alguien provista y gestione las credenciales.

**Recomendación técnica neutral.** Opción A por ahora, y C si se confirma que
el muro de redes sociales debe existir en Gaceta. No tocar
`SocialMediaBlock.php` sin autorización: es código del módulo institucional.

**Pregunta.** ¿El muro de redes sociales (`udg_media`) debe existir en el sitio
de Gaceta? Si sí, ¿quién provee las credenciales de API? Y por otra parte,
¿la eliminación de estas dos librerías en el docroot fue deliberada, o es un
accidente de la actualización a 10.6.9?

---

## D-13 — Qué copia de `drudg8b3` y `udg_liston` es la autoritativa

```text
DECISIÓN REQUERIDA
Bloquea: FASE 11 (reconstrucción visual)
```

**Contexto.** El material entregado contiene **dos** copias del tema y del
módulo institucionales.

**Datos confirmados.**

```text
plantilla_drupal/drudg8b3/            copia entregada, suelta
plantilla_drupal/Drudg10.6.9/themes/drudg8b3/   copia desplegada (la que sirve)

Difieren:
  Sólo en la entregada:  css/res-10062025.css
  Sólo en el docroot:    css/style_v2.css, fonts/Montserrat/
  Contenido distinto:    css/style.css, libraries/smoveConf.js

udg_liston: la copia entregada declara accesibilidadUdg y udg_media;
            el docroot las había perdido (ver B-04 y D-12).
```

**Problema.** Se auditó una copia y se sirve la otra. La divergencia en
`udg_liston` es precisamente lo que permitió detectar B-04, así que la copia
entregada tiene valor como **línea base**. Pero mientras no se declare cuál es
la autoritativa, cualquier afirmación sobre "el tema" es ambigua.

**Decisión ya aplicada sobre el versionado, por motivos de seguridad.**

```text
APLICADO: las copias sueltas se retiraron del índice de Git (siguen en disco).
```

Motivo, independiente de esta decisión: el repositorio del proyecto es
**público** y esas copias contienen:

```text
API keys de terceros en udg_liston/js/jquery.social.stream.1.6.2.js y .min.js
  -> CLAUDE.md §37 prohíbe API keys en Git, sin excepción por procedencia.
14 archivos .otf de Camber (Emtype Foundry), tipografía COMERCIAL de licencia
  de pago, sin archivo de licencia.
```

Sólo `Archivo_Narrow` incluye su licencia (`OFL.txt`). Publicar binarios de una
fuente comercial en un repositorio público es una exposición de licenciamiento
institucional.

```text
IMPORTANTE: no hubo filtración. El repositorio nunca se publicó: origin/main
sigue en el commit inicial del remoto.
```

**Alternativas para la autoridad del artefacto.**

- **A.** El docroot es autoritativo; la copia entregada queda sólo como línea
  base de referencia, sin versionar.
- **B.** La copia entregada es autoritativa y el docroot debe alinearse con
  ella, revirtiendo los cambios de la actualización a 10.6.9.

**Riesgo.** La opción B revertiría `style_v2.css` y las fuentes Montserrat, que
podrían ser mejoras deliberadas del responsable del template. La opción A
consolida un estado que ya demostró contener una regresión de accesibilidad.

**Recomendación técnica neutral.** Opción A, **más** una comparación
documentada de las diferencias antes de la FASE 11, para que ninguna divergencia
se consolide sin haberse revisado. La detección de B-04 demuestra que esa
comparación tiene valor real.

**Pregunta.**

1. ¿Cuál de las dos copias es la autoritativa?
2. ¿`style_v2.css`, las fuentes Montserrat y los cambios de `style.css` y
   `smoveConf.js` del docroot son deliberados?
3. ¿Existe licencia de la UDG para la tipografía Camber? Si no, ¿debe
   sustituirse antes de publicar el sitio?

---

## D-14 — Modelo de créditos editoriales: vocabulario, no cuentas de usuario

```text
Fecha:   2026-09-30
Estado:  RESUELTA por el responsable del proyecto y su superior.
Afecta:  FASE 4 (modelo), FASE 8 (migración de autores)
```

**Problema.** WordPress reporta 162 usuarios. CLAUDE.md §27 advierte que eso no
implica crear 162 cuentas en Drupal, pero no fijaba el mecanismo alternativo.
`docs/content-model.md` dejaba la pregunta abierta con tres alternativas.

**Resolución.**

```text
NO se crean cuentas de usuario en Drupal para los autores de WordPress.
Los créditos se modelan como TÉRMINOS DE UN VOCABULARIO asociados al contenido.
Deben admitir combinaciones de: autoría del texto, fotografía y otros
colaboradores.
```

**Razón que dio el responsable.** Las cuentas de WordPress existen únicamente
para poder asignar el crédito de los artículos. **Las personas nunca inician
sesión con esas cuentas.** Son identidades de atribución, no de acceso.

**Por qué es la decisión correcta técnicamente.** Coincide con lo que el
contrato ya pedía y lo refuerza:

1. **Seguridad.** 162 cuentas que nadie usa son 162 superficies de
   autenticación, con sus contraseñas, sus sesiones y sus permisos. No
   crearlas elimina ese riesgo de raíz. CLAUDE.md §27 pide expresamente *"no
   importar contraseñas ni secretos innecesariamente"*, y §37 prohíbe
   versionar hashes. Sin cuentas, nada de eso entra al proyecto.
2. **Fidelidad al dato.** Un usuario de Drupal modela *quién puede entrar*. Un
   término de taxonomía modela *a quién se atribuye algo*. La segunda es la
   semántica real del dato.
3. **Expresividad.** WordPress sólo admite **un** `post_author` por entrada.
   Un vocabulario con varios campos de referencia permite acreditar texto,
   fotografía y colaboraciones por separado, y a varias personas en cada rol.
   El modelo destino es **más rico** que el de origen, no más pobre.

### Diseño propuesto

Un **único vocabulario** de personas, con **tres campos** de referencia en el
contenido:

```text
Vocabulario:  credito_editorial     (términos = personas)

Campos en el tipo de contenido noticia, todos de valor múltiple:
  field_autor_texto     entity_reference -> credito_editorial   "Autoría del texto"
  field_fotografia      entity_reference -> credito_editorial   "Fotografía"
  field_colaboradores   entity_reference -> credito_editorial   "Otros créditos"
```

**Por qué un solo vocabulario y no uno por rol.** La misma persona puede firmar
el texto de una nota y la fotografía de otra. Con un vocabulario único existe
**un término canónico por persona**, lo que evita duplicados, permite una
página de autor que reúna todo su trabajo, y hace posible preguntar "qué ha
publicado esta persona, en cualquier rol". Con tres vocabularios, la misma
persona sería tres términos sin relación entre sí.

**Alternativa evaluada y descartada por ahora.** Un vocabulario de roles más
entidades Paragraph que emparejen persona y rol. Es más flexible si los roles
se multiplican o importa su orden, pero añade el módulo Paragraphs y bastante
complejidad. Con tres roles concretos y definidos, tres campos son más simples
y más fáciles de auditar. Si en el futuro aparecen muchos roles, ésa es la vía
de escape.

### Campos de trazabilidad en el término

Para cumplir CLAUDE.md §35 (poder responder dónde terminó cada registro):

```text
field_wp_user_id     integer   dc8_users.ID de origen
field_wp_user_login  string    login de origen
```

```text
NO se migra: contraseñas, hashes, claves de activación, ni direcciones de
correo electrónico.
```

El correo es dato personal y no aporta nada a la atribución editorial. Si
alguna vez se necesitara, se decide entonces y con base legal.

### Lo que esta decisión NO resuelve todavía

Hay un hueco de datos que conviene no disimular:

```text
CONFIRMADO:  dc8_posts.post_author existe y es un id numérico de usuario.
             De ahí sale field_autor_texto. Migración directa y fiable.
DESCONOCIDO: de dónde salen los créditos de FOTOGRAFÍA y de OTROS
             COLABORADORES en el origen.
```

WordPress no tiene un campo estructurado para el fotógrafo. Las posibilidades
son: una clave en `dc8_postmeta`, el texto del propio artículo (patrones del
tipo "Foto:", "Fotografía:", "Texto y fotos:"), o el pie de la imagen.

```text
El modelo queda PREPARADO para recibir esos créditos. Que se puedan EXTRAER
del origen es otra cuestión, y está sin verificar.
```

Si resultara que sólo están dentro del cuerpo del texto, extraerlos exige
reconocer patrones en prosa, lo cual es inherentemente inexacto y sería una
transformación de contenido editorial. Eso requeriría su propia decisión, con
muestras reales y una tasa de error medida. **No se hará por iniciativa
propia.** Mientras no se resuelva, los dos campos quedan vacíos y el crédito
original permanece íntegro dentro del cuerpo del artículo, que es donde está
hoy: no se pierde información.

**Pendiente de investigación (requiere B-03).**

```text
- [ ] Claves de dc8_postmeta que puedan contener créditos de fotografía
- [ ] Frecuencia de patrones "Foto:", "Fotografía:", "Texto:" en post_content
- [ ] Cuántos de los 162 usuarios tienen contenido realmente atribuido
- [ ] Si hay nombres de autor duplicados o variantes del mismo nombre
```

Esa última es importante: si la misma persona aparece como "Juan Pérez" y
"Juan Pérez Gómez", unificarla o no es una decisión editorial, no técnica.

---

## D-15 — Migración sin los archivos de media: B-02 se acepta como dependencia externa

```text
Fecha:   2026-09-30
Estado:  RESUELTA por el responsable del proyecto.
Afecta:  B-02, FASE 3, FASE 6
```

**Problema.** `wp-content/uploads` no existe en la copia local. Producción
reporta ~84.23 GB. B-02 bloqueaba las FASES 3 y 6.

**Resolución del responsable.**

```text
Los archivos no se copiaron por falta de espacio en la máquina.
El trabajo continúa SIN las imágenes.
Condición expresa: se tiene que salvar TODA la información.
```

**Cómo se cumplen las dos cosas a la vez.** La aparente contradicción entre
"sin imágenes" y "sin perder información" se resuelve distinguiendo dos cosas
que suelen confundirse:

```text
Los ARCHIVOS binarios  -> están en producción. No los tenemos.
La INFORMACIÓN sobre ellos -> está en la base de datos. Sí la tenemos.
```

Todo lo que *se sabe* de cada imagen vive en el dump, no en el archivo: el
nombre, la ruta, la URL original, el tipo, las dimensiones, el texto
alternativo, el pie de foto, el título, la descripción, la fecha, el autor del
adjunto y **qué contenidos la referencian**.

```text
Por tanto: no migrar los binarios NO implica perder información, siempre que
se preserve íntegra cada referencia y cada metadato.
```

Lo que sí implica es que **la migración de media no puede declararse
completa**, y eso se mantiene: CLAUDE.md §33 y §48 lo exigen.

### Estrategia adoptada: dos etapas

```text
ETAPA 1 (ahora, sin archivos)
  Inventario completo de referencias de media desde la base de datos.
  Metadatos íntegros. Rutas de destino calculadas de forma determinista.
  Las referencias en el HTML del contenido se preservan y se reescriben a la
  ruta final que tendrá el archivo en Drupal.

ETAPA 2 (cuando haya acceso a los archivos)
  Se depositan los binarios en la ruta ya calculada y se crean las entidades
  Media que los envuelven. No hay que repetir la migración de contenido.
```

El detalle está en `docs/media-strategy.md`.

**Consecuencia asumida.** Hasta la etapa 2, el sitio Drupal mostrará las
imágenes roto o, si se opta por el puente que describe la estrategia de media,
servidas todavía desde el dominio de producción. Ninguna de las dos es un
estado final aceptable, y ambas quedan registradas como deuda explícita.

```text
B-02 deja de ser un BLOCKED del proyecto y pasa a ser una DEPENDENCIA EXTERNA
ACEPTADA, con la migración de media declarada INCOMPLETA por decisión
informada del responsable.
```

**Pregunta que sigue abierta.** ¿Existirá en algún momento acceso al árbol de
uploads de producción, aunque sea por lotes o por años? La estrategia de dos
etapas está diseñada para admitir una entrega parcial e incremental, así que
cualquier subconjunto que llegue es aprovechable sin rehacer nada.
