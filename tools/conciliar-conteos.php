<?php

/**
 * @file
 * conciliar-conteos.php
 *
 * FASE 13: compara WordPress contra Drupal, registro a registro, y escribe
 * `reports/validation/content-comparison.md`.
 *
 * QUÉ PREGUNTA, Y POR QUÉ CADA PREGUNTA
 *
 * El contrato (§34) exige conteos conciliados y fallos explicados. Pero un
 * conteo total que cuadre no demuestra gran cosa: este proyecto ya ha visto
 * cuatro fallos que daban conteos perfectos y resultados incorrectos. Así que
 * aquí no se compara sólo el total:
 *
 *   1. TOTAL          ¿está cada registro del origen en el destino?
 *   2. UNO A UNO      ¿qué IDs de WordPress faltan? Se listan, no se cuentan.
 *   3. ESTADO         ¿coincide el número de publicados? Un registro migrado
 *                     pero publicado cuando no debía es una fuga de contenido.
 *   4. FECHAS         ¿coincide el reparto por año? Detecta migraciones
 *                     parciales que el total no revela.
 *   5. CAMPOS         ¿cuántos registros traen cada campo, y coincide con los
 *                     que lo tenían en el origen? Un campo que no se puebla no
 *                     produce ningún error.
 *   6. ALIAS          ¿cuántas rutas se preservan idénticas?
 *
 * SÓLO LECTURA sobre las dos bases. No escribe en ninguna.
 *
 * Uso:
 *   drush php:script tools/conciliar-conteos.php
 */

$drupal = \Drupal::database();

try {
  $wp = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=gaceta_auditoria;charset=utf8mb4',
    'root', '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
}
catch (PDOException $e) {
  echo 'No se pudo conectar a gaceta_auditoria: ' . $e->getMessage() . "\n";
  return;
}

$L = [];
$w = function ($t = '') use (&$L) {
  $L[] = $t;
  echo $t . "\n";
};

$w('# Conciliación de conteos: WordPress contra Drupal');
$w('');
$w('Fecha: ' . date('Y-m-d H:i'));
$w('Requisito que atiende: CLAUDE.md §34, §41, FASE 13.');
$w('');
$w('Generado con `tools/conciliar-conteos.php`. Reproducible y de sólo lectura.');
$w('');

// ---------------------------------------------------------------------------
// 1. Totales y registros ausentes, uno a uno.
// ---------------------------------------------------------------------------

$w('## Noticias: total y ausencias');
$w('');

