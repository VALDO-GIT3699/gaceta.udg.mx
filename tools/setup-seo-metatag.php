<?php

/**
 * @file
 * setup-seo-metatag.php
 *
 * Configura los valores por omisión de `metatag` a partir de las plantillas
 * REALES de Yoast, no de criterios inventados.
 *
 * POR QUÉ ESTO Y NO MIGRAR EL PLUGIN
 *
 * §23 es explícito: «No migres el plugin Yoast. Migra los datos SEO que tengan
 * valor.» Y los datos de valor son de dos clases distintas:
 *
 * ```text
 * 1. Lo que una PERSONA escribio a mano:
 *    910 meta descriptions. Ya migradas a field_metatags, una por nodo.
 *
 * 2. Las PLANTILLAS con que Yoast generaba el resto:
 *    es lo que configura este script, como valores por omision.
 * ```
 *
 * La diferencia importa: guardar en cada uno de los 36 666 nodos un título
 * derivado de una plantilla sería duplicar 36 666 veces la misma regla, y
 * además congelarla. Metatag resuelve en cascada, así que la plantilla se
 * escribe UNA vez.
 *
 * LAS PLANTILLAS DE YOAST, leídas de `dc8_options.wpseo_titles`
 *
 * ```text
 * title-home-wpseo     %%sitename%% %%page%% %%sep%% %%sitedesc%%
 * title-post           %%title%% %%page%% %%sep%% %%sitename%%
 * title-page           %%title%% %%page%% %%sep%% %%sitename%%
 * title-tax-category   %%term_title%% archivos %%page%% %%sep%% %%sitename%%
 * title-tax-post_tag   %%term_title%% archivos %%page%% %%sep%% %%sitename%%
 * title-404-wpseo      Pagina no encontrada %%sep%% %%sitename%%
 * separator            sc-dash   (es el guion)
 * metadesc-home-wpseo  VACIO
 * metadesc-post        VACIO
 * ```
 *
 * TRADUCCIÓN DE TOKENS, y lo que NO tiene equivalente
 *
 * ```text
 * %%title%%        -> [node:title]
 * %%term_title%%  -> [term:name]
 * %%sitename%%    -> [site:name]
 * %%sitedesc%%    -> [site:slogan]
 * %%sep%%         -> -        (sc-dash)
 * %%page%%        -> SE OMITE. Drupal no tiene un token de numero de pagina
 *                    para el titulo, y en Yoast estaba vacio salvo en
 *                    paginacion. Inventar uno cambiaria el titulo de todas
 *                    las paginas, no solo de las paginadas.
 * ```
 *
 * ```text
 * Las dos metadesc de plantilla estaban VACIAS en Yoast, asi que no hay nada
 * que trasladar. No se inventa ninguna: una descripcion derivada del cuerpo
 * seria texto que nadie escribio. Los 910 casos con descripcion propia ya
 * estan en el nodo.
 * ```
 *
 * TAMBIÉN se fija el `canonical`, que §23 pide revisar. Sin él, una misma
 * noticia alcanzable por su alias y por `/node/N` se indexaría dos veces.
 *
 * IDEMPOTENTE: vuelve a escribir los mismos valores. Reversible: es config.
 *
 * Uso:
 *   drush php:script tools/setup-seo-metatag.php
 */

// El separador de Yoast era 'sc-dash'. No se elige: se traslada.
$sep = '-';

$defaults = [
  // Global: la base de la cascada.
  'global' => [
    'title' => '[current-page:title] ' . $sep . ' [site:name]',
    'canonical_url' => '[current-page:url]',
  ],
  // Portada. En Yoast el eslogan iba al final, pero esta VACIO en el origen,
  // asi que el titulo queda en el nombre del sitio y no en "Gaceta UDG - ".
  'front' => [
    'title' => '[site:name]',
    'canonical_url' => '[site:url]',
  ],
  // Noticias y paginas.
  'node' => [
    'title' => '[node:title] ' . $sep . ' [site:name]',
    'canonical_url' => '[node:url]',
  ],
  // Secciones, subsecciones, categorias y etiquetas. Se conserva la palabra
  // "archivos" que Yoast ponia: es el texto que hoy esta indexado.
  'taxonomy_term' => [
    'title' => '[term:name] archivos ' . $sep . ' [site:name]',
    'canonical_url' => '[term:url]',
  ],
  '404' => [
    'title' => 'Página no encontrada ' . $sep . ' [site:name]',
  ],
];

$fabrica = \Drupal::configFactory();

foreach ($defaults as $id => $etiquetas) {
  $nombre = 'metatag.metatag_defaults.' . $id;
  $config = $fabrica->getEditable($nombre);
  if ($config->isNew()) {
    echo "  ! no existe $nombre. Se omite en lugar de crearlo a ciegas.\n";
    continue;
  }
  $actuales = $config->get('tags') ?: [];
  $nuevas = $actuales;
  $cambios = [];
  foreach ($etiquetas as $etiqueta => $valor) {
    if (($actuales[$etiqueta] ?? NULL) === $valor) {
      continue;
    }
    $nuevas[$etiqueta] = $valor;
    $cambios[] = $etiqueta;
  }
  if (!$cambios) {
    echo "  = sin cambios: $id\n";
    continue;
  }
  $config->set('tags', $nuevas)->save();
  echo sprintf("  + %-14s %s\n", $id, implode(', ', $cambios));
}

echo "\n";
echo "Trasladado de las plantillas de Yoast en dc8_options.wpseo_titles.\n";
echo "El token %%page%% se OMITE: Drupal no tiene equivalente y en Yoast\n";
echo "estaba vacio salvo en paginacion. Inventarlo cambiaria el titulo de\n";
echo "TODAS las paginas, no solo de las paginadas.\n";
echo "\n";
echo "Las metadesc de plantilla estaban VACIAS en el origen: no se inventa\n";
echo "ninguna. Las 910 escritas a mano ya estan en field_metatags.\n";
