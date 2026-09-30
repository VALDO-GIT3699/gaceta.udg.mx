# Flujo de trabajo Git — Gaceta UDG

Deriva de CLAUDE.md §2, §3, §4 y §37. Es de cumplimiento obligatorio.

## Repositorio

```text
git@github.com:VALDO-GIT3699/gaceta.udg.mx.git
```

Fuente de verdad del desarrollo. La estructura definitiva del repositorio está
pendiente de la decisión **D-06** (`docs/decisiones.md`).

```text
ADVERTENCIA: el repositorio es actualmente PÚBLICO.
```

Mientras siga siéndolo, **no se publica** ningún reporte con muestras de
contenido editorial, comentarios de lectores ni envíos de formularios.

## Mensajes de commit

En español, descriptivos, con prefijo de ámbito.

Prefijos en uso:

```text
migración:   extracción, transformación o carga de datos
auditoría:   inventarios, análisis, evidencia
tema:        drudg8b3 y presentación
módulo:      udg_liston y módulos custom
config:      configuración de Drupal (exportada/importada)
validación:  scripts y reportes de comparación
docs:        documentación y contrato
entorno:     herramientas, scripts operativos
```

### Prohibido

Mensajes genéricos. CLAUDE.md §2 los enumera explícitamente:

```text
update · fix · changes · stuff · migration · test
```

### Ejemplos válidos

```text
auditoría: inventaria el esquema del dump WordPress y confirma el conflicto de encoding
tema: establece drudg8b3 como tema por defecto del sitio
migración: preserva slugs y rutas históricas de Gaceta
validación: agrega reporte de conteos WordPress contra Drupal
```

## Un commit, un cambio lógico

No se mezclan en un mismo commit:

```text
migración · cambios visuales · refactor · limpieza · dependencias
```

salvo razón documentada en el propio mensaje del commit.

## Ramas

```text
feature/auditoria-wordpress
feature/modelo-contenido-gaceta
feature/migracion-media
feature/migracion-contenido
feature/urls-seo
feature/vistas-gaceta
feature/tema-gaceta
feature/validacion-migracion
```

## Pull requests

Cada PR explica, en español: objetivo, cambios, archivos afectados, decisiones
tomadas, pruebas realizadas, resultados, riesgos conocidos, pendientes y
evidencia de validación.

## Reporte por commit relevante

Formato de CLAUDE.md §4:

```text
COMMIT:
Título:

QUÉ SE HIZO:
POR QUÉ:
ARCHIVOS MODIFICADOS:
PRUEBAS:
RESULTADO:    PASS / PASS CON OBSERVACIONES / BLOCKED
PENDIENTES:
RIESGOS:
SIGUIENTE PASO:
```

Los reportes se archivan en `reports/migration/`.

## Correo

```text
CONFIRMADO: no existe ningún mecanismo de notificación por correo configurado
            para este proyecto.
```

CLAUDE.md §4 exige no afirmar que se enviará correo hasta comprobar que el
mecanismo existe y funciona. **No se ha comprobado, por lo tanto no se
afirma.**

GitHub envía notificaciones propias de push y PR a quien esté suscrito al
repositorio; eso es funcionalidad de GitHub, no automatización de este
proyecto. Si se requiere notificación adicional, se propondrá GitHub Actions
con secretos de GitHub. **No se inventan credenciales SMTP ni se guardan en
Git.**

## Nunca versionar

De CLAUDE.md §37, y verificado contra este proyecto concreto:

| Ruta / tipo | Motivo |
|---|---|
| `sites/default/settings.php` | contiene `hash_salt` y la contraseña de la base de datos |
| `sites/default/services.yml` | configuración sensible del contenedor |
| `wp/` completo | dump de 3.54 GB y copia del WordPress |
| `wp/DB/*.sql` | dump con todo el contenido y datos de usuarios |
| `vendor/` | se reconstruye con Composer |
| `sites/*/files/` | contenido subido por usuarios |
| cualquier `.sql` | volcados de base de datos |
| contraseñas, tokens, API keys, cookies, hashes | §37 |
| JSON de cuentas de servicio, claves privadas | §37 |

Si aparece un dato sensible en un reporte, se escribe `REDACTED`.

## Antes de cada commit

```bash
git status
git remote -v
git branch --show-current

# verificar que no se cuela nada sensible:
git diff --cached --name-only | grep -iE "settings\.php|services\.yml|\.sql$|\.env"
```

Si ese último comando devuelve algo, **no se hace commit**.
