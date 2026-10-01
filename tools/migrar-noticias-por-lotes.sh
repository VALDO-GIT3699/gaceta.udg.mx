#!/usr/bin/env bash
#
# migrar-noticias-por-lotes.sh
#
# Ejecuta la FASE 9: migra las 36 666 noticias de Gaceta por lotes.
#
# POR QUE POR LOTES Y NO DE UNA VEZ
#
# CLAUDE.md FASE 9 lo exige: cada lote debe producir sus cifras de
# source/created/updated/failed/skipped, y la ejecucion debe DETENERSE si
# failed_count > 0 sin explicar y aprobar el tratamiento. Un unico
# migrate:import de 36 666 registros no deja ese rastro: da una sola linea al
# final, y si falla a mitad no se sabe donde.
#
# Medido: un lote de 100 en frio tarda 55 s, pero ~15 de esos son el arranque de
# Drupal y el resto es cache fria. En caliente son ~70 s por cada 1000, asi que
# el corpus completo sale en unos 45 minutos. La primera estimacion de 4 horas
# salio de extrapolar la medicion en frio: una lectura falsa.
#
# QUE HACE EXACTAMENTE
#
#   1. Llama a migrate:import --limit=$LOTE tantas veces como haga falta.
#   2. Anota en la bitacora las cifras de cada lote.
#   3. SE DETIENE si un lote reporta failed > 0, o si no progresa.
#
# Migrate salta los registros que ya estan en su tabla de mapa, asi que el
# script es REANUDABLE: si se corta, se vuelve a lanzar y continua donde iba.
# Y es REVERSIBLE: migrate:rollback gaceta_noticia deshace todo.
#
# El orden es cronologico, porque GacetaNoticia::query() ordena por post_date
# ascendente. Eso hace los lotes predecibles y repetibles.
#
# Uso:
#   bash tools/migrar-noticias-por-lotes.sh [tamano_lote] [max_lotes]

set -u

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
DRUPAL="$RAIZ/plantilla_drupal/Drudg10.6.9"
LOTE="${1:-1000}"
MAX="${2:-60}"
BITACORA="$RAIZ/work/fase9-bitacora.txt"

mkdir -p "$RAIZ/work"

# GD por proceso (D-09): el php.ini del equipo es compartido con otro proyecto
# y no se modifica. opcache en CLI para no reparsear Drupal en cada lote.
DRUSH=(php -d extension=gd -d opcache.enable_cli=1 -d memory_limit=512M
       "$DRUPAL/vendor/drush/drush/drush.php")

cd "$DRUPAL" || exit 1

# Si una ejecucion anterior se corto a mitad, Migrate deja la migracion marcada
# como "Importing" y cualquier import o rollback posterior falla con "esta
# ocupada con otra operacion". Reponerlo es inofensivo cuando ya esta en Idle.
#
# LECCION APRENDIDA: al matar este script, el proceso hijo de drush NO muere
# con el. Siguio importando varios minutos despues de detener el guion, y dejo
# el estado bloqueado. Si hay que pararlo de verdad, matar tambien el php.exe
# que ejecuta drush.
"${DRUSH[@]}" migrate:reset-status gaceta_noticia >/dev/null 2>&1

{
  echo "========================================================================"
  echo "FASE 9 - Migracion de contenido por lotes"
  echo "Inicio:       $(date '+%Y-%m-%d %H:%M:%S')"
  echo "Tamano lote:  $LOTE"
  echo "Maximo lotes: $MAX"
  echo "========================================================================"
} | tee -a "$BITACORA"

# Cifras de partida, para poder conciliar al final.
inicial=$("${DRUSH[@]}" sql:query "SELECT COUNT(*) FROM migrate_map_gaceta_noticia" 2>/dev/null | tr -d '\r')
echo "Ya migrados antes de empezar: ${inicial:-0}" | tee -a "$BITACORA"
echo "" | tee -a "$BITACORA"

previo="${inicial:-0}"

for ((i = 1; i <= MAX; i++)); do
  t0=$(date +%s)
  salida=$("${DRUSH[@]}" migrate:import gaceta_noticia --limit="$LOTE" 2>&1 | tr -d '\r')
  t1=$(date +%s)

  # La linea de resumen de drush: "Processed N items (A created, B updated,
  # C failed, D ignored)".
  resumen=$(echo "$salida" | grep -oE 'Processed [0-9]+ items \([^)]*\)' | tail -1)
  fallidos=$(echo "$resumen" | grep -oE '[0-9]+ failed' | grep -oE '[0-9]+')
  fallidos="${fallidos:-0}"

  actual=$("${DRUSH[@]}" sql:query "SELECT COUNT(*) FROM migrate_map_gaceta_noticia" 2>/dev/null | tr -d '\r')
  actual="${actual:-$previo}"
  avance=$((actual - previo))

  printf 'Lote %-3d  %4ds  +%-5d  total %-6d  %s\n' \
    "$i" "$((t1 - t0))" "$avance" "$actual" "${resumen:-SIN RESUMEN}" \
    | tee -a "$BITACORA"

  if [ "$fallidos" -gt 0 ]; then
    {
      echo ""
      echo "DETENIDO: el lote $i reporta $fallidos fallos."
      echo "CLAUDE.md FASE 9 prohibe continuar sin explicar y aprobar el"
      echo "tratamiento. Revisar con: drush migrate:messages gaceta_noticia"
    } | tee -a "$BITACORA"
    exit 2
  fi

  if [ "$avance" -eq 0 ]; then
    {
      echo ""
      echo "FIN: el lote $i no migro ningun registro nuevo."
      echo "No quedan registros sin procesar, o algo impide el avance."
    } | tee -a "$BITACORA"
    break
  fi

  previo="$actual"
done

{
  echo ""
  echo "========================================================================"
  echo "Fin: $(date '+%Y-%m-%d %H:%M:%S')"
  echo "Migrados en esta ejecucion: $((previo - ${inicial:-0}))"
  echo "Total en el mapa:           $previo"
  echo "========================================================================"
} | tee -a "$BITACORA"
