#!/usr/bin/env bash
#
# indexar-busqueda.sh
#
# Construye el indice de busqueda llamando a indexar-busqueda.php UNA VEZ POR
# PROCESO, tantas tandas como haga falta.
#
# LA CAUSA REAL DEL "Duplicate entry", que no era la que yo crei
#
# La indexacion reventaba asi:
#
#   SQLSTATE[23000]: Duplicate entry '19343-es-node_search' for key 'PRIMARY'
#
# Mi primera explicacion fue que llamar a updateIndex() en bucle dentro del
# mismo proceso rompia el seguimiento. REESCRIBI el script para hacer una tanda
# por proceso y FALLO IGUAL, con otro nodo. Asi que la explicacion era falsa.
#
# La causa es que habia DOS indexadores a la vez:
#
#   CONFIRMADO: el modulo automated_cron esta activo, con intervalo de 3 horas
#   CONFIRMADO: system.cron_last marca 1969-12-31, es decir NUNCA se ejecuto
#
# Con el cron vencido desde siempre, CUALQUIER peticion al sitio lo dispara, y
# el cron de Drupal indexa la busqueda. Mis propias pruebas de humo con curl lo
# estaban lanzando mientras el script indexaba, y los dos intentaban insertar
# el mismo nodo en search_dataset.
#
# Por eso este guion DESACTIVA automated_cron mientras indexa y lo RESTAURA al
# terminar, incluso si se corta: el trap se encarga.
#
# Se mantiene la tanda por proceso de todos modos, porque mantiene la memoria
# plana con 36 800 nodos.
#
# MEDIDO: ~72 nodos por minuto en este entorno local, asi que los 36 800 son
# varias HORAS. El cuello de botella es que cada nodo se RENDERIZA para
# indexarlo y los archivos estan en OneDrive. En un servidor real es mucho mas
# rapido, y luego el cron normal mantiene el indice al dia por si solo.
#
# REANUDABLE: si se corta, se vuelve a lanzar y sigue donde iba.
# SOLO ANADE al indice: no modifica ningun contenido.
#
# Uso:
#   bash tools/indexar-busqueda.sh [tamano_tanda] [max_tandas]

set -u

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
DRUPAL="$RAIZ/plantilla_drupal/Drudg10.6.9"
TANDA="${1:-500}"
MAX="${2:-200}"
BITACORA="$RAIZ/work/indexacion-busqueda.txt"

mkdir -p "$RAIZ/work"
cd "$DRUPAL" || exit 1

DRUSH=(php -d extension=gd -d memory_limit=1024M
       "$DRUPAL/vendor/drush/drush/drush.php")

{
  echo "========================================================================"
  echo "Indexacion de busqueda"
  echo "Inicio: $(date '+%Y-%m-%d %H:%M:%S')   tanda=$TANDA   max=$MAX"
  echo "========================================================================"
} | tee -a "$BITACORA"

# Intervalo original de automated_cron, para devolverlo tal cual.
INTERVALO=$("${DRUSH[@]}" config:get automated_cron.settings interval --format=string </dev/null 2>/dev/null | tr -d '
 ')
if ! echo "${INTERVALO}" | grep -qE '^[0-9]+$'; then
  INTERVALO=10800
fi

restaurar_cron() {
  "${DRUSH[@]}" config:set automated_cron.settings interval "$INTERVALO" -y </dev/null >/dev/null 2>&1
  echo "automated_cron restaurado a ${INTERVALO}s" | tee -a "$BITACORA"
}
# El trap lo devuelve incluso si el guion se corta por Ctrl+C o por un kill.
trap restaurar_cron EXIT

echo "automated_cron: ${INTERVALO}s -> 0 (desactivado mientras se indexa)" | tee -a "$BITACORA"
"${DRUSH[@]}" config:set automated_cron.settings interval 0 -y </dev/null >/dev/null 2>&1

inicio=$(date +%s)
previo=-1

for ((i = 1; i <= MAX; i++)); do
  salida=$("${DRUSH[@]}" php:script "$RAIZ/tools/indexar-busqueda.php" -- "$TANDA" </dev/null 2>&1 | tr -d '\r')
  quedan=$(echo "$salida" | grep -oE 'QUEDAN:[0-9]+' | grep -oE '[0-9]+')
  quedan="${quedan:-}"

  if [ -z "$quedan" ]; then
    {
      echo "Tanda $i: SIN RESPUESTA. Ultimas lineas:"
      echo "$salida" | tail -4
    } | tee -a "$BITACORA"
    break
  fi

  printf 'Tanda %-4d  quedan %7d  %6ds\n' "$i" "$quedan" "$(( $(date +%s) - inicio ))" \
    | tee -a "$BITACORA"

  if [ "$quedan" -eq 0 ]; then
    echo "COMPLETO" | tee -a "$BITACORA"
    break
  fi

  # Si una tanda no avanza, algo impide indexar y seguir seria un bucle.
  if [ "$previo" -ne -1 ] && [ "$quedan" -ge "$previo" ]; then
    {
      echo "DETENIDO: la tanda $i no avanzo ($previo -> $quedan)."
      echo "Revisar el log de Drupal: drush watchdog:show --severity=Error"
    } | tee -a "$BITACORA"
    break
  fi
  previo="$quedan"
done

{
  echo ""
  echo "Fin: $(date '+%Y-%m-%d %H:%M:%S')   $(( $(date +%s) - inicio ))s"
  echo "========================================================================"
} | tee -a "$BITACORA"
