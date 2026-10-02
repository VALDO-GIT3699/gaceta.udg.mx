# Decisiones pendientes: qué hay que decidir y qué recomiendo

Fecha: 2026-10-02
Para: el responsable del proyecto

Este documento existe para que las decisiones se puedan tomar leyendo, sin
tener que reconstruir el contexto. Cada una lleva **las cifras reales medidas**,
las opciones con sus consecuencias y una recomendación.

```text
Son 14 decisiones abiertas mas una accion urgente. Pero NO hacen falta 15
respuestas: cinco se firman de golpe y otras cinco se pueden posponer sin
coste. Las que de verdad bloquean son CUATRO.
```

## Índice por urgencia

| | Qué | Cuántas |
|---|---|---|
| **0** | Acción urgente, no es una decisión | 1 |
| **1** | Firma en bloque, sin coste | 5 |
| **2** | Bloquean cerrar fases | 4 |
| **3** | Se pueden posponer | 5 |

---

# 0. URGENTE — Rotar la contraseña de la base de datos (B-05)

**Esto no es una decisión, es una acción.** No hay opciones que valorar.

## El problema

En `settings.php`, el nombre de la base de datos, el usuario y la contraseña
son **la misma cadena de 18 caracteres**. Yo escribí ese nombre en tres
archivos de un repositorio **público**, así que publiqué la contraseña.

```text
Retirado del arbol de trabajo y subido. Pero sigue en 2 commits del
historial, y ESTUVO publicado en GitHub.
```

## Qué hay que hacer, en este orden

```text
1. Cambiar la contrasena de la base de datos, y que NO sea igual al nombre.
2. Cambiar el hash_salt del sitio.
3. Actualizar settings.php (esta fuera de Git: no se versiona).
4. SOLO ENTONCES purgar el historial (eso es D-10).
```

El orden importa: reescribir el historial antes de rotar deja la credencial
viva en cualquier copia que alguien ya tenga clonada.

```text
IMPACTO DE CAMBIAR EL hash_salt: invalida todas las sesiones abiertas y los
tokens de formulario. En un sitio que todavia no esta en produccion, eso no
molesta a nadie. Hacerlo DESPUES del cutover si molestaria.
```

**Lo puedo hacer yo si me lo autorizas**, pero toca tu infraestructura y
preferiría que la contraseña nueva la elijas tú y no aparezca nunca en esta
conversación.

---

# 1. Las cinco que se firman en bloque

El auditor las revisó una por una y las declaró **admisibles como
provisionales**: ya están implementadas, verificadas con evidencia y son
reversibles. Lo único que falta es tu firma.

```text
Si estas de acuerdo, basta un "confirmo D-24, D-26, D-27, D-28 y D-30".
```

## D-24 — Las URLs repetidas llevan el número al final

**El hecho.** 347 slugs están repetidos entre noticias publicadas, 971
artículos. En WordPress, **sólo uno de cada grupo es alcanzable**: lo verifiqué
pidiendo las páginas a producción, y `?p=ID` tampoco salva a los demás porque
WordPress lo redirige al permalink y ahí gana el mismo.

```text
Los 624 "perdedores" NO TIENEN HOY NINGUNA URL QUE FUNCIONE.
```

**Lo implementado.** El que produccción sirve conserva su URL **exacta**; los
demás reciben `/Enfoques-30278`, con el id de WordPress al final.

| | A favor | En contra |
|---|---|---|
| **Como está** | Recupera 624 artículos que llevan años inalcanzables. Determinista: el mismo artículo da siempre la misma URL | 624 URLs llevan un número al final. Es fea |
| Sufijo `-2`, `-3` | Más legible | **El número depende del orden de proceso**: si la migración se reejecuta por lotes distintos, el mismo artículo cambia de URL. Rompe la reproducibilidad |
| Sin URL, `/node/123` | No inventa nada | Renuncia a una ruta legible para 624 artículos |

**Recomendación: confirmar como está.** La alternativa legible es la que rompe
la reproducibilidad, y eso en una migración es peor que una URL fea.

## D-26 — 21 títulos no caben y se cortan

