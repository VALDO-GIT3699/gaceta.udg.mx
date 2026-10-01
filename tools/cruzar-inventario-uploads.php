<?php

/**
 * @file
 * cruzar-inventario-uploads.php
 *
 * Cruza el inventario de archivos reales (generado por
 * tools/inventario-uploads.ps1 en el equipo que tiene los uploads) contra las
 * referencias de media que hay en la base de auditoría.
 *
 * Responde las tres preguntas que cierran la FASE 3:
 *
 *   1. De las 48 358 rutas que la base referencia, ¿cuántas existen de verdad?
 *   2. ¿Cuáles faltan? (los uploads entregados son PARCIALES)
 *   3. De las 16 102 referencias de foto1, que sólo guardan el NOMBRE del
 *      archivo y no su carpeta, ¿cuántas se pueden resolver? (decisión D-17)
 *
 * SÓLO LECTURA: no escribe en ninguna base de datos ni toca ningún archivo de
 * media. Sólo lee el inventario de texto y consulta la base de auditoría.
 *
 * SALIDA: va a work/, excluido de Git, porque lista nombres de archivo de
 * contenido editorial. En Git entra sólo el resumen agregado.
 *
 * Uso:
 *   php tools/cruzar-inventario-uploads.php uploads-inventario.txt [work]
 */

$inventario = $argv[1] ?? NULL;
$salida = $argv[2] ?? 'work';

if (!$inventario || !is_file($inventario)) {
  fwrite(STDERR, "Uso: php tools/cruzar-inventario-uploads.php <inventario.txt> [dir_salida]\n");
  fwrite(STDERR, "El inventario lo genera tools/inventario-uploads.ps1\n");
  exit(1);
}
if (!is_dir($salida)) {
  mkdir($salida, 0777, TRUE);
}

// ---------------------------------------------------------------------------
// 1. Cargar el inventario de archivos reales.
// ---------------------------------------------------------------------------

fwrite(STDERR, "1. Leyendo el inventario...\n");

// Dos índices: por ruta completa y por nombre de archivo.
// El segundo es el que resuelve foto1, que no trae carpeta.
$porRuta = [];
$porNombre = [];
$bytesTotal = 0;
$lineas = 0;

$fh = fopen($inventario, 'r');
while (($linea = fgets($fh)) !== FALSE) {
  $linea = rtrim($linea, "\r\n");
  if ($linea === '' || $linea[0] === '#') {
    continue;
  }
  $partes = explode("\t", $linea);
  $ruta = $partes[0];
  $bytes = isset($partes[1]) ? (int) $partes[1] : 0;

  $porRuta[$ruta] = $bytes;
  $base = basename($ruta);
  // Un mismo nombre puede existir en varias carpetas. Se guardan todas para
  // poder distinguir entre "resuelto sin ambigüedad" y "ambiguo".
  $porNombre[$base][] = $ruta;

  $bytesTotal += $bytes;
  $lineas++;
}
fclose($fh);

fwrite(STDERR, sprintf("   %d archivos, %.2f GB, %d nombres distintos\n",
  $lineas, $bytesTotal / 1073741824, count($porNombre)));

// ---------------------------------------------------------------------------
// 2. Conectar a la base de auditoría.
// ---------------------------------------------------------------------------

try {
  $db = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=gaceta_auditoria;charset=utf8mb4',
    'root', '',
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => FALSE,
    ]
  );
}
catch (PDOException $e) {
  fwrite(STDERR, 'No se pudo conectar a gaceta_auditoria: ' . $e->getMessage() . "\n");
  exit(1);
}

// ---------------------------------------------------------------------------
// 3. Cruzar las rutas de _wp_attached_file.
// ---------------------------------------------------------------------------

fwrite(STDERR, "2. Cruzando las rutas de los adjuntos...\n");

$st = ['total' => 0, 'existe' => 0, 'falta' => 0];
$porAnio = [];

$fh = fopen("$salida/media-cobertura-adjuntos.csv", 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, ['wp_attachment_id', 'ruta_relativa', 'estado', 'bytes_en_disco']);

