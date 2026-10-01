# Lotes de migración

Requisito: CLAUDE.md §33 (FASE 9) y §41.

Cada lote registra `source_count`, `created_count`, `updated_count`,
`failed_count` y `skipped_count`.

```text
REGLA: no se continúa si failed_count > 0 sin explicación aprobada.
```

---

## Lote 1 — Taxonomías y créditos editoriales

```text
Fecha:        2026-10-01
Migraciones:  gaceta_seccion, gaceta_subseccion, gaceta_credito, gaceta_etiqueta
Orden:        sin dependencias entre ellas; se ejecutaron con --tag=gaceta
Origen:       gaceta_auditoria, usuario gaceta_ro (SELECT únicamente)
Destino:      webPlantillaUDGD10
RESULTADO:    PASS
```

### Conteos

| Migración | source | created | updated | failed | ignored |
|---|---:|---:|---:|---:|---:|
| `gaceta_seccion` | 447 | **447** | 0 | **0** | 0 |
| `gaceta_subseccion` | 654 | **654** | 0 | **0** | 0 |
| `gaceta_credito` | 142 | **142** | 0 | **0** | 0 |
| `gaceta_etiqueta` | 7 724 | **7 724** | 0 | **0** | 0 |
| **TOTAL** | **8 967** | **8 967** | 0 | **0** | 0 |

```text
failed_count = 0 en las cuatro. No hay nada que explicar ni aprobar.
```

### Conciliación origen contra destino

Conteo independiente en Drupal, consultado después de la importación:

| Vocabulario | Términos en Drupal | Esperados | ¿Cuadra? |
|---|---:|---:|---|
| `tags` | 7 724 | 7 724 | sí |
| `subseccion_historica` | 654 | 654 | sí |
| `seccion_historica` | 447 | 447 | sí |
| `credito_editorial` | 142 | 142 | sí |

```text
CONFIRMADO: conciliación exacta. Ninguna pérdida.
```

Los 60 términos de los 8 vocabularios del template (`centro_sede`,
`modalidad`, `puesto`…) permanecen intactos: no se tocaron.

### Validación del encoding, de extremo a extremo

Es la validación que más importaba, porque cierra en datos reales lo que
`reports/audit/encoding-audit.md` había demostrado por análisis de bytes.

El texto recorrió: columna utf8mb3 en el origen → conexión utf8mb4 →
Migrate API → columna utf8mb4 en Drupal.

```text
Buzón                      sección
Música                     sección
Rubén Hernández Rentería   crédito
Iván Serrano Jauregui      crédito
```

```text
CONFIRMADO: los acentos sobreviven intactos. La estrategia de NO CONVERTIR
era la correcta, y ahora está verificada contra datos migrados.
```

### Validación de la trazabilidad (§35)

Muestra de términos de crédito con sus campos de origen poblados:

| Término en Drupal | `field_wp_user_id` | `field_wp_user_login` |
|---|---:|---|
| Universidad de Guadalajara | 1 | `edvargas20` |
| Historico UdeG | 2 | `Historico` |
| Mariano Ceballos | 3 | `Mariano Ceballos` |
| Fernando Ocegueda | 4 | `Fernando Ocegueda` |
| Rubén Hernández Rentería | 5 | `Ruben Hernandez` |
| Iván Serrano Jauregui | 6 | `Jaureguivan` |

```text
CONFIRMADO: se puede responder "¿dónde terminó este usuario de WordPress?"
sin depender de las tablas de Migrate.
```

Nótese el caso del usuario 1: nombre mostrado "Universidad de Guadalajara" y
login `edvargas20`. Es la cuenta genérica que acumula el 64.5 % del corpus, y
el término conserva ambos datos, lo que permitirá tratarla de forma distinta
cuando se migre el contenido.

### Cifras que corrigen estimaciones previas

| Dato | Estimado antes | Real |
|---|---:|---:|
| Secciones distintas | ~200 | **447** |
| Subsecciones distintas | ~1 500 | **654** |

Ambas estimaciones eran de `docs/migration-strategy.md` y se hicieron sin
contar. Las reales salen de `SELECT DISTINCT` sobre los datos cargados.

### Lo que este lote NO hizo

```text
No se migró contenido: ningún nodo.
No se migraron contraseñas, hashes, claves de activación ni correos (§27, §37).
No se crearon cuentas de usuario: los créditos son términos (D-14).
No se escribió en el origen: el usuario gaceta_ro sólo tiene SELECT.
No se tocó el WordPress de producción.
```

### Reversibilidad

```bash
drush migrate:rollback --tag=gaceta
```

Revierte los 8 967 términos usando las tablas de mapeo de Migrate. El origen
permanece intacto y las migraciones se pueden reejecutar: son idempotentes por
el mapa de identificadores.

### Pendientes que este lote deja abiertos

```text
Las 447 secciones y 654 subsecciones se migraron TAL CUAL, lo que incluye:
  - variantes de mayúsculas ("Artes visuales" y "Artes Visuales" son dos
    términos distintos)
  - 1 066 subsecciones truncadas a 15 caracteres por el sistema anterior

Normalizarlas modifica contenido editorial y es decisión de la redacción, no
técnica. Migrar primero con fidelidad y normalizar después, si se autoriza, es
reversible; lo contrario no.
```

```text
Los 7 724 términos de etiqueta se migraron sin su slug de origen. La FASE 10
necesitará ese slug para reproducir las rutas /tag/<slug>/ indexadas. El dato
está disponible en el origen y se añadirá al configurar pathauto.
```

