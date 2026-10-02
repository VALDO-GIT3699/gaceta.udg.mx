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
 * llamarse «Plantilla UDG D10» y que el menú fuera el de Gaceta.
 *
 * EL MENÚ SE COPIA DE PRODUCCIÓN, NO SE DEDUCE
 *
 * La primera versión de este script eligió las secciones por VOLUMEN de
 * contenido, suponiendo que las categorías con más artículos serían las de la
 * navegación. Después se leyó el menú real de producción (§46, modo lectura) y
 * no coincidía:
 *
 * ```text
 * PRODUCCION:  Inicio | Investigacion y Conocimiento | Noti Red | Deporte U |
 *              Talento U | 02 Cultura | Especiales | Informacion Oficial |
 *              Hemeroteca
 *
 * LO QUE YO DEDUJE: incluia Universidad, Primer Plano, Comunidad UdeG y
 *              Cartones, que tienen MUCHO contenido pero NO estan en el menu;
 *              y le faltaban Talento U, Especiales y Hemeroteca.
 * ```
 *
 * El menú de producción es **manual**, no un árbol de taxonomía: coloca
 * COVID-19 bajo «Investigación y Conocimiento» aunque en la taxonomía ese
 * término cuelga de «Especiales». Deducirlo del volumen o de la jerarquía da un
 * resultado plausible y a la vez equivocado.
 *
 * ```text
 * LECCION: §46 avisa de esto literalmente. "No conviertas una observacion
 * visual en una decision de modelo de datos sin verificar el origen". Aqui el
 * error fue el inverso y igual de malo: deducir del modelo de datos algo que
 * habia que ir a MIRAR.
 * ```
 *
 * «02 CULTURA» SE DEJA COMO ESTÁ
 *
 * El término lleva un prefijo numérico que parece un artefacto de ordenación, y
 * en la primera versión lo limpié a «Cultura». Pero **producción muestra
 * literalmente «02 Cultura»** en su menú. Así que el prefijo no es un residuo
 * invisible: es lo que el lector ve hoy.
 *
 * ```text
 * Se conserva por FIDELIDAD, y queda como pregunta para el responsable: si
 * quiere que diga "Cultura", es cambiar una linea. Lo que no corresponde es
 * que yo "mejore" el sitio del cliente sin que nadie lo pida.
 * ```
 *
 * «HEMEROTECA» NO SE CREA
 *
 * Es el único elemento del menú de producción sin término equivalente en la
 * taxonomía migrada. No se inventa un destino: se informa y se deja fuera.
 *
 * IDEMPOTENTE. Reversible: sólo toca configuración y enlaces de menú.
 *
 * Uso:
 *   drush php:script tools/setup-identidad-navegacion.php
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\taxonomy\Entity\Term;

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
// 2. El menú principal, copiado del de producción.
//
// La etiqueta es la que produccion muestra. El tid es el termino migrado al que
// apunta. Los hijos van en 'hijos', con su propia etiqueta y tid.
// ---------------------------------------------------------------------------

$menu = [
  [
    'etiqueta' => 'Investigación y Conocimiento',
    'tid' => 9049,
    'hijos' => [
      ['etiqueta' => 'COVID-19', 'tid' => 9139],
      ['etiqueta' => 'Ciencia y Tecnología', 'tid' => 9075],
      ['etiqueta' => 'Medio Ambiente', 'tid' => 9077],
      ['etiqueta' => 'Opinión', 'tid' => 9080],
      ['etiqueta' => 'Sociedad', 'tid' => 9079],
      ['etiqueta' => 'Economía', 'tid' => 9076],
      ['etiqueta' => 'Salud', 'tid' => 9074],
    ],
  ],
  [
    'etiqueta' => 'Noti Red',
    'tid' => 9052,
    'hijos' => [
      ['etiqueta' => 'Corresponsal Gaceta', 'tid' => 9069],
    ],
  ],
  ['etiqueta' => 'Deporte U', 'tid' => 9054],
  ['etiqueta' => 'Talento U', 'tid' => 9051],
  // Se conserva el prefijo: es lo que produccion muestra. Ver el docblock.
  ['etiqueta' => '02 Cultura', 'tid' => 9053],
  ['etiqueta' => 'Especiales', 'tid' => 9061],
  ['etiqueta' => 'Información Oficial', 'tid' => 9055],
];

$almacen = \Drupal::entityTypeManager()->getStorage('menu_link_content');

/**
 * Crea o reutiliza un enlace de menú a un término.
 */
