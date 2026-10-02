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
Fecha: 2026-10-02 01:18:26
Base:  http://127.0.0.1:8093
========================================================================

1. RUTAS ESTRUCTURALES
  OK    200 8.029263s  portada                    /
  OK    200 1.301911s  listado de noticias        /noticias
  OK    200 0.831408s  agenda                     /agenda
  OK    200 0.700289s  formulario contacto        /form<ruta-de-articulo>
  OK    200 0.357728s  formulario colaborador     /form<ruta-de-articulo>
  OK    200 0.398445s  formulario boletin         /form<ruta-de-articulo>
  OK    404 0.432242s  404 correcto               <ruta-de-articulo>

2. ARTICULOS MIGRADOS (muestra aleatoria)
  OK    200 0.470277s  articulo                   <ruta-de-articulo>
  OK    200 0.578041s  articulo                   <ruta-de-articulo>
  OK    200 0.308488s  articulo                   <ruta-de-articulo>
  OK    200 0.408768s  articulo                   <ruta-de-articulo>
  OK    200 0.314082s  articulo                   <ruta-de-articulo>
  OK    200 0.382530s  articulo                   <ruta-de-articulo>
  OK    200 0.381962s  articulo                   <ruta-de-articulo>
  OK    200 0.361312s  articulo                   <ruta-de-articulo>

3. PAGINAS DE SECCION Y CATEGORIA
  OK    200 0.532436s  termino 8241               /taxonomy/term/8241
  OK    200 0.502023s  termino 8362               /taxonomy/term/8362
  OK    200 0.334965s  termino 9152               /taxonomy/term/9152
  OK    200 0.383592s  termino 8094               /taxonomy/term/8094

4. REDIRECCIONES 301 DE URLS HISTORICAS
  OK    301 0.167124s  redireccion                /Feminicidios:-asignatura-pendiente
  OK    301 0.080710s  redireccion                <ruta-de-articulo>
  OK    301 0.106346s  redireccion                <ruta-de-articulo>/-DYAD-1909
  OK    301 0.125516s  redireccion                /Un-día-de-“trampa”-en-Guadalajara
  OK    301 0.080827s  redireccion                <ruta-de-articulo>:-¡Arrrrroz!

5. ACCESIBILIDAD (§12, B-04)
  OK    presente              accesibilityUdg.js
  OK    presente              Sepia
  OK    presente              Grises
  OK    presente              skip-link
  OK    presente              visually-hidden

6. RENDIMIENTO SOBRE PAGINA NO CACHEADA
   (medir solo la portada da una lectura falsa: la sirve la cache)
  en frio     5.012186s
  en caliente 0.039811s

========================================================================
RESULTADO: 29 comprobaciones OK, 0 FALLOS
PASS
========================================================================
```
