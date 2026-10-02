<?php

/**
 * @file
 * calcular-hash-migracion.php
 *
 * Implementa el hash de migración que CLAUDE.md §36 exige, y completa la
 * matriz de trazabilidad de §35 con las dos columnas que le faltaban.
 *
 * PARA QUÉ SIRVE, que no es para adornar la matriz
 *
 * La migración se hizo sobre un volcado del **2026-09-17 12:39**, y WordPress
 * ha seguido publicando. La FASE 16 tiene que responder a esta pregunta:
 *
 * > «¿Qué artículos han cambiado en WordPress desde la foto?»
 *
 * Sin hash, la única respuesta es confiar en `post_modified`. Y eso no basta:
 *
 * ```text
 * CONFIRMADO: 12 860 articulos tienen post_modified POSTERIOR a post_date.
 *             Es decir, se editaron despues de publicarse.
 * ```
 *
 * `post_modified` cambia cuando alguien abre y guarda sin tocar nada, y **no
 * cambia** si el contenido se altera por SQL o por una herramienta que no
 * actualice la columna. El hash compara el contenido de verdad.
 *
 * ```text
 * EN LA SINCRONIZACION FINAL: se recalcula el hash sobre el volcado nuevo y se
 * compara con el guardado. Distinto = reimportar ese articulo. Igual = saltarlo
 * aunque post_modified haya cambiado.
 * ```
 *
 * QUÉ CAMPOS ENTRAN AL HASH, que §36 obliga a documentar EXACTAMENTE
 *
 * ```text
 * SHA-256 de estos 11 valores, en este orden, separados por "\x1f":
 *
 *    1  ID                 identificador de origen
 *    2  post_title         titulo CRUDO, sin decodificar entidades
 *    3  post_name          slug
 *    4  post_content       cuerpo CRUDO, sin convertir shortcodes
 *    5  post_excerpt       extracto
 *    6  post_status        estado en WordPress
 *    7  post_date          fecha de publicacion
 *    8  post_author        autor
 *    9  seccion            seccion editorial
 *   10  subseccion         subseccion editorial
 *   11  balazo             antetitulo
 * ```
 *
 * POR QUÉ CRUDO Y NO TRANSFORMADO, que es la decisión importante
 *
 * El hash se calcula sobre el valor **tal como está en el origen**, antes de
 * cualquier transformación mía. Si se calculara sobre el resultado, cambiar mi
 * propio código —decodificar una entidad, convertir un shortcode— alteraría
 * el hash de 36 666 artículos sin que nadie hubiera tocado WordPress, y la
 * sincronización creería que todo cambió.
 *
 * ```text
 * EL HASH MIDE EL ORIGEN, NO MI TRABAJO.
 * ```
 *
 * QUÉ NO ENTRA, y por qué
 *
 * ```text
 * foto1, term_id, original_id   no cambian nunca: son identificadores
 * cita                          entra ya via post_content en la practica
 * comment_count                 cambia al comentar, sin tocar el articulo
 * post_modified                 es precisamente lo que el hash viene a no
 *                               tener que creer
 * ```
 *
 * SÓLO LECTURA sobre las dos bases. Escribe `work/traceability.csv`, que no
 * entra en Git porque lleva títulos y rutas (§37, D-06).
 *
 * Uso:
 *   drush php:script tools/calcular-hash-migracion.php
 */

/**
 * Los campos del hash, en orden. Cambiar esta lista INVALIDA los hashes
 * anteriores, asi que si se cambia hay que recalcularlos todos.
 */
const CAMPOS_HASH = [
  'ID', 'post_title', 'post_name', 'post_content', 'post_excerpt',
  'post_status', 'post_date', 'post_author', 'seccion', 'subseccion', 'balazo',
];

/**
 * Separador de campos. Se usa el ASCII 31 (unit separator) a proposito: no
 * aparece en texto editorial, asi que dos registros distintos no pueden
 * producir la misma cadena por concatenacion ambigua.
 */
const SEPARADOR = "\x1f";

$drupal = \Drupal::database();

try {
  $wp = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=gaceta_auditoria;charset=utf8mb4',
    'root', '',
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => FALSE,
    ]
  );
}
catch (PDOException $e) {
  echo 'No se pudo conectar a gaceta_auditoria: ' . $e->getMessage() . "\n";
  return;
}

