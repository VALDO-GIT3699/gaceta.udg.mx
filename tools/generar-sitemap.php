<?php

/**
 * @file
 * generar-sitemap.php
 *
 * Genera el sitemap XML llamando a la API de simple_sitemap EN PROCESO.
 *
 * POR QUÉ NO SE USA `drush simple-sitemap:generate`
 *
 * Ese comando lanza un SUBPROCESO que invoca `vendor/bin/drush`, y en Windows
 * ese archivo es un script de shell sin asociación ejecutable:
 *
 * ```text
 * 'vendor/bin/drush' is not recognized as an internal or external command
 * In ProcessBase.php line 155: Output is empty.
 * ```
 *
 * El comando termina con código 0 y la tabla `simple_sitemap` se queda con 0
 * filas: un fallo silencioso más. `/sitemap.xml` devolvía la página 404.
 *
 * ```text
 * EN UN SERVIDOR LINUX el comando de drush funciona y esto no hace falta. Es
 * un apano al entorno local, como la carga de GD por proceso (D-09). Queda
 * anotado en docs/deployment.md.
 * ```
 *
 * Uso:
 *   drush php:script tools/generar-sitemap.php
 */

$generador = \Drupal::service('simple_sitemap.generator');
$trabajador = \Drupal::service('simple_sitemap.queue_worker');

echo "Reconstruyendo la cola de URLs...\n";
$generador->rebuildQueue();

$enCola = $trabajador->getInitialElementCount();
printf("  %d elementos en cola\n\n", $enCola);

if ($enCola === 0) {
  echo "La cola esta vacia. Revisar que haya bundles incluidos:\n";
  echo "  drush php:script tools/setup-sitemap.php\n";
  return;
}

echo "Generando...\n";
$inicio = microtime(TRUE);
$vuelta = 0;

// Se procesa en vueltas y no de golpe para que el consumo de memoria no
// crezca con las 45 000 URLs.
while ($trabajador->generationInProgress() || $vuelta === 0) {
  $vuelta++;
  // generate() y NO generateSitemap(): ese metodo NO EXISTE en 4.2.3. Lo
  // comprobe con get_class_methods en lugar de seguir deduciendolo del
  // nombre, que es lo que habia hecho la primera vez y lo que costo esta
  // vuelta entera.
  $trabajador->generate();
  $quedan = $trabajador->getQueuedElementCount();
  printf("  vuelta %-3d  quedan %7d  %5.0fs\n",
    $vuelta, $quedan, microtime(TRUE) - $inicio);
  if ($quedan === 0) {
    break;
  }
  if ($vuelta > 500) {
    echo "  ! 500 vueltas sin terminar. Se detiene para no quedarse colgado.\n";
    break;
  }
}

echo "\n";

$db = \Drupal::database();
$filas = $db->query('SELECT type, COUNT(*) AS n, SUM(link_count) AS enlaces
  FROM {simple_sitemap} GROUP BY type')->fetchAll();

if (!$filas) {
  echo "RESULTADO: la tabla simple_sitemap sigue VACIA. No se genero nada.\n";
  return;
}

foreach ($filas as $f) {
  printf("  %-20s %3d fragmentos  %7d enlaces\n", $f->type, $f->n, $f->enlaces);
}

$total = (int) $db->query('SELECT SUM(link_count) FROM {simple_sitemap}')->fetchField();
printf("\nTOTAL: %d URLs en el sitemap\n", $total);
echo "Comprobar en /sitemap.xml\n";
