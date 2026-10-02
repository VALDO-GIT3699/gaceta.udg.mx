<?php

/**
 * @file
 * indexar-busqueda.php
 *
 * Construye el índice de búsqueda del sitio migrado.
 *
 * POR QUÉ HACE FALTA UN SCRIPT Y NO BASTA EL CRON
 *
 * §34 incluye la búsqueda entre los criterios de aceptación. Al comprobarla
 * apareció esto:
 *
 * ```text
 * nodos publicados        36 803
 * nodos en el indice         246
 * ```
 *
 * Es decir: la búsqueda del sitio **no encontraba casi nada**, y no daba
 * ningún error. Otro fallo silencioso: la caja de búsqueda funciona, responde,
 * y devuelve vacío.
 *
 * Drupal indexa por cron, a razón de `search.settings: index.cron_limit`
 * elementos por ejecución, que por omisión son 100. Con 36 803 nodos eso son
 * **369 ejecuciones de cron**. En un servidor con cron cada hora, quince días.
 *
 * Este script hace la primera carga de golpe y **restaura el límite original**
 * al terminar, para no dejar el sitio con una configuración anómala.
 *
 * ```text
 * EN PRODUCCION el cron normal mantiene el indice al dia por si solo. Este
 * script es solo para la carga INICIAL tras una migracion masiva.
 * ```
 *
 * IDEMPOTENTE: si el índice ya está completo, no hace nada. REANUDABLE: si se
 * corta, se vuelve a lanzar y sigue donde iba.
 *
 * SÓLO AÑADE al índice. No modifica ningún contenido.
 *
 * Uso:
 *   drush php:script tools/indexar-busqueda.php
 */

$gestor = \Drupal::service('plugin.manager.search');
$config = \Drupal::configFactory()->getEditable('search.settings');

$limiteOriginal = (int) $config->get('index.cron_limit');
$limiteTrabajo = 1000;

/** @var \Drupal\search\Plugin\SearchIndexingInterface $plugin */
$plugin = $gestor->createInstance('node_search');
if (!$plugin instanceof \Drupal\search\Plugin\SearchIndexingInterface) {
  echo "El plugin node_search no es indexable. Se detiene.\n";
  return;
}

$estado = $plugin->indexStatus();
printf("Antes:  %d de %d indexados, %d pendientes\n",
  $estado['total'] - $estado['remaining'], $estado['total'], $estado['remaining']);

if ($estado['remaining'] === 0) {
  echo "El indice ya esta completo. No hay nada que hacer.\n";
  return;
}

$config->set('index.cron_limit', $limiteTrabajo)->save();
printf("cron_limit %d -> %d (temporal)\n\n", $limiteOriginal, $limiteTrabajo);

$inicio = microtime(TRUE);
$vuelta = 0;
$previo = $estado['remaining'];

try {
  while (TRUE) {
    $vuelta++;
    $plugin->updateIndex();
    $e = $plugin->indexStatus();
    $quedan = (int) $e['remaining'];

    printf("  vuelta %-4d  pendientes %7d  %6.0fs\n",
      $vuelta, $quedan, microtime(TRUE) - $inicio);

    if ($quedan === 0) {
      break;
    }
    // Si una vuelta no avanza, algo impide indexar y seguir seria un bucle.
    if ($quedan >= $previo) {
      echo "\n  ! la vuelta $vuelta no avanzo. Se detiene para no quedarse\n";
      echo "    colgado. Revisar el log de Drupal.\n";
      break;
    }
    $previo = $quedan;
    if ($vuelta > 200) {
      echo "\n  ! 200 vueltas. Se detiene; relanzar para continuar.\n";
      break;
    }
  }
}
finally {
  // El limite se restaura SIEMPRE, incluso si algo falla a mitad: dejar el
  // sitio con cron_limit=1000 haria que cada cron de produccion reindexara mil
  // nodos sin que nadie lo hubiera pedido.
  $config->set('index.cron_limit', $limiteOriginal)->save();
  printf("\ncron_limit restaurado a %d\n", $limiteOriginal);
}

$db = \Drupal::database();
printf("\nDespues: %d nodos en el indice, %d palabras distintas\n",
  (int) $db->query('SELECT COUNT(*) FROM {search_dataset}')->fetchField(),
  (int) $db->query('SELECT COUNT(*) FROM {search_index}')->fetchField());