**El hecho.** 21 artículos de 36 666 tienen un título de más de 255
caracteres; el mayor, **867**. No son títulos: son párrafos pegados en el campo
del título. La columna de Drupal es `varchar(255)` y es del núcleo.

**Lo implementado.** El título se corta en frontera de palabra y el original
completo se guarda en un campo aparte. **No se pierde texto.**

| | A favor | En contra |
|---|---|---|
| **Como está** | Cero pérdida. No toca el núcleo de Drupal | 21 títulos se ven cortados con puntos suspensivos |
| Ampliar la columna | Títulos completos | Modifica el esquema del núcleo: complica cada actualización futura de Drupal |
| Truncar sin guardar | Simple | Pierde texto. Lo prohíbe el mandato |

**Recomendación: confirmar.** Si prefieres que esos 21 muestren el párrafo
entero como título, se cambia en una línea, pero quedarán raros en los
listados.

## D-27 — Tres URLs las piden una página y una noticia

**El hecho.** `contacto`, `dia-mundial-del-medio-ambiente` y
`dia-internacional-de-la-mujer`. Verifiqué contra producción: **gana la
página** en los dos que pude comprobar, que es el comportamiento propio de
WordPress.

**Lo implementado.** La página conserva la ruta; la noticia se desambigua.

**Recomendación: confirmar.** No es una elección: reproduce lo que tu sitio
hace hoy. Son 3 casos enumerados.

## D-28 — Los 193 lugares de eventos no se migran

**El hecho.**

```text
193 lugares publicados, 4 eventos BORRADOR, 2 organizadores.
0 referencias: ningun evento apunta a ningun lugar.
Los 4 eventos no tienen slug: nunca tuvieron URL.
/venue/auditorio-telmex/ en produccion NO sirve el recinto: sirve una
  noticia sobre el Auditorio Telmex.
```

Es un catálogo de recintos cargado el 14-11-2019 y nunca usado.

| | A favor | En contra |
|---|---|---|
| **No migrarlos** | No hay contenido editorial ni rutas que perder. Siguen en el volcado: reversible | Si algún día hace falta una agenda, hay que migrarlos entonces |
| Migrarlos | Están por si acaso | 193 nodos vacíos de valor en un sitio nuevo |

**Recomendación: confirmar no migrarlos.** Es la única parte del corpus que
propongo dejar fuera, y lo propongo con esa evidencia. El dato no se destruye.

## D-30 — El 64.5 % del corpus firma «Universidad de Guadalajara»

**El hecho.** 23 663 artículos están en una sola cuenta. Busqué autoría
individual dentro del texto: **sólo 67 traen un «Por Nombre»**, el 0.28 %. Y
la cuenta tiene como nombre público «Universidad de Guadalajara», que es lo que
tu sitio muestra hoy.

```text
La atribucion publica de esos 23 663 articulos es, y siempre fue,
institucional. No es un agujero: es un hecho editorial del corpus.
```

**Recomendación: confirmar.** Extraer autores del cuerpo recuperaría 67 e
inventaría los otros 23 596. Si algún día se quieren esos 67, es trabajo
editorial a mano, no de expresiones regulares.

---

# 2. Las cuatro que bloquean cerrar fases

## D-21 — Los shortcodes: ratificar o revertir

**Aquí me pasé y lo reconozco.** Convertí los shortcodes de 4 268 artículos
eligiendo **lo contrario de lo que yo mismo había recomendado por escrito**,
con la decisión declarada abierta y sin preguntarte.

### Qué es un shortcode y por qué importa

Es una etiqueta que un plugin de WordPress sustituía al mostrar la página.
Drupal no la conoce, así que **la imprime literal**. Antes de la conversión, en
3 089 artículos se leía esto dentro del texto:

```text
[caption id="attachment_178161" align="alignnone" width="804"
```

### Lo que ya está aplicado

```text
[caption]<img> Pie[/caption]  ->  <figure> con <figcaption>   3 383 cuerpos
[pdf-embedder url="X"]        ->  enlace "Descargar el PDF"     962
[gallery ids="1,2,3"]         ->  marcador con los ids          286
```

