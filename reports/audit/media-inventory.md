# Inventario y cobertura de media

Fecha: 2026-10-01
Requisito que atiende: CLAUDE.md §25, §26, §41. Cierra la etapa 2 de
`docs/media-strategy.md` y la decisión D-17.

Generado con `tools/inventario-uploads.ps1` (en el equipo que tiene los
archivos) y `tools/cruzar-inventario-uploads.php` (aquí). Reproducible.

## Qué se recibió, y qué no

El responsable obtuvo una copia **parcial** de `/wp-content/uploads` en el
equipo de su trabajo. No se copió ni un byte a esta máquina: se generó un
inventario de texto con nombres y tamaños, y se cruzó contra las referencias
de la base de auditoría.

```text
Lineas del inventario          428566
Archivos reales                214140      44.37 GB
Ruido de macOS (._*)           214426       0.82 GB   descartado
```

El inventario llegó con 428 566 líneas y se habló de 82 GB. La mitad son
archivos `._nombre` de 4096 bytes: la bifurcación de recursos que macOS
escribe al copiar hacia un volumen que no es HFS/APFS. **No son contenido**:
son metadato del sistema de archivos de ORIGEN.

```text
CONFIRMADO: lo recibido son 214 140 archivos y 44.37 GB, no 428 566 ni 82 GB.
Produccion reporto 84.23 GB, asi que faltan unos 40 GB por recibir.
```

Las cifras de cobertura **no cambiaron** al descartar el ruido, y no podían
cambiar: `._foto.jpg` nunca coincide con `foto.jpg`. El descarte corrige el
encabezado del reporte, no el resultado del cruce. Se documenta para que
nadie repita la cuenta inflada.

## Cobertura de los adjuntos (`_wp_attached_file`)

```text
Referencias en la base     48358
PRESENTES                  15063    31.1 %
AUSENTES                   33295    68.9 %
```

## El patrón: no es aleatorio, es un corte cronológico

Catorce carpetas dan **100 %** de cobertura. Eso demuestra que el
emparejamiento de rutas funciona y que lo ausente falta de verdad: si fuera
un error de comparación, fallarían todas por igual.

| Carpeta | Presentes | Ausentes | Cobertura | ¿en disco? |
|---|---:|---:|---:|---|
| `2005` | 2 | 27 | 6.9 % | sí |
| `2007` | 175 | 0 | 100.0 % | sí |
| `2008` | 1228 | 1 | 99.9 % | sí |
| `2009` | 1199 | 0 | 100.0 % | sí |
| `2010` | 293 | 0 | 100.0 % | sí |
| `2011` | 1087 | 0 | 100.0 % | sí |
| `2012` | 1194 | 0 | 100.0 % | sí |
| `2013` | 140 | 0 | 100.0 % | sí |
| `2014` | 14 | 0 | 100.0 % | sí |
| `2015` | 1428 | 0 | 100.0 % | sí |
| `2016` | 8 | 0 | 100.0 % | sí |
| `2017` | 6 | 301 | 2.0 % | sí |
| `2018` | 246 | 0 | 100.0 % | sí |
| `2019` | 1457 | 17 | 98.8 % | sí |
| `2020` | 5059 | 1064 | 82.6 % | sí |
| `2021` | 0 | 4126 | 0.0 % | **no** |
| `2022` | 0 | 6253 | 0.0 % | **no** |
| `2023` | 0 | 6379 | 0.0 % | **no** |
| `2024` | 0 | 6056 | 0.0 % | **no** |
| `2025` | 0 | 6398 | 0.0 % | **no** |
| `2026` | 910 | 2665 | 25.5 % | sí |
| `slider2` | 142 | 3 | 97.9 % | sí |
| `slider3` | 475 | 5 | 99.0 % | sí |

Al bajar a nivel de mes aparece el corte exacto:

