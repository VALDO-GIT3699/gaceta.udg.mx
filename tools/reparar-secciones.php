<?php

/**
 * @file
 * reparar-secciones.php
 *
 * Identifica y rehace las noticias que tienen sección o subsección en el
 * origen pero NO la tienen en Drupal, y escribe la lista para trazabilidad.
 *
 * POR QUÉ HACEN FALTA
 *
 * El vocabulario de secciones se construyó con `GROUP BY seccion`, y la
 * colación del origen (`utf8_general_ci`) no distingue mayúsculas ni acentos:
 * agrupó 485 grafías reales en 447 términos y guardó UNA grafía por grupo.
 * Después `migration_lookup` buscaba la grafía LITERAL de cada fila, de modo
 * que las filas escritas de otra forma no encontraban su término.
 *
 * ```text
 * Resultado: 1 350 secciones y 978 subsecciones sin referencia, con los
 * 36 666 articulos migrados y la migracion dando 0 errores.
 * ```
 *
 * Ejemplo: `Buzon`, `Buzón` y `buzon` son 1 737 artículos de UNA sola sección
 * escrita de tres formas.
 *
 * La causa está corregida en `GacetaNoticia::prepareRow()`, que ahora lleva
 * cada valor a la grafía que sí se migró usando `NormalizaTexto`. Este script
 * sólo determina QUÉ registros hay que rehacer, para no reprocesar los 36 666.
 *
 * NO MODIFICA NADA por sí mismo: escribe la lista de IDs en `work/` y los
 * comandos a ejecutar. La reparación se hace con Migrate, que es reversible.
 *
 * Uso:
 *   drush php:script tools/reparar-secciones.php
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

echo str_repeat('=', 72) . "\n";
echo "NOTICIAS CON SECCION EN EL ORIGEN Y SIN ELLA EN DRUPAL\n";
echo str_repeat('=', 72) . "\n\n";

// 1. Qué IDs del origen tienen cada valor.
$conSeccion = $wp->query("
  SELECT ID FROM dc8_posts
  WHERE post_type = 'post' AND seccion <> '' AND seccion IS NOT NULL
")->fetchAll(PDO::FETCH_COLUMN);
$conSubseccion = $wp->query("
  SELECT ID FROM dc8_posts
  WHERE post_type = 'post' AND subseccion <> '' AND subseccion IS NOT NULL
")->fetchAll(PDO::FETCH_COLUMN);

$conSeccion = array_map('intval', $conSeccion);
$conSubseccion = array_map('intval', $conSubseccion);

// 2. Qué IDs tienen el campo poblado en Drupal.
$pobladoSeccion = $drupal->query('
  SELECT w.field_wp_post_id_value
  FROM {node__field_wp_post_id} w
  INNER JOIN {node__field_seccion} s ON s.entity_id = w.entity_id
')->fetchCol();
$pobladoSubseccion = $drupal->query('
  SELECT w.field_wp_post_id_value
  FROM {node__field_wp_post_id} w
  INNER JOIN {node__field_subseccion} b ON b.entity_id = w.entity_id
')->fetchCol();

$pobladoSeccion = array_map('intval', $pobladoSeccion);
$pobladoSubseccion = array_map('intval', $pobladoSubseccion);

$faltaSeccion = array_diff($conSeccion, $pobladoSeccion);
$faltaSubseccion = array_diff($conSubseccion, $pobladoSubseccion);

printf("Con seccion en el origen       %7d\n", count($conSeccion));
printf("  poblada en Drupal            %7d\n", count($pobladoSeccion));
printf("  FALTA                        %7d\n\n", count($faltaSeccion));

printf("Con subseccion en el origen    %7d\n", count($conSubseccion));
printf("  poblada en Drupal            %7d\n", count($pobladoSubseccion));
printf("  FALTA                        %7d\n\n", count($faltaSubseccion));

$reparar = array_values(array_unique(array_merge($faltaSeccion, $faltaSubseccion)));
sort($reparar);

printf("REGISTROS DISTINTOS A REHACER  %7d\n\n", count($reparar));

if (!$reparar) {
  echo "No hay nada que reparar.\n";
  return;
}

// 3. Cuantos de esos valores ni siquiera TIENEN termino. Si hubiera muchos, el
//    problema no seria la busqueda sino el vocabulario, y habria que mirar
//    ahi antes de rehacer nada.
$sinTermino = 0;
$marcadores = $wp->prepare("
  SELECT seccion, subseccion FROM dc8_posts WHERE ID = ?
");
$mapaSec = \Drupal\gaceta_migrate\NormalizaTexto::mapaDesdeMigracion('migrate_map_gaceta_seccion');
$mapaSub = \Drupal\gaceta_migrate\NormalizaTexto::mapaDesdeMigracion('migrate_map_gaceta_subseccion');

foreach (array_slice($reparar, 0, 2000) as $id) {
  $marcadores->execute([$id]);
  $r = $marcadores->fetch(PDO::FETCH_ASSOC);
  if (!$r) {
    continue;
  }
  $s = trim((string) $r['seccion']);
  $b = trim((string) $r['subseccion']);
  $kS = \Drupal\gaceta_migrate\NormalizaTexto::clave($s);
  $kB = \Drupal\gaceta_migrate\NormalizaTexto::clave($b);
  if (($s !== '' && !isset($mapaSec[$kS])) || ($b !== '' && !isset($mapaSub[$kB]))) {
    $sinTermino++;
  }
}

printf("De una muestra de %d, sin termino en el vocabulario: %d\n",
  min(2000, count($reparar)), $sinTermino);
if ($sinTermino > 0) {
  echo "  ATENCION: esos valores no existen como termino. Revisar el\n";
  echo "  vocabulario antes de rehacer, porque rehacer no los creara.\n";
}
else {
  echo "  Todos tienen termino: el problema era SOLO la busqueda, y la\n";
  echo "  correccion de NormalizaTexto los resuelve al rehacer.\n";
}
echo "\n";

// 4. La lista, troceada para pasarla a --idlist sin pasarse de longitud.
$dir = DRUPAL_ROOT . '/../../work';
if (!is_dir($dir)) {
  mkdir($dir, 0777, TRUE);
}
$trozos = array_chunk($reparar, 300);
$lineas = [];
foreach ($trozos as $t) {
  $lineas[] = implode(',', $t);
}
file_put_contents($dir . '/reparar-secciones-idlist.txt', implode("\n", $lineas) . "\n");

printf("Lista escrita en work/reparar-secciones-idlist.txt (%d trozos de 300)\n",
  count($trozos));
echo "\nPara reparar, por cada linea del archivo:\n";
echo "  drush migrate:rollback gaceta_noticia --idlist=<linea>\n";
echo "  drush migrate:import   gaceta_noticia --idlist=<linea>\n";
echo "\nLo automatiza tools/reparar-secciones.sh\n";
