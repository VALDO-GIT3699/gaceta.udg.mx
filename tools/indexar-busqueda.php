<?php

/**
 * @file
 * indexar-busqueda.php
 *
 * Indexa UNA tanda de nodos para la búsqueda del sitio. Está pensado para que
 * lo llame en bucle `tools/indexar-busqueda.sh`, un proceso por tanda.
 *
 * POR QUÉ HACE FALTA, Y POR QUÉ NO BASTA EL CRON
 *
 * §34 incluye la búsqueda entre los criterios de aceptación. Al comprobarla
 * tras la migración masiva apareció esto:
 *
 * ```text
 * nodos publicados        36 803
 * nodos en el indice         246
 * ```
 *
 * La caja de búsqueda funcionaba, respondía y devolvía casi nada. **Sin dar
 * ningún error**: otro fallo silencioso.
 *
 * Drupal indexa por cron, a razón de `search.settings: index.cron_limit`
 * elementos por ejecución, que por omisión son 100. Con 36 803 nodos son
 * **369 ejecuciones de cron**; con cron cada hora, quince días.
 *
 * POR QUÉ UNA TANDA POR PROCESO Y NO UN BUCLE INTERNO
 *
 * La primera versión llamaba a `updateIndex()` en bucle dentro del mismo
 * proceso. Reventó así:
 *
 * ```text
 * SQLSTATE[23000]: Duplicate entry '19343-es-node_search' for key 'PRIMARY'
 * ```
 *
 * El seguimiento de qué queda por indexar no se refresca bien entre llamadas
 * en la misma petición, y el plugin acaba intentando insertar dos veces el
 * mismo nodo. Es la misma lección que con el sitemap: **lo que Drupal espera
 * ejecutar una vez por petición, se ejecuta una vez por proceso.**
 *
 * ```text
 * LO QUE SI FUNCIONO de aquella version: el bloque finally restauro
 * cron_limit a 100 aunque el script muriera a mitad, y search_dataset quedo
 * sin un solo duplicado. El fallo no dejo el sitio en un estado raro.
 * ```
 *
 * IDEMPOTENTE y REANUDABLE. Sólo añade al índice: no modifica ningún
 * contenido.
 *
 * Imprime en la última línea el número de nodos que quedan, para que el guion
 * de bash sepa cuándo parar.
 *
 * Uso:
 *   bash tools/indexar-busqueda.sh          <- lo normal
 *   drush php:script tools/indexar-busqueda.php [tamano_tanda]
 */

$tanda = isset($extra[0]) ? (int) $extra[0] : 500;
if ($tanda < 1 || $tanda > 5000) {
  $tanda = 500;
}

$gestor = \Drupal::service('plugin.manager.search');
$config = \Drupal::configFactory()->getEditable('search.settings');
$limiteOriginal = (int) $config->get('index.cron_limit');

/** @var \Drupal\search\Plugin\SearchIndexingInterface $plugin */
$plugin = $gestor->createInstance('node_search');
if (!$plugin instanceof \Drupal\search\Plugin\SearchIndexingInterface) {
  echo "node_search no es indexable.\nQUEDAN:0\n";
  return;
}

$estado = $plugin->indexStatus();
if ((int) $estado['remaining'] === 0) {
  printf("Indice completo: %d nodos.\nQUEDAN:0\n", (int) $estado['total']);
  return;
}

try {
  $config->set('index.cron_limit', $tanda)->save();
  $plugin->updateIndex();
}
finally {
  // Se restaura SIEMPRE, incluso si updateIndex() muere: dejar el sitio con
  // cron_limit alto haria que cada cron de produccion reindexara cientos de
  // nodos sin que nadie lo hubiera pedido. Comprobado que funciona: tras el
  // fallo por clave duplicada, cron_limit habia vuelto a 100.
  $config->set('index.cron_limit', $limiteOriginal)->save();
}

$e = $plugin->indexStatus();
$hechos = (int) $e['total'] - (int) $e['remaining'];
printf("indexados %d de %d\nQUEDAN:%d\n",
  $hechos, (int) $e['total'], (int) $e['remaining']);
