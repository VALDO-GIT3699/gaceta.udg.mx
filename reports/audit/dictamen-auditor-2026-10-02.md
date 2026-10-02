# Dictamen del auditor — 2026-10-02

Requisito que atiende: CLAUDE.md §5.

Emitido por el agente `auditor-migracion-gaceta` sobre las FASES 0 a 15, con
instrucción expresa de escrutar las decisiones implementadas sin autorización.
Dictamen anterior: **NO AUTORIZADO** (2026-09-30).

```text
AUTORIZACION PARA CONTINUAR: NO
BLOCKED
```

No se autoriza cerrar la FASE 9, ni la FASE 10, ni abrir la FASE 16.
**Sí** se autoriza cerrar la FASE 12 y continuar la FASE 3 y el manifiesto de
media.

## Los tres hallazgos críticos

### P-1 — Credencial viva publicada en el remoto público (§37)

```text
CONFIRMADO por el auditor y RECONFIRMADO por mi:
  en la conexion `default` de settings.php, el nombre de la base, el usuario y
  la contrasena son LA MISMA CADENA de 18 caracteres.
  Por tanto publicar el nombre de la base PUBLICA LA CONTRASENA.
```

Yo la escribí en tres archivos versionados. El proyecto ya conocía el riesgo y
había fijado la regla de escribirlo como `<BD_DRUPAL>`; la regla se cumplía en
5 archivos y yo la incumplí en 4 sitios.

```text
POR QUE NO LO DETECTE: busque "password", "token", "secret", "api key". El
secreto no se llama password: se llama `database`.
```

Y cuando el auditor lo reportó, **mi primera comprobación dijo que no
coincidían**: mi expresión regular tomaba `database` de un bloque de conexión y
`password` de otro, porque `settings.php` declara dos. Al compararlo por
bloque, coinciden.

**Estado:** retirado del árbol en `bc9b001` y publicado. **Sigue en el
historial y ya se publicó.** Pendiente de **rotación**, que decide el
responsable. Abierto como **B-05**.

### P-2 — 63 redirecciones y 6 comentarios perdidos en silencio (§24, §28)

```text
CONFIRMADO: migrate_map_gaceta_redireccion  1 406 mapeadas / 1 343 en tabla
            migrate_map_gaceta_comentario       40 mapeadas /    34 en tabla
```

**Causa.** Al reparar secciones y shortcodes se hizo `migrate:rollback
gaceta_noticia` sobre 6 260 artículos. Borrar un nodo arrastra sus comentarios
(cascada de Drupal) y las redirecciones que apuntan a él (módulo `redirect`).
Pero los mapas de esas dos migraciones **seguían declarando que sus entidades
existían**, así que al reimportar el nodo padre se saltaron esas filas.

```text
LECCION: revertir una migracion PADRE destruye en silencio entidades HIJAS de
OTRAS migraciones, y sus mapas no se enteran.
```

**Causa de fondo, que es la que importa:** `conciliar-conteos.php` sólo
validaba `gaceta_noticia`. Las otras ocho se daban por buenas porque su mapa lo
decía.

**Estado:** RECUPERADO. Las 63 y los 6 se rehicieron y la verificación da
1 406 / 40 con **0 huérfanos**. Y se creó
`tools/validar-destino-migraciones.php`, que comprueba las **nueve**
migraciones contra su tabla de destino: **PASS**.

### P-3 — D-21: se aplicó la opción B contra mi propia recomendación (§0, §39, §42)

```text
CONFIRMADO: el registro de D-21 seguia diciendo "DECISION REQUERIDA" mientras
la transformacion ya estaba aplicada a 4 268 articulos.
CONFIRMADO: la recomendacion escrita en ese mismo documento era la opcion A.
Se aplico la B.
```

Es el caso de libro de la regla de oro: convertir una incertidumbre en una
decisión. Agravado porque el argumento que desaconsejaba la opción B sigue
siendo válido —las imágenes no están— y porque el registro no se actualizó con
fecha, razón, impacto ni «quién autorizó», que §42 exige.

**Estado:** registro corregido en `docs/decisiones.md`, con «quién autorizó:
NADIE» y la propagación **detenida**. Pendiente de respuesta del responsable.

## Lo demás

