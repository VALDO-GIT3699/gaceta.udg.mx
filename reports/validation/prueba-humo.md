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
Fecha: 2026-10-02 03:37:59
Base:  http://127.0.0.1:8093
========================================================================

1. RUTAS ESTRUCTURALES
  OK    200 30.234118s  portada                    /
  OK    200 1.165632s  listado de noticias        /noticias
  OK    200 0.800043s  agenda                     /agenda
  OK    200 1.321325s  formulario contacto        /form<ruta-de-articulo>
  OK    200 0.457410s  formulario colaborador     /form<ruta-de-articulo>
  OK    200 0.386516s  formulario boletin         /form<ruta-de-articulo>
  OK    404 0.458765s  404 correcto               <ruta-de-articulo>

2. ARTICULOS MIGRADOS (muestra aleatoria)
  OK    200 0.695006s  articulo                   <ruta-de-articulo>
  OK    200 0.611171s  articulo                   <ruta-de-articulo>
  OK    200 0.312900s  articulo                   /Curso:-periodismo-emprendedor
  OK    200 0.451346s  articulo                   <ruta-de-articulo>
  OK    200 0.283130s  articulo                   <ruta-de-articulo>
  OK    200 0.400872s  articulo                   <ruta-de-articulo>
  OK    200 0.374515s  articulo                   <ruta-de-articulo>:-¿privados-o-estatales?
  OK    200 0.354908s  articulo                   <ruta-de-articulo>

3. PAGINAS DE SECCION Y CATEGORIA
  OK    200 1.028023s  termino 9120               /taxonomy/term/9120
  OK    200 0.475310s  termino 9134               /taxonomy/term/9134
  OK    200 0.435763s  termino 8196               /taxonomy/term/8196
  OK    200 0.306423s  termino 8111               /taxonomy/term/8111

4. REDIRECCIONES 301 DE URLS HISTORICAS
  OK    301 0.137316s  redireccion                <ruta-de-articulo>:-necesidad-de-estudios-de-nuevo-enfoque
  OK    301 0.084367s  redireccion                /Literalmente-“vacas-flacas”
  OK    301 0.130798s  redireccion                <ruta-de-articulo>
  OK    301 0.089073s  redireccion                /Ayuquila:-limpieza-a-mediano-plazo
  OK    301 0.085754s  redireccion                <ruta-de-articulo>

5. ACCESIBILIDAD (§12, B-04)
  OK    presente              accesibilityUdg.js
  OK    presente              Sepia
  OK    presente              Grises
  OK    presente              skip-link
  OK    presente              visually-hidden

6. RENDIMIENTO SOBRE PAGINA NO CACHEADA
   (medir solo la portada da una lectura falsa: la sirve la cache)
  en frio     5.381712s
  en caliente 0.037091s

========================================================================
RESULTADO: 29 comprobaciones OK, 0 FALLOS
PASS
========================================================================
```