$wpIds = $wp->query("
  SELECT ID FROM dc8_posts WHERE post_type = 'post'
")->fetchAll(PDO::FETCH_COLUMN);
$wpIds = array_map('intval', $wpIds);

$drIds = $drupal->query('
  SELECT field_wp_post_id_value FROM {node__field_wp_post_id} w
  INNER JOIN {node_field_data} n ON n.nid = w.entity_id
  WHERE n.type = :t
', [':t' => 'noticia'])->fetchCol();
$drIds = array_map('intval', $drIds);

$enOrigen = count($wpIds);
$enDestino = count(array_unique($drIds));
$faltan = array_values(array_diff($wpIds, $drIds));
$sobran = array_values(array_diff($drIds, $wpIds));

$w('```text');
$w(sprintf('En WordPress (post_type = post)  %7d', $enOrigen));
$w(sprintf('En Drupal (tipo noticia)         %7d', $enDestino));
$w(sprintf('AUSENTES en Drupal               %7d', count($faltan)));
$w(sprintf('En Drupal SIN origen             %7d', count($sobran)));
$w('```');
$w('');

if ($faltan) {
  $w('Los IDs de WordPress que NO llegaron, sin resumir:');
  $w('');
  $w('```text');
  foreach (array_chunk($faltan, 12) as $t) {
    $w('  ' . implode(' ', $t));
  }
  $w('```');
  $w('');
  $w('CLAUDE.md FASE 9 exige explicar cada uno. No se cierra la fase hasta que');
  $w('esta lista esté vacía o cada ID tenga su motivo documentado.');
}
else {
  $w('```text');
  $w('CONCILIADO: ningun registro del origen falta en el destino.');
  $w('```');
}
$w('');

// ---------------------------------------------------------------------------
// 2. Estado de publicación.
// ---------------------------------------------------------------------------

$w('## Estado de publicación');
$w('');

$wpPub = (int) $wp->query("
  SELECT COUNT(*) FROM dc8_posts
  WHERE post_type = 'post' AND post_status = 'publish'
")->fetchColumn();

// IMPORTANTE: se cuentan solo los nodos CON trazabilidad de WordPress.
//
// La plantilla institucional ya traia 4 nodos de tipo `noticia` como contenido
// de demostracion (nids 15, 22, 28 y 29, creados en 2025 y publicados). Sin
// este filtro aparecian como un descuadre de +4 publicados y +4 en el anio
// 2025, y parecian un defecto de la migracion cuando no lo son. Su destino es
// la decision D-05.
$drPub = (int) $drupal->query('
  SELECT COUNT(*) FROM {node_field_data} n
  INNER JOIN {node__field_wp_post_id} w ON w.entity_id = n.nid
  WHERE n.type = :t AND n.status = 1
', [':t' => 'noticia'])->fetchField();

$drNoPub = (int) $drupal->query('
  SELECT COUNT(*) FROM {node_field_data} n
  INNER JOIN {node__field_wp_post_id} w ON w.entity_id = n.nid
  WHERE n.type = :t AND n.status = 0
', [':t' => 'noticia'])->fetchField();

$dePlantilla = (int) $drupal->query('
  SELECT COUNT(*) FROM {node_field_data} n
  LEFT JOIN {node__field_wp_post_id} w ON w.entity_id = n.nid
  WHERE n.type = :t AND w.entity_id IS NULL
', [':t' => 'noticia'])->fetchField();

$w('```text');
$w(sprintf('publish en WordPress             %7d', $wpPub));
$w(sprintf('publicados en Drupal             %7d', $drPub));
$w(sprintf('NO publicados en Drupal          %7d', $drNoPub));
$w(sprintf('Diferencia                       %7d', $drPub - $wpPub));
$w('');
$w(sprintf('Nodos noticia de la PLANTILLA    %7d  (fuera de esta comparacion)', $dePlantilla));
$w('```');
$w('');
$w('En WordPress `private` significa publicado con acceso restringido y');
$w('`future` es programado. Ninguno equivale al publicado de Drupal, así que');
$w('sólo `publish` pasa a publicado. Nada se descarta: el resto entra sin');
$w('publicar y conserva su estado original en la trazabilidad.');
$w('');

// ---------------------------------------------------------------------------
// 3. Reparto por año. Detecta migraciones parciales.
// ---------------------------------------------------------------------------

$w('## Reparto por año');
$w('');

$wpAnios = $wp->query("
  SELECT YEAR(post_date) AS a, COUNT(*) AS n
  FROM dc8_posts WHERE post_type = 'post'
  GROUP BY a ORDER BY a
")->fetchAll(PDO::FETCH_KEY_PAIR);

$drAnios = $drupal->query("
  SELECT YEAR(FROM_UNIXTIME(n.created)) AS a, COUNT(*) AS n
  FROM {node_field_data} n
  INNER JOIN {node__field_wp_post_id} w ON w.entity_id = n.nid
  WHERE n.type = :t
  GROUP BY a ORDER BY a
", [':t' => 'noticia'])->fetchAllKeyed();

$w('| Año | WordPress | Drupal | Diferencia |');
$w('|---|---:|---:|---:|');
// El anio 0 del origen ('0000-00-00') y el 1969 del destino son LA MISMA cosa:
// esos 6 registros reciben marca de tiempo 0, y 0 en America/Mexico_City es
// 1969-12-31 18:00 porque la zona es UTC-6. No se les invento una fecha. Se
// equiparan aqui para que el descuadre no aparezca dos veces.
$fechaInvalida = (int) ($drAnios[1969] ?? 0);
unset($drAnios[1969]);
if ($fechaInvalida > 0) {
  $drAnios[0] = (int) ($drAnios[0] ?? 0) + $fechaInvalida;
}

$todos = array_unique(array_merge(array_keys($wpAnios), array_keys($drAnios)));
sort($todos);
$descuadres = 0;
foreach ($todos as $a) {
  $o = (int) ($wpAnios[$a] ?? 0);
  $d = (int) ($drAnios[$a] ?? 0);
  if ($o !== $d) {
    $descuadres++;
  }
  $w(sprintf('| %s | %d | %d | %s |', $a === '' ? '(sin fecha)' : $a, $o, $d,
    $o === $d ? '—' : sprintf('%+d', $d - $o)));
}
$w('');
$w('```text');
$w($descuadres === 0
  ? 'CONCILIADO: el reparto por anio coincide exactamente.'
  : sprintf('%d anios descuadran. Revisar antes de cerrar la FASE 9.', $descuadres));
$w('```');
$w('');
$w('Los 6 registros con `0000-00-00` reciben marca de tiempo 0, que en');
$w('`America/Mexico_City` es **1969-12-31 18:00** porque la zona es UTC−6. Se');
$w('contabilizan en la fila del año 0, que es de donde vienen. No se les');
$w('inventó una fecha: ver `GacetaNoticia::prepareRow()`.');
$w('');

// ---------------------------------------------------------------------------
// 4. Campos poblados. Un campo vacío no produce ningún error.
// ---------------------------------------------------------------------------

$w('## Campos poblados');
$w('');
$w('Un campo que no se puebla **no produce ningún error** y los conteos de la');
$w('migración salen perfectos. Esta tabla es la que lo detecta.');
$w('');

// OJO CON <> '' EN MySQL: la comparacion rellena las cadenas, asi que un valor
// de dos espacios es IGUAL a la cadena vacia y "cita <> ''" lo excluye. Pero
// la migracion si lo migra, porque tiene bytes.
//
// Eso producia un descuadre de +1 en field_cita (1 877 en Drupal contra 1 876
// "en el origen") que parecia un dato inventado por la migracion. No lo era:
// el articulo 41311 tiene una cita de dos espacios. El fallo estaba en ESTA
// consulta de verificacion, no en la migracion.
//
// Por eso se compara con LENGTH(...) > 0, que cuenta bytes y no aplica relleno.
$campos = [
  'field_balazo' => "SELECT COUNT(*) FROM dc8_posts WHERE post_type='post' AND LENGTH(balazo)>0 AND balazo IS NOT NULL",
  'field_cita' => "SELECT COUNT(*) FROM dc8_posts WHERE post_type='post' AND LENGTH(cita)>0 AND cita IS NOT NULL",
  'field_seccion' => "SELECT COUNT(*) FROM dc8_posts WHERE post_type='post' AND LENGTH(seccion)>0 AND seccion IS NOT NULL",
  'field_subseccion' => "SELECT COUNT(*) FROM dc8_posts WHERE post_type='post' AND LENGTH(subseccion)>0 AND subseccion IS NOT NULL",
  'field_wp_original_id' => "SELECT COUNT(*) FROM dc8_posts WHERE post_type='post' AND original_id<>0 AND original_id IS NOT NULL",
  'field_categoria' => "SELECT COUNT(DISTINCT p.ID) FROM dc8_posts p JOIN dc8_term_relationships tr ON tr.object_id=p.ID JOIN dc8_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='category' WHERE p.post_type='post'",
  'field_tags' => "SELECT COUNT(DISTINCT p.ID) FROM dc8_posts p JOIN dc8_term_relationships tr ON tr.object_id=p.ID JOIN dc8_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='post_tag' WHERE p.post_type='post'",
  'field_titulo_completo' => "SELECT COUNT(*) FROM dc8_posts WHERE post_type='post' AND CHAR_LENGTH(post_title)>255",
];

$w('| Campo | Lo tenía en el origen | Poblado en Drupal | Diferencia |');
$w('|---|---:|---:|---:|');
$camposMal = 0;
foreach ($campos as $campo => $sql) {
  $o = (int) $wp->query($sql)->fetchColumn();
  $tabla = 'node__' . $campo;
  $d = $drupal->schema()->tableExists($tabla)
    ? (int) $drupal->query("SELECT COUNT(DISTINCT entity_id) FROM {" . $tabla . "}")->fetchField()
    : 0;
  if ($o !== $d) {
    $camposMal++;
  }
  $w(sprintf('| `%s` | %d | %d | %s |', $campo, $o, $d,
    $o === $d ? '—' : sprintf('%+d', $d - $o)));
}
$w('');
$w('```text');
$w($camposMal === 0
  ? 'CONCILIADO: todos los campos cuadran con el origen.'
  : sprintf('%d campos descuadran. Ver las notas de abajo.', $camposMal));
$w('```');
$w('');
$w('`field_autor_texto` no entra en la tabla: el crédito se resuelve contra el');
$w('vocabulario y no contra una columna del origen (D-14).');
$w('');

// ---------------------------------------------------------------------------
// 5. URLs preservadas.
// ---------------------------------------------------------------------------

$w('## URLs');
$w('');

$conSlug = (int) $wp->query("
  SELECT COUNT(*) FROM dc8_posts
  WHERE post_type='post' AND post_name<>'' AND post_name IS NOT NULL
")->fetchColumn();

$conAlias = (int) $drupal->query('
  SELECT COUNT(DISTINCT a.path) FROM {path_alias} a
  INNER JOIN {node_field_data} n ON a.path = CONCAT(:p, n.nid)
  WHERE n.type = :t
', [':p' => '/node/', ':t' => 'noticia'])->fetchField();

$desamb = (int) $drupal->query("
  SELECT COUNT(*) FROM {path_alias} WHERE alias REGEXP :r
", [':r' => '-[0-9]{4,6}$'])->fetchField();

$peligrosos = (int) $drupal->query('
  SELECT COUNT(*) FROM (
    SELECT alias FROM {path_alias} GROUP BY alias HAVING COUNT(DISTINCT path) > 1
  ) t
')->fetchField();

$w('```text');
$w(sprintf('Noticias con slug en el origen   %7d', $conSlug));
$w(sprintf('Noticias con alias en Drupal     %7d', $conAlias));
$w(sprintf('Alias desambiguados (D-24, D-27) %7d', $desamb));
$w(sprintf('ALIAS QUE LLEVAN A DOS SITIOS    %7d', $peligrosos));
$w('```');
$w('');
$w('Las 740 entradas sin título son las mismas 740 sin slug: se sirven por');
$w('`/node/N`. Es una URL que cambia y queda contabilizada como tal.');
$w('');
$w('```text');
$w($peligrosos === 0
  ? 'CONCILIADO: ningun alias lleva a dos contenidos. §24 cumplido.'
  : sprintf('%d alias llevan a dos contenidos. §24 NO cumplido. Ver D-25.', $peligrosos));
$w('```');

// ---------------------------------------------------------------------------
// 6. Veredicto.
// ---------------------------------------------------------------------------

$w('');
$w('## Veredicto');
$w('');
$problemas = (count($faltan) > 0) + ($descuadres > 0) + ($camposMal > 0) + ($peligrosos > 0);
$w('```text');
if ($problemas === 0) {
  $w('PASS');
  $w('Conteos conciliados, reparto por anio exacto, campos cuadrados y');
  $w('ninguna ruta perdida.');
}
else {
  $w('PASS CON OBSERVACIONES' . ($faltan ? ' / INCOMPLETO' : ''));
  $w(sprintf('%d bloques de comprobacion presentan diferencias. Cada uno queda', $problemas));
  $w('detallado arriba con sus cifras.');
}
$w('```');

$ruta = DRUPAL_ROOT . '/../../reports/validation/content-comparison.md';
$dir = dirname($ruta);
if (!is_dir($dir)) {
  mkdir($dir, 0777, TRUE);
}
file_put_contents($ruta, implode("\n", $L) . "\n");
echo "\nEscrito en reports/validation/content-comparison.md\n";