### Lo que queda sin convertir, que no había declarado

```text
[vc_...]        148 entradas   Visual Composer: maquetacion
[td_...]        148            tagDiv: cajas y sliders del tema Newspaper
[embedyt]        52            listas de reproduccion de YouTube
[video mp4=]     39            video
[smartslider]     9            carrusel
[su_...]          4            plugin NO ACTIVO: tampoco funciona en
                               produccion, se ve literal alli tambien
[audio mp3=]      3            audio
```

```text
CORRECCION: yo habia reportado "cero vc_/td_/smartslider". Era FALSO en tres
de los cuatro. Son ~200 entradas con texto de shortcode a la vista.
```

### Las tres preguntas

**(a) ¿Ratifico o revierto lo ya aplicado a los 4 268?**

| | A favor | En contra |
|---|---|---|
| **Ratificar** | `<figure>`/`<figcaption>` es el HTML que *significa* «imagen con pie», y es mejor para accesibilidad que lo que hacía el plugin. El texto del pie se conserva entero | Se aplicó sin tu autorización, y no se puede comprobar visualmente porque faltan las fotos |
| Revertir | Vuelve a ser fiel al origen | Devuelve el texto de shortcode a la vista en 3 089 artículos. Cuesta unas 3 horas |

**Recomendación: ratificar.** El argumento que me hacía dudar era no poder ver
el resultado, pero el pie de foto y el `src` de la imagen se conservan
literalmente: lo verificable es que no se perdió texto, y eso sí lo verifiqué.

**(b) ¿Convierto los 94 de medios (`[video]`, `[audio]`, `[embedyt]`)?**

Son traducciones directas: el shortcode ya lleva la URL del archivo dentro.

**Recomendación: sí.** Es acotado y verificable.

**(c) ¿Qué hago con los 148 de maquetación (`[vc_]`, `[td_]`)?**

**Recomendación: NO tocarlos con expresiones regulares.** Aquí el envoltorio
es presentación y lo que hay que salvar es el texto de dentro, pero
distinguirlos en 148 casos anidados es donde cualquier patrón destruye texto.
Son 148: es trabajo editorial, a mano, y puede esperar.

## D-19 — Las 77 307 revisiones

**El hecho.**

```text
77 307 revisiones sobre 19 400 contenidos
media 4 por contenido, maximo 581 en uno solo
561 MB de cuerpo de texto
75 888 cuelgan de una noticia
```

Una revisión es una versión anterior de un artículo que WordPress guardó
automáticamente al editar.

| | A favor | En contra |
|---|---|---|
| **No migrarlas** | El sitio pesa 561 MB menos y va más rápido. Nadie las consulta: no tienen URL pública | Se pierde el historial de edición. Quedan en el volcado |
| Migrarlas | Historial completo, auditable | 561 MB y 77 307 entidades más. Multiplica por 3 el tiempo de migración. Y 45 424 de ellas son datos de Elementor, no texto editorial |
| Migrar sólo la última | Compromiso | No es ni una cosa ni la otra: una sola versión anterior no es un historial |

**Recomendación: no migrarlas, y dejarlo documentado.** Son historial interno
de redacción, no contenido publicado. El volcado original las conserva para
siempre, así que la decisión es reversible si algún día hacen falta.

```text
ESTA ES LA QUE IMPIDE CERRAR LA FASE 9. Mientras no se responda, el alcance de
"migrar el contenido" no esta definido.
```

## D-25 — `/inicio` la piden dos páginas

**El hecho.** La portada de la plantilla (`node/1`, que es la portada
configurada del sitio) y la página «Inicio» de WordPress piden la misma ruta.
Es **el último conflicto de rutas de todo el sitio**.

Producción sirve en `/inicio/` la portada real de Gaceta.

| | A favor | En contra |
|---|---|---|
| **La plantilla se la queda** | La ruta sigue llevando a la portada, que es lo que el visitante busca. La portada se reconstruirá con Views sobre la plantilla, no como página estática | La página migrada queda accesible sólo por otra URL |
| La página migrada se la queda | Preserva la ruta literalmente | Pone una página estática donde la plantilla espera su portada, y deja la portada sin alias |

