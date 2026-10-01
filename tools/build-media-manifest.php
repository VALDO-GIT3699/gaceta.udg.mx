<?php
/**
 * build-media-manifest.php
 *
 * Construye el MANIFIESTO DE MEDIA: el inventario completo de cada archivo que
 * el sitio referencia, extraído de la base de auditoría.
 *
 * Es la etapa 1 de docs/media-strategy.md. Existe porque los archivos
 * binarios no están disponibles (B-02 / D-15) pero la INFORMACIÓN sobre ellos
 * sí: nombre, ruta, URL, tipo, dimensiones, derivados, texto alternativo, pie
 * de foto y qué contenidos los usan. Preservar eso es lo que permite afirmar
 * que no se pierde información aunque los binarios lleguen más tarde.
 *
 * Se usa PHP y no SQL a secas porque _wp_attachment_metadata está serializado
 * con el formato de PHP, y PHP lo deserializa de forma nativa y fiable. Un
 * parser propio sobre esa cadena sería exactamente lo que CLAUDE.md §13
 * prohíbe.
 *
 * SÓLO LECTURA: no escribe en ninguna base de datos.
 *
 * SALIDA: va a work/, que está excluido de Git. El manifiesto contiene
 * títulos y pies de foto, es decir CONTENIDO EDITORIAL, y el repositorio del
 * proyecto es público. En Git sólo entra el resumen agregado.
 *
 * Uso:
 *   php tools/build-media-manifest.php [directorio-de-salida]
 */

$salida = $argv[1] ?? 'work';
$host = '127.0.0.1';
$puerto = '3306';
$base = 'gaceta_auditoria';
$usuario = 'root';
$clave = '';

if (!is_dir($salida)) {
  mkdir($salida, 0777, TRUE);
}

$dsn = "mysql:host=$host;port=$puerto;dbname=$base;charset=utf8mb4";
try {
  $db = new PDO($dsn, $usuario, $clave, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => FALSE,
  ]);
}
catch (PDOException $e) {
  fwrite(STDERR, "No se pudo conectar a $base: " . $e->getMessage() . "\n");
  exit(1);
}

fwrite(STDERR, "Base: $base\n");
fwrite(STDERR, "Salida: $salida\n\n");

// ---------------------------------------------------------------------------
// 1. Manifiesto de adjuntos.
// ---------------------------------------------------------------------------

fwrite(STDERR, "1. Adjuntos...\n");

$sql = "
  SELECT
    p.ID                AS wp_attachment_id,
    p.post_title        AS titulo,
    p.post_excerpt      AS pie_de_foto,
    p.post_content      AS descripcion,
    p.post_date         AS fecha,
    p.post_author       AS autor_id,
    p.post_parent       AS contenido_padre,
    p.guid              AS url_original,
    p.post_mime_type    AS mime,
    p.post_name         AS slug,
    mf.meta_value       AS ruta_relativa,
    mm.meta_value       AS metadatos_serializados,
    ma.meta_value       AS texto_alternativo
  FROM dc8_posts p
  LEFT JOIN dc8_postmeta mf ON mf.post_id = p.ID AND mf.meta_key = '_wp_attached_file'
  LEFT JOIN dc8_postmeta mm ON mm.post_id = p.ID AND mm.meta_key = '_wp_attachment_metadata'
  LEFT JOIN dc8_postmeta ma ON ma.post_id = p.ID AND ma.meta_key = '_wp_attachment_image_alt'
  WHERE p.post_type = 'attachment'
  ORDER BY p.ID
";

$fh = fopen("$salida/media-manifest.csv", 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, [
  'wp_attachment_id', 'titulo', 'pie_de_foto', 'descripcion', 'fecha',
  'autor_id', 'contenido_padre', 'url_original', 'mime', 'slug',
  'ruta_relativa', 'carpeta', 'nombre_archivo', 'extension',
  'ancho', 'alto', 'tamano_bytes', 'num_derivados', 'derivados',
  'texto_alternativo', 'ruta_destino_drupal', 'estado',
]);

$stats = [
  'total' => 0,
  'sin_ruta' => 0,
  'con_alt' => 0,
  'con_pie' => 0,
  'con_metadatos' => 0,
  'derivados_total' => 0,
  'bytes_declarados' => 0,
  'por_mime' => [],
  'por_carpeta' => [],
];

