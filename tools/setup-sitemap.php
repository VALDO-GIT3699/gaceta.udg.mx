<?php

/**
 * @file
 * setup-sitemap.php
 *
 * Configura el sitemap XML del sitio migrado.
 *
 * POR QUÉ HACE FALTA, Y POR QUÉ NO ES COSMÉTICA
 *
 * §23 pide revisar el sitemap como parte del SEO. Importa especialmente en
 * esta migración por una razón concreta:
 *
 * ```text
 * 1 772 noticias cambian de URL (D-24, D-27) y 763 pasan a servirse por
 * /node/N porque no tenian slug en el origen. Son 2 535 rutas nuevas que
 * ningun buscador conoce.
 * ```
 *
 * Sin sitemap, esas 2 535 páginas dependen de que un buscador las descubra
 * siguiendo enlaces. Con sitemap se declaran explícitamente.
 *
 * QUÉ SE INCLUYE, Y QUÉ NO
 *
 * ```text
 * SI   node:noticia          36 666   el corpus editorial
 * SI   node:page                185
 * SI   taxonomy_term          9 096   secciones, subsecciones, categorias y
 *                                     etiquetas, que son paginas reales de
 *                                     navegacion con contenido
 * NO   los tipos de la plantilla      su destino es la decision D-05
 * ```
 *
 * ```text
 * NO se incluyen las etiquetas de un solo uso ni nada mas: simple_sitemap ya
 * excluye lo no publicado. Anadir reglas finas AHORA seria adivinar lo que el
 * responsable quiere indexar. Se deja el criterio amplio y documentado.
 * ```
 *
 * PRIORIDADES
 *
 * Se usan las de simple_sitemap por omisión (0.5) salvo para las noticias,
 * que son el contenido editorial y llevan 0.5 también: **no se inflan**. Una
 * prioridad alta en 36 666 páginas no prioriza nada, porque la prioridad es
 * relativa dentro del propio sitio.
 *
 * IDEMPOTENTE. Reversible: es configuración.
 *
 * Uso:
 *   drush php:script tools/setup-sitemap.php
 */

// 1. Activar el mapa por omision.
$mapa = \Drupal\simple_sitemap\Entity\SimpleSitemap::load('default');
if (!$mapa) {
  echo "  ! no existe el sitemap 'default'. Se detiene.\n";
  return;
}
if (!$mapa->status()) {
  $mapa->enable()->save();
  echo "  + sitemap 'default' activado\n";
}
else {
  echo "  = el sitemap 'default' ya estaba activo\n";
}

$indice = \Drupal\simple_sitemap\Entity\SimpleSitemap::load('index');
if ($indice && !$indice->status()) {
  $indice->enable()->save();
  echo "  + indice de sitemaps activado\n";
}

// 2. Que entidades entran.
if (!\Drupal::hasService('simple_sitemap.entity_manager')) {
  echo "  ! no existe el servicio simple_sitemap.entity_manager.\n";
  echo "    La version instalada expone otra API. Se detiene en lugar de\n";
  echo "    adivinar: configurar a mano en /admin/config/search/simplesitemap\n";
  return;
}

$gestor = \Drupal::service('simple_sitemap.entity_manager');

$incluir = [
  ['node', 'noticia'],
  ['node', 'page'],
  ['taxonomy_term', 'seccion_historica'],
  ['taxonomy_term', 'subseccion_historica'],
  ['taxonomy_term', 'categoria_wp'],
  ['taxonomy_term', 'tags'],
];

foreach ($incluir as [$tipo, $bundle]) {
  try {
    $gestor->setBundleSettings($tipo, $bundle, [
      'index' => TRUE,
      // 0.5 a proposito: ver el docblock. Inflar la prioridad de 36 666
      // paginas no prioriza nada.
      'priority' => 0.5,
      'changefreq' => $tipo === 'node' ? 'monthly' : 'weekly',
      'include_images' => FALSE,
    ], ['default']);
    echo sprintf("  + incluido: %-14s %s\n", $tipo, $bundle);
  }
  catch (\Throwable $e) {
    echo sprintf("  ! %s.%s: %s\n", $tipo, $bundle, $e->getMessage());
  }
}

echo "\n";
echo "Hecho. Generar con: drush simple-sitemap:generate\n";
echo "Son mas de 45 000 URLs, asi que la generacion tarda.\n";
