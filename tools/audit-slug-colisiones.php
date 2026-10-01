<?php

/**
 * @file
 * audit-slug-colisiones.php
 *
 * Caracteriza las colisiones de slug de `dc8_posts` para la decisión D-16, que
 * es el único bloqueo real de la migración masiva de las 36 666 noticias.
 *
 * EL PROBLEMA
 * WordPress sirve con permalinks /%postname%/. En `dc8_posts` hay 1 382 slugs
 * repetidos; `Enfoques` aparece 108 veces. Drupal NO impone unicidad en
 * `path_alias`: acepta dos alias idénticos sin protestar y después resuelve
 * sólo uno. El resultado es una pérdida SILENCIOSA de rutas, que CLAUDE.md §24
 * prohíbe de forma expresa.
 *
 * El piloto lo reprodujo: tres nodos comparten `/Enfoques`.
 *
 * LO QUE ESTE SCRIPT MIDE, y por qué cada cosa importa
 *
 *   1. Cuántos slugs están repetidos y cuántos registros arrastran, separando
 *      `post` de `page`, porque se migran por rutas distintas.
 *   2. CUÁNTOS DE LOS COLISIONADOS ESTÁN PUBLICADOS. Esto es lo decisivo: si
 *      en un grupo de 108 sólo uno está `publish`, en producción sólo uno
 *      tiene URL viva y los otros 107 no son rutas que preservar. El tamaño
 *      del problema puede ser una fracción del que aparenta.
 *   3. La distribución del tamaño de los grupos, para saber si es un problema
 *      concentrado en unos pocos slugs o repartido por todo el corpus.
 *   4. Si los miembros de un grupo son contenido distinto o duplicados reales,
 *      comparando fecha y título. Un slug repetido con el mismo título es otra
 *      cosa que un slug repetido con títulos distintos.
 *
 * SÓLO LECTURA. No escribe en ninguna base de datos. No toca producción.
 *
 * Uso:
 *   php tools/audit-slug-colisiones.php [dir_salida]
 */

$salida = $argv[1] ?? 'work';
if (!is_dir($salida)) {
  mkdir($salida, 0777, TRUE);
}

try {
  $db = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=gaceta_auditoria;charset=utf8mb4',
    'root', '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
}
catch (PDOException $e) {
  fwrite(STDERR, 'No se pudo conectar a gaceta_auditoria: ' . $e->getMessage() . "\n");
  exit(1);
}

$linea = str_repeat('-', 72);

// ---------------------------------------------------------------------------
// 1. Panorama por tipo de contenido.
// ---------------------------------------------------------------------------

echo "$linea\n1. COLISIONES POR TIPO DE CONTENIDO\n$linea\n\n";

$sql = "
  SELECT post_type,
         COUNT(*)                         AS registros,
         COUNT(DISTINCT post_name)        AS slugs_distintos,
         SUM(post_name = '' OR post_name IS NULL) AS sin_slug
  FROM dc8_posts
  WHERE post_type IN ('post', 'page')
  GROUP BY post_type
";
printf("%-8s %10s %10s %10s\n", 'tipo', 'registros', 'slugs', 'sin slug');
foreach ($db->query($sql) as $r) {
  printf("%-8s %10d %10d %10d\n",
    $r['post_type'], $r['registros'], $r['slugs_distintos'], $r['sin_slug']);
}

// ---------------------------------------------------------------------------
// 2. Grupos colisionados y su estado de publicación.
//
// Aquí está la pregunta que decide el tamaño del problema.
// ---------------------------------------------------------------------------

echo "\n$linea\n2. GRUPOS COLISIONADOS Y CUANTOS ESTAN PUBLICADOS\n$linea\n\n";

