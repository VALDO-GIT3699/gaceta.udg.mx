<?php

/**
 * @file
 * rotar-credenciales.php
 *
 * Rota la contraseña de la base de datos de Drupal y el `hash_salt` del sitio.
 *
 * POR QUÉ HACE FALTA
 *
 * En la conexión `default` de `settings.php`, el nombre de la base de datos,
 * el usuario y la contraseña eran **la misma cadena**. Ese nombre se escribió
 * en tres archivos versionados de un repositorio **público**, así que
 * publicarlo publicó la contraseña. Bloqueo **B-05**.
 *
 * Retirar el valor de los archivos no basta: sigue en el historial y ya estuvo
 * en GitHub. La única solución real es rotar.
 *
 * ```text
 * ORDEN EXIGIDO POR EL RESPONSABLE, y este script lo sigue literalmente:
 *   1. contrasena nueva
 *   2. hash_salt nuevo
 *   3. actualizar settings.php
 *   4. comprobar funcionamiento
 *   5. verificar que la credencial ANTERIOR ya no funciona
 *   6. solo despues, purgar el historial (D-10)
 * ```
 *
 * El paso 6 NO lo hace este script: es D-10 y va aparte.
 *
 * REGLA ABSOLUTA DE ESTE SCRIPT
 *
 * ```text
 * NO IMPRIME NI LA CONTRASENA NI EL SALT. Ni por pantalla, ni en un archivo de
 * registro, ni en ningun reporte. Los escribe DIRECTAMENTE en settings.php,
 * que esta excluido de Git, y dice en que linea quedaron para que el
 * responsable los lea del archivo si los necesita.
 * ```
 *
 * SEGURIDAD DEL PROCESO
 *
 *   - Respalda `settings.php` antes de tocarlo, con marca de tiempo.
 *   - Si algo falla, dice exactamente cómo volver atrás.
 *   - Comprueba la credencial nueva ANTES de dar nada por bueno.
 *   - Comprueba que la ANTIGUA ya no entra, que es la verificación que de
 *     verdad cierra el bloqueo.
 *
 * Uso:
 *   php tools/rotar-credenciales.php            (simulación: no cambia nada)
 *   php tools/rotar-credenciales.php --ejecutar
 */

$ejecutar = in_array('--ejecutar', $argv, TRUE);

$settings = __DIR__ . '/../plantilla_drupal/Drudg10.6.9/sites/default/settings.php';
if (!is_file($settings)) {
  fwrite(STDERR, "No encuentro settings.php en:\n  $settings\n");
  exit(1);
}

$linea = str_repeat('=', 72);
echo "$linea\nROTACION DE CREDENCIALES  (B-05)\n$linea\n\n";

// ---------------------------------------------------------------------------
// Leer la configuración actual SIN imprimir valores.
// ---------------------------------------------------------------------------

$txt = file_get_contents($settings);

if (!preg_match(
  '/\$databases\s*\[\s*[\'"]default[\'"]\s*\]\s*\[\s*[\'"]default[\'"]\s*\]\s*=\s*array\s*\((.*?)\n\);/s',
  $txt, $m)) {
  fwrite(STDERR, "No pude localizar la conexion default en settings.php.\n");
  exit(1);
}
$bloque = $m[1];
$leer = function ($clave) use ($bloque) {
  return preg_match('/[\'"]' . $clave . '[\'"]\s*=>\s*[\'"]([^\'"]*)[\'"]/', $bloque, $x)
    ? $x[1] : NULL;
};

$baseDatos = $leer('database');
$usuario = $leer('username');
$claveVieja = $leer('password');
$host = $leer('host') ?: '127.0.0.1';

if ($baseDatos === NULL || $usuario === NULL || $claveVieja === NULL) {
  fwrite(STDERR, "No pude leer database/username/password.\n");
  exit(1);
}

printf("Base de datos      %s\n", $baseDatos);
printf("Usuario            %s\n", $usuario);
printf("Contrasena actual  %d caracteres%s\n", strlen($claveVieja),
  $claveVieja === $baseDatos ? '  <-- ES IGUAL AL NOMBRE DE LA BASE' : '');
printf("hash_salt actual   %s\n\n",
  preg_match('/\$settings\[[\'"]hash_salt[\'"]\]\s*=\s*[\'"]([^\'"]*)[\'"]/', $txt, $h)
    ? strlen($h[1]) . ' caracteres' : 'NO LO ENCUENTRO');

// ---------------------------------------------------------------------------
// Generar los valores nuevos. random_bytes es criptograficamente seguro.
// ---------------------------------------------------------------------------

/**
 * Contraseña sin caracteres que compliquen comillas en PHP o en la shell.
 */
$alfabeto = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789-_.';
$claveNueva = '';
for ($i = 0; $i < 40; $i++) {
  $claveNueva .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
}
$saltNuevo = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes(55)));

echo "Valores nuevos generados: contrasena de " . strlen($claveNueva)
  . " caracteres y salt de " . strlen($saltNuevo) . ".\n";
echo "NO se imprimen. Quedaran solo en settings.php, que esta fuera de Git.\n\n";

if (!$ejecutar) {
  echo "SIMULACION. No se ha cambiado nada.\n\n";
  echo "Lo que haria, en el orden exigido:\n";
  echo "  1. ALTER USER del usuario de la base con la contrasena nueva\n";
  echo "  2. generar el hash_salt nuevo\n";
  echo "  3. escribir los dos en settings.php, con respaldo previo\n";
  echo "  4. conectar con la credencial NUEVA para comprobar que funciona\n";
  echo "  5. intentar conectar con la ANTIGUA y comprobar que YA NO entra\n\n";
  echo "Para ejecutarlo de verdad:\n";
  echo "  php tools/rotar-credenciales.php --ejecutar\n";
  exit(0);
}

