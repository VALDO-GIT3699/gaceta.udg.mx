<?php

/**
 * @file
 * setup-pagina-contacto.php
 *
 * Coloca los tres formularios de Gaceta en su página de contacto.
 *
 * CÓMO LO HACE, Y POR QUÉ ASÍ
 *
 * Con **bloques de Drupal** sobre la página migrada, no incrustando HTML ni
 * reescribiendo el cuerpo del nodo. §44 lo exige: «No reemplazar Drupal
 * blocks por hacks de HTML estático.»
 *
 * ```text
 * La pagina /contacto ya existe: es el nodo migrado desde WordPress #493.
 * Lo que le faltaba eran los formularios, porque un formulario de CF7 no
 * migra: se reconstruye (D-29).
 * ```
 *
 * QUÉ HABÍA EN PRODUCCIÓN
 *
 * Se comprobó `/contacto/` de producción en modo lectura: muestra tres
 * formularios —consultas generales, colaboración y alta en el boletín—, que
 * son exactamente Contacto, Colaborador y Newsletter de CF7.
 *
 * VISIBILIDAD
 *
 * Los bloques se limitan a la ruta de contacto con una condición de
 * `request_path`. No se ponen en todas las páginas: un formulario de
 * suscripción en los 36 666 artículos es una decisión editorial que nadie ha
 * pedido.
 *
 * IDEMPOTENTE. Reversible: son tres bloques que se desactivan o se borran.
 *
 * Uso:
 *   drush php:script tools/setup-pagina-contacto.php
 */

use Drupal\block\Entity\Block;

// La ruta de la pagina de contacto migrada.
$ruta = '/contacto';

$alias = \Drupal::service('path_alias.repository')->lookupByAlias($ruta, 'es')
  ?: \Drupal::service('path_alias.repository')->lookupByAlias($ruta, 'und');

if (!$alias) {
  // Se busca sin idioma: la migracion no fijo idioma en los alias.
  $fila = \Drupal::database()->query(
    'SELECT path FROM {path_alias} WHERE alias = :a LIMIT 1', [':a' => $ruta]
  )->fetchField();
  if (!$fila) {
    echo "  ! no existe la ruta $ruta. Se detiene en lugar de crear una pagina\n";
    echo "    nueva: la de contacto ya deberia venir migrada desde WordPress.\n";
    return;
  }
  echo "  = pagina de contacto encontrada en $ruta -> $fila\n";
}
else {
  echo "  = pagina de contacto encontrada en $ruta -> " . $alias['path'] . "\n";
}

$bloques = [
  'gaceta_form_contacto' => [
    'webform' => 'gaceta_contacto',
    'etiqueta' => 'Escríbenos',
    'peso' => 10,
  ],
  'gaceta_form_colaborador' => [
    'webform' => 'gaceta_colaborador',
    'etiqueta' => 'Colabora con Gaceta',
    'peso' => 11,
  ],
  'gaceta_form_boletin' => [
    'webform' => 'gaceta_boletin',
    'etiqueta' => 'Recibe el boletín',
    'peso' => 12,
  ],
];

foreach ($bloques as $id => $d) {
  if (Block::load($id)) {
    echo "  = ya existe el bloque: $id\n";
    continue;
  }

  Block::create([
    'id' => $id,
    'theme' => 'drudg8b3',
    'region' => 'content',
    'plugin' => 'webform_block',
    'weight' => $d['peso'],
    'status' => TRUE,
    'settings' => [
      'id' => 'webform_block',
      'label' => $d['etiqueta'],
      'label_display' => 'visible',
      'provider' => 'webform',
      'webform_id' => $d['webform'],
      'default_data' => '',
      'redirect' => FALSE,
    ],
    'visibility' => [
      // Solo en la pagina de contacto. Un formulario de suscripcion en los
      // 36 666 articulos es una decision editorial que nadie ha pedido.
      'request_path' => [
        'id' => 'request_path',
        'negate' => FALSE,
        'pages' => $ruta,
      ],
    ],
  ])->save();

  echo sprintf("  + bloque: %-26s -> %s\n", $id, $d['webform']);
}

echo "\n";
echo "Colocados como bloques de Drupal sobre la pagina migrada (§44).\n";
echo "El cuerpo del nodo NO se ha tocado: sigue siendo el texto de origen.\n";
