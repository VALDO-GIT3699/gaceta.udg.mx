<?php

/**
 * @file
 * setup-noticia-display.php
 *
 * Configura la VISUALIZACIÓN de los campos nuevos del tipo `noticia`, tanto en
 * el formulario de edición como en la presentación del nodo.
 *
 * Existe porque crear un campo no lo hace visible. tools/setup-content-model.php
 * creó los 9 campos nuevos y el piloto los pobló correctamente, pero al
 * renderizar un artículo migrado no aparecía ninguno: los datos estaban en la
 * base y no en la página. Es un fallo que ningún conteo detecta.
 *
 * ORDEN DE PRESENTACIÓN, con criterio periodístico:
 *
 *   balazo      antes del cuerpo, es el antetítulo
 *   cita        antes del cuerpo, es la cita destacada
 *   cuerpo      el artículo
 *   créditos    después del cuerpo: autoría, fotografía, colaboraciones
 *   sección     después, como metadato de navegación
 *   etiquetas   al final
 *
 * Los dos campos de trazabilidad (field_wp_post_id y field_wp_original_id) se
 * OCULTAN en la presentación: son datos internos de migración, no contenido
 * para el lector. Siguen disponibles en el formulario y por SQL.
 *
 * IDEMPOTENTE. REVERSIBLE: es sólo configuración de visualización.
 *
 * Uso:
 *   drush php:script tools/setup-noticia-display.php
 */

$tipo = 'node';
$bundle = 'noticia';

/** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $repo */
$repo = \Drupal::service('entity_display.repository');

$vista = $repo->getViewDisplay($tipo, $bundle, 'default');
$form = $repo->getFormDisplay($tipo, $bundle, 'default');

// ---------------------------------------------------------------------------
// Presentación del nodo.
// ---------------------------------------------------------------------------

$presentacion = [
  'field_balazo' => [
    'peso' => -5,
    'formateador' => 'text_default',
    'etiqueta' => 'hidden',
  ],
  'field_cita' => [
    'peso' => -4,
    'formateador' => 'basic_string',
    'etiqueta' => 'hidden',
  ],
  'field_autor_texto' => [
    'peso' => 10,
    'formateador' => 'entity_reference_label',
    'etiqueta' => 'inline',
  ],
  'field_credito_fotografia' => [
    'peso' => 11,
    'formateador' => 'entity_reference_label',
    'etiqueta' => 'inline',
  ],
  'field_colaboradores' => [
    'peso' => 12,
    'formateador' => 'entity_reference_label',
    'etiqueta' => 'inline',
  ],
  'field_categoria' => [
    'peso' => 19,
    'formateador' => 'entity_reference_label',
    'etiqueta' => 'inline',
  ],
  'field_seccion' => [
    'peso' => 20,
    'formateador' => 'entity_reference_label',
    'etiqueta' => 'inline',
  ],
  'field_subseccion' => [
    'peso' => 21,
    'formateador' => 'entity_reference_label',
    'etiqueta' => 'inline',
  ],
];

foreach ($presentacion as $campo => $d) {
  $vista->setComponent($campo, [
    'label' => $d['etiqueta'],
    'type' => $d['formateador'],
    'weight' => $d['peso'],
    'settings' => [],
    'third_party_settings' => [],
  ]);
  echo sprintf("  + visible en el nodo: %-28s (peso %d, %s)\n",
    $campo, $d['peso'], $d['formateador']);
}

// El titulo completo se OCULTA en la presentacion: el titulo del nodo ya lo
// muestra cortado y repetir el parrafo entero debajo seria ruido. El dato
// sigue en la base y en el formulario, que es lo que exige no perderlo.
// Afecta a 21 de 36 666 noticias.
$vista->removeComponent('field_titulo_completo');
echo "  - oculto en el nodo:  field_titulo_completo (21 casos, dato preservado)
";

// Trazabilidad: oculta al lector, pero el dato sigue en la base.
foreach (['field_wp_post_id', 'field_wp_original_id'] as $campo) {
  $vista->removeComponent($campo);
  echo "  - oculto en el nodo:  $campo (dato interno de migración)\n";
}

$vista->save();

// ---------------------------------------------------------------------------
// Formulario de edición.
// ---------------------------------------------------------------------------

$formulario = [
  'field_balazo' => ['peso' => -5, 'widget' => 'text_textarea'],
  'field_cita' => ['peso' => -4, 'widget' => 'string_textarea'],
  'field_seccion' => ['peso' => 10, 'widget' => 'options_select'],
  'field_subseccion' => ['peso' => 11, 'widget' => 'options_select'],
  'field_autor_texto' => ['peso' => 12, 'widget' => 'entity_reference_autocomplete_tags'],
  'field_credito_fotografia' => ['peso' => 13, 'widget' => 'entity_reference_autocomplete_tags'],
  'field_colaboradores' => ['peso' => 14, 'widget' => 'entity_reference_autocomplete_tags'],
  'field_categoria' => ['peso' => 15, 'widget' => 'entity_reference_autocomplete_tags'],
  // Visible en el formulario para que un editor pueda recuperar el titulo
  // original de las 21 noticias cuyo titulo no cabia.
  'field_titulo_completo' => ['peso' => 16, 'widget' => 'string_textarea'],
  // Los de trazabilidad SÍ se muestran en el formulario, para que un editor
  // pueda consultar de dónde vino el contenido sin pedir acceso a la base.
  'field_wp_post_id' => ['peso' => 50, 'widget' => 'number'],
  'field_wp_original_id' => ['peso' => 51, 'widget' => 'number'],
];

foreach ($formulario as $campo => $d) {
  $form->setComponent($campo, [
    'type' => $d['widget'],
    'weight' => $d['peso'],
    'settings' => [],
    'third_party_settings' => [],
  ]);
  echo sprintf("  + en el formulario:   %-28s (peso %d, %s)\n",
    $campo, $d['peso'], $d['widget']);
}

$form->save();

echo "\nHecho. Es sólo configuración de visualización: ningún dato se tocó.\n";
