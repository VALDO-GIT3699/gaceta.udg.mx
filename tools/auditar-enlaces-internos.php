<?php

/**
 * @file
 * auditar-enlaces-internos.php
 *
 * Mide cuántas referencias del CUERPO de los artículos apuntan al dominio de
 * producción con URL absoluta, y por tanto se romperán en el cutover.
 *
 * POR QUÉ ESTO NO SE VE HASTA QUE ES TARDE
 *
 * El contenido migrado trae enlaces e imágenes escritos así:
 *
 * ```text
 * <img src="http://www.gaceta.udg.mx/wp-content/uploads/2026/09/foto.jpg">
 * <a href="https://www.gaceta.udg.mx/otro-articulo/">
 * ```
 *
 * HOY FUNCIONAN, porque ese dominio sigue sirviendo WordPress. Una revisión
 * visual del sitio migrado los ve perfectos.
 *
 * ```text
 * EN EL CUTOVER el dominio pasa a apuntar a Drupal. En ese momento:
 *
 *   /wp-content/uploads/...   deja de existir  -> IMAGENES ROTAS
 *   /otro-articulo/           existe si el alias se preservo, y si no, 404
 *
 * Es decir: el dano aparece el dia del cambio, no antes. Una validacion
 * visual previa al cutover da un FALSO POSITIVO.
 * ```
 *
 * §24 pide revisar los enlaces internos y §34 los incluye en los criterios de
 * aceptación. Esto los cuenta.
 *
 * QUÉ NO HACE
 *
 * No reescribe nada. Reescribir los `src` de las imágenes exige saber dónde
 * quedará cada archivo, y eso depende de recibir los ~40 GB que faltan (B-02).
 * Apuntarlos ahora a rutas que no existen sería peor que dejarlos, porque hoy
 * se ven.
 *
 * SÓLO LECTURA. Escribe un reporte agregado, sin rutas de artículo.
 *
 * Uso:
 *   drush php:script tools/auditar-enlaces-internos.php
 */

$db = \Drupal::database();