foreach ($db->query($sql) as $f) {
  $stats['total']++;

  $ruta = $f['ruta_relativa'] ?? '';
  if ($ruta === '') {
    $stats['sin_ruta']++;
  }
  $carpeta = ($ruta !== '' && strpos($ruta, '/') !== FALSE)
    ? substr($ruta, 0, strrpos($ruta, '/'))
    : '';
  $nombre = ($ruta !== '') ? basename($ruta) : '';
  $ext = ($nombre !== '') ? strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) : '';

  // Deserializar los metadatos de WordPress: dimensiones y derivados.
  $ancho = $alto = $bytes = '';
  $derivados = [];
  if (!empty($f['metadatos_serializados'])) {
    $stats['con_metadatos']++;
    $m = @unserialize($f['metadatos_serializados']);
    if (is_array($m)) {
      $ancho = $m['width'] ?? '';
      $alto = $m['height'] ?? '';
      $bytes = $m['filesize'] ?? '';
      if (!empty($m['sizes']) && is_array($m['sizes'])) {
        foreach ($m['sizes'] as $nombre_tam => $datos) {
          if (!is_array($datos)) {
            continue;
          }
          $derivados[] = sprintf('%s:%s(%sx%s)',
            $nombre_tam,
            $datos['file'] ?? '?',
            $datos['width'] ?? '?',
            $datos['height'] ?? '?'
          );
        }
      }
    }
  }
  $stats['derivados_total'] += count($derivados);
  if (is_numeric($bytes)) {
    $stats['bytes_declarados'] += (int) $bytes;
  }

  if (!empty(trim((string) $f['texto_alternativo']))) {
    $stats['con_alt']++;
  }
  if (!empty(trim((string) $f['pie_de_foto']))) {
    $stats['con_pie']++;
  }

  $mime = $f['mime'] ?: '(vacio)';
  $stats['por_mime'][$mime] = ($stats['por_mime'][$mime] ?? 0) + 1;
  $raiz = $carpeta !== '' ? explode('/', $carpeta)[0] : '(sin carpeta)';
  $stats['por_carpeta'][$raiz] = ($stats['por_carpeta'][$raiz] ?? 0) + 1;

  // Ruta de destino en Drupal, calculada de forma DETERMINISTA. Es la clave de
  // la estrategia de dos etapas: cuando lleguen los binarios, se depositan
  // aquí y las referencias del contenido ya apuntan al sitio correcto.
  $destino = $ruta !== '' ? "public://migrado/$ruta" : '';

  fputcsv($fh, [
    $f['wp_attachment_id'],
    $f['titulo'],
    $f['pie_de_foto'],
    $f['descripcion'],
    $f['fecha'],
    $f['autor_id'],
    $f['contenido_padre'],
    $f['url_original'],
    $f['mime'],
    $f['slug'],
    $ruta,
    $carpeta,
    $nombre,
    $ext,
    $ancho,
    $alto,
    $bytes,
    count($derivados),
    implode('|', $derivados),
    $f['texto_alternativo'],
    $destino,
    'PENDIENTE_DE_ARCHIVO',
  ]);
}
fclose($fh);
fwrite(STDERR, "   {$stats['total']} adjuntos escritos\n");

// ---------------------------------------------------------------------------
// 2. Referencias de foto1, con su estado de resolución.
// ---------------------------------------------------------------------------

fwrite(STDERR, "2. Referencias de foto1...\n");

// Índice nombre de archivo -> rutas conocidas, para resolver foto1.
$indice = [];
foreach ($db->query("SELECT meta_value FROM dc8_postmeta WHERE meta_key = '_wp_attached_file'") as $r) {
  $b = basename($r['meta_value']);
  $indice[$b][] = $r['meta_value'];
}

$sql2 = "
  SELECT p.ID, p.foto1, p.post_date, p.post_type, p.post_status, p.post_name
  FROM dc8_posts p
  WHERE p.foto1 IS NOT NULL AND p.foto1 <> ''
  ORDER BY p.ID
";

$fh2 = fopen("$salida/media-foto1.csv", 'w');
fwrite($fh2, "\xEF\xBB\xBF");
fputcsv($fh2, [
  'wp_post_id', 'post_type', 'post_status', 'slug', 'fecha',
  'foto1', 'resolucion', 'ruta_resuelta', 'candidatas', 'ruta_destino_drupal',
]);

