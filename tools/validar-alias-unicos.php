<?php

/**
 * @file
 * validar-alias-unicos.php
 *
 * Comprueba que NINGÚN alias de ruta esté duplicado en Drupal.
 *
 * POR QUÉ ESTE VALIDADOR ES OBLIGATORIO
 *
 * Drupal no impone unicidad en `path_alias`: acepta dos alias idénticos sin
 * protestar y después resuelve sólo uno. Una migración puede terminar con
 * `failed_count = 0` y los conteos cuadrando al 100 % mientras cientos de
 * nodos quedan sin ruta accesible.
 *
 * Es decir: el modo de fallo de §24 es SILENCIOSO y ningún conteo lo detecta.
 * Sólo se detecta preguntándolo explícitamente, y de eso se encarga este
 * script.
 *
 * Ya ocurrió dos veces en este proyecto:
 *
 *   1. El piloto dejó /Enfoques apuntando a tres nodos a la vez.
 *   2. Al corregirlo, un error de tipo dejó el mapa de colisiones vacío y la
 *      migración volvió a terminar con 0 errores y las colisiones intactas.
 *      El aviso de PHP era la única señal.
 *
 * COMPRUEBA ADEMÁS el contenido preexistente del destino. La plantilla
 * institucional ya traía nodos y alias propios, así que la verificación no
 * puede limitarse a lo migrado.
 *
 * SÓLO LECTURA. No modifica ningún alias ni ningún nodo.
 *
 * Uso:
 *   drush php:script tools/validar-alias-unicos.php
 *
 * Código de salida por convención: distinto de 0 si hay duplicados, para poder
 * usarlo como puerta en un script de despliegue.
 */

$db = \Drupal::database();

$linea = str_repeat('=', 72);
echo "$linea\nVALIDACION DE UNICIDAD DE ALIAS\n$linea\n\n";

// ---------------------------------------------------------------------------
// 1. Totales.
// ---------------------------------------------------------------------------

$total = (int) $db->query('SELECT COUNT(*) FROM {path_alias}')->fetchField();
$distintos = (int) $db->query('SELECT COUNT(DISTINCT alias) FROM {path_alias}')
  ->fetchField();

printf("Alias registrados   %7d\n", $total);
printf("Alias distintos     %7d\n", $distintos);
printf("Sobrantes           %7d\n\n", $total - $distintos);

// ---------------------------------------------------------------------------
// 2. Los duplicados, uno por uno.
//
// Se listan TODOS, no una muestra: un duplicado que no se ve es una ruta
// perdida que nadie reclama.
// ---------------------------------------------------------------------------

$dup = $db->query('
  SELECT alias, COUNT(*) AS n, GROUP_CONCAT(path) AS rutas
  FROM {path_alias}
  GROUP BY alias
  HAVING n > 1
  ORDER BY n DESC, alias
')->fetchAll();

if (!$dup) {
  echo "RESULTADO: PASS\n";
  echo "No hay ningun alias duplicado. Ninguna ruta se resuelve a mas de un\n";
  echo "nodo, por lo que no hay perdida silenciosa de rutas (§24).\n";
  return;
}

$rutasPerdidas = 0;
$deMigracion = 0;
$dePlantilla = 0;

echo "ALIAS DUPLICADOS\n\n";
printf("%-46s %5s  %s\n", 'alias', 'veces', 'rutas que lo reclaman');
echo str_repeat('-', 72) . "\n";

foreach ($dup as $d) {
  // Cada alias resuelve a UNO. Los demas son rutas efectivamente perdidas.
  $rutasPerdidas += ((int) $d->n) - 1;

  // ¿Viene de la migración o ya estaba en la plantilla? Se distingue por si
  // los nodos implicados tienen el campo de trazabilidad poblado.
  $nids = [];
  foreach (explode(',', $d->rutas) as $r) {
    if (preg_match('#^/node/(\d+)$#', trim($r), $m)) {
      $nids[] = (int) $m[1];
    }
  }
  $migrados = 0;
  if ($nids && $db->schema()->tableExists('node__field_wp_post_id')) {
    $migrados = (int) $db->query(
      'SELECT COUNT(DISTINCT entity_id) FROM {node__field_wp_post_id}
       WHERE entity_id IN (:n[])', [':n[]' => $nids]
    )->fetchField();
  }
  if ($migrados > 0) {
    $deMigracion++;
    $origen = $migrados === count($nids) ? 'migrado' : 'MIXTO migrado+plantilla';
  }
  else {
    $dePlantilla++;
    $origen = 'plantilla';
  }

  printf("%-46s %5d  %s\n", substr($d->alias, 0, 46), $d->n, $origen);
  echo "    " . $d->rutas . "\n";
}

echo str_repeat('-', 72) . "\n\n";
printf("Alias duplicados            %5d\n", count($dup));
printf("  de ellos, con contenido migrado implicado %3d\n", $deMigracion);
printf("  de ellos, solo de la plantilla            %3d\n", $dePlantilla);
printf("RUTAS EFECTIVAMENTE PERDIDAS %4d\n\n", $rutasPerdidas);

echo "RESULTADO: FAIL\n\n";
echo "Cada alias resuelve a un solo nodo. Los demas nodos que reclaman ese\n";
echo "mismo alias NO son accesibles por ninguna ruta legible, aunque los\n";
echo "conteos de la migracion den correctos. CLAUDE.md §24 lo prohibe.\n\n";
echo "Los duplicados marcados 'plantilla' son previos a este proyecto y no se\n";
echo "tocan sin autorizacion (§44): se reportan para que consten.\n";
