<?php

/**
 * @file
 * setup-content-model.php
 *
 * Crea los vocabularios y campos que el modelo de contenido necesita y que el
 * template institucional no traía. Es la implementación de docs/content-model.md.
 *
 * Qué resuelve, con la justificación de cada pieza:
 *
 *   field_balazo        dc8_posts.balazo       13 914 registros lo usan
 *   field_cita          dc8_posts.cita          1 876
 *   field_seccion       dc8_posts.seccion      24 672
 *   field_subseccion    dc8_posts.subseccion   24 673
 *   field_autor_texto   dc8_posts.post_author  decisión D-14
 *   field_credito_fotografia  sin fuente estructurada, decisión D-14
 *   field_colaboradores sin fuente estructurada, decisión D-14
 *   field_wp_post_id    dc8_posts.ID           trazabilidad, CLAUDE.md §35
 *   field_wp_original_id dc8_posts.original_id 25 121, sistema anterior
 *
 * Sin estos campos, migrar perdería 24 672 secciones, 13 914 antetítulos y
 * 1 876 citas destacadas. CLAUDE.md prohíbe la pérdida deliberada.
 *
 * DECISIONES DE DISEÑO QUE LOS DATOS IMPUSIERON:
 *
 * - seccion y subseccion son DOS vocabularios PLANOS, no uno jerárquico.
 *   Motivo: "Crónica" aparece bajo 7 secciones padre distintas y un término
 *   de Drupal sólo admite un padre. Evidencia: reports/audit/content-counts.md
 *
 * - credito_editorial es UN vocabulario con TRES campos de referencia, no tres
 *   vocabularios. Motivo: la misma persona firma texto en una nota y fotografía
 *   en otra; así existe un término canónico por persona. Decisión D-14.
 *
 * - NO se crean cuentas de usuario para los 162 autores de WordPress. Esas
 *   cuentas sólo servían para asignar créditos y nadie inicia sesión con
 *   ellas. Decisión del responsable y su superior (D-14).
 *
 * IDEMPOTENTE: se puede ejecutar varias veces sin duplicar nada.
 * REVERSIBLE: no migra ningún dato. Sólo crea estructura vacía.
 *
 * Uso:
 *   drush php:script tools/setup-content-model.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\Entity\Vocabulary;

// ---------------------------------------------------------------------------
// Vocabularios.
// ---------------------------------------------------------------------------

$vocabularios = [
  'seccion_historica' => [
    'name' => 'Sección histórica',
    'description' => 'Secciones editoriales de Gaceta procedentes de la columna dc8_posts.seccion. Vocabulario PLANO: los datos de origen no forman jerarquía.',
  ],
  'subseccion_historica' => [
    'name' => 'Subsección histórica',
    'description' => 'Subsecciones editoriales procedentes de dc8_posts.subseccion. Vocabulario PLANO e independiente del de secciones: una misma subsección aparece bajo varias secciones.',
  ],
  'credito_editorial' => [
    'name' => 'Crédito editorial',
    'description' => 'Personas a quienes se atribuye el contenido: autoría del texto, fotografía y otras colaboraciones. NO son cuentas de usuario: son identidades de atribución.',
  ],
  // Vocabulario PROPIO y JERARQUICO, separado de seccion_historica.
  //
  // Esta era la duda pendiente de la FASE 7: si `category` y
  // `seccion`/`subseccion` describian lo mismo. Se midio y NO:
  //
  //   18 052 pares articulo-category en articulos que tambien tienen seccion
  //    4 246 (23.5 %) el nombre coincide
  //   13 806 (76.5 %) el nombre es DISTINTO
  //
  // Y se reparten en el tiempo de forma complementaria: `seccion` cubre el
  // 96.6 % del corpus anterior a 2015 y solo el 42 % del posterior, mientras
  // `category` domina a partir de 2015. Es la estructura de la etapa
  // WordPress, no la de la edicion impresa.
  //
  // FUSIONARLAS PERDERIA EL 76.5 % DE LAS CLASIFICACIONES.
  //
  // Tampoco se reutiliza el vocabulario `tags` del template: `tags` es plano
  // y `category` tiene 103 de sus 129 terminos con padre. Aplanarla
  // destruiria la jerarquia que §30 obliga a preservar.
  'categoria_wp' => [
    'name' => 'Categoría',
    'description' => 'Taxonomía `category` de WordPress: la estructura de navegación de la etapa WordPress del sitio. JERÁRQUICA, 103 de 129 términos tienen padre. Distinta de la sección histórica: sólo coinciden en el 23.5 % de los casos.',
  ],
];

foreach ($vocabularios as $vid => $info) {
  if (Vocabulary::load($vid)) {
    echo "  = vocabulario ya existe: $vid\n";
    continue;
  }
  Vocabulary::create([
    'vid' => $vid,
    'name' => $info['name'],
    'description' => $info['description'],
  ])->save();
  echo "  + vocabulario creado: $vid\n";
}

// ---------------------------------------------------------------------------
// Definición de los campos.
// ---------------------------------------------------------------------------

/**
 * Cada entrada declara el almacenamiento y su instancia en el bundle.
 *
 * 'cardinality' -1 significa valores ilimitados.
 */
