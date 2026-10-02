<?php

/**
 * @file
 * validar-alias-unicos.php
 *
 * Comprueba que ningún alias de ruta lleve a dos contenidos distintos.
 *
 * POR QUÉ ESTE VALIDADOR ES OBLIGATORIO
 *
 * Drupal no impone unicidad en `path_alias`: acepta dos alias idénticos sin
 * protestar y después resuelve sólo uno. Una migración puede terminar con
 * `failed_count = 0` y los conteos cuadrando al 100 % mientras cientos de
 * nodos quedan sin ruta accesible.
 *
 * Es decir: el modo de fallo de §24 es SILENCIOSO y ningún conteo lo detecta.
 * Sólo se ve preguntándolo explícitamente, y de eso se encarga este script.
 *
 * Ya ha encontrado cuatro fallos distintos en este proyecto:
 *
 *   1. El piloto dejó /Enfoques apuntando a tres nodos a la vez.
 *   2. Un error de tipo dejó el mapa de colisiones vacío: la migración acabó
 *      con 0 errores y las colisiones intactas.
 *   3. La colación de MySQL agrupaba sin distinguir acentos y PHP buscaba byte
 *      a byte: 66 rutas perdidas con 0 errores.
 *   4. Colisiones entre una noticia y una PÁGINA, que el mapa no veía porque
 *      filtraba post_type = 'post'.
 *
 * Los cuatro daban conteos correctos.
 *
 * COMPRUEBA ADEMÁS el contenido preexistente del destino: la plantilla
 * institucional ya traía nodos y alias propios, así que la verificación no
 * puede limitarse a lo migrado.
 *
 * SÓLO LECTURA. No modifica ningún alias ni ningún nodo.
 *
 * Uso:
 *   drush php:script tools/validar-alias-unicos.php
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

printf("Filas en path_alias   %7d\n", $total);
printf("Alias distintos       %7d\n\n", $distintos);

// ---------------------------------------------------------------------------
// 2. DOS CLASES DE DUPLICADO, y confundirlas es peligroso.
//
// Esta distinción se añadió después de que este mismo validador reportara
// 8 818 grupos duplicados y yo lo leyera como una catástrofe. No lo era:
//
//   MISMO alias -> RUTAS DISTINTAS   el visitante llega a UN solo contenido y
//                                    el resto queda inaccesible. Es la pérdida
//                                    silenciosa de rutas que §24 prohíbe.
//
//   MISMO alias -> LA MISMA ruta     filas redundantes. El visitante llega
//                                    igual a su contenido. Es suciedad de
//                                    registro, no pérdida.
//
// Las redundantes las genera Drupal al REPROCESAR un nodo: al guardarlo otra
// vez inserta una fila de alias nueva en lugar de actualizar la que ya tenía.
// Una pasada sobre 8 305 nodos dejó 13 749 filas sobrantes.
//
// SI SE CUENTAN JUNTAS, miles de filas inocuas esconden las pocas que sí son
// una pérdida real. Por eso se reportan separadas y SÓLO la primera clase hace
// fallar la validación.
// ---------------------------------------------------------------------------

$peligrosos = $db->query('
  SELECT alias, COUNT(DISTINCT path) AS rutas,
         GROUP_CONCAT(DISTINCT path) AS cuales
  FROM {path_alias}
  GROUP BY alias
  HAVING rutas > 1
  ORDER BY rutas DESC, alias
')->fetchAll();

$pares = (int) $db->query('
  SELECT COUNT(*) FROM (
    SELECT alias, path FROM {path_alias}
    GROUP BY alias, path HAVING COUNT(*) > 1
  ) t
')->fetchField();

$sobrantes = (int) $db->query('
  SELECT COUNT(*) - COUNT(DISTINCT CONCAT(path, :sep, alias)) FROM {path_alias}
', [':sep' => '||'])->fetchField();

echo "FILAS REDUNDANTES (mismo alias y MISMA ruta)\n\n";
printf("  Filas sobrantes     %7d\n", $sobrantes);
printf("  Pares afectados     %7d\n\n", $pares);
echo "  NO son perdida de rutas: el visitante llega igual a su contenido.\n";
echo "  Se limpian con tools/limpiar-alias-redundantes.php\n\n";

echo str_repeat('-', 72) . "\n\n";

if (!$peligrosos) {
  echo "RESULTADO: PASS\n\n";
  echo "Ningun alias lleva a dos contenidos distintos. No hay perdida\n";
  echo "silenciosa de rutas (CLAUDE.md §24).\n";
  return;
}

$rutasPerdidas = 0;
$deMigracion = 0;
$dePlantilla = 0;

echo "ALIAS QUE LLEVAN A CONTENIDOS DISTINTOS\n\n";

foreach ($peligrosos as $d) {
  // Cada alias resuelve a UNA ruta. Las demás quedan inaccesibles.
  $rutasPerdidas += ((int) $d->rutas) - 1;

  // ¿Viene de la migración o ya estaba en la plantilla? Se distingue por si
  // los nodos implicados tienen poblado el campo de trazabilidad.
  $nids = [];
  foreach (explode(',', $d->cuales) as $r) {
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

  printf("  %-44s %2d rutas  [%s]\n", $d->alias, $d->rutas, $origen);
  echo '      ' . $d->cuales . "\n";
}

echo "\n";
printf("Alias en conflicto             %4d\n", count($peligrosos));
printf("  con contenido migrado        %4d\n", $deMigracion);
printf("  solo de la plantilla         %4d\n", $dePlantilla);
printf("RUTAS EFECTIVAMENTE PERDIDAS   %4d\n\n", $rutasPerdidas);

echo "RESULTADO: FAIL\n\n";
echo "Cada alias resuelve a un solo contenido. Los demas que reclaman ese\n";
echo "mismo alias NO son accesibles por ninguna ruta legible, aunque los\n";
echo "conteos de la migracion den correctos. CLAUDE.md §24 lo prohibe.\n\n";
echo "Los marcados 'plantilla' son previos a este proyecto y no se tocan sin\n";
echo "autorizacion (§44): se reportan para que consten.\n";
