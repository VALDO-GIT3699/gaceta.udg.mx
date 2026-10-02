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
Fecha: 2026-10-02 15:04:40
Base:  http://127.0.0.1:8093
========================================================================

1. RUTAS ESTRUCTURALES
  OK    200 14.513319s  portada                    /
  OK    200 3.012944s  listado de noticias        /noticias
  OK    200 2.289442s  agenda                     /agenda
  OK    200 2.601732s  formulario contacto        /form<ruta-de-articulo>
  OK    200 1.192654s  formulario colaborador     /form<ruta-de-articulo>
  OK    200 1.212888s  formulario boletin         /form<ruta-de-articulo>
  OK    404 0.959697s  404 correcto               <ruta-de-articulo>

2. ARTICULOS MIGRADOS (muestra aleatoria)
  OK    200 1.862958s  articulo                   <ruta-de-articulo>
  OK    200 1.410825s  articulo                   <ruta-de-articulo>
  OK    200 0.892324s  articulo                   <ruta-de-articulo>
  OK    200 0.956381s  articulo                   <ruta-de-articulo>
  OK    200 0.902565s  articulo                   <ruta-de-articulo>
  OK    200 0.862090s  articulo                   <ruta-de-articulo>
  OK    200 0.931485s  articulo                   <ruta-de-articulo>
  OK    200 0.884518s  articulo                   <ruta-de-articulo>;boleto-de-orordquo;

3. PAGINAS DE SECCION Y CATEGORIA
  OK    200 2.626027s  termino 8027               /taxonomy/term/8027
  OK    200 1.671301s  termino 9120               /taxonomy/term/9120
  OK    200 1.156941s  termino 8239               /taxonomy/term/8239
  OK    200 1.185497s  termino 9121               /taxonomy/term/9121

4. REDIRECCIONES 301 DE URLS HISTORICAS
  OK    301 0.487771s  redireccion                <ruta-de-articulo><ruta-de-articulo>!!!
  OK    301 0.287624s  redireccion                <ruta-de-articulo>
  OK    301 0.291899s  redireccion                <ruta-de-articulo>
  OK    301 0.298702s  redireccion                <ruta-de-articulo>
  OK    301 0.241570s  redireccion                <ruta-de-articulo><ruta-de-articulo>

5. ACCESIBILIDAD (§12, B-04)
  OK    presente              accesibilityUdg.js
  OK    presente              Sepia
  OK    presente              Grises
  OK    presente              skip-link
  OK    presente              visually-hidden

6. RENDIMIENTO SOBRE PAGINA NO CACHEADA
   (medir solo la portada da una lectura falsa: la sirve la cache)
  en frio     60.149851s
  en caliente 37.135053s

========================================================================
RESULTADO: 29 comprobaciones OK, 0 FALLOS
PASS
========================================================================
```