foreach (['post', 'page'] as $tipo) {
  $sql = "
    SELECT post_name,
           COUNT(*) AS n,
           SUM(post_status = 'publish') AS publicados,
           COUNT(DISTINCT post_title)   AS titulos_distintos
    FROM dc8_posts
    WHERE post_type = :t AND post_name <> '' AND post_name IS NOT NULL
    GROUP BY post_name
    HAVING n > 1
  ";
  $st = $db->prepare($sql);
  $st->execute([':t' => $tipo]);
  $grupos = $st->fetchAll(PDO::FETCH_ASSOC);

  $nGrupos = count($grupos);
  $nRegistros = 0;
  $nPublicados = 0;
  // Grupos donde a lo sumo UNO está publicado: no hay colisión real de ruta
  // viva, porque en producción sólo uno responde.
  $inocuos = 0;
  $conflictivos = 0;
  $registrosEnConflicto = 0;
  $mismoTitulo = 0;

  foreach ($grupos as $g) {
    $nRegistros += (int) $g['n'];
    $nPublicados += (int) $g['publicados'];
    if ((int) $g['publicados'] <= 1) {
      $inocuos++;
    }
    else {
      $conflictivos++;
      $registrosEnConflicto += (int) $g['publicados'];
    }
    if ((int) $g['titulos_distintos'] === 1) {
      $mismoTitulo++;
    }
  }

  echo strtoupper($tipo) . "\n";
  printf("  Grupos con slug repetido          %7d\n", $nGrupos);
  printf("  Registros implicados              %7d\n", $nRegistros);
  printf("  De ellos, con post_status publish %7d\n", $nPublicados);
  echo "\n";
  printf("  Grupos con 0 o 1 publicado        %7d   <- NO hay ruta viva en disputa\n", $inocuos);
  printf("  Grupos con 2 o mas publicados     %7d   <- COLISION REAL\n", $conflictivos);
  printf("  Registros publicados en conflicto %7d\n", $registrosEnConflicto);
  printf("  Grupos cuyo titulo es identico    %7d   (posibles duplicados de contenido)\n", $mismoTitulo);
  echo "\n";

  // Distribución del tamaño de los grupos conflictivos.
  if ($conflictivos > 0) {
    $hist = [];
    foreach ($grupos as $g) {
      if ((int) $g['publicados'] > 1) {
        $k = (int) $g['publicados'];
        $cubeta = $k == 2 ? '2' : ($k <= 5 ? '3-5' : ($k <= 20 ? '6-20' : ($k <= 100 ? '21-100' : '>100')));
        $hist[$cubeta] = ($hist[$cubeta] ?? 0) + 1;
      }
    }
    echo "  Tamano de los grupos en conflicto (publicados por slug):\n";
    foreach (['2', '3-5', '6-20', '21-100', '>100'] as $c) {
      if (isset($hist[$c])) {
        printf("    %-8s %6d grupos\n", $c, $hist[$c]);
      }
    }
    echo "\n";
  }

  // Los peores casos, para poder comprobarlos uno a uno contra producción.
  $sql = "
    SELECT post_name, COUNT(*) AS n, SUM(post_status = 'publish') AS publicados
    FROM dc8_posts
    WHERE post_type = :t AND post_name <> '' AND post_name IS NOT NULL
    GROUP BY post_name
    HAVING publicados > 1
    ORDER BY publicados DESC, n DESC
    LIMIT 15
  ";
  $st = $db->prepare($sql);
  $st->execute([':t' => $tipo]);
  $peores = $st->fetchAll(PDO::FETCH_ASSOC);
  if ($peores) {
    echo "  Los 15 slugs con mas registros PUBLICADOS compartiendo ruta:\n";
    printf("    %-42s %6s %10s\n", 'slug', 'total', 'publicados');
    foreach ($peores as $p) {
      printf("    %-42s %6d %10d\n",
        substr($p['post_name'], 0, 42), $p['n'], $p['publicados']);
    }
    echo "\n";
  }
}

// ---------------------------------------------------------------------------
// 3. CSV con el detalle, para work/. Lleva títulos: no entra en Git.
// ---------------------------------------------------------------------------

$fh = fopen("$salida/url-colisiones.csv", 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, [
  'post_name', 'post_type', 'registros_en_grupo', 'publicados_en_grupo',
  'wp_post_id', 'post_status', 'post_date', 'post_title', 'es_ruta_viva',
]);

$sql = "
  SELECT p.ID, p.post_name, p.post_type, p.post_status, p.post_date, p.post_title,
         g.n, g.publicados
  FROM dc8_posts p
  INNER JOIN (
    SELECT post_name, post_type, COUNT(*) AS n,
           SUM(post_status = 'publish') AS publicados
    FROM dc8_posts
    WHERE post_type IN ('post', 'page')
      AND post_name <> '' AND post_name IS NOT NULL
    GROUP BY post_name, post_type
    HAVING n > 1
  ) g ON g.post_name = p.post_name AND g.post_type = p.post_type
  ORDER BY g.publicados DESC, p.post_name, p.post_date
";
$n = 0;
foreach ($db->query($sql) as $r) {
  fputcsv($fh, [
    $r['post_name'], $r['post_type'], $r['n'], $r['publicados'],
    $r['ID'], $r['post_status'], $r['post_date'], $r['post_title'],
    ($r['post_status'] === 'publish' && (int) $r['publicados'] > 1) ? 'EN_DISPUTA' : 'no',
  ]);
  $n++;
}
fclose($fh);

echo "$linea\n";
echo "Detalle de $n registros en $salida/url-colisiones.csv\n";
echo "Ese CSV lleva titulos de articulos: queda en work/, fuera de Git (D-06).\n";
echo "No se escribio en ninguna base de datos ni se toco produccion.\n";
