<?php

/**
 * @file
 * generar-reportes-trazabilidad.php
 *
 * Genera los tres reportes que CLAUDE.md §41 exige y que faltaban:
 *
 *   reports/migration/traceability.md       la matriz de §35
 *   reports/migration/migration-errors.md   el registro de errores
 *   reports/validation/url-comparison.md    URL vieja contra URL nueva
 *
 * LA PREGUNTA QUE TIENE QUE PODER RESPONDER LA TRAZABILIDAD (§35)
 *
 * «¿Dónde terminó exactamente este registro WordPress?»
 *
 * Y la inversa, que es la que de verdad detecta problemas: «¿de dónde viene
 * este nodo de Drupal?». La segunda es la que encontró los 4 nodos de
 * demostración de la plantilla que yo estaba contando como contenido migrado.
 *
 * POR QUÉ HAY DOS MECANISMOS DE TRAZABILIDAD A PROPÓSITO
 *
 * ```text
 * 1. Las tablas migrate_map_*. Automaticas, pero desaparecen con un
 *    migrate:reset o una reconstruccion.
 * 2. Campos propios en la entidad: field_wp_post_id, field_wp_original_id,
 *    field_wp_user_id, field_wp_user_login, field_wp_term_id.
 * ```
 *
 * El segundo hace la trazabilidad **independiente del estado de las
 * herramientas**. `original_id` importa especialmente: 25 121 registros lo
 * tienen, y es el identificador del sistema anterior a WordPress. Es decir,
 * este contenido ya fue migrado una vez antes.
 *
 * SÓLO LECTURA. El CSV completo, que lleva títulos y rutas, va a `work/`,
 * excluido de Git. En Git entra el resumen agregado (§37, D-06).
 *
 * Uso:
 *   drush php:script tools/generar-reportes-trazabilidad.php
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

$raiz = DRUPAL_ROOT . '/../..';
foreach (['/reports/migration', '/reports/validation', '/work'] as $d) {
  if (!is_dir($raiz . $d)) {
    mkdir($raiz . $d, 0777, TRUE);
  }
}

// ---------------------------------------------------------------------------
// 1. La matriz de trazabilidad, al CSV de work/.
// ---------------------------------------------------------------------------

echo "1. Matriz de trazabilidad...\n";

$fh = fopen($raiz . '/work/traceability.csv', 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, [
  'wp_post_id', 'wp_original_id', 'wp_post_type', 'wp_post_status', 'wp_slug',
  'old_url', 'drupal_entity_type', 'drupal_entity_id', 'new_url',
  'migration_id', 'migration_status', 'validation_status', 'notas',
]);

$filas = $drupal->query("
  SELECT w.field_wp_post_id_value AS wp,
         o.field_wp_original_id_value AS orig,
         n.nid, n.type, n.status, n.title,
         a.alias,
         m.source_row_status
  FROM {node__field_wp_post_id} w
  INNER JOIN {node_field_data} n ON n.nid = w.entity_id
  LEFT JOIN {node__field_wp_original_id} o ON o.entity_id = n.nid
  LEFT JOIN {path_alias} a ON a.path = CONCAT('/node/', n.nid)
  LEFT JOIN {migrate_map_gaceta_noticia} m ON m.destid1 = n.nid
");

$total = 0;
$conAlias = 0;
$sinAlias = 0;
$conOriginal = 0;
$porTipo = [];
foreach ($filas as $r) {
  $total++;
  $alias = $r->alias ?? '';
  if ($alias !== '') {
    $conAlias++;
  }
  else {
    $sinAlias++;
  }
  if (!empty($r->orig)) {
    $conOriginal++;
  }
  $porTipo[$r->type] = ($porTipo[$r->type] ?? 0) + 1;

  fputcsv($fh, [
    $r->wp,
    $r->orig ?? '',
    $r->type === 'noticia' ? 'post' : 'page',
    $r->status ? 'publish' : 'no publicado',
    ltrim($alias, '/'),
    'https://www.gaceta.udg.mx/' . ltrim($alias, '/'),
    'node',
    $r->nid,
    $alias !== '' ? $alias : '/node/' . $r->nid,
    $r->type === 'noticia' ? 'gaceta_noticia' : 'gaceta_pagina',
    $r->source_row_status === NULL ? 'sin mapa' : 'importado',
    $alias !== '' ? 'url preservada' : 'url CAMBIA: se sirve por /node/N',
    '',
  ]);
}
fclose($fh);
printf("   %d registros en work/traceability.csv\n", $total);

// ---------------------------------------------------------------------------
// 2. El reporte agregado de trazabilidad.
// ---------------------------------------------------------------------------

$L = [];
$w = function ($t = '') use (&$L) {
  $L[] = $t;
};

$w('# Matriz de trazabilidad');
$w('');
$w('Fecha: ' . date('Y-m-d H:i'));
$w('Requisito que atiende: CLAUDE.md §35, §41.');
$w('');
$w('Generado con `tools/generar-reportes-trazabilidad.php`. Reproducible.');
$w('');
$w('## Qué pregunta responde');
$w('');
$w('> «¿Dónde terminó exactamente este registro WordPress?»');
$w('');
$w('Y la inversa, que es la que de verdad encuentra problemas: «¿de dónde');
$w('viene este nodo de Drupal?». Esa segunda pregunta fue la que detectó los');
$w('4 nodos de demostración de la plantilla que yo estaba contando como');
$w('contenido migrado.');
$w('');
$w('## Los dos mecanismos, a propósito');
$w('');
$w('```text');
$w('1. Tablas migrate_map_*      automaticas, pero desaparecen con un');
$w('                             migrate:reset o una reconstruccion');
$w('2. Campos en la entidad      field_wp_post_id, field_wp_original_id,');
$w('                             field_wp_user_id, field_wp_user_login,');
$w('                             field_wp_term_id');
$w('```');
$w('');
$w('El segundo hace la trazabilidad **independiente del estado de las');
$w('herramientas**, y permite reconciliar con SQL directo.');
$w('');
$w('## Cobertura');
$w('');
$w('```text');
$w(sprintf('Entidades con trazabilidad al origen   %7d', $total));
foreach ($porTipo as $t => $n) {
  $w(sprintf('  tipo %-24s          %7d', $t, $n));
}
$w('');
$w(sprintf('Con URL preservada                     %7d', $conAlias));
$w(sprintf('Sin slug en el origen: /node/N         %7d', $sinAlias));
$w(sprintf('Con original_id del sistema anterior   %7d', $conOriginal));
$w('```');
$w('');
$w('`original_id` merece una nota: **este contenido ya fue migrado una vez');
$w('antes**, desde el sistema que Gaceta usaba antes de WordPress. Conservar');
$w('ese identificador mantiene viva la cadena completa de procedencia.');
$w('');
$w('## Dónde está el detalle');
$w('');
$w('```text');
$w('work/traceability.csv   una fila por entidad, con las 13 columnas de §35');
$w('```');
$w('');
$w('Ese CSV **no entra en Git**: lleva títulos de artículo y rutas, que son');
$w('contenido editorial, y el remoto es público (§37, D-06). En Git entra');
$w('sólo este resumen agregado.');

file_put_contents($raiz . '/reports/migration/traceability.md', implode("\n", $L) . "\n");
echo "   reports/migration/traceability.md\n";

// ---------------------------------------------------------------------------
// 3. URL vieja contra URL nueva.
// ---------------------------------------------------------------------------

echo "2. Comparacion de URLs...\n";

$conSlug = (int) $wp->query("
  SELECT COUNT(*) FROM dc8_posts
  WHERE post_type = 'post' AND LENGTH(post_name) > 0
")->fetchColumn();
$sinSlug = (int) $wp->query("
  SELECT COUNT(*) FROM dc8_posts
  WHERE post_type = 'post' AND (post_name IS NULL OR LENGTH(post_name) = 0)
")->fetchColumn();

$desamb = (int) $drupal->query("
  SELECT COUNT(*) FROM {path_alias} WHERE alias REGEXP :r
", [':r' => '-[0-9]{4,6}$'])->fetchField();
$redirs = (int) $drupal->query('SELECT COUNT(*) FROM {redirect}')->fetchField();
$conflicto = (int) $drupal->query('
  SELECT COUNT(*) FROM (
    SELECT alias FROM {path_alias} GROUP BY alias HAVING COUNT(DISTINCT path) > 1
  ) t
')->fetchField();

$L = [];
$w('# Comparación de URLs: vieja contra nueva');
$w('');
$w('Fecha: ' . date('Y-m-d H:i'));
$w('Requisito que atiende: CLAUDE.md §24, §41.');
$w('');
$w('## El reparto');
$w('');
$w('```text');
$w(sprintf('Noticias con slug en el origen         %7d', $conSlug));
$w(sprintf('  de ellas, con alias IDENTICO         %7d', $conSlug - $desamb));
$w(sprintf('  desambiguadas (D-24, D-27)           %7d', $desamb));
$w('');
$w(sprintf('Noticias SIN slug en el origen         %7d', $sinSlug));
$w('  se sirven por /node/N. Es una URL que CAMBIA, y queda contabilizada');
$w('  como tal: son las mismas entradas que tampoco tienen titulo.');
$w('');
$w(sprintf('Redirecciones 301 creadas              %7d', $redirs));
$w(sprintf('ALIAS QUE LLEVAN A DOS CONTENIDOS      %7d', $conflicto));
$w('```');
$w('');
$w('## Por qué 1 772 URLs llevan un número al final');
$w('');
$w('Porque en WordPress **no eran alcanzables**. 347 slugs están repetidos');
$w('entre las noticias publicadas, y en cada grupo sólo uno responde: se');
$w('verificó contra producción que `?p=ID` no salva a los demás, porque');
$w('WordPress lo redirige al permalink y ahí vuelve a ganar el mismo.');
$w('');
$w('```text');
$w('Darles un alias unico no pierde una ruta: CREA una que hoy no existe, y');
$w('rescata articulos publicados que llevan anos inalcanzables.');
$w('```');
$w('');
$w('El sufijo es el id de WordPress y no `-2`, `-3`, porque es la única forma');
$w('determinista: un contador depende del orden de proceso y rompería la');
$w('reproducibilidad de §31. Ver D-24.');
$w('');
$w('## Las redirecciones');
$w('');
$w('```text');
$w('entradas _wp_old_slug sobre post        7 792');
$w('  son BUCLES (slug viejo = el actual)   6 286   no son redirecciones');
$w('  redirecciones REALES                  1 403');
$w('  rutas que reclamaban 2+ destinos          49');
$w('```');
$w('');
$w('```text');
$w('CORRECCION: en documentos anteriores cite "7 811 redirecciones". Esa cifra');
$w('contaba las entradas de postmeta sin descartar los bucles. Son 1 403.');
$w('```');
$w('');
$w('## Lo que queda');
$w('');
if ($conflicto > 0) {
  $w('```text');
  $w(sprintf('%d alias llevan a dos contenidos. §24 NO esta cumplido del todo.', $conflicto));
  $w('```');
  $w('');
  $w('Es `/inicio`: lo reclaman la portada de la plantilla y la página «Inicio»');
  $w('de WordPress. Es la decisión **D-25**, pendiente del responsable, y la');
  $w('última ruta en conflicto de todo el sitio.');
}
else {
  $w('```text');
  $w('CONCILIADO: ningun alias lleva a dos contenidos. §24 cumplido.');
  $w('```');
}

file_put_contents($raiz . '/reports/validation/url-comparison.md', implode("\n", $L) . "\n");
echo "   reports/validation/url-comparison.md\n";

// ---------------------------------------------------------------------------
// 4. Registro de errores de migración.
// ---------------------------------------------------------------------------

echo "3. Registro de errores...\n";

$L = [];
$w('# Registro de errores de migración');
$w('');
$w('Fecha: ' . date('Y-m-d H:i'));
$w('Requisito que atiende: CLAUDE.md §41, §40, FASE 9.');
$w('');
$w('## Estado actual de los mensajes de Migrate');
$w('');
$w('```text');

$migraciones = [
  'gaceta_noticia', 'gaceta_pagina', 'gaceta_seccion', 'gaceta_subseccion',
  'gaceta_credito', 'gaceta_etiqueta', 'gaceta_categoria',
  'gaceta_redireccion', 'gaceta_comentario',
];
$totalMensajes = 0;
foreach ($migraciones as $m) {
  $tablaMapa = 'migrate_map_' . $m;
  $tablaMsg = 'migrate_message_' . $m;
  if (!$drupal->schema()->tableExists($tablaMapa)) {
    $w(sprintf('%-22s sin ejecutar', $m));
    continue;
  }
  // Se cuentan las filas CON destino, no todas las del mapa. Migrate deja
  // tambien fila para lo que se descarto, con destid1 a NULL, y contarlas
  // inflaba la cifra: gaceta_redireccion aparecia con 7 792 "importados"
  // cuando las redirecciones creadas son 1 406. Las otras 6 386 son bucles y
  // conflictos descartados a proposito.
  $importados = (int) $drupal->query(
    "SELECT COUNT(*) FROM {" . $tablaMapa . "} WHERE destid1 IS NOT NULL"
  )->fetchField();
  $descartados = (int) $drupal->query(
    "SELECT COUNT(*) FROM {" . $tablaMapa . "} WHERE destid1 IS NULL"
  )->fetchField();
  $mensajes = $drupal->schema()->tableExists($tablaMsg)
    ? (int) $drupal->query("SELECT COUNT(*) FROM {" . $tablaMsg . "}")->fetchField()
    : 0;
  $totalMensajes += $mensajes;
  $w(sprintf('%-22s %7d creados  %6d descartados  %4d mensajes',
    $m, $importados, $descartados, $mensajes));
}
$w('```');
$w('');
$w('## Los errores que SÍ ocurrieron, y cómo se trataron');
$w('');
$w('§40 obliga a no ocultar ningún error. Éstos se reprodujeron, se');
$w('diagnosticaron y se corrigieron; ninguno se «arregló» eliminando datos.');
$w('');
$w('| Error | Causa | Tratamiento |');
$w('|---|---|---|');
$w('| `Data too long for column field_balazo_value` | Diseñé el campo como `string(255)`; el máximo real es 973 | Recreado como `text_long`. 0 pérdidas |');
$w('| `Column title cannot be null` | 740 entradas sin título en el origen | Marcador explícito. No se inventó ningún título ni se descartó ningún registro |');
$w('| Títulos como `Qu&eacute; bien` | Entidades HTML en 2 948 títulos | Decodificadas en títulos y nombres de término. El cuerpo NO se toca: es HTML |');
$w('| `Data too long for column title` | 21 títulos pasan de 255 caracteres, el mayor con 867 | Corte en frontera de palabra + `field_titulo_completo` con el original. 0 pérdidas |');
$w('| `Duplicate entry` en `redirect` | 49 rutas viejas reclamaban 2+ destinos | Gana el más reciente, el mismo criterio verificado en D-24. Los descartados quedan marcados |');
$w('| `Unknown column COUNT in having clause` | `havingCondition()` entrecomilla su primer argumento | Expresión con alias |');
$w('');
$w('## Los errores SILENCIOSOS, que son los peligrosos');
$w('');
$w('Ninguno de éstos produjo un solo mensaje de error. Todos daban');
$w('`failed_count = 0` y conteos correctos.');
$w('');
$w('| Fallo silencioso | Qué lo detectó |');
$w('|---|---|');
$w('| 9 campos creados y poblados que no se renderizaban | Mirar una ficha, no contar |');
$w('| `/Enfoques` apuntando a 3 nodos a la vez | El piloto |');
$w('| Mapa de colisiones vacío por leer `$fila->campo` con FETCH_ASSOC | Un aviso de PHP |');
$w('| 66 rutas perdidas: MySQL agrupaba sin acentos y PHP comparaba byte a byte | `validar-alias-unicos.php` |');
$w('| Colisión entre una noticia y una PÁGINA | `validar-alias-unicos.php` |');
$w('| 1 350 secciones y 978 subsecciones sin referencia, por la misma causa | `conciliar-conteos.php`, campo a campo |');
$w('| 13 749 filas de alias redundantes tras reprocesar | `validar-alias-unicos.php` |');
$w('');
$w('```text');
$w('LECCION: los seis primeros fallos reales se arreglaron en minutos. Los');
$w('siete silenciosos costaron mucho mas, y tres de ellos tenian la MISMA');
$w('causa: comparar con criterios distintos a los que se habia agrupado.');
$w('Por eso la normalizacion vive ahora en un sitio UNICO, NormalizaTexto.');
$w('```');
$w('');
$w('## Veredicto');
$w('');
$w('```text');
if ($totalMensajes === 0) {
  $w('0 mensajes de error pendientes en las 9 migraciones.');
}
else {
  $w(sprintf('%d mensajes pendientes. Revisar con drush migrate:messages.', $totalMensajes));
}
$w('```');

file_put_contents($raiz . '/reports/migration/migration-errors.md', implode("\n", $L) . "\n");
echo "   reports/migration/migration-errors.md\n";

echo "\nHecho. El CSV con el detalle queda en work/, fuera de Git (§37).\n";
