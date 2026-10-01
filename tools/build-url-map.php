<?php
/**
 * build-url-map.php
 *
 * Construye el MAPA DE URLs: toda ruta que el sitio sirve hoy, con su destino
 * previsto en Drupal y su tipo de resolución.
 *
 * Lo exige CLAUDE.md §24 ("la preservación de URLs es prioritaria",
 * "no existen pérdidas silenciosas de rutas") y §35 (trazabilidad).
 *
 * Cubre las tres clases de URL que describe docs/url-strategy.md:
 *   Clase 1  la URL actual de cada contenido, desde post_name
 *   Clase 2  los 7 811 slugs históricos de _wp_old_slug, que WordPress
 *            redirige automáticamente y que nadie había inventariado
 *   Clase 3  queda fuera de este mapa: taxonomías, fechas, autores y feeds
 *            no dependen de post_name y se tratan por separado
 *
 * Detecta además las colisiones, que son el caso que no se puede resolver
 * automáticamente: una URL no puede redirigir a dos destinos.
 *
 * SÓLO LECTURA: no escribe en ninguna base de datos.
 *
 * SALIDA: va a work/, excluido de Git. El mapa contiene slugs y títulos, es
 * decir contenido editorial, y el repositorio del proyecto es público. En Git
 * entra sólo el resumen agregado.
 *
 * Uso:
 *   php tools/build-url-map.php [directorio-de-salida]
 */

$salida = $argv[1] ?? 'work';
$dominio = 'https://www.gaceta.udg.mx';

if (!is_dir($salida)) {
  mkdir($salida, 0777, TRUE);
}

try {
  $db = new PDO('mysql:host=127.0.0.1;port=3306;dbname=gaceta_auditoria;charset=utf8mb4',
    'root', '', [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => FALSE,
    ]);
}
catch (PDOException $e) {
  fwrite(STDERR, 'No se pudo conectar: ' . $e->getMessage() . "\n");
  exit(1);
}

// Tipos cuya URL sigue el permalink /%postname%/.
$tipos = "'post','page'";

fwrite(STDERR, "1. Slugs actuales (clase 1)...\n");