// ---------------------------------------------------------------------------
// PASO 0. Respaldo de settings.php.
// ---------------------------------------------------------------------------

$respaldo = $settings . '.antes-de-rotar-' . date('Ymd-His');
if (!copy($settings, $respaldo)) {
  fwrite(STDERR, "No pude respaldar settings.php. Se detiene.\n");
  exit(1);
}
echo "0. Respaldo: " . basename($respaldo) . "\n";

// ---------------------------------------------------------------------------
// PASO 1. La contraseña de la base.
// ---------------------------------------------------------------------------

try {
  $admin = new PDO("mysql:host=$host", 'root', '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
catch (PDOException $e) {
  fwrite(STDERR, "No pude conectar como administrador: " . $e->getMessage() . "\n");
  exit(1);
}

// MariaDB 10.4 admite ALTER USER. Se usa un parametro para que la clave no
// pase por el log de consultas como literal visible en el historial de la
// shell.
try {
  $st = $admin->prepare("ALTER USER ?@'localhost' IDENTIFIED BY ?");
  // MariaDB no admite parametros en ALTER USER, asi que se escapa a mano.
  $u = str_replace("'", "''", $usuario);
  $c = addslashes($claveNueva);
  $admin->exec("ALTER USER '$u'@'localhost' IDENTIFIED BY '$c'");
  $admin->exec('FLUSH PRIVILEGES');
  echo "1. Contrasena de la base cambiada.\n";
}
catch (PDOException $e) {
  fwrite(STDERR, "FALLO al cambiar la contrasena: " . $e->getMessage() . "\n");
  fwrite(STDERR, "settings.php NO se ha tocado. Nada que revertir.\n");
  exit(1);
}

// ---------------------------------------------------------------------------
// PASOS 2 y 3. hash_salt nuevo y escritura en settings.php.
// ---------------------------------------------------------------------------

$nuevoTxt = $txt;

// La contrasena, SOLO dentro del bloque de la conexion default.
$bloqueNuevo = preg_replace(
  '/([\'"]password[\'"]\s*=>\s*[\'"])' . preg_quote($claveVieja, '/') . '([\'"])/',
  '${1}' . $claveNueva . '${2}',
  $bloque, 1
);
if ($bloqueNuevo === $bloque) {
  fwrite(STDERR, "No pude sustituir la contrasena en el bloque. REVERTIR:\n");
  fwrite(STDERR, "  ALTER USER '$usuario'@'localhost' IDENTIFIED BY '<la anterior>'\n");
  exit(1);
}
$nuevoTxt = str_replace($bloque, $bloqueNuevo, $nuevoTxt);

// El hash_salt.
$nuevoTxt = preg_replace(
  '/(\$settings\[[\'"]hash_salt[\'"]\]\s*=\s*[\'"])[^\'"]*([\'"])/',
  '${1}' . $saltNuevo . '${2}',
  $nuevoTxt, 1, $cuentaSalt
);
if (!$cuentaSalt) {
  // Si no existia, se anade al final.
  $nuevoTxt .= "\n\$settings['hash_salt'] = '" . $saltNuevo . "';\n";
}
echo "2. hash_salt nuevo generado.\n";

if (file_put_contents($settings, $nuevoTxt) === FALSE) {
  fwrite(STDERR, "FALLO al escribir settings.php. Restaurar con:\n  $respaldo\n");
  exit(1);
}
echo "3. settings.php actualizado.\n";

// ---------------------------------------------------------------------------
// PASO 4. La credencial NUEVA funciona.
// ---------------------------------------------------------------------------

try {
  $p = new PDO("mysql:host=$host;dbname=$baseDatos;charset=utf8mb4",
    $usuario, $claveNueva, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  $n = $p->query('SELECT COUNT(*) FROM node_field_data')->fetchColumn();
  printf("4. La credencial NUEVA conecta. node_field_data: %s filas.\n", $n);
}
catch (PDOException $e) {
  fwrite(STDERR, "FALLO: la credencial nueva NO conecta.\n");
  fwrite(STDERR, "  " . $e->getMessage() . "\n");
  fwrite(STDERR, "RESTAURAR settings.php desde:\n  $respaldo\n");
  exit(1);
}

// ---------------------------------------------------------------------------
// PASO 5. La credencial ANTIGUA ya NO entra. Esta es la que cierra B-05.
// ---------------------------------------------------------------------------

$viejaFunciona = FALSE;
try {
  new PDO("mysql:host=$host;dbname=$baseDatos", $usuario, $claveVieja,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  $viejaFunciona = TRUE;
}
catch (PDOException $e) {
  // Lo esperado.
}

if ($viejaFunciona) {
  echo "\n5. ATENCION: la credencial ANTIGUA SIGUE FUNCIONANDO.\n";
  echo "   B-05 NO queda cerrado. Revisar si hay otro usuario con los mismos\n";
  echo "   permisos, o un host distinto de localhost.\n";
  exit(2);
}
echo "5. La credencial ANTIGUA ya NO conecta. Verificado.\n";

echo "\n$linea\n";
echo "ROTACION COMPLETADA\n";
echo "$linea\n\n";
echo "Los valores nuevos estan SOLO en:\n";
echo "  plantilla_drupal/Drudg10.6.9/sites/default/settings.php\n";
echo "que esta excluido de Git. Si necesitas la contrasena, leela de ahi.\n\n";
echo "El respaldo anterior queda en:\n  " . basename($respaldo) . "\n";
echo "BORRALO cuando confirmes que todo va bien: contiene la clave vieja.\n\n";
echo "PENDIENTE: ahora si se puede purgar el historial (D-10).\n";
