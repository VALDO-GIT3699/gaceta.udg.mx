<?php

/**
 * @file
 * validar-destino-migraciones.php
 *
 * Comprueba, para CADA migración, que las entidades que su mapa dice haber
 * creado existan de verdad en el destino.
 *
 * POR QUÉ EXISTE ESTE VALIDADOR
 *
 * Lo pidió el auditor del proyecto, y tenía razón. La conciliación de conteos
 * sólo validaba `gaceta_noticia`: total, estado, años y 8 campos. Las otras
 * ocho migraciones se daban por buenas **porque su mapa decía que sí**.
 *
 * Y el mapa miente en cuanto alguien borra la entidad por otro camino:
 *
 * ```text
 * ENCONTRADO:  migrate_map_gaceta_redireccion   1 406 mapeadas
 *              tabla redirect                   1 343 filas
 *              huerfanas                            63
 *
 *              migrate_map_gaceta_comentario       40 mapeadas
 *              tabla comment_field_data            34 filas
 *              huerfanos                             6
 * ```
 *
 * LA CAUSA, Y ES UNA TRAMPA QUE VOLVERÁ A APARECER
 *
 * Al reparar las secciones y los shortcodes se hizo
 * `migrate:rollback gaceta_noticia --idlist=...` sobre 6 260 artículos. Borrar
 * un nodo arrastra:
 *
 * ```text
 *   sus COMENTARIOS            Drupal los borra en cascada
 *   las REDIRECCIONES hacia el el modulo redirect las limpia
 * ```
 *
 * Pero los mapas de `gaceta_comentario` y `gaceta_redireccion` **siguen
 * diciendo que sus entidades existen**, así que al reimportar el nodo padre
 * esas migraciones se saltan las filas: ya las consideran hechas.
 *
 * ```text
 * RESULTADO: 6 comentarios REALES de lectores y 63 redirecciones 301
 * desaparecieron sin un solo mensaje de error, y el reporte de errores
 * declaraba "0 descartados, 0 mensajes". §24 y §28 lo prohiben.
 * ```
 *
 * ```text
 * LECCION: revertir una migracion PADRE destruye en silencio las entidades
 * HIJAS de otras migraciones. Tras cualquier rollback de nodos, hay que
 * ejecutar esto.
 * ```
 *
 * SÓLO LECTURA. Devuelve el detalle para poder repararlo con Migrate.
 *
 * Uso:
 *   drush php:script tools/validar-destino-migraciones.php
 */

$db = \Drupal::database();

// Para cada migración: su tabla de destino y la columna de clave primaria.
$migraciones = [
  'gaceta_noticia' => ['node_field_data', 'nid', 'nodos de noticia'],
  'gaceta_pagina' => ['node_field_data', 'nid', 'nodos de página'],
  'gaceta_seccion' => ['taxonomy_term_field_data', 'tid', 'términos de sección'],
  'gaceta_subseccion' => ['taxonomy_term_field_data', 'tid', 'términos de subsección'],
  'gaceta_credito' => ['taxonomy_term_field_data', 'tid', 'créditos editoriales'],
  'gaceta_etiqueta' => ['taxonomy_term_field_data', 'tid', 'etiquetas'],
  'gaceta_categoria' => ['taxonomy_term_field_data', 'tid', 'categorías'],
  'gaceta_redireccion' => ['redirect', 'rid', 'redirecciones 301'],
  'gaceta_comentario' => ['comment_field_data', 'cid', 'comentarios'],
];

$linea = str_repeat('=', 72);
echo "$linea\nVALIDACION DE DESTINO DE LAS MIGRACIONES\n$linea\n\n";
echo "Comprueba que lo que el mapa dice haber creado EXISTA en el destino.\n";
echo "Un mapa que dice SI y una tabla que dice NO es perdida silenciosa.\n\n";

printf("%-22s %9s %9s %9s\n", 'migración', 'en mapa', 'existen', 'HUÉRFANOS');
echo str_repeat('-', 72) . "\n";

$totalHuerfanos = 0;
$conProblema = [];

foreach ($migraciones as $id => [$tabla, $pk, $etiqueta]) {
  $mapa = 'migrate_map_' . $id;
  if (!$db->schema()->tableExists($mapa)) {
    printf("%-22s %9s\n", $id, 'sin ejecutar');
    continue;
  }
  if (!$db->schema()->tableExists($tabla)) {
    printf("%-22s %9s  (no existe la tabla %s)\n", $id, '?', $tabla);
    continue;
  }

  $enMapa = (int) $db->query(
    "SELECT COUNT(*) FROM {" . $mapa . "} WHERE destid1 IS NOT NULL"
  )->fetchField();

  // Se cuenta por EXISTENCIA en el destino, no por el total de la tabla: la
  // tabla puede tener filas de la plantilla que no vienen de ninguna
  // migracion, y compararlas falsearia el resultado en los dos sentidos.
  $existen = (int) $db->query(
    "SELECT COUNT(*) FROM {" . $mapa . "} m
     WHERE m.destid1 IS NOT NULL
       AND EXISTS (SELECT 1 FROM {" . $tabla . "} t WHERE t." . $pk . " = m.destid1)"
  )->fetchField();

  $huerfanos = $enMapa - $existen;
  $totalHuerfanos += $huerfanos;

  printf("%-22s %9d %9d %9d%s\n", $id, $enMapa, $existen, $huerfanos,
    $huerfanos > 0 ? '  <-- PERDIDA' : '');

  if ($huerfanos > 0) {
    $ids = $db->query(
      "SELECT m.sourceid1 FROM {" . $mapa . "} m
       WHERE m.destid1 IS NOT NULL
         AND NOT EXISTS (SELECT 1 FROM {" . $tabla . "} t WHERE t." . $pk . " = m.destid1)"
    )->fetchCol();
    $conProblema[$id] = $ids;
  }
}

echo str_repeat('-', 72) . "\n\n";

if ($totalHuerfanos === 0) {
  echo "RESULTADO: PASS\n\n";
  echo "Todas las entidades que los mapas declaran existen en el destino.\n";
  return;
}

printf("RESULTADO: FAIL  -  %d entidades perdidas\n\n", $totalHuerfanos);

echo "COMO REPARARLO. Para cada migracion, revertir SOLO esas filas del mapa y\n";
echo "reimportarlas; asi Migrate deja de creerlas hechas y las vuelve a crear.\n\n";

foreach ($conProblema as $id => $ids) {
  // Se trocea por si la lista no cabe en la linea de comandos de Windows.
  foreach (array_chunk($ids, 300) as $trozo) {
    $lista = implode(',', $trozo);
    echo "drush migrate:rollback $id --idlist=$lista\n";
    echo "drush migrate:import   $id --idlist=$lista\n\n";
  }
}

echo "§40: NO se arregla borrando las filas del mapa. Eso esconderia la\n";
echo "perdida en lugar de recuperar las entidades.\n";
