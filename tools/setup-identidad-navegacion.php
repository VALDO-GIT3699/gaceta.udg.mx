<?php

/**
 * @file
 * setup-identidad-navegacion.php
 *
 * FASE 11: pone la identidad de Gaceta y su navegación sobre la plantilla
 * institucional, sin sustituir nada de la plantilla por HTML estático (§44).
 *
 * QUÉ ESTABA YA RESUELTO POR LA PLANTILLA, y conviene no rehacerlo
 *
 * Al comprobar el sitio migrado resultó que buena parte de la FASE 11 ya
 * funcionaba, porque las Views del template filtran por tipo de contenido y
 * recogieron las noticias migradas solas:
 *
 * ```text
 * /noticias                  LISTA las noticias migradas
 * ficha de articulo          muestra cuerpo, balazo, autoria, seccion,
 *                            subseccion y categoria
 * /taxonomy/term/<tid>       LISTA por seccion, subseccion, categoria y
 *                            etiqueta. taxonomy_index tiene 154 494 filas
 *                            sobre 8 958 terminos y 36 633 nodos
 * portada                    ya mezcla articulos migrados con la demo
 * ```
 *
 * Así que lo que faltaba no era construir listados: era que el sitio dejara de
 * llamarse «Plantilla UDG D10» y que el menú dejara de ofrecer «Ejemplo de
 * Estilos».
 *
 * QUÉ HACE ESTE SCRIPT
 *
 *   1. El nombre del sitio: «Gaceta UDG», tomado LITERALMENTE del encabezado
 *      de producción, no inventado.
 *   2. El menú principal con las secciones reales de Gaceta, que son las
 *      categorías raíz con más contenido, en el orden de producción.
 *   3. Desactiva, NO BORRA, los enlaces de demostración de la plantilla.
 *
 * ```text
 * LOS ENLACES DE DEMO SE DESACTIVAN, NO SE BORRAN. Es reversible con un clic y
 * respeta que el destino del contenido de demostracion es la decision D-05,
 * aun abierta. Dejar "Ejemplo de Estilos" en el menu de Gaceta tampoco es
 * neutral: parece un sitio roto.
 * ```
 *
 * IDEMPOTENTE. Reversible: sólo toca configuración y enlaces de menú.
 *
 * Uso:
 *   drush php:script tools/setup-identidad-navegacion.php
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

// ---------------------------------------------------------------------------
// 1. Identidad del sitio.
// ---------------------------------------------------------------------------

$config = \Drupal::configFactory()->getEditable('system.site');
$antes = $config->get('name');
if ($antes !== 'Gaceta UDG') {
  $config->set('name', 'Gaceta UDG')->save();
  echo "  + nombre del sitio: \"$antes\" -> \"Gaceta UDG\"\n";
}
else {
  echo "  = el nombre del sitio ya es \"Gaceta UDG\"\n";
}

// ---------------------------------------------------------------------------
// 2. El menú principal.
//
// Las secciones son las categorías RAÍZ con más contenido, que son las mismas
// que producción muestra en su navegación. Se enlazan a la página del término,
// que ya funciona.
//
// OJO CON LAS ETIQUETAS: el término se llama «02 Cultura», con un prefijo
// numérico que en WordPress servía para ordenar. En el MENÚ se escribe
// «Cultura», porque el prefijo es un artefacto de ordenación y no el nombre de
// la sección. El TÉRMINO NO SE RENOMBRA: es un dato migrado y se queda como
// está en el origen. Sólo cambia la etiqueta del enlace, que es presentación.
// ---------------------------------------------------------------------------

$secciones = [
  9049 => 'Investigación y Conocimiento',
  9067 => 'Universidad',
  9053 => 'Cultura',
  9052 => 'Noti Red',
  9046 => 'Primer Plano',
  9055 => 'Información Oficial',
  9054 => 'Deporte U',
  9050 => 'Comunidad UdeG',
  9048 => 'Cartones',
];

$almacen = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$peso = -50;

foreach ($secciones as $tid => $etiqueta) {
  $termino = \Drupal\taxonomy\Entity\Term::load($tid);
  if (!$termino) {
    echo "  ! el término $tid no existe. Se omite en lugar de inventar un enlace.\n";
    continue;
  }

  $existentes = $almacen->loadByProperties([
    'menu_name' => 'main',
    'link.uri' => 'entity:taxonomy_term/' . $tid,
  ]);
  if ($existentes) {
    echo "  = ya existe el enlace: $etiqueta\n";
    $peso++;
    continue;
  }

  MenuLinkContent::create([
    'title' => $etiqueta,
    'link' => ['uri' => 'entity:taxonomy_term/' . $tid],
    'menu_name' => 'main',
    'weight' => $peso,
    'expanded' => FALSE,
  ])->save();

  echo sprintf("  + enlace: %-30s -> termino %d (\"%s\")\n",
    $etiqueta, $tid, $termino->label());
  $peso++;
}

// ---------------------------------------------------------------------------
// 3. Los enlaces de demostración de la plantilla.
// ---------------------------------------------------------------------------

$demo = [
  'DRUDG 10',
  'Ejemplo de Estilos',
  'Ejemplo con archivos',
];

$todos = $almacen->loadByProperties(['menu_name' => 'main']);
foreach ($todos as $enlace) {
  if (!in_array($enlace->getTitle(), $demo, TRUE)) {
    continue;
  }
  if (!$enlace->isEnabled()) {
    echo "  = ya estaba desactivado: " . $enlace->getTitle() . "\n";
    continue;
  }
  $enlace->set('enabled', FALSE)->save();
  echo "  - desactivado (NO borrado): " . $enlace->getTitle() . "\n";
}

// Las secciones de Gaceta pesan menos que los enlaces estructurales de la
// plantilla, para que la navegacion empiece por el contenido editorial y deje
// Agenda, Multimedia y Contacto al final, como en produccion.
foreach ($todos as $enlace) {
  $t = $enlace->getTitle();
  if (in_array($t, ['Noticias', 'Agenda', 'Multimedia', 'Contacto'], TRUE)) {
    $enlace->set('weight', 10)->save();
  }
}

\Drupal::service('plugin.manager.menu.link')->rebuild();

echo "\n";
echo "Hecho. Solo configuracion y enlaces de menu: ningun contenido se toco.\n";
echo "Los enlaces de demostracion quedan DESACTIVADOS, no borrados (D-05).\n";
