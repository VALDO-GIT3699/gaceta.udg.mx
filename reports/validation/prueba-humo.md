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
Fecha: 2026-10-02 02:21:14
Base:  http://127.0.0.1:8093
========================================================================

1. RUTAS ESTRUCTURALES
  OK    200 0.057947s  portada                    /
  OK    200 1.362460s  listado de noticias        /noticias
  OK    200 0.881704s  agenda                     /agenda
  OK    200 0.686359s  formulario contacto        /form<ruta-de-articulo>
  OK    200 0.450373s  formulario colaborador     /form<ruta-de-articulo>
  OK    200 0.615504s  formulario boletin         /form<ruta-de-articulo>
  OK    404 0.531345s  404 correcto               <ruta-de-articulo>

2. ARTICULOS MIGRADOS (muestra aleatoria)
  OK    200 0.839366s  articulo                   <ruta-de-articulo>
  OK    200 0.540844s  articulo                   <ruta-de-articulo>
  OK    200 0.415273s  articulo                   <ruta-de-articulo>
  OK    200 0.479653s  articulo                   <ruta-de-articulo>
  OK    200 0.365061s  articulo                   <ruta-de-articulo>
  OK    200 0.407131s  articulo                   <ruta-de-articulo>
  OK    200 0.335792s  articulo                   <ruta-de-articulo>
  OK    200 0.377265s  articulo                   <ruta-de-articulo>

3. PAGINAS DE SECCION Y CATEGORIA
  OK    200 0.797830s  termino 8172               /taxonomy/term/8172
  OK    200 0.477697s  termino 8228               /taxonomy/term/8228
  OK    200 0.577291s  termino 8372               /taxonomy/term/8372
  OK    200 0.436462s  termino 9058               /taxonomy/term/9058

4. REDIRECCIONES 301 DE URLS HISTORICAS
  OK    301 0.143314s  redireccion                /Internet:-caro-pero-necesario
  OK    301 0.089522s  redireccion                <ruta-de-articulo>
  OK    301 0.147932s  redireccion                /¡Sólo-muévete!
  OK    301 0.086106s  redireccion                /Agua:-principio-y-fin
  OK    301 0.176483s  redireccion                /Proyecto-“Sé-bicible”

5. ACCESIBILIDAD (§12, B-04)
  OK    presente              accesibilityUdg.js
  OK    presente              Sepia
  OK    presente              Grises
  OK    presente              skip-link
  OK    presente              visually-hidden

6. RENDIMIENTO SOBRE PAGINA NO CACHEADA
   (medir solo la portada da una lectura falsa: la sirve la cache)
  en frio     6.270043s
  en caliente 0.076868s

========================================================================
RESULTADO: 29 comprobaciones OK, 0 FALLOS
PASS
========================================================================
```