$raiz = DRUPAL_ROOT . '/../..';
if (!is_dir($raiz . '/work')) {
  mkdir($raiz . '/work', 0777, TRUE);
}

echo "1. Leyendo el destino...\n";

// [wp_post_id => [nid, tipo, publicado, alias, original_id]]
$destino = [];
$filas = $drupal->query("
  SELECT w.field_wp_post_id_value AS wp, n.nid, n.type, n.status,
         a.alias, o.field_wp_original_id_value AS orig
  FROM {node__field_wp_post_id} w
  INNER JOIN {node_field_data} n ON n.nid = w.entity_id
  LEFT JOIN {path_alias} a ON a.path = CONCAT('/node/', n.nid)
  LEFT JOIN {node__field_wp_original_id} o ON o.entity_id = n.nid
");
foreach ($filas as $r) {
  $destino[(int) $r->wp] = [
    'nid' => (int) $r->nid,
    'tipo' => $r->type,
    'publicado' => (int) $r->status,
    'alias' => (string) ($r->alias ?? ''),
    'orig' => $r->orig,
  ];
}
printf("   %d entidades con trazabilidad\n", count($destino));

echo "2. Calculando hashes sobre el ORIGEN...\n";

$fh = fopen($raiz . '/work/traceability.csv', 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, [
  'wp_post_id', 'wp_original_id', 'wp_post_type', 'wp_post_status', 'wp_slug',
  'old_url', 'drupal_entity_type', 'drupal_entity_id', 'new_url',
  'migration_id', 'migration_status', 'migration_hash', 'validation_status',
  'validation_date', 'notas',
]);

$campos = implode(', ', CAMPOS_HASH);
$q = $wp->query("
  SELECT $campos
  FROM dc8_posts
  WHERE post_type IN ('post', 'page')
  ORDER BY ID
");

$ahora = date('c');
$n = 0;
$sinDestino = 0;
$hashes = [];

foreach ($q as $r) {
  $n++;
  $id = (int) $r['ID'];

  // El hash, sobre los valores CRUDOS del origen.
  $partes = [];
  foreach (CAMPOS_HASH as $c) {
    $partes[] = (string) ($r[$c] ?? '');
  }
  $hash = hash('sha256', implode(SEPARADOR, $partes));
  $hashes[$hash] = ($hashes[$hash] ?? 0) + 1;

  $d = $destino[$id] ?? NULL;
  if ($d === NULL) {
    $sinDestino++;
  }

  $alias = $d['alias'] ?? '';
  fputcsv($fh, [
    $id,
    $d['orig'] ?? '',
    $r['post_type'] ?? '',
    $r['post_status'],
    $r['post_name'],
    'https://www.gaceta.udg.mx/' . ltrim($r['post_name'], '/'),
    $d ? 'node' : '',
    $d['nid'] ?? '',
    $d ? ($alias !== '' ? $alias : '/node/' . $d['nid']) : '',
    $d ? ($d['tipo'] === 'noticia' ? 'gaceta_noticia' : 'gaceta_pagina') : '',
    $d ? 'importado' : 'SIN DESTINO',
    $hash,
    $d ? ($alias !== '' ? 'url preservada' : 'url CAMBIA: se sirve por /node/N')
       : 'NO MIGRADO',
    $ahora,
    '',
  ]);
}
fclose($fh);

printf("   %d registros procesados\n", $n);
printf("   %d sin entidad en el destino\n\n", $sinDestino);

// Un hash repetido significa dos registros con contenido IDENTICO en los 11
// campos. No es un error del hash: es un duplicado real del origen, y conviene
// saber cuantos hay antes de que la sincronizacion los trate como uno.
$repetidos = array_filter($hashes, fn($c) => $c > 1);
$registrosRepetidos = array_sum($repetidos);

echo "RESULTADO\n";
printf("  hashes distintos          %7d\n", count($hashes));
printf("  hashes repetidos          %7d  (%d registros)\n",
  count($repetidos), $registrosRepetidos);
echo "\n";
if ($repetidos) {
  echo "  Un hash repetido = dos registros con contenido IDENTICO en los 11\n";
  echo "  campos. No es fallo del hash: es un duplicado real del origen.\n\n";
}
echo "  Matriz completa en work/traceability.csv, con las 15 columnas de §35\n";
echo "  incluidas migration_hash y validation_date, que faltaban.\n";
echo "\n";
echo "  Ese CSV NO entra en Git: lleva titulos y rutas (§37, D-06).\n";