$campos = [

  // --- Contenido editorial que el template no contemplaba. ---

  // field_balazo es text_long y NO string, por dos razones medidas en el
  // origen. De sus 13 914 valores, 2 571 pasan de 255 caracteres (el máximo
  // es 973) y 7 205 contienen HTML.
  //
  // El primer diseño lo puso como string(255) y el piloto falló con
  // "Data too long for column 'field_balazo_value'" en 7 de 25 registros. Es
  // exactamente lo que el piloto de la FASE 5 existe para encontrar.
  'field_balazo' => [
    'entity_type' => 'node',
    'bundle' => 'noticia',
    'type' => 'text_long',
    'cardinality' => 1,
    'label' => 'Balazo',
    'description' => 'Antetítulo periodístico. Procede de dc8_posts.balazo. Admite HTML: 7 205 de 13 914 valores lo llevan.',
  ],
  'field_cita' => [
    'entity_type' => 'node',
    'bundle' => 'noticia',
    'type' => 'string_long',
    'cardinality' => 1,
    'label' => 'Cita destacada',
    // string_long y no text_long: de los 1 876 valores, NINGUNO contiene HTML
    // y el máximo son 487 caracteres. No necesita formato de texto.
    'description' => 'Cita destacada del contenido. Procede de dc8_posts.cita.',
  ],

  // --- Arquitectura editorial histórica. ---

  'field_seccion' => [
    'entity_type' => 'node',
    'bundle' => 'noticia',
    'type' => 'entity_reference',
    'cardinality' => 1,
    'label' => 'Sección',
    'description' => 'Sección editorial. Procede de dc8_posts.seccion.',
    'target_type' => 'taxonomy_term',
    'target_bundles' => ['seccion_historica'],
  ],
  'field_subseccion' => [
    'entity_type' => 'node',
    'bundle' => 'noticia',
    'type' => 'entity_reference',
    'cardinality' => 1,
    'label' => 'Subsección',
    'description' => 'Subsección editorial. Procede de dc8_posts.subseccion.',
    'target_type' => 'taxonomy_term',
    'target_bundles' => ['subseccion_historica'],
  ],

  // --- Créditos editoriales (D-14). ---

  'field_autor_texto' => [
    'entity_type' => 'node',
    'bundle' => 'noticia',
    'type' => 'entity_reference',
    'cardinality' => -1,
    'label' => 'Autoría del texto',
    'description' => 'Quién firma el texto. Procede de dc8_posts.post_author.',
    'target_type' => 'taxonomy_term',
    'target_bundles' => ['credito_editorial'],
  ],
  // OJO: NO usar 'field_fotografia'. Ese nombre ya existe en el template como
  // campo de IMAGEN, usado por el tipo de contenido 'directorio'. Reutilizar
  // su almacenamiento añade un campo de imagen a noticia en lugar de una
  // referencia a créditos.
  'field_credito_fotografia' => [
    'entity_type' => 'node',
    'bundle' => 'noticia',
    'type' => 'entity_reference',
    'cardinality' => -1,
    'label' => 'Fotografía',
    'description' => 'Quién firma las fotografías. SIN fuente estructurada en el origen: ver D-14.',
    'target_type' => 'taxonomy_term',
    'target_bundles' => ['credito_editorial'],
  ],
  'field_colaboradores' => [
    'entity_type' => 'node',
    'bundle' => 'noticia',
    'type' => 'entity_reference',
    'cardinality' => -1,
    'label' => 'Otros créditos',
    'description' => 'Otras colaboraciones. SIN fuente estructurada en el origen: ver D-14.',
    'target_type' => 'taxonomy_term',
    'target_bundles' => ['credito_editorial'],
  ],

  // --- Trazabilidad (CLAUDE.md §35). ---

  'field_wp_post_id' => [
    'entity_type' => 'node',
    'bundle' => 'noticia',
    'type' => 'integer',
    'cardinality' => 1,
    'label' => 'ID de origen en WordPress',
    'description' => 'dc8_posts.ID. Permite responder dónde terminó cada registro sin depender de las tablas de Migrate.',
  ],
  'field_wp_original_id' => [
    'entity_type' => 'node',
    'bundle' => 'noticia',
    'type' => 'integer',
    'cardinality' => 1,
    'label' => 'ID del sistema anterior a WordPress',
    'description' => 'dc8_posts.original_id. 25 121 registros lo tienen: este contenido ya fue migrado una vez.',
  ],

  // --- Trazabilidad también en las páginas. ---
  //
  // CLAUDE.md §35 exige poder responder dónde terminó cada registro de origen,
  // y eso vale para las 185 páginas igual que para las 36 666 noticias. El
  // almacenamiento ya existe (lo creó el bloque de noticia): aquí sólo se
  // añade la instancia al bundle `page`.
  'field_wp_post_id__page' => [
    'campo_real' => 'field_wp_post_id',
    'entity_type' => 'node',
    'bundle' => 'page',
    'type' => 'integer',
    'cardinality' => 1,
    'label' => 'ID de origen en WordPress',
    'description' => 'dc8_posts.ID de la página de origen.',
  ],
  'field_wp_original_id__page' => [
    'campo_real' => 'field_wp_original_id',
    'entity_type' => 'node',
    'bundle' => 'page',
    'type' => 'integer',
    'cardinality' => 1,
    'label' => 'ID del sistema anterior a WordPress',
    'description' => 'dc8_posts.original_id de la página de origen.',
  ],

  // --- Trazabilidad de los términos de crédito. ---

  // Referencia de la noticia a su categoria. Cardinalidad ilimitada porque en
  // WordPress un contenido puede estar en varias category a la vez.
  'field_categoria' => [
    'entity_type' => 'node',
    'bundle' => 'noticia',
    'type' => 'entity_reference',
    'cardinality' => -1,
    'label' => 'Categoría',
    'description' => 'Categoría de la etapa WordPress. Procede de la taxonomía `category`, vía dc8_term_relationships. La usan 29 485 de las 36 666 noticias.',
    'target_type' => 'taxonomy_term',
    'target_bundles' => ['categoria_wp'],
  ],

  // Trazabilidad del termino de categoria a su term_id de WordPress. Hace
  // falta para reproducir las rutas /category/<slug>/ en la FASE 10 y para
  // reconciliar conteos en la FASE 13 sin depender de las tablas de Migrate.
  'field_wp_term_id' => [
    'entity_type' => 'taxonomy_term',
    'bundle' => 'categoria_wp',
    'type' => 'integer',
    'cardinality' => 1,
    'label' => 'ID del término en WordPress',
    'description' => 'dc8_term_taxonomy.term_id de origen.',
  ],

  'field_wp_user_id' => [
    'entity_type' => 'taxonomy_term',
    'bundle' => 'credito_editorial',
    'type' => 'integer',
    'cardinality' => 1,
    'label' => 'ID de usuario en WordPress',
    'description' => 'dc8_users.ID de origen.',
  ],
  'field_wp_user_login' => [
    'entity_type' => 'taxonomy_term',
    'bundle' => 'credito_editorial',
    'type' => 'string',
    'cardinality' => 1,
    'label' => 'Login en WordPress',
    'description' => 'dc8_users.user_login de origen. No se migra correo ni contraseña.',
    'settings' => ['max_length' => 255],
  ],
];

