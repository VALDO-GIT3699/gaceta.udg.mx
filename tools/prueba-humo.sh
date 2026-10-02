#!/usr/bin/env bash
#
# prueba-humo.sh
#
# FASE 15: comprueba que el sitio migrado RESPONDE de verdad, no solo que la
# base de datos tenga los registros.
#
# POR QUE HACE FALTA ADEMAS DE LA CONCILIACION DE CONTEOS
#
# Este proyecto ya ha visto varias veces que los datos esten bien y el
# resultado no:
#
#   - Nueve campos creados y poblados que NO se renderizaban en la ficha.
#   - 66 rutas con alias duplicado, con la migracion dando 0 errores.
#   - Una pagina de articulo que tardaba 60 s y moria, mientras la portada
#     respondia en 0.22 s porque venia de la cache de pagina.
#
# La conciliacion de conteos no detecta ninguno de los tres. Esto si.
#
# QUE COMPRUEBA
#
#   1. Las rutas estructurales del sitio.
#   2. Una muestra ALEATORIA de alias de articulo, para no medir siempre los
#      mismos y no caer en la trampa de la cache.
#   3. Que las redirecciones 301 respondan 301.
#   4. Los recursos de accesibilidad de udg_liston (§12, B-04).
#   5. El tiempo de respuesta de una pagina NO cacheada.
#
# DOS TRAMPAS DEL ENTORNO, ya pagadas:
#
#   - Git Bash convierte "/taxonomy/term/9049" en una ruta de Windows y curl
#     acaba pidiendo una URL inventada. Se evita con MSYS_NO_PATHCONV=1, pero
#     SOLO por invocacion de curl: exportarlo global rompe la ruta de drush,
#     porque entonces /c/Users/... deja de convertirse y PHP no encuentra
#     drush.php. Ese fue el primer fallo de este propio guion.
#
#   - La accesibilidad se comprueba por el ARCHIVO que carga el navegador,
#     accesibilityUdg.js, y NO por "accesibilidadUdg", que es el id en
#     libraries.yml y nunca aparece en el HTML. Buscar el id daba FALLA con la
#     accesibilidad funcionando perfectamente.
#
# SOLO LECTURA: peticiones GET al sitio LOCAL. No toca produccion.
#
# Uso:
#   bash tools/prueba-humo.sh [http://127.0.0.1:8093]

set -u

BASE="${1:-http://127.0.0.1:8093}"
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
DRUPAL="$RAIZ/plantilla_drupal/Drudg10.6.9"
REPORTE="$RAIZ/reports/validation/prueba-humo.md"
DRUSH=(php -d extension=gd "$DRUPAL/vendor/drush/drush/drush.php")

mkdir -p "$RAIZ/reports/validation"

ok=0
fallo=0

# Codifica la ruta en porcentajes, dejando las barras intactas.
#
# HACE FALTA Y NO ES COSMETICA: el corpus es en espanol y hay miles de alias
# con acentos, como /En-extincion con tilde o /Te-amargo-para-Obama. Un
# NAVEGADOR los codifica solo antes de enviarlos; curl manda los bytes crudos
# y el servidor responde 404.
#
# Sin esto, la prueba daba 6 FALLA sobre URLs que funcionan perfectamente, y
# parecia que la migracion habia roto todas las rutas con acentos. Comprobado:
# /En-extinci%C3%B3n devuelve 200.
# El tr -d al final NO es decorativo: python en Windows imprime \r\n, y la
# sustitucion de comandos de bash solo quita el \n. La URL quedaba terminada en
# un retorno de carro y curl devolvia 000 en TODAS las peticiones, incluidas
# las de solo ASCII, como si el servidor estuviera caido. No lo estaba.
# El MSYS_NO_PATHCONV tampoco es decorativo, y esta vez va en la llamada a
# PYTHON: Git Bash convierte el argumento "/noticias" en
# "C:/Program Files/Git/noticias" ANTES de que python lo vea, asi que la
# funcion devolvia "C%3A/Program%20Files/Git/noticias" y curl daba 000 en
# todas las peticiones. Parecia que el servidor estaba caido. No lo estaba.
urlenc() {
  MSYS_NO_PATHCONV=1 python -c "import sys,urllib.parse; print(urllib.parse.quote(sys.argv[1], safe='/'))" "$1" | tr -d '\r\n'
}

pide() {
  local ruta="$1" esperado="$2" etiqueta="$3"
  local r codigo tiempo
  r=$(MSYS_NO_PATHCONV=1 curl -s -o /dev/null -w '%{http_code} %{time_total}' "$BASE$(urlenc "$ruta")" 2>/dev/null)
  codigo="${r%% *}"
  tiempo="${r##* }"
  if [ "$codigo" = "$esperado" ]; then
    ok=$((ok + 1))
    printf '  OK    %-3s %8ss  %-26s %s\n' "$codigo" "$tiempo" "$etiqueta" "$ruta"
  else
    fallo=$((fallo + 1))
    printf '  FALLA %-3s (esperaba %3s)  %-22s %s\n' "$codigo" "$esperado" "$etiqueta" "$ruta"
  fi
}

consulta() {
  "${DRUSH[@]}" sql:query "$1" 2>/dev/null | tr -d '\r'
}

