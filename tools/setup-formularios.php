<?php

/**
 * @file
 * setup-formularios.php
 *
 * Reconstruye como Webform los formularios que Gaceta tiene hoy en
 * producción con Contact Form 7.
 *
 * POR QUÉ SE RECONSTRUYEN Y NO SE MIGRAN
 *
 * CLAUDE.md §16 es explícito: no se hace «plugin WordPress → plugin Drupal»,
 * se hace «funcionalidad real → auditoría → decisión → implementación
 * Drupal». Un formulario no es contenido: es comportamiento. Migrar la
 * definición de CF7 no serviría de nada porque Drupal no la entiende.
 *
 * QUÉ HAY EN EL ORIGEN, auditado
 *
 * ```text
 * wpcf7_contact_form   4 publicados
 * wpforms              2 publicados
 *
 * Formulario de contacto 1   nombre*, correo*, asunto, mensaje
 * Newsletter                 correo*
 * Colaborador                nombre*, correo*, asunto, mensaje
 * Contacto                   nombre*, correo*, asunto, mensaje
 * ```
 *
 * Y están EN USO: la página `/contacto/` de producción muestra exactamente
 * tres formularios —consultas generales, colaboración y alta en el boletín—,
 * que se corresponden con Contacto, Colaborador y Newsletter.
 *
 * ```text
 * §22 advierte que "0 envios visibles" NO significa que el formulario nunca
 * se usara. Comprobado: las tablas de envios de WPForms estan vacias porque
 * CF7 no guarda los envios, los ENVIA POR CORREO. Que no haya registros no
 * dice nada sobre el uso.
 * ```
 *
 * LOS DESTINATARIOS
 *
 * Se leen de la base de auditoría en el momento de crear el formulario y se
 * escriben directamente en la configuración de Drupal.
 *
 * ```text
 * NUNCA se imprimen por pantalla, NUNCA entran en un reporte y NUNCA se
 * versionan: el remoto es publico (§37, D-06). Este script los copia de una
 * base a la otra sin que pasen por ningun sitio visible.
 * ```
 *
 * NO se toca el webform `contact` que la plantilla ya traía (§44).
 *
 * IDEMPOTENTE: si el formulario ya existe, no se modifica.
 *
 * Uso:
 *   drush php:script tools/setup-formularios.php
 */

use Drupal\webform\Entity\Webform;

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

/**
 * Saca el destinatario de la configuración de CF7 sin exponerlo.
 */
$destinatario = function ($postId) use ($wp) {
  $st = $wp->prepare("
    SELECT meta_value FROM dc8_postmeta
    WHERE post_id = ? AND meta_key = '_mail'
  ");
  $st->execute([$postId]);
  $crudo = (string) $st->fetchColumn();
  if ($crudo === '') {
    return NULL;
  }
  // La configuracion de CF7 es un array serializado con la clave 'recipient'.
  $datos = @unserialize($crudo);
  $valor = is_array($datos) ? ($datos['recipient'] ?? '') : '';
  if (!is_string($valor) || strpos($valor, '@') === FALSE) {
    return NULL;
  }
  // Puede traer varios separados por coma, y tokens de CF7 como [your-email].
  $limpios = [];
  foreach (preg_split('/[,;]/', $valor) as $trozo) {
    $trozo = trim($trozo);
    if ($trozo !== '' && strpos($trozo, '[') === FALSE
      && filter_var($trozo, FILTER_VALIDATE_EMAIL)) {
      $limpios[] = $trozo;
    }
  }
  return $limpios ? implode(',', $limpios) : NULL;
};

// Los elementos, en YAML, que es como Webform los guarda.
$camposCompletos = <<<YAML
nombre:
  '#type': textfield
  '#title': Nombre
  '#required': true
correo:
  '#type': email
  '#title': 'Correo electrónico'
  '#required': true
asunto:
  '#type': textfield
  '#title': Asunto
mensaje:
  '#type': textarea
  '#title': Mensaje
  '#rows': 6
enviar:
  '#type': webform_actions
  '#title': 'Enviar'
YAML;

$camposBoletin = <<<YAML
correo:
  '#type': email
  '#title': 'Correo electrónico'
  '#required': true
  '#description': 'Se usará únicamente para enviarte el boletín de Gaceta UdeG.'
enviar:
  '#type': webform_actions
  '#title': 'Suscribirme'
YAML;

$formularios = [
  'gaceta_contacto' => [
    'titulo' => 'Contacto',
    'origen' => 54689,
    'elementos' => $camposCompletos,
  ],
  'gaceta_colaborador' => [
    'titulo' => 'Colaborar con Gaceta',
    'origen' => 54685,
    'elementos' => $camposCompletos,
  ],
  'gaceta_boletin' => [
    'titulo' => 'Boletín',
    'origen' => 51631,
    'elementos' => $camposBoletin,
  ],
];

foreach ($formularios as $id => $d) {
  if (Webform::load($id)) {
    echo "  = ya existe: $id\n";
    continue;
  }

  $correo = $destinatario($d['origen']);

  $manejadores = [];
  if ($correo !== NULL) {
    $manejadores['correo'] = [
      'id' => 'email',
      'label' => 'Aviso por correo',
      'handler_id' => 'correo',
      'status' => TRUE,
      'weight' => 0,
      'settings' => [
        'to_mail' => $correo,
        'from_mail' => '[site:mail]',
        'from_name' => '[site:name]',
        'subject' => '[webform_submission:values:asunto] (' . $d['titulo'] . ')',
        'body' => '[webform_submission:values]',
        'html' => TRUE,
      ],
    ];
  }

  Webform::create([
    'id' => $id,
    'title' => $d['titulo'],
    'description' => 'Reconstruido desde Contact Form 7 (WordPress #'
      . $d['origen'] . '). Ver docs/decisiones.md.',
    'elements' => $d['elementos'],
    'handlers' => $manejadores,
    'settings' => [
      // LOS ENVIOS NO SE GUARDAN, y esto cambio respecto a la primera version.
      //
      // Yo lo habia puesto en FALSE, es decir guardandolos, y lo presente como
      // "mejora deliberada": CF7 solo los mandaba por correo, asi que un
      // correo perdido era un mensaje perdido sin rastro, y §22 advertia de
      // eso.
      //
      // El auditor lo marco, y tiene razon: decidir que el sitio NUEVO
      // ALMACENE datos personales que el origen nunca retuvo no es una mejora
      // tecnica, es la decision D-04 ("Tratamiento de envios de formularios:
      // datos personales"), que sigue ABIERTA en el tablero.
      //
      // Y es la menos reversible de todas las que he tomado: un
      // migrate:rollback borra nodos, pero no borra datos personales ya
      // recogidos de personas reales.
      //
      // Asi que por omision NO se guardan, que es el comportamiento del
      // origen. Si el responsable resuelve D-04 a favor de guardarlos, se
      // cambia esta linea.
      'results_disabled' => TRUE,
      'form_confidential' => FALSE,
      'confirmation_type' => 'message',
      'confirmation_message' => 'Gracias. Hemos recibido tu mensaje.',
    ],
  ])->save();

  echo sprintf("  + creado: %-22s \"%s\"  destinatario: %s\n",
    $id, $d['titulo'], $correo === NULL ? 'NO CONFIGURADO' : 'copiado del origen');
}

echo "\n";
echo "Los destinatarios se copiaron de una base a la otra sin imprimirse (§37).\n";
echo "El webform `contact` de la plantilla NO se ha tocado (§44).\n";
echo "\nPENDIENTE: colocar los formularios en la pagina de contacto, que forma\n";
echo "parte de la reconstruccion visual (FASE 11).\n";