$filas = $db->query("
  SELECT b.body_value AS cuerpo
  FROM {node__body} b
  INNER JOIN {node__field_wp_post_id} w ON w.entity_id = b.entity_id
");

$n = 0;
$conImgAbsoluta = 0;
$conEnlaceAbsoluto = 0;
$totalImg = 0;
$totalEnlaces = 0;
$totalImgAbsolutas = 0;
$totalEnlacesAbsolutos = 0;
$conUploads = 0;
$totalUploads = 0;
$dominios = [];

foreach ($filas as $f) {
  $n++;
  $c = (string) $f->cuerpo;
  if ($c === '') {
    continue;
  }

  // Imagenes
  $img = preg_match_all('/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/iu', $c, $mi);
  $totalImg += $img;
  $absI = 0;
  foreach ($mi[1] ?? [] as $u) {
    if (preg_match('#^https?://#i', $u)) {
      $absI++;
      if (preg_match('#^https?://([^/]+)#i', $u, $d)) {
        $dominios[strtolower($d[1])] = ($dominios[strtolower($d[1])] ?? 0) + 1;
      }
    }
  }
  $totalImgAbsolutas += $absI;
  if ($absI > 0) {
    $conImgAbsoluta++;
  }

  // Enlaces
  $a = preg_match_all('/<a\b[^>]*\bhref=["\']([^"\']+)["\']/iu', $c, $ma);
  $totalEnlaces += $a;
  $absA = 0;
  foreach ($ma[1] ?? [] as $u) {
    if (preg_match('#^https?://(www\.)?gaceta\.udg\.mx#i', $u)) {
      $absA++;
    }
  }
  $totalEnlacesAbsolutos += $absA;
  if ($absA > 0) {
    $conEnlaceAbsoluto++;
  }

  // Referencias a /wp-content/uploads, que es lo que desaparece.
  $up = preg_match_all('#/wp-content/uploads/#i', $c);
  $totalUploads += $up;
  if ($up > 0) {
    $conUploads++;
  }
}

arsort($dominios);

$L = [];
$w = function ($t = '') use (&$L) {
  $L[] = $t;
  echo $t . "\n";
};

$w('# Enlaces e imágenes del cuerpo: qué se rompe en el cutover');
$w('');
$w('Fecha: ' . date('Y-m-d H:i'));
$w('Requisito que atiende: CLAUDE.md §24, §34, FASE 10.');
$w('');
$w('Generado con `tools/auditar-enlaces-internos.php`. Sólo lectura.');
$w('');
$w('## Por qué esto no se ve hasta que es tarde');
$w('');
$w('El contenido migrado trae las referencias con **URL absoluta** al dominio');
$w('de producción. Hoy funcionan, porque ese dominio sigue sirviendo');
$w('WordPress, así que una revisión visual del sitio migrado las ve perfectas.');
$w('');
$w('```text');
$w('EN EL CUTOVER el dominio pasa a apuntar a Drupal y /wp-content/uploads');
$w('deja de existir. El dano aparece EL DIA DEL CAMBIO, no antes.');
$w('');
$w('Una validacion visual previa al cutover da un FALSO POSITIVO.');
$w('```');
$w('');
$w('## Las cifras');
$w('');
$w('```text');
$w(sprintf('Cuerpos analizados                     %7d', $n));
$w('');
$w(sprintf('Etiquetas <img> en total               %7d', $totalImg));
$w(sprintf('  con src absoluto                     %7d', $totalImgAbsolutas));
$w(sprintf('  articulos afectados                  %7d', $conImgAbsoluta));
$w('');
$w(sprintf('Enlaces <a> en total                   %7d', $totalEnlaces));
$w(sprintf('  apuntando a gaceta.udg.mx            %7d', $totalEnlacesAbsolutos));
$w(sprintf('  articulos afectados                  %7d', $conEnlaceAbsoluto));
$w('');
$w(sprintf('Referencias a /wp-content/uploads/     %7d', $totalUploads));
$w(sprintf('  articulos afectados                  %7d', $conUploads));
$w('```');
$w('');
$w('## Dominios de las imágenes');
$w('');
$w('```text');
$i = 0;
foreach ($dominios as $d => $c) {
  $w(sprintf('%-40s %7d', $d, $c));
  if (++$i >= 12) {
    $w('...');
    break;
  }
}
$w('```');
$w('');
$w('## Qué hacer, y por qué no ahora');
$w('');
$w('```text');
$w('LAS IMAGENES: reescribir cada src exige saber donde quedara el archivo, y');
$w('eso depende de recibir los ~40 GB que faltan (B-02). Apuntarlas AHORA a');
$w('rutas que no existen seria PEOR que dejarlas, porque hoy se ven.');
$w('```');
$w('');
$w('```text');
$w('LOS ENLACES entre articulos: se resuelven solos si el alias se preservo,');
$w('y 36 088 de 36 851 lo conservan. Los que apunten a un slug desambiguado o');
$w('a una entrada sin slug necesitaran la redireccion 301, que ya existe para');
$w('1 406 rutas historicas.');
$w('```');
$w('');
$w('## Tarea de la FASE 6, cuando lleguen los archivos');
$w('');
$w('```text');
$w('1. Copiar el arbol de uploads a sites/default/files/migrado/');
$w('2. Reescribir en el cuerpo:');
$w('     http://www.gaceta.udg.mx/wp-content/uploads/AAAA/MM/x.jpg');
$w('  -> /sites/default/files/migrado/AAAA/MM/x.jpg');
$w('3. Reescribir los enlaces absolutos a gaceta.udg.mx como RELATIVOS, para');
$w('   que no dependan del dominio.');
$w('4. Volver a ejecutar esta auditoria y comprobar que los contadores bajan');
$w('   a cero.');
$w('```');
$w('');
$w('Los pasos 2 y 3 son una pasada de `migrate:import --update` con un proceso');
$w('de reescritura añadido: no hay que remigrar contenido ni tocar los');
$w('archivos donde estén.');

$ruta = DRUPAL_ROOT . '/../../reports/validation/enlaces-internos.md';
if (!is_dir(dirname($ruta))) {
  mkdir(dirname($ruta), 0777, TRUE);
}
file_put_contents($ruta, implode("\n", $L) . "\n");
echo "\nEscrito en reports/validation/enlaces-internos.md\n";
