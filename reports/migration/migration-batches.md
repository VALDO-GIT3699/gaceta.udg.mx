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