// Contar cuántos contenidos reclaman cada slug, para detectar colisiones.
$colisiones = [];
$q = $db->query("
  SELECT post_name, COUNT(*) AS n
  FROM dc8_posts
  WHERE post_type IN ($tipos) AND post_name <> '' AND post_status = 'publish'
  GROUP BY post_name HAVING n > 1
");
foreach ($q as $r) {
  $colisiones[$r['post_name']] = (int) $r['n'];
}
fwrite(STDERR, '   colisiones de slug actual: ' . count($colisiones) . "\n");

// Slugs históricos y cuántos contenidos reclaman cada uno.
fwrite(STDERR, "2. Slugs históricos (clase 2)...\n");
$hist_por_slug = [];
foreach ($db->query("
  SELECT meta_value AS slug, COUNT(DISTINCT post_id) AS n
  FROM dc8_postmeta WHERE meta_key = '_wp_old_slug'
  GROUP BY meta_value
") as $r) {
  $hist_por_slug[$r['slug']] = (int) $r['n'];
}

$fh = fopen("$salida/url-map.csv", 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, [
  'wp_post_id', 'wp_post_type', 'wp_post_status', 'titulo',
  'slug', 'old_url', 'clase', 'tipo_resolucion',
  'contenidos_que_reclaman_la_url', 'nuevo_url_previsto',
  'migration_status', 'validation_status', 'notas',
]);

$st = [
  'clase1' => 0, 'clase1_sin_slug' => 0, 'clase1_colision' => 0,
  'clase2' => 0, 'clase2_colision' => 0,
  'identica' => 0, 'redirect' => 0, 'sin_equivalente' => 0,
  'entidad_html' => 0, 'mayusculas' => 0, 'no_ascii' => 0, 'copy' => 0,
];

/**
 * Señala problemas conocidos de calidad en un slug.
 */
function problemas_de_slug($slug, array &$st) {
  $p = [];
  if (preg_match('/[a-z]+tilde;|aacute;|eacute;|iacute;|oacute;|uacute;|ntilde;/i', $slug)) {
    $p[] = 'ENTIDAD_HTML_ROTA';
    $st['entidad_html']++;
  }
  if ($slug !== mb_strtolower($slug, 'UTF-8')) {
    $p[] = 'MAYUSCULAS';
    $st['mayusculas']++;
  }
  if (preg_match('/[^\x20-\x7E]/', $slug)) {
    $p[] = 'NO_ASCII';
    $st['no_ascii']++;
  }
  if (preg_match('/-copy(-\d+)?$/', $slug)) {
    $p[] = 'ARTEFACTO_POST_DUPLICATOR';
    $st['copy']++;
  }
  return $p;
}

// --- Clase 1: URL actual de cada contenido. --------------------------------

$q = $db->query("
  SELECT ID, post_type, post_status, post_title, post_name
  FROM dc8_posts
  WHERE post_type IN ($tipos)
  ORDER BY ID
");
foreach ($q as $r) {
  $st['clase1']++;
  $slug = $r['post_name'];
  $notas = [];

  if ($slug === '') {
    $st['clase1_sin_slug']++;
    $st['sin_equivalente']++;
    fputcsv($fh, [
      $r['ID'], $r['post_type'], $r['post_status'], $r['post_title'],
      '', '', 1, 'SIN_SLUG_EN_ORIGEN', 0, '',
      'PENDIENTE', 'PENDIENTE',
      'Drupal tendra que generar un alias: la URL CAMBIA',
    ]);
    continue;
  }

  $notas = problemas_de_slug($slug, $st);
  $n = $colisiones[$slug] ?? 1;
  if ($n > 1) {
    $st['clase1_colision']++;
    $notas[] = 'COLISION_SLUG_ACTUAL';
  }

  // Si el slug es único y limpio, la URL se conserva idéntica.
  if ($n === 1) {
    $tipo = 'IDENTICA';
    $st['identica']++;
  }
  else {
    $tipo = 'COLISION_REQUIERE_DECISION';
  }

  fputcsv($fh, [
    $r['ID'], $r['post_type'], $r['post_status'], $r['post_title'],
    $slug, "$dominio/$slug/", 1, $tipo, $n, "/$slug",
    'PENDIENTE', 'PENDIENTE', implode('|', $notas),
  ]);
}
fwrite(STDERR, "   {$st['clase1']} contenidos\n");

// --- Clase 2: slugs históricos. --------------------------------------------

$q = $db->query("
  SELECT m.post_id, m.meta_value AS slug_viejo,
         p.post_type, p.post_status, p.post_title, p.post_name
  FROM dc8_postmeta m
  JOIN dc8_posts p ON p.ID = m.post_id
  WHERE m.meta_key = '_wp_old_slug'
  ORDER BY m.post_id
");
foreach ($q as $r) {
  $st['clase2']++;
  $slug = $r['slug_viejo'];
  $notas = problemas_de_slug($slug, $st);
  $n = $hist_por_slug[$slug] ?? 1;

  if ($n > 1) {
    $st['clase2_colision']++;
    $notas[] = 'COLISION_SLUG_HISTORICO';
    $tipo = 'COLISION_REQUIERE_DECISION';
  }
  else {
    $tipo = 'REDIRECT_301';
    $st['redirect']++;
  }

  fputcsv($fh, [
    $r['post_id'], $r['post_type'], $r['post_status'], $r['post_title'],
    $slug, "$dominio/$slug/", 2, $tipo, $n,
    $r['post_name'] !== '' ? '/' . $r['post_name'] : '',
    'PENDIENTE', 'PENDIENTE', implode('|', $notas),
  ]);
}
fwrite(STDERR, "   {$st['clase2']} slugs históricos\n");
fclose($fh);

// --- Resumen agregado, sin contenido editorial. ----------------------------

$total = $st['clase1'] + $st['clase2'];
$r = [];
$r[] = '# Resumen del mapa de URLs';
$r[] = '';
$r[] = 'Generado por `tools/build-url-map.php` el ' . date('Y-m-d');
$r[] = 'Fuente: `gaceta_auditoria` (sólo lectura)';
$r[] = '';
$r[] = '```text';
$r[] = 'El mapa completo NO se versiona: contiene slugs y títulos, es decir';
$r[] = 'contenido editorial, y el repositorio es público. Vive en work/.';
$r[] = '```';
$r[] = '';
$r[] = '## Totales';
$r[] = '';
$r[] = '```text';
$r[] = sprintf('URLs en el mapa:                  %7d', $total);
$r[] = sprintf('  Clase 1 (URL actual):           %7d', $st['clase1']);
$r[] = sprintf('  Clase 2 (slug histórico):       %7d', $st['clase2']);
$r[] = '```';
$r[] = '';
$r[] = '## Tipo de resolución';
$r[] = '';
$r[] = '| Resolución | URLs | Qué implica |';
$r[] = '|---|---:|---|';
$r[] = sprintf('| `IDENTICA` | %d | la URL no cambia: no hace falta redirección |', $st['identica']);
$r[] = sprintf('| `REDIRECT_301` | %d | redirección desde el slug histórico |', $st['redirect']);
$r[] = sprintf('| `COLISION_REQUIERE_DECISION` | %d | **no se puede resolver sola** |',
  $st['clase1_colision'] + $st['clase2_colision']);
$r[] = sprintf('| `SIN_SLUG_EN_ORIGEN` | %d | la URL cambia por fuerza |', $st['clase1_sin_slug']);
$r[] = '';
$r[] = '```text';
$pct = $total > 0 ? 100.0 * $st['identica'] / $total : 0;
$r[] = sprintf('URLs que se preservan sin tocar nada: %.1f %% del mapa', $pct);
$r[] = '```';
$r[] = '';
$r[] = '## Problemas de calidad detectados en los slugs';
$r[] = '';
$r[] = '| Problema | URLs |';
$r[] = '|---|---:|';
$r[] = sprintf('| Entidad HTML roto (`ntilde;`, `aacute;`…) | %d |', $st['entidad_html']);
$r[] = sprintf('| Mayúsculas en el slug | %d |', $st['mayusculas']);
$r[] = sprintf('| Caracteres no ASCII (acentos, signos) | %d |', $st['no_ascii']);
$r[] = sprintf('| Artefacto de Post Duplicator (`-copy`) | %d |', $st['copy']);
$r[] = '';
$r[] = 'Ninguno impide preservar la URL: WordPress las sirve hoy tal cual y';
$r[] = 'Drupal puede servirlas igual. Se listan porque condicionan la decisión';
$r[] = 'D-16 y porque conviene crear además la versión corregida de las que';
$r[] = 'llevan una entidad HTML roto.';
$r[] = '';
$r[] = '## Lo que este mapa NO cubre';
$r[] = '';
$r[] = '```text';
$r[] = 'Clase 3: taxonomías (/category/, /tag/), archivos por fecha, páginas de';
$r[] = 'autor (/author/), feeds, paginación y URLs de adjunto. No dependen de';
$r[] = 'post_name y se tratan por separado en docs/url-strategy.md.';
$r[] = '```';

file_put_contents("$salida/url-map-resumen.md", implode("\n", $r) . "\n");
fwrite(STDERR, "\n3. Resumen escrito en $salida/url-map-resumen.md\n");
