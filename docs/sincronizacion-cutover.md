# Sincronización final y cutover

Fecha: 2026-10-02
Requisito que atiende: CLAUDE.md FASE 16, §33, §48.

```text
Este documento NO describe ninguna operacion de escritura sobre el WordPress
de produccion, que es de solo lectura (D-07, §38). Todo lo que sigue se ejecuta
sobre copias y sobre el sitio Drupal.
```

## El problema, con fecha y cifra

WordPress **sigue siendo el sitio editorial** y sigue publicando. La migración
se hizo sobre un volcado, y un volcado es una foto de un instante:

```text
CONFIRMADO: el volcado wp/DB/gaceta (2).sql se tomo el 2026-09-17 12:39.
CONFIRMADO: la ultima publicacion que contiene es del 2026-09-18 06:29.
CONFIRMADO: 12 860 articulos del volcado tienen post_modified posterior a
            post_date, es decir se editaron despues de publicarse.
```

```text
HOY ES 2026-10-02. Son 15 DIAS de contenido editorial que la migracion no
tiene, y la brecha crece cada dia que pasa.
```

§33 lo advierte: «No declares terminado el proyecto simplemente porque una
copia anterior fue migrada.»

## Lo que NO es un problema

La brecha **no invalida nada de lo hecho**. La migración es reproducible: las
mismas herramientas, sobre un volcado más nuevo, producen el mismo resultado
más los registros nuevos. Lo que hay que hacer no es rehacer, es **añadir**.

## Cómo se sincroniza, y por qué Migrate lo hace fácil

Migrate guarda en `migrate_map_*` qué registro de origen produjo qué entidad de
destino. Al reejecutar con un volcado nuevo:

```text
registro YA en el mapa y sin cambios   ->  se salta
registro YA en el mapa y modificado    ->  se ACTUALIZA el nodo existente
registro NUEVO                         ->  se CREA
```

Es decir: **no se duplica nada y no se pierde el trabajo de validación**. El
`nid` de cada artículo se conserva, y con él sus alias y sus redirecciones.

### El procedimiento

```bash
# 1. Volcado NUEVO de produccion. Lo pide quien administra el servidor; este
#    proyecto no tiene permiso de escritura y tampoco lo necesita: basta un
#    mysqldump de lectura.

# 2. Recargar la base de auditoria con el volcado nuevo.
#    NO se borra la anterior hasta comprobar que la nueva carga bien.
python tools/extract-tables.py --tabla dc8_posts    | mysql gaceta_auditoria_nueva
python tools/extract-tables.py --tabla dc8_postmeta | mysql gaceta_auditoria_nueva
# ... y el resto de tablas dc8_*

# 3. Comprobar el encoding del volcado NUEVO antes de migrar nada.
#    No se da por bueno porque el anterior lo fuera.
python tools/audit-encoding.py

# 4. Apuntar Drupal a la base nueva en settings.php (clave gaceta_wp).

# 5. Taxonomias y creditos primero: puede haber secciones o firmas nuevas.
drush migrate:import gaceta_seccion
drush migrate:import gaceta_subseccion
drush migrate:import gaceta_credito
drush migrate:import gaceta_etiqueta
drush migrate:import gaceta_categoria

# 6. Contenido. --update para que los articulos EDITADOS se actualicen.
drush migrate:import gaceta_pagina --update
bash tools/migrar-noticias-por-lotes.sh 1000 60 update

# 7. Redirecciones y comentarios nuevos.
drush migrate:import gaceta_redireccion
drush migrate:import gaceta_comentario

# 8. Limpiar los alias redundantes que deja todo reproceso.
drush php:script tools/limpiar-alias-redundantes.php -- --si

# 9. LAS TRES PUERTAS. Ninguna es opcional.
drush php:script tools/validar-alias-unicos.php      # §24
drush php:script tools/conciliar-conteos.php         # §34
bash tools/prueba-humo.sh                            # FASE 15

# 10. Reindexar la busqueda y regenerar el sitemap.
drush php:script tools/indexar-busqueda.php
drush php:script tools/generar-sitemap.php
```

```text
EL PASO 8 NO SE SALTA. Una pasada de --update sobre 8 305 nodos dejo 13 749
filas de alias sobrantes. No rompen nada, pero tapan las POCAS que si son una
colision real, y entonces la puerta del paso 9 deja de servir para nada.
```

## Lista de comprobación antes del cutover

```text
[ ] Volcado nuevo cargado y con el encoding verificado de nuevo
[ ] Conciliacion de conteos: 0 ausentes, 0 sobrantes, campos cuadrados
[ ] validar-alias-unicos.php da PASS, sin alias que lleven a dos contenidos
[ ] prueba-humo.sh da PASS
[ ] Las fotos recibidas y copiadas a sites/default/files/migrado/
[ ] Las URL ABSOLUTAS del cuerpo reescritas: 21 207 imagenes y 6 039 enlaces
    apuntan hoy a www.gaceta.udg.mx y se rompen EL DIA DEL CAMBIO
    (ver reports/validation/enlaces-internos.md)
[ ] Indice de busqueda completo
[ ] Sitemap con base_url al dominio REAL, no al de pruebas
[ ] Las decisiones abiertas resueltas, en particular D-05 y D-25
[ ] Dictamen del auditor con AUTORIZACION PARA CONTINUAR: SI
[ ] Respaldo de la base de Drupal, con backup_migrate, inmediatamente antes
```

```text
LA MAS FACIL DE OLVIDAR es la de las URL absolutas, porque HOY FUNCIONAN: el
dominio sigue sirviendo WordPress. Una revision visual previa al cutover las ve
perfectas y da un FALSO POSITIVO. Se rompen en el momento en que el DNS
cambia.
```

## El cutover

```text
1. Congelar la publicacion en WordPress. Hora acordada con la redaccion.
2. Volcado FINAL y sincronizacion, con los pasos 2 a 10 de arriba.
3. Ejecutar las tres puertas por ultima vez.
4. Cambiar el DNS o la configuracion del servidor web a Drupal.
5. Comprobar en caliente: portada, un articulo, una seccion, una redireccion
   301 y un formulario.
6. NO desmontar WordPress. Se deja accesible en un dominio interno durante al
   menos un mes.
```

### Vuelta atrás

```text
El plan de retirada es el DNS: se devuelve a WordPress, que sigue intacto
porque este proyecto nunca escribio en el (D-07).
```

Eso es lo que convierte el cutover en una operación reversible en minutos, y es
la razón de fondo por la que producción se ha tratado como de sólo lectura
desde el primer día.

## Después del cutover

```text
[ ] Vigilar los 404 durante dos semanas: es ahi donde aparece una ruta que se
    perdio y que ningun conteo detecto.
[ ] Dar de alta el sitemap en Search Console.
[ ] Revisar que el cron de Drupal mantiene el indice de busqueda al dia.
[ ] Decidir el destino del WordPress retirado.
```
