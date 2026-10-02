#!/usr/bin/env bash
#
# reparar-secciones.sh
#
# Rehace las noticias que tienen seccion o subseccion en el origen pero no la
# tienen en Drupal. La lista la genera tools/reparar-secciones.php.
#
# POR QUE ROLLBACK + IMPORT Y NO --update
#
# --update deja el alias ANTIGUO en path_alias ademas del nuevo, porque Drupal
# inserta una fila en lugar de actualizarla. rollback borra el nodo con sus
# alias y import lo crea limpio. Verificado: una pasada --update sobre 8 305
# nodos dejo 13 749 filas de alias sobrantes.
#
# Se trocea en grupos de 300 porque --idlist con 1 995 valores se pasa de la
# longitud que admite la linea de comandos en Windows.
#
# REVERSIBLE: cada paso es una operacion de Migrate.
#
# Uso:
#   bash tools/reparar-secciones.sh

set -u

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
DRUPAL="$RAIZ/plantilla_drupal/Drudg10.6.9"
LISTA="$RAIZ/work/reparar-metatags-idlist.txt"
BITACORA="$RAIZ/work/reparacion-metatags.txt"

if [ ! -f "$LISTA" ]; then
  echo "No existe $LISTA"
  echo "Generalo primero: drush php:script tools/reparar-secciones.php"
  exit 1
fi

DRUSH=(php -d extension=gd -d opcache.enable_cli=1 -d memory_limit=512M
       "$DRUPAL/vendor/drush/drush/drush.php")

cd "$DRUPAL" || exit 1
"${DRUSH[@]}" migrate:reset-status gaceta_noticia >/dev/null 2>&1

{
  echo "========================================================================"
  echo "Reparacion de metadatos SEO"
  echo "Inicio: $(date '+%Y-%m-%d %H:%M:%S')"
  echo "========================================================================"
} | tee -a "$BITACORA"

i=0
total=0
# OJO: se lee por el descriptor 3 y no por stdin, y cada drush lleva
# </dev/null. Sin eso, drush CONSUME el stdin del bucle y se come las lineas
# que quedaban: la primera version reparo 300 ids de 1 995 y termino como si
# hubiera acabado, con un solo trozo de siete.
while IFS= read -r -u 3 idlist; do
  [ -z "$idlist" ] && continue
  i=$((i + 1))
  n=$(echo "$idlist" | tr ',' '\n' | grep -c .)

  "${DRUSH[@]}" migrate:rollback gaceta_noticia --idlist="$idlist" </dev/null >/dev/null 2>&1
  salida=$("${DRUSH[@]}" migrate:import gaceta_noticia --idlist="$idlist" </dev/null 2>&1 | tr -d '\r')
  resumen=$(echo "$salida" | grep -oE 'Processed [0-9]+ items \([^)]*\)' | tail -1)
  fallidos=$(echo "$resumen" | grep -oE '[0-9]+ failed' | grep -oE '[0-9]+')
  fallidos="${fallidos:-0}"
  total=$((total + n))

  printf 'Trozo %-3d  %4d ids  %s\n' "$i" "$n" "${resumen:-SIN RESUMEN}" \
    | tee -a "$BITACORA"

  if [ "$fallidos" -gt 0 ]; then
    echo "DETENIDO: el trozo $i reporta $fallidos fallos." | tee -a "$BITACORA"
    exit 2
  fi
done 3< "$LISTA"

{
  echo ""
  echo "Fin: $(date '+%Y-%m-%d %H:%M:%S')   $total ids rehechos en $i trozos"
  echo "========================================================================"
} | tee -a "$BITACORA"
