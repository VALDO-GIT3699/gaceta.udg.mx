<?php

/**
 * @file
 * limpiar-alias-redundantes.php
 *
 * Elimina las filas SOBRANTES de `path_alias`: las que repiten exactamente el
 * mismo par (ruta, alias). Conserva una de cada par, la de `id` más bajo.
 *
 * QUÉ LAS PRODUCE
 *
 * Al reprocesar un nodo con Migrate, Drupal INSERTA una fila de alias nueva en
 * lugar de actualizar la que ya tenía. Una pasada sobre 8 305 nodos dejó
 * 13 749 filas sobrantes: 29 839 filas para 16 088 rutas.
 *
 * POR QUÉ NO ES UNA PÉRDIDA DE RUTAS, Y POR QUÉ IGUAL HAY QUE LIMPIARLO
 *
 * Las filas duplicadas llevan al MISMO contenido, así que el visitante llega
 * donde debe y nada se pierde. Pero:
 *
 *   - hinchan la tabla que Drupal consulta en CADA peticion;
 *   - ensucian cualquier conteo de URLs de la FASE 13;
 *   - y sobre todo, si se cuentan como "alias duplicados" tapan las POCAS que
 *     si son una colision real. Eso es lo peligroso: 13 749 filas inocuas
 *     escondiendo 3 que si pierden rutas.
 *
 * QUÉ NO TOCA
 *
 * ```text
 * NO toca ningun alias que lleve a una ruta DISTINTA. Esos son colisiones
 * reales y su tratamiento es una decision, no una limpieza: D-24, D-25, D-27.
 * ```
 *
 * REVERSIBLE: lo que elimina es, por definición, una copia exacta de una fila
 * que se conserva. Y los alias se regeneran reimportando.
 *
 * Uso:
 *   drush php:script tools/limpiar-alias-redundantes.php          (simulacion)
 *   drush php:script tools/limpiar-alias-redundantes.php -- --si  (ejecuta)
 */

$db = \Drupal::database();

// Por omisión SIMULA. Borrar filas requiere pedirlo explícitamente.
$ejecutar = in_array('--si', $extra ?? [], TRUE)
  || in_array('--si', $_SERVER['argv'] ?? [], TRUE);

$linea = str_repeat('=', 72);
echo "$linea\nLIMPIEZA DE FILAS DE ALIAS REDUNDANTES\n$linea\n\n";

$antes = (int) $db->query('SELECT COUNT(*) FROM {path_alias}')->fetchField();

// Las filas a eliminar: para cada par (path, alias) repetido, todas menos la
// de id mas bajo. Se hace por pares y no por alias para NO tocar nunca dos
// filas que lleven a rutas distintas.
$sobrantes = $db->query('
  SELECT a.id
  FROM {path_alias} a
  INNER JOIN (
    SELECT path, alias, MIN(id) AS conservar, COUNT(*) AS n
    FROM {path_alias}
    GROUP BY path, alias
    HAVING n > 1
  ) g ON g.path = a.path AND g.alias = a.alias
  WHERE a.id <> g.conservar
')->fetchCol();

printf("Filas en path_alias        %7d\n", $antes);
printf("Filas redundantes         %7d\n", count($sobrantes));
printf("Quedarian                 %7d\n\n", $antes - count($sobrantes));

// Comprobación de seguridad: ninguna de las filas a eliminar puede ser la
// ÚNICA de su ruta. Si lo fuera, se perdería un alias de verdad.
if ($sobrantes) {
  $rutasAfectadas = $db->query(
    'SELECT DISTINCT path FROM {path_alias} WHERE id IN (:i[])',
    [':i[]' => $sobrantes]
  )->fetchCol();

  $enPeligro = 0;
  foreach (array_chunk($rutasAfectadas, 500) as $trozo) {
    $quedan = $db->query('
      SELECT path, COUNT(*) AS n FROM {path_alias}
      WHERE path IN (:p[]) AND id NOT IN (:i[])
      GROUP BY path
    ', [':p[]' => $trozo, ':i[]' => $sobrantes])->fetchAllKeyed();
    foreach ($trozo as $p) {
      if (empty($quedan[$p])) {
        $enPeligro++;
        echo "  ! $p se quedaria SIN alias\n";
      }
    }
  }
  if ($enPeligro > 0) {
    echo "\nABORTADO: $enPeligro rutas se quedarian sin alias. No se borra nada.\n";
    return;
  }
  echo "Comprobado: cada una de las " . count($rutasAfectadas)
    . " rutas afectadas conserva su alias.\n\n";
}

if (!$sobrantes) {
  echo "No hay nada que limpiar.\n";
  return;
}

if (!$ejecutar) {
  echo "SIMULACION. No se ha borrado nada.\n";
  echo "Para ejecutar de verdad:\n";
  echo "  drush php:script tools/limpiar-alias-redundantes.php -- --si\n";
  return;
}

$borradas = 0;
foreach (array_chunk($sobrantes, 1000) as $trozo) {
  $borradas += $db->delete('path_alias')
    ->condition('id', $trozo, 'IN')
    ->execute();
}

$despues = (int) $db->query('SELECT COUNT(*) FROM {path_alias}')->fetchField();

printf("Filas eliminadas           %7d\n", $borradas);
printf("Filas en path_alias ahora  %7d\n\n", $despues);

// Conciliación: lo que se borró debe cuadrar exactamente.
if ($antes - $borradas === $despues && $borradas === count($sobrantes)) {
  echo "CONCILIADO: $antes - $borradas = $despues. Cuadra exacto.\n";
}
else {
  echo "ATENCION: no cuadra. Esperado " . ($antes - count($sobrantes))
    . ", hay $despues. Revisar antes de continuar.\n";
}

\Drupal::service('cache.data')->deleteAll();
echo "\nCache de datos vaciada: path_alias se consulta en cada peticion.\n";
