# Enlaces e imágenes del cuerpo: qué se rompe en el cutover

Fecha: 2026-10-02 03:41
Requisito que atiende: CLAUDE.md §24, §34, FASE 10.

Generado con `tools/auditar-enlaces-internos.php`. Sólo lectura.

## Por qué esto no se ve hasta que es tarde

El contenido migrado trae las referencias con **URL absoluta** al dominio
de producción. Hoy funcionan, porque ese dominio sigue sirviendo
WordPress, así que una revisión visual del sitio migrado las ve perfectas.

```text
EN EL CUTOVER el dominio pasa a apuntar a Drupal y /wp-content/uploads
deja de existir. El dano aparece EL DIA DEL CAMBIO, no antes.

Una validacion visual previa al cutover da un FALSO POSITIVO.
```

## Las cifras

```text
Cuerpos analizados                       35975

Etiquetas <img> en total                 21911
  con src absoluto                       21207
  articulos afectados                     8160

Enlaces <a> en total                     23209
  apuntando a gaceta.udg.mx               6039
  articulos afectados                     1894

Referencias a /wp-content/uploads/       82252
  articulos afectados                     8304
```

## Dominios de las imágenes

```text
www.gaceta.udg.mx                          21132
gaceta.udg.mx                                 24
148.202.34.142                                21
static.xx.fbcdn.net                            6
pbs.twimg.com                                  4
www.libreriacarlosfuentes.mx                   4
scontent.fgdl4-1.fna.fbcdn.net                 4
editorialparaisoperdido.com                    3
scontent.fgdl9-1.fna.fbcdn.net                 2
s3.amazonaws.com                               1
i59.tinypic.com                                1
gilbertobrenis.com                             1
...
```

## Qué hacer, y por qué no ahora

```text
LAS IMAGENES: reescribir cada src exige saber donde quedara el archivo, y
eso depende de recibir los ~40 GB que faltan (B-02). Apuntarlas AHORA a
rutas que no existen seria PEOR que dejarlas, porque hoy se ven.
```

```text
LOS ENLACES entre articulos: se resuelven solos si el alias se preservo,
y 36 088 de 36 851 lo conservan. Los que apunten a un slug desambiguado o
a una entrada sin slug necesitaran la redireccion 301, que ya existe para
1 406 rutas historicas.
```

## Tarea de la FASE 6, cuando lleguen los archivos

```text
1. Copiar el arbol de uploads a sites/default/files/migrado/
2. Reescribir en el cuerpo:
     http://www.gaceta.udg.mx/wp-content/uploads/AAAA/MM/x.jpg
  -> /sites/default/files/migrado/AAAA/MM/x.jpg
3. Reescribir los enlaces absolutos a gaceta.udg.mx como RELATIVOS, para
   que no dependan del dominio.
4. Volver a ejecutar esta auditoria y comprobar que los contadores bajan
   a cero.
```

Los pasos 2 y 3 son una pasada de `migrate:import --update` con un proceso
de reescritura añadido: no hay que remigrar contenido ni tocar los
archivos donde estén.