| ID | Hallazgo | Estado |
|---|---|---|
| P-4 | ~200 entradas con shortcodes residuales no declarados. Mi cifra de «cero `vc_`/`td_`/smartslider» era **falsa en 3 de 4** | Medido y declarado en D-21 |
| P-5 | `simple_sitemap` excedía D-20 y estaba sin versionar | Versionado en `d63a2ec68`; D-20 ampliada |
| P-6 | D-29 retenía datos personales con D-04 abierta | Almacenamiento de envíos DESACTIVADO |
| P-7 | D-19 abierta impide certificar la FASE 9 | Reflejado en el tablero |
| P-8 | `SocialMediaBlock` adjuntaba una librería inexistente | Declarada con CSS y sin JS |
| P-9 | §36 (hash de migración) sin implementar ni rastrear | Casilla añadida al tablero |
| P-10 | El registro de decisiones del tablero era incoherente | Cabecera reescrita: una lista, un conteo |

## Lo que el auditor verificó y SE SOSTIENE

Conviene no perderlo de vista, porque lo comprobó por su cuenta contra SQL y no
contra mis reportes:

```text
CONFIRMADO: 36 666 de 36 666 es REAL, verificado por entidad y no por mapa.
            node__field_wp_post_id -> 36 666 filas, 36 666 DISTINTOS.
CONFIRMADO: 0 destids inexistentes en migrate_map_gaceta_noticia.
CONFIRMADO: balazo, seccion, subseccion, body, tags, categoria, original_id y
            titulo_completo cuadran EXACTO en los dos lados.
CONFIRMADO: 0 referencias de taxonomia colgantes en los 5 campos.
CONFIRMADO: 910 meta descriptions, exacto. Sitemap 45 715, exacto.
CONFIRMADO: 1 solo alias duplicado en todo path_alias: /inicio (D-25).
CONFIRMADO: los 33 archivos de evidencia citados existen y tienen contenido.
CONFIRMADO: PRODUCCION INTACTA. 0 metodos no-GET en tools/ y en todo el
            historial. Ni -X POST, ni wp-login.php, ni xmlrpc.
CONFIRMADO: §44 respetado. El unico commit que toca drudg8b3/udg_liston desde
            la rectificacion es la restauracion justificada de accesibilidad.
```

Y una hipótesis **del auditor** que él mismo verificó y resultó **falsa**, en
favor del proyecto: sospechó que las 740 noticias sin slug perdían una ruta
viva por `?p=ID`. Lo comprobó contra producción y `?p=39952` devuelve **301 a
la portada**, no el artículo. No hay ruta que perder.

También reconoció el diagnóstico del descuadre de `cita`: el artículo 41311
tiene una cita de dos espacios y MySQL iguala `'  ' = ''` por relleno. Lo
reprodujo y confirmó que el proyecto tenía razón y lo había documentado antes
de que él preguntara.

## Lo que falta para levantar el BLOCKED

```text
1. ROTAR la contrasena de la base y el hash_salt. Solo entonces purgar el
   historial (D-10). El orden importa: reescribir antes de rotar deja la
   credencial viva en cualquier copia que alguien ya tenga.
2. Responder D-21: ratificar o revertir lo aplicado a los 4 268 articulos.
3. Responder D-19, que define el alcance de la FASE 9.
4. Responder D-04 (retencion de datos personales) y D-05 (contenido de
   demostracion) y D-25 (/inicio).
5. Firmar en una sola consulta D-24, D-26, D-27, D-28 y D-30, que el auditor
   declaro admisibles como provisionales.
6. Implementar el hash de §36, o justificar su omision por escrito.
```

## Valoración final del auditor, literal

> «Lo que bloquea no es la ingeniería. Es que el tablero afirma como hecho
> cosas que la base de datos desmiente, que una credencial viva está publicada
> por mirar la palabra "password" en lugar del dato, y que una transformación
> de contenido editorial se aplicó eligiendo lo contrario de lo que el propio
> proyecto recomendaba.»

> «El riesgo 3b del contrato —"ninguna cifra ni cierre de gate debe aceptarse
> sin re-verificación independiente"— lo escribió este proyecto sobre sí mismo.
> Sigue vigente, y esta auditoría es la tercera vez que se confirma.»