| Mes | Presentes | Ausentes | Cobertura | Archivos en disco |
|---|---:|---:|---:|---:|
| `2005/01` | 0 | 1 | 0.0 % | 0 |
| `2005/02` | 0 | 6 | 0.0 % | 0 |
| `2005/03` | 0 | 3 | 0.0 % | 0 |
| `2005/04` | 1 | 5 | 16.7 % | 31 |
| `2005/06` | 0 | 5 | 0.0 % | 0 |
| `2005/07` | 1 | 4 | 20.0 % | 29 |
| `2005/08` | 0 | 1 | 0.0 % | 0 |
| `2005/12` | 0 | 2 | 0.0 % | 0 |
| `2017/05` | 3 | 2 | 60.0 % | 79 |
| `2017/06` | 0 | 2 | 0.0 % | 0 |
| `2017/07` | 0 | 1 | 0.0 % | 0 |
| `2017/09` | 1 | 0 | 100.0 % | 18 |
| `2017/10` | 2 | 133 | 1.5 % | 52 |
| `2017/11` | 0 | 104 | 0.0 % | 0 |
| `2017/12` | 0 | 59 | 0.0 % | 0 |
| `2019/01` | 27 | 0 | 100.0 % | 385 |
| `2019/02` | 103 | 0 | 100.0 % | 1388 |
| `2019/03` | 78 | 0 | 100.0 % | 1049 |
| `2019/04` | 48 | 0 | 100.0 % | 647 |
| `2019/05` | 92 | 0 | 100.0 % | 1296 |
| `2019/06` | 86 | 0 | 100.0 % | 1125 |
| `2019/07` | 88 | 0 | 100.0 % | 1196 |
| `2019/08` | 84 | 0 | 100.0 % | 1228 |
| `2019/09` | 162 | 0 | 100.0 % | 2095 |
| `2019/10` | 117 | 2 | 98.3 % | 1522 |
| `2019/11` | 243 | 3 | 98.8 % | 3851 |
| `2019/12` | 329 | 12 | 96.5 % | 6597 |
| `2020/01` | 333 | 6 | 98.2 % | 6924 |
| `2020/02` | 3186 | 21 | 99.3 % | 34950 |
| `2020/03` | 395 | 2 | 99.5 % | 7983 |
| `2020/04` | 223 | 15 | 93.7 % | 4919 |
| `2020/05` | 220 | 6 | 97.3 % | 5003 |
| `2020/06` | 224 | 10 | 95.7 % | 5235 |
| `2020/07` | 213 | 6 | 97.3 % | 4999 |
| `2020/08` | 265 | 27 | 90.8 % | 6659 |
| `2020/09` | 0 | 260 | 0.0 % | 0 |
| `2020/10` | 0 | 278 | 0.0 % | 0 |
| `2020/11` | 0 | 249 | 0.0 % | 0 |
| `2020/12` | 0 | 184 | 0.0 % | 0 |
| `2026/01` | 337 | 0 | 100.0 % | 9037 |
| `2026/02` | 573 | 0 | 100.0 % | 15415 |
| `2026/03` | 0 | 669 | 0.0 % | 0 |
| `2026/04` | 0 | 440 | 0.0 % | 0 |
| `2026/05` | 0 | 465 | 0.0 % | 0 |
| `2026/06` | 0 | 330 | 0.0 % | 0 |
| `2026/07` | 0 | 201 | 0.0 % | 0 |
| `2026/08` | 0 | 395 | 0.0 % | 0 |
| `2026/09` | 0 | 165 | 0.0 % | 0 |

```text
Todo lo anterior a 2020/09 esta COMPLETO.
Desde 2020/09 no hay nada, salvo 2026/01 y 2026/02, que si llegaron.
2005 y 2017 son escasos en ORIGEN, no truncados: 2017/10 tiene 52 archivos
  en disco para 135 referencias, es decir dos originales con sus derivados.
```

## Lo que hay que pedir, y nada más

Para no volver a mover los 44 GB ya recibidos, esta es la lista concreta. Los
números son adjuntos referenciados por la base que hoy no existen.

| Qué pedir | Adjuntos ausentes |
|---|---:|
| Carpetas `2021/` `2022/` `2023/` `2024/` `2025/` completas | 29212 |
| `2026/03` a `2026/09` | 2665 |
| `2020/09` a `2020/12` | 971 |
| `2017/` completa (es pequeña) | 301 |
| `2005/` completa (es diminuta) | 27 |
| Dispersos dentro de carpetas ya recibidas | 119 |
| **Total** | **33295** |

```text
Reconciliacion: 33176 + 119 = 33295 = AUSENTES. Cuadra exacto.
```

Los dispersos son unidades sueltas repartidas en meses con cobertura del 90
al 99 %. No justifican pedir una carpeta entera; se tratarán caso por caso
al final, cuando llegue el resto.

## Decisión D-17: las referencias de `foto1`

`dc8_posts.foto1` guarda **sólo el nombre** del archivo, sin su carpeta:
16 102 referencias y cero con barra. El inventario permite resolverlas
buscando ese nombre.

```text
Referencias de foto1      16102
RESUELTAS a una ruta       7266    45.1 %
AMBIGUAS (varias rutas)      14     0.1 %
AUSENTES del disco         8822    54.8 %
```

```text
D-17 QUEDA RESUELTA EN SU METODO: la busqueda por nombre funciona y solo 14
de 16 102 resultan ambiguas, el 0.09 %. Las 8 822 ausentes no son un fallo
del metodo: son los mismos archivos que faltan por recibir.
```

Las 14 ambiguas tienen el mismo nombre en varias carpetas. Se desempatarán
cruzando `post_date` del artículo con la carpeta de año y mes, fiable porque
WordPress organiza `uploads` por año y mes. Son 14 registros: si la
heurística deja alguno sin resolver, se revisa a mano.

## Por qué esto no bloquea la migración de contenido

El modelo separa el texto de los archivos. La migración de artículos escribe
la referencia (`field_wp_post_id` y la ruta esperada del manifiesto) sin
necesitar el archivo. Cuando lleguen los 40 GB restantes, el árbol se copia
**una vez** desde donde esté a `sites/default/files/migrado/`, preservando la
estructura de carpetas, y las entidades Media se crean contra las rutas que
el manifiesto ya calculó. No hay que tocar las fotos donde están ni
remigrar contenido.

```text
B-02 sigue ABIERTO como dependencia externa documentada, no como defecto de
la migracion. CLAUDE.md §48 lo admite: "Toda media requerida fue migrada o
esta bloqueada por una dependencia externa documentada."
```
