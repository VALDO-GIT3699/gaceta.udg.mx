# Prueba de humo del sitio migrado

Fecha: 2026-10-02
Requisito que atiende: CLAUDE.md FASE 15, §41.

Generado con `tools/prueba-humo.sh`. Reproducible.

Comprueba que el sitio RESPONDE, no solo que la base tenga los
registros. La conciliacion de conteos no detecta un campo que no se
renderiza, un alias duplicado ni una pagina que tarda 60 segundos.

```text
========================================================================
PRUEBA DE HUMO DEL SITIO MIGRADO
Fecha: 2026-10-02 06:50:33
Base:  http://127.0.0.1:8093
========================================================================

1. RUTAS ESTRUCTURALES
  OK    200 0.062663s  portada                    /
  OK    200 1.225752s  listado de noticias        /noticias
  OK    200 0.845259s  agenda                     /agenda
  OK    200 0.742119s  formulario contacto        /form<ruta-de-articulo>
  OK    200 0.385980s  formulario colaborador     /form<ruta-de-articulo>
  OK    200 0.429518s  formulario boletin         /form<ruta-de-articulo>
  OK    404 0.499130s  404 correcto               <ruta-de-articulo>

2. ARTICULOS MIGRADOS (muestra aleatoria)
  OK    200 0.731182s  articulo                   /seraphine-2
  OK    200 0.590493s  articulo                   <ruta-de-articulo>
  OK    200 0.298808s  articulo                   <ruta-de-articulo>
  OK    200 0.389988s  articulo                   <ruta-de-articulo>
  OK    200 0.316327s  articulo                   /THE-WHO-<ruta-de-articulo>
  OK    200 0.393611s  articulo                   /ninth
  OK    200 0.297730s  articulo                   <ruta-de-articulo>
  OK    200 0.383682s  articulo                   <ruta-de-articulo>

3. PAGINAS DE SECCION Y CATEGORIA
  OK    200 0.807670s  termino 8320               /taxonomy/term/8320
  OK    200 0.429264s  termino 9068               /taxonomy/term/9068
  OK    200 0.434985s  termino 8070               /taxonomy/term/8070
  OK    200 0.370133s  termino 8247               /taxonomy/term/8247

4. REDIRECCIONES 301 DE URLS HISTORICAS
  OK    301 0.145134s  redireccion                <ruta-de-articulo>
  OK    301 0.110461s  redireccion                <ruta-de-articulo>
  OK    301 0.091994s  redireccion                <ruta-de-articulo>
  OK    301 0.086952s  redireccion                <ruta-de-articulo>;os-de-cultura-fisica
  OK    301 0.152670s  redireccion                <ruta-de-articulo>“La-muerte-de-un-huichol”

5. ACCESIBILIDAD (§12, B-04)
  OK    presente              accesibilityUdg.js
  OK    presente              Sepia
  OK    presente              Grises
  OK    presente              skip-link
  OK    presente              visually-hidden

6. RENDIMIENTO SOBRE PAGINA NO CACHEADA
   (medir solo la portada da una lectura falsa: la sirve la cache)
  en frio     5.641863s
  en caliente 0.030826s

========================================================================
RESULTADO: 29 comprobaciones OK, 0 FALLOS
PASS
========================================================================
```
