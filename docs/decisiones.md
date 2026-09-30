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
            aparece en 2 commits previos.
CONFIRMADO: ese repositorio no tiene remotos, por lo que nada se ha filtrado.
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