$f1 = ['total' => 0, 'unica' => 0, 'ambigua' => 0, 'sin_coincidencia' => 0];
foreach ($db->query($sql2) as $r) {
  $f1['total']++;
  $nombre = $r['foto1'];
  $cands = $indice[$nombre] ?? [];
  $n = count(array_unique($cands));

  if ($n === 1) {
    $res = 'RESUELTA';
    $ruta = $cands[0];
    $f1['unica']++;
  }
  elseif ($n > 1) {
    $res = 'AMBIGUA';
    $ruta = '';
    $f1['ambigua']++;
  }
  else {
    $res = 'SIN_RUTA_CONOCIDA';
    $ruta = '';
    $f1['sin_coincidencia']++;
  }

  fputcsv($fh2, [
    $r['ID'], $r['post_type'], $r['post_status'], $r['post_name'],
    $r['post_date'], $nombre, $res, $ruta,
    $n > 1 ? implode('|', array_unique($cands)) : '',
    $ruta !== '' ? "public://migrado/$ruta" : '',
  ]);
}
fclose($fh2);
fwrite(STDERR, "   {$f1['total']} referencias escritas\n");

// ---------------------------------------------------------------------------
// 3. Resumen agregado. Esto SÍ puede versionarse: no lleva contenido editorial.
// ---------------------------------------------------------------------------

$r = [];
$r[] = "# Resumen del manifiesto de media";
$r[] = "";
$r[] = "Generado por `tools/build-media-manifest.php` el " . date('Y-m-d');
$r[] = "Fuente: `gaceta_auditoria` (sólo lectura)";
$r[] = "";
$r[] = "```text";
$r[] = "Los archivos del manifiesto NO se versionan: contienen títulos y pies de";
$r[] = "foto, es decir contenido editorial, y el repositorio es público.";
$r[] = "Viven en work/, que está excluido de Git.";
$r[] = "```";
$r[] = "";
$r[] = "## Adjuntos";
$r[] = "";
$r[] = "```text";
$r[] = sprintf("Adjuntos en el manifiesto:        %7d", $stats['total']);
$r[] = sprintf("Sin ruta (_wp_attached_file):     %7d", $stats['sin_ruta']);
$r[] = sprintf("Con metadatos de imagen:          %7d", $stats['con_metadatos']);
$r[] = sprintf("Con texto alternativo:            %7d", $stats['con_alt']);
$r[] = sprintf("Con pie de foto:                  %7d", $stats['con_pie']);
$r[] = sprintf("Derivados declarados (total):     %7d", $stats['derivados_total']);
$r[] = sprintf("Tamano declarado por WordPress:   %7.2f GB",
  $stats['bytes_declarados'] / 1073741824);
$r[] = "```";
$r[] = "";
$r[] = "## Por tipo MIME";
$r[] = "";
$r[] = "| MIME | Archivos |";
$r[] = "|---|---:|";
arsort($stats['por_mime']);
foreach ($stats['por_mime'] as $k => $v) {
  $r[] = sprintf("| `%s` | %d |", $k, $v);
}
$r[] = "";
$r[] = "## Por carpeta raiz";
$r[] = "";
$r[] = "| Carpeta | Archivos |";
$r[] = "|---|---:|";
arsort($stats['por_carpeta']);
foreach ($stats['por_carpeta'] as $k => $v) {
  $r[] = sprintf("| `%s` | %d |", $k, $v);
}
$r[] = "";
$r[] = "## Referencias de foto1";
$r[] = "";
$r[] = "```text";
$r[] = sprintf("Referencias totales:              %7d", $f1['total']);
$r[] = sprintf("Resueltas (ruta unica):           %7d", $f1['unica']);
$r[] = sprintf("Ambiguas (varias candidatas):     %7d", $f1['ambigua']);
$r[] = sprintf("SIN RUTA CONOCIDA:                %7d", $f1['sin_coincidencia']);
$r[] = "```";
$r[] = "";
$r[] = "Las que no tienen ruta conocida sólo se resolverán con el listado";
$r[] = "recursivo de `/wp-content/uploads` de produccion (decision D-17).";

file_put_contents("$salida/media-manifest-resumen.md", implode("\n", $r) . "\n");

fwrite(STDERR, "\n3. Resumen escrito en $salida/media-manifest-resumen.md\n");
fwrite(STDERR, "\nTodo el estado es PENDIENTE_DE_ARCHIVO: no hay binarios (B-02 / D-15).\n");