$q = $db->query("
  SELECT post_id, meta_value AS ruta
  FROM dc8_postmeta
  WHERE meta_key = '_wp_attached_file'
");
foreach ($q as $r) {
  $st['total']++;
  $ruta = $r['ruta'];
  $existe = isset($porRuta[$ruta]);

  if ($existe) {
    $st['existe']++;
    $estado = 'PRESENTE';
    $bytes = $porRuta[$ruta];
  }
  else {
    $st['falta']++;
    $estado = 'AUSENTE';
    $bytes = '';
  }

  $anio = (strpos($ruta, '/') !== FALSE) ? explode('/', $ruta)[0] : '(sin carpeta)';
  if (!isset($porAnio[$anio])) {
    $porAnio[$anio] = ['presente' => 0, 'ausente' => 0];
  }
  $porAnio[$anio][$existe ? 'presente' : 'ausente']++;

  fputcsv($fh, [$r['post_id'], $ruta, $estado, $bytes]);
}
fclose($fh);

fwrite(STDERR, sprintf("   %d referencias: %d presentes, %d ausentes\n",
  $st['total'], $st['existe'], $st['falta']));

// ---------------------------------------------------------------------------
// 4. Resolver foto1, que sólo trae el nombre del archivo (D-17).
// ---------------------------------------------------------------------------

fwrite(STDERR, "3. Resolviendo las referencias de foto1...\n");

$f1 = ['total' => 0, 'unica' => 0, 'ambigua' => 0, 'ausente' => 0];

$fh = fopen("$salida/media-cobertura-foto1.csv", 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, ['wp_post_id', 'foto1', 'estado', 'ruta_resuelta', 'candidatas']);

$q = $db->query("
  SELECT ID, foto1
  FROM dc8_posts
  WHERE post_type = 'post' AND foto1 IS NOT NULL AND foto1 <> ''
");
foreach ($q as $r) {
  $f1['total']++;
  $nombre = $r['foto1'];
  $cands = array_unique($porNombre[$nombre] ?? []);
  $n = count($cands);

  if ($n === 1) {
    $f1['unica']++;
    $estado = 'RESUELTA';
    $ruta = reset($cands);
    $otras = '';
  }
  elseif ($n > 1) {
    $f1['ambigua']++;
    $estado = 'AMBIGUA';
    $ruta = '';
    $otras = implode('|', $cands);
  }
  else {
    $f1['ausente']++;
    $estado = 'NO_ESTA_EN_EL_DISCO';
    $ruta = '';
    $otras = '';
  }

  fputcsv($fh, [$r['ID'], $nombre, $estado, $ruta, $otras]);
}
fclose($fh);

fwrite(STDERR, sprintf("   %d referencias: %d resueltas, %d ambiguas, %d ausentes\n",
  $f1['total'], $f1['unica'], $f1['ambigua'], $f1['ausente']));

// ---------------------------------------------------------------------------
// 5. Resumen agregado. Esto SÍ se versiona: no lleva nombres de archivo.
// ---------------------------------------------------------------------------

$pct = fn($a, $b) => $b > 0 ? round(100.0 * $a / $b, 1) : 0.0;

$r = [];
$r[] = '# Cobertura de media: inventario real contra referencias';
$r[] = '';
$r[] = 'Generado por `tools/cruzar-inventario-uploads.php` el ' . date('Y-m-d');
$r[] = '';
$r[] = '```text';
$r[] = sprintf('Inventario leído:  %d archivos, %.2f GB', $lineas, $bytesTotal / 1073741824);
$r[] = sprintf('Nombres distintos: %d', count($porNombre));
$r[] = '```';
$r[] = '';
$r[] = '## Adjuntos referenciados por `_wp_attached_file`';
$r[] = '';
$r[] = '```text';
$r[] = sprintf('Referencias totales: %7d', $st['total']);
$r[] = sprintf('PRESENTES:           %7d  (%.1f %%)', $st['existe'], $pct($st['existe'], $st['total']));
$r[] = sprintf('AUSENTES:            %7d  (%.1f %%)', $st['falta'], $pct($st['falta'], $st['total']));
$r[] = '```';
$r[] = '';
$r[] = '## Cobertura por carpeta';
$r[] = '';
$r[] = '| Carpeta | Presentes | Ausentes | Cobertura |';
$r[] = '|---|---:|---:|---:|';
ksort($porAnio);
foreach ($porAnio as $anio => $d) {
  $tot = $d['presente'] + $d['ausente'];
  $r[] = sprintf('| `%s` | %d | %d | %.1f %% |',
    $anio, $d['presente'], $d['ausente'], $pct($d['presente'], $tot));
}
$r[] = '';
$r[] = '## Referencias de `foto1` (decisión D-17)';
$r[] = '';
$r[] = 'Son las 16 102 referencias que sólo guardan el NOMBRE del archivo, sin';
$r[] = 'su carpeta. Se resuelven buscando ese nombre en el inventario.';
$r[] = '';
$r[] = '```text';
$r[] = sprintf('Referencias totales:  %6d', $f1['total']);
$r[] = sprintf('RESUELTAS (una ruta): %6d  (%.1f %%)', $f1['unica'], $pct($f1['unica'], $f1['total']));
$r[] = sprintf('AMBIGUAS (varias):    %6d  (%.1f %%)', $f1['ambigua'], $pct($f1['ambigua'], $f1['total']));
$r[] = sprintf('NO ESTÁN EN DISCO:    %6d  (%.1f %%)', $f1['ausente'], $pct($f1['ausente'], $f1['total']));
$r[] = '```';
$r[] = '';
$r[] = 'Las AMBIGUAS tienen el mismo nombre en varias carpetas. Se resolverán';
$r[] = 'cruzando la fecha del contenido con la carpeta del año, que es una';
$r[] = 'heurística fiable porque WordPress organiza los uploads por año y mes.';
$r[] = '';
$r[] = '## Qué sigue';
$r[] = '';
$r[] = '```text';
$r[] = 'Los archivos NO se copian a esta máquina. Cuando exista el servidor de';
$r[] = 'Drupal, el árbol se copia UNA vez desde donde está, preservando la';
$r[] = 'estructura de carpetas, a sites/default/files/migrado/.';
$r[] = 'Las rutas de destino del manifiesto ya apuntan ahí.';
$r[] = '```';

file_put_contents("$salida/media-cobertura-resumen.md", implode("\n", $r) . "\n");

fwrite(STDERR, "\n4. Resumen en $salida/media-cobertura-resumen.md\n");
fwrite(STDERR, "   Detalle por archivo en $salida/media-cobertura-*.csv\n");
fwrite(STDERR, "\nNo se copió ni se modificó ningún archivo de media.\n");