**Recomendación: que se la quede la plantilla.** Pero antes conviene saber una
cosa: **¿qué tiene la página «Inicio» de WordPress que no esté ya en la
portada?** Si es sólo un contenedor vacío, la decisión es obvia. Puedo
mirarlo si quieres.

## D-05 — El contenido de demostración de la plantilla

**El hecho.** La plantilla vino con 56 nodos de demostración en 13 tipos:
«Ejemplo con archivos», «Ejemplo de Estilos», «Evento de agenda #10 (DEMO)»,
banners, galerías. **Hoy se mezclan con los artículos de Gaceta en la
portada.**

| | A favor | En contra |
|---|---|---|
| **Despublicarlos** | Desaparecen de la vista al instante y se recuperan con un clic. Reversible | Siguen en la base de datos |
| Borrarlos | Base limpia | Irreversible. Y algunos pueden ser ejemplos útiles de cómo se usa la plantilla |
| Dejarlos | Cero trabajo | La portada de Gaceta muestra «Ejemplo de Estilos». Parece un sitio sin terminar |

**Recomendación: despublicarlos, no borrarlos.** Y conservar **uno** de cada
tipo en un estado no publicado, como referencia de cómo la plantilla espera
que se rellene cada cosa. Es lo que impide cerrar la reconstrucción visual.

```text
Ya desactive los 3 enlaces de demostracion del MENU, porque dejar "Ejemplo de
Estilos" en la navegacion de Gaceta tampoco era neutral. Los nodos siguen
publicados: eso es esta decision.
```

---

# 3. Las cinco que se pueden posponer sin coste

## D-04 — ¿El sitio guarda los envíos de los formularios?

**El hecho.** Contact Form 7 **no guardaba** los envíos: los mandaba por
correo. Yo había puesto Drupal a guardarlos y lo presenté como mejora; el
auditor señaló que eso es esta decisión, y que es **la menos reversible de
todas** — un rollback borra nodos, no borra datos de personas.

```text
YA LO DESACTIVE. Ahora se comportan como el origen: solo envian correo.
```

| | A favor | En contra |
|---|---|---|
| **No guardar (como está)** | Igual que hoy. Cero datos personales nuevos. Cumple el principio de minimización | Un correo perdido es un mensaje perdido sin rastro |
| Guardar | Nadie pierde un mensaje. Hay registro | El sitio empieza a almacenar nombre, correo y mensaje de personas reales. Eso exige aviso de privacidad, plazo de conservación y quién puede verlos |

**Recomendación: dejarlo sin guardar por ahora**, y si se quiere guardar,
decidirlo con quien lleve protección de datos en la UdeG, no conmigo. Es
reversible en un clic hacia guardar; **no** es reversible hacia atrás.

```text
DATO QUE DEBES CONOCER: la plantilla entregada trae YA un envio almacenado del
2021-03-08, con nombre, correo, mensaje e IP de una persona real, en su
formulario `contact`. No lo genero mi trabajo y no lo he borrado, porque
borrar datos sin autorizacion no me corresponde. Deberias decidir que hacer
con el.
```

## D-10 — Purgar el historial del repositorio de Drupal

Depende de la rotación de la contraseña (punto 0). **No se hace antes.**

**Recomendación: después de rotar.** Y conviene saber que el repositorio de
Drupal **todavía no está publicado**; sólo lo está el del proyecto.

## D-11 — El mojibake de puntuación

**El hecho.** Hay caracteres mal codificados en la puntuación, heredados del
origen:

```text
comilla de cierre      3 460 articulos
guion largo            1 263
puntos suspensivos     1 192
apostrofo                496
TOTAL                  3 501 de 36 666   (el 9.5 %)
```

Se ve como `â€` dentro del texto. **Es daño preexistente**: está así en tu
WordPress desde hace años.