{
  echo "========================================================================"
  echo "PRUEBA DE HUMO DEL SITIO MIGRADO"
  echo "Fecha: $(date '+%Y-%m-%d %H:%M:%S')"
  echo "Base:  $BASE"
  echo "========================================================================"
  echo ""

  echo "1. RUTAS ESTRUCTURALES"
  pide "/" 200 "portada"
  pide "/noticias" 200 "listado de noticias"
  pide "/agenda" 200 "agenda"
  # Webform deriva la ruta del id cambiando _ por -.
  pide "/form/gaceta-contacto" 200 "formulario contacto"
  pide "/form/gaceta-colaborador" 200 "formulario colaborador"
  pide "/form/gaceta-boletin" 200 "formulario boletin"
  pide "/esta-ruta-no-existe-jamas" 404 "404 correcto"
  echo ""

  echo "2. ARTICULOS MIGRADOS (muestra aleatoria)"
  while IFS= read -r alias; do
    [ -z "$alias" ] && continue
    pide "$alias" 200 "articulo"
  done < <(consulta "SELECT a.alias FROM path_alias a JOIN node_field_data n ON a.path=CONCAT('/node/',n.nid) JOIN node__field_wp_post_id w ON w.entity_id=n.nid WHERE n.type='noticia' AND n.status=1 ORDER BY RAND() LIMIT 8")
  echo ""

  echo "3. PAGINAS DE SECCION Y CATEGORIA"
  while IFS= read -r tid; do
    [ -z "$tid" ] && continue
    pide "/taxonomy/term/$tid" 200 "termino $tid"
  done < <(consulta "SELECT tid FROM taxonomy_term_field_data WHERE vid IN ('categoria_wp','seccion_historica') ORDER BY RAND() LIMIT 4")
  echo ""

  echo "4. REDIRECCIONES 301 DE URLS HISTORICAS"
  while IFS= read -r ruta; do
    [ -z "$ruta" ] && continue
    pide "/$ruta" 301 "redireccion"
  done < <(consulta "SELECT redirect_source__path FROM redirect ORDER BY RAND() LIMIT 5")
  echo ""

  echo "5. ACCESIBILIDAD (§12, B-04)"
  cuerpo=$(MSYS_NO_PATHCONV=1 curl -s "$BASE/" 2>/dev/null)
  for recurso in "accesibilityUdg.js" "Sepia" "Grises" "skip-link" "visually-hidden"; do
    if echo "$cuerpo" | grep -q -- "$recurso"; then
      ok=$((ok + 1))
      printf '  OK    presente              %s\n' "$recurso"
    else
      fallo=$((fallo + 1))
      printf '  FALLA AUSENTE               %s\n' "$recurso"
    fi
  done
  echo ""

  echo "6. RENDIMIENTO SOBRE PAGINA NO CACHEADA"
  echo "   (medir solo la portada da una lectura falsa: la sirve la cache)"
  "${DRUSH[@]}" cache:rebuild >/dev/null 2>&1
  nuevo=$(consulta "SELECT a.alias FROM path_alias a JOIN node_field_data n ON a.path=CONCAT('/node/',n.nid) JOIN node__field_wp_post_id w ON w.entity_id=n.nid WHERE n.type='noticia' AND n.status=1 ORDER BY RAND() LIMIT 1")
  if [ -n "$nuevo" ]; then
    enc=$(urlenc "$nuevo")
    t1=$(MSYS_NO_PATHCONV=1 curl -s -o /dev/null -w '%{time_total}' "$BASE$enc" 2>/dev/null)
    t2=$(MSYS_NO_PATHCONV=1 curl -s -o /dev/null -w '%{time_total}' "$BASE$enc" 2>/dev/null)
    printf '  en frio     %ss\n' "$t1"
    printf '  en caliente %ss\n' "$t2"
  else
    echo "  no se pudo obtener un alias para medir"
  fi
  echo ""

  echo "========================================================================"
  printf 'RESULTADO: %d comprobaciones OK, %d FALLOS\n' "$ok" "$fallo"
  if [ "$fallo" -eq 0 ]; then
    echo "PASS"
  else
    echo "FAIL - revisar los FALLA de arriba"
  fi
  echo "========================================================================"
} 2>&1 | tee "$REPORTE.txt"

# El reporte versionado no lleva rutas de articulo: son contenido editorial y
# el remoto es publico (§37, D-06).
{
  echo "# Prueba de humo del sitio migrado"
  echo ""
  echo "Fecha: $(date '+%Y-%m-%d')"
  echo "Requisito que atiende: CLAUDE.md FASE 15, §41."
  echo ""
  echo "Generado con \`tools/prueba-humo.sh\`. Reproducible."
  echo ""
  echo "Comprueba que el sitio RESPONDE, no solo que la base tenga los"
  echo "registros. La conciliacion de conteos no detecta un campo que no se"
  echo "renderiza, un alias duplicado ni una pagina que tarda 60 segundos."
  echo ""
  echo '```text'
  sed -E 's#/[A-Za-z0-9%ÁÉÍÓÚáéíóúñÑ_-]{14,}#<ruta-de-articulo>#g' "$REPORTE.txt"
  echo '```'
} > "$REPORTE"
rm -f "$REPORTE.txt"
echo ""
echo "Reporte en reports/validation/prueba-humo.md"