---

## Lote 2 — Piloto de contenido (FASE 5)

```text
Fecha:       2026-10-01
Migración:   gaceta_noticia
Alcance:     60 entradas, las más antiguas por fecha
RESULTADO:   PASS, tras tres iteraciones
```

CLAUDE.md FASE 5 exige no migrar todo de golpe y que el 100 % de los casos
piloto tenga resultado conocido. Esto es lo que encontró.

### Conteos por iteración

| Iteración | source | created | failed | Causa del fallo |
|---|---:|---:|---:|---|
| 1ª (25 registros) | 25 | 18 | **7** | `field_balazo` demasiado corto |
| 2ª (40 registros) | 40 | 35 | **5** | título nulo en el origen |
| 3ª (60 registros) | 60 | 60 | **0** | — |

```text
El piloto encontró dos defectos de DISEÑO PROPIO antes de tocar las 36 666
entradas. Es exactamente su función.
```

### Defecto 1 — `field_balazo` mal dimensionado

Se diseñó como `string` de 255 caracteres, asumiendo que un antetítulo es
corto. La medición del origen desmiente el supuesto:

| Campo | Valores | Máximo | Pasan de 255 | Con HTML |
|---|---:|---:|---:|---:|
| `balazo` | 13 914 | **973** | **2 571** | **7 205** |
| `cita` | 1 876 | 487 | 136 | **0** |

```text
Error: SQLSTATE[22001] Data too long for column 'field_balazo_value'
```

Corregido a `text_long` con formato de texto, porque más de la mitad de los
valores contienen HTML. `field_cita` se queda en `string_long`: ninguno de sus
1 876 valores lleva HTML y el máximo son 487 caracteres.

Se añadió además una **guarda de tipo** a `tools/setup-content-model.php`, que
aborta si un nombre de campo ya existe con otro tipo.

### Defecto 2 — 740 entradas sin título

```text
Error: SQLSTATE[23000] Column 'title' cannot be null
```

Resuelto con un marcador explícito en lugar de descartar o inventar. Detalle y
alternativas en `docs/decisiones.md` (D-22).

### Defecto 3 — entidades HTML en el título

No produjo un fallo, así que no habría aparecido en los conteos. Se detectó al
**mirar** el resultado, no al contarlo:

```text
Título migrado:  "Qu&eacute; bien qu&eacute; mal"
Debería ser:     "Qué bien qué mal"
```

```text
CONFIRMADO: 2 948 títulos del corpus (el 8 %) traen entidades HTML.
CONFIRMADO: 6 500 balazos también, y 6 de los 7 724 términos de etiqueta.
```

El título de un nodo es un campo de **texto plano**: Drupal lo escapa al
renderizar, así que la entidad se vería literal en la página, en la pestaña del
navegador y en los menús.

Se decodifican las entidades en el **título** y en el **nombre de los
términos**. No es una transformación editorial: `&eacute;` y `é` son el mismo
carácter, uno escapado y otro no, y en texto plano conservar el escape es
simplemente incorrecto.

```text
El CUERPO y el BALAZO no se decodifican, y es deliberado: son campos HTML,
donde las entidades se renderizan bien y tocarlas sí alteraría contenido.
```

Verificado tras la corrección: **0 títulos con entidades** en los 60 migrados.

### Conciliación de un registro, origen contra destino

| | Origen (`dc8_posts` ID 46340) | Destino (Drupal) |
|---|---|---|
| Título | `Nuevas fachadas para Tonal&aacute;` | `Nuevas fachadas para Tonalá` |
| Slug | `Nuevas-fachadas-para-Tonala` | `/Nuevas-fachadas-para-Tonala` |
| Cuerpo | 2 540 caracteres | **2 540 caracteres** |
| Sección | `Universidad` | `Universidad` |

```text
CONFIRMADO: el cuerpo pasa con la MISMA longitud exacta. No se filtró ni se
transformó nada.
CONFIRMADO: la URL se preserva con sus mayúsculas. pathauto no la normalizó.
CONFIRMADO: la referencia a la sección se resolvió contra el término migrado
en el lote 1.
```

Esa segunda línea importa más de lo que parece: `docs/url-strategy.md`
advertía que si pathauto generara el alias desde el título, 24 260 URLs con
mayúsculas cambiarían en silencio. El piloto demuestra que no ocurre.

### Campos poblados en los 60

```text
con_wp_id       60/60    trazabilidad completa
con_autor       60/60    todos resueltos contra credito_editorial
con_subseccion  49/60
con_seccion     43/60
con_balazo      26/60
con_etiquetas    1/60    esperado: el contenido de 1995-2005 casi no tiene
con_cita         1/60
alias de ruta   55/60    los 5 sin alias son las 5 entradas sin slug
```

### Lo que queda pendiente de este piloto

```text
Los 17 casos límite que docs/migration-strategy.md enumera NO se han cubierto
todos. El piloto tomó las 60 entradas más antiguas por fecha, que cubren:
registro vacío, fecha inválida, título ausente, slug ausente, entidades HTML,
sección y autor genérico.

FALTAN por probar explícitamente: una entrada con Elementor (de las 2 359),
una con mojibake de puntuación, una con subsección truncada, una con slug en
colisión, una con comentario aprobado, y una con meta description propia.
```

```text
GATE FASE 5: NO SUPERADO TODAVÍA. Falta cubrir esos seis casos.
```

No se procede a la migración masiva hasta cubrirlos: es justo lo que el
contrato pide y lo que acaba de demostrar su valor.