// ---------------------------------------------------------------------------
// Creación.
// ---------------------------------------------------------------------------

foreach ($campos as $clave => $d) {
  // La clave del array puede llevar un sufijo como '__page' para declarar el
  // MISMO campo en dos bundles distintos. El nombre real del campo se toma de
  // 'campo_real' cuando existe.
  $nombre = $d['campo_real'] ?? $clave;
  $tipo_entidad = $d['entity_type'];
  $bundle = $d['bundle'];

  // Almacenamiento: es común a todos los bundles de la entidad.
  $storage = FieldStorageConfig::loadByName($tipo_entidad, $nombre);
  if (!$storage) {
    $valores = [
      'field_name' => $nombre,
      'entity_type' => $tipo_entidad,
      'type' => $d['type'],
      'cardinality' => $d['cardinality'],
    ];
    if ($d['type'] === 'entity_reference') {
      $valores['settings'] = ['target_type' => $d['target_type']];
    }
    elseif (!empty($d['settings'])) {
      $valores['settings'] = $d['settings'];
    }
    FieldStorageConfig::create($valores)->save();
    echo "  + almacenamiento creado: $tipo_entidad.$nombre ({$d['type']})\n";
  }
  else {
    // GUARDA DE TIPO. Un almacenamiento con el mismo nombre puede existir ya
    // en el template con OTRO tipo. Reutilizarlo a ciegas crea un campo que
    // parece correcto y no lo es: la instancia hereda el tipo del
    // almacenamiento, no el que se declara aquí.
    //
    // Ocurrió con field_fotografia, que el template ya usaba como campo de
    // imagen en el tipo de contenido 'directorio'. Esta guarda existe para
    // que no vuelva a pasar.
    $tipo_real = $storage->getType();
    if ($tipo_real !== $d['type']) {
      echo "  ! ABORTADO: $tipo_entidad.$nombre ya existe como '$tipo_real' "
        . "y aquí se declara '{$d['type']}'.\n";
      echo "    Elige otro nombre de campo. No se toca el almacenamiento "
        . "existente del template.\n";
      continue;
    }
    echo "  = almacenamiento ya existe y el tipo coincide: $tipo_entidad.$nombre\n";
  }

  // Instancia en el bundle.
  if (FieldConfig::loadByName($tipo_entidad, $bundle, $nombre)) {
    echo "  = campo ya existe:          $tipo_entidad.$bundle.$nombre\n";
    continue;
  }

  $valores = [
    'field_name' => $nombre,
    'entity_type' => $tipo_entidad,
    'bundle' => $bundle,
    'label' => $d['label'],
    'description' => $d['description'],
    'required' => FALSE,
  ];

  if ($d['type'] === 'entity_reference') {
    $destinos = [];
    foreach ($d['target_bundles'] as $b) {
      $destinos[$b] = $b;
    }
    $valores['settings'] = [
      'handler' => 'default:' . $d['target_type'],
      'handler_settings' => [
        'target_bundles' => $destinos,
        'sort' => ['field' => 'name', 'direction' => 'asc'],
        // auto_create FALSE a propósito: los términos se crean en su propia
        // migración, con su trazabilidad. Crearlos al paso ocultaría errores
        // de mapeo y generaría duplicados silenciosos.
        'auto_create' => FALSE,
      ],
    ];
  }

  FieldConfig::create($valores)->save();
  echo "  + campo creado:              $tipo_entidad.$bundle.$nombre\n";
}

echo "\nHecho. No se migró ningún dato: sólo se creó estructura vacía.\n";
echo "Revertir: drush field:delete, o restaurar el respaldo de configuración.\n";