$enlazar = function ($etiqueta, $tid, $peso, $padre = NULL) use ($almacen) {
  if (!Term::load($tid)) {
    echo "  ! el término $tid no existe. Se omite \"$etiqueta\" en lugar de\n";
    echo "    inventar un destino.\n";
    return NULL;
  }
  $uri = 'entity:taxonomy_term/' . $tid;
  $existentes = $almacen->loadByProperties([
    'menu_name' => 'main',
    'link.uri' => $uri,
  ]);
  if ($existentes) {
    $enlace = reset($existentes);
    $cambio = [];
    if ($enlace->getTitle() !== $etiqueta) {
      $enlace->set('title', $etiqueta);
      $cambio[] = 'etiqueta';
    }
    if ((int) $enlace->getWeight() !== $peso) {
      $enlace->set('weight', $peso);
      $cambio[] = 'peso';
    }
    if ($padre !== NULL && $enlace->getParentId() !== $padre) {
      $enlace->set('parent', $padre);
      $cambio[] = 'padre';
    }
    if ($cambio) {
      $enlace->set('enabled', TRUE)->save();
      echo sprintf("  ~ %-32s (%s)\n", $etiqueta, implode(', ', $cambio));
    }
    else {
      echo sprintf("  = %-32s sin cambios\n", $etiqueta);
    }
    return 'menu_link_content:' . $enlace->uuid();
  }

  $enlace = MenuLinkContent::create([
    'title' => $etiqueta,
    'link' => ['uri' => $uri],
    'menu_name' => 'main',
    'weight' => $peso,
    'expanded' => $padre === NULL,
    'parent' => $padre ?? '',
  ]);
  $enlace->save();
  echo sprintf("  + %-32s -> termino %d\n", $etiqueta, $tid);
  return 'menu_link_content:' . $enlace->uuid();
};

$peso = -50;
foreach ($menu as $entrada) {
  $idPadre = $enlazar($entrada['etiqueta'], $entrada['tid'], $peso);
  $peso++;
  if ($idPadre === NULL || empty($entrada['hijos'])) {
    continue;
  }
  $pesoHijo = 0;
  foreach ($entrada['hijos'] as $hijo) {
    $enlazar($hijo['etiqueta'], $hijo['tid'], $pesoHijo, $idPadre);
    $pesoHijo++;
  }
}

// ---------------------------------------------------------------------------
// 3. Los enlaces que NO estan en el menu de produccion.
//
// Se DESACTIVAN, no se borran: es reversible con un clic, y el destino del
// contenido de demostracion es la decision D-05, aun abierta. Pero dejar
// "Ejemplo de Estilos" en el menu de Gaceta tampoco es neutral: parece un
// sitio roto.
//
// Universidad, Primer Plano, Comunidad UdeG y Cartones los habia puesto YO
// deduciendolos del volumen de contenido. Produccion no los tiene en el menu,
// asi que salen. Siguen accesibles por su pagina de termino y por los
// listados: no se pierde ninguna ruta.
// ---------------------------------------------------------------------------

$fuera = [
  'DRUDG 10',
  'Ejemplo de Estilos',
  'Ejemplo con archivos',
  'Universidad',
  'Primer Plano',
  'Comunidad UdeG',
  'Cartones',
  // La etiqueta limpia que yo habia puesto, ahora sustituida por "02 Cultura".
  'Cultura',
];

foreach ($almacen->loadByProperties(['menu_name' => 'main']) as $enlace) {
  if (!in_array($enlace->getTitle(), $fuera, TRUE)) {
    continue;
  }
  if (!$enlace->isEnabled()) {
    continue;
  }
  $enlace->set('enabled', FALSE)->save();
  echo "  - desactivado (NO borrado): " . $enlace->getTitle() . "\n";
}

// ---------------------------------------------------------------------------
// 4. Los enlaces estructurales de la plantilla van al final, como en produccion.
// ---------------------------------------------------------------------------

foreach ($almacen->loadByProperties(['menu_name' => 'main']) as $enlace) {
  if (in_array($enlace->getTitle(), ['Noticias', 'Agenda', 'Multimedia', 'Contacto'], TRUE)) {
    $enlace->set('weight', 10)->save();
  }
}

\Drupal::service('plugin.manager.menu.link')->rebuild();

echo "\n";
echo "FALTA 'Hemeroteca', que produccion tiene en su menu y que NO tiene\n";
echo "termino equivalente en la taxonomia migrada. No se inventa un destino.\n";
echo "\n";
echo "Solo configuracion y enlaces de menu: ningun contenido se toco.\n";