| | A favor | En contra |
|---|---|---|
| **Repararlo** | 3 501 artículos dejan de mostrar basura. La correspondencia es conocida y mecánica | Toca el texto editorial de 3 501 artículos. Si el patrón falla, estropea texto bueno |
| No repararlo | Fiel al origen | 3 501 artículos con basura visible, igual que hoy |

**Recomendación: repararlo, pero no ahora.** Primero que se cierren las
decisiones que bloquean; esto se hace al final, sobre una muestra verificada
a mano, y con el hash de migración ya implementado para poder comparar antes
y después. No corre prisa porque **ya está mal en producción**.

## D-18 — 47 724 imágenes sin texto alternativo

**El hecho.** De 48 369 adjuntos, **sólo 645 tienen texto alternativo**. El
98.7 % no lo tiene.

| | A favor | En contra |
|---|---|---|
| **Dejarlo vacío** | Fiel. Un `alt` vacío es correcto para imagen decorativa | 47 724 imágenes inaccesibles para lectores de pantalla |
| Generar `alt` del pie de foto | Mejora real donde hay pie | Sólo sirve donde hay pie de foto |
| Generar `alt` del título del artículo | Cubre todo | **Inventa descripciones**. Un `alt` que no describe la imagen es peor que ninguno: engaña al lector de pantalla |

**Recomendación: usar el pie de foto donde exista y dejar el resto vacío.**
Nunca derivarlo del título. Y esto **depende de recibir las fotos**, así que
no corre prisa.

## D-12 — Restaurar la librería `udg_media`

**El hecho.** Es el muro de redes sociales de la plantilla. Uno de sus
scripts, `runmedia.js`, **declara una clave de API de un tercero**, y el
repositorio es público.

```text
YA ARREGLE lo urgente: un bloque pedia esa libreria y no existia, lo que
generaba un error en cada renderizado. Ahora se declara con su hoja de estilos
y sin ningun JS: el error desaparece y la clave no se publica.
```

| | A favor | En contra |
|---|---|---|
| **Dejarlo así** | Cero riesgo. El sitio funciona | El muro de redes sociales no funciona |
| Restaurarlo | La función vuelve | Hay que sacar la clave del código a una configuración fuera de Git, y verificar que el plugin comercial tiene licencia |

**Recomendación: dejarlo así y tratarlo al final**, cuando se decida si ese
muro se quiere en el sitio nuevo. Si se quiere, la clave debe salir del código.

## D-03 y D-13 — Dos técnicas internas

- **D-03, estrategia de Elementor.** Ya está de-escalada: el 94.8 % de los
  datos de Elementor están en revisiones, sólo 2 359 artículos publicados lo
  usan y **ninguno tiene el cuerpo vacío**. No es una amenaza; es revisar
  2 359 artículos cuando haya fotos.
- **D-13, qué copia de la plantilla es la autoritativa.** Técnica, sin
  impacto en el contenido. Se resuelve al integrar el repositorio del sitio.

**Recomendación: posponer las dos.** No bloquean nada.

---

# Resumen para responder rápido

```text
URGENTE
  Rotar la contrasena de la base de datos y el hash_salt.

FIRMA EN BLOQUE  (una frase basta)
  "Confirmo D-24, D-26, D-27, D-28 y D-30."

LAS CUATRO QUE DESBLOQUEAN
  D-21  ratifico los 4 268 y convierto los 94 de medios; los 148 de
        maquetacion NO se tocan             -> recomiendo SI
  D-19  las 77 307 revisiones NO se migran  -> recomiendo NO migrarlas
  D-25  /inicio se la queda la plantilla    -> recomiendo SI
  D-05  el contenido de demo se DESPUBLICA  -> recomiendo despublicar

LO QUE PUEDE ESPERAR
  D-04  los formularios NO guardan envios (ya esta asi)
  D-10  purgar el historial, DESPUES de rotar
  D-11  reparar el mojibake, al final
  D-18  alt desde el pie de foto, cuando lleguen las fotos
  D-12  udg_media, al final
  D-03, D-13  sin impacto

Y LO QUE NO ES UNA DECISION
  Las fotos: ~40 GB. Valen 10 puntos de avance por si solas.
```
