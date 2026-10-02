# Comparación de media: lo que el sitio referencia contra lo que existe

Fecha: 2026-10-02
Requisito que atiende: CLAUDE.md §25, §26, §41.

Reproducible con `tools/inventario-uploads.ps1` y
`tools/cruzar-inventario-uploads.php`. El detalle por archivo vive en `work/`,
fuera de Git.

```text
ESTE ES EL UNICO BLOQUE DE LA MIGRACION CON UNA DEPENDENCIA EXTERNA ABIERTA.
No es un defecto del trabajo: es que faltan archivos por recibir.
```

## El estado, en una tabla

| | Referencias | Presentes | Ausentes |
|---|---:|---:|---:|
| Adjuntos (`_wp_attached_file`) | 48 358 | **15 063** (31.1 %) | 33 295 |
| `foto1` (imagen heredada) | 16 102 | **7 266** (45.1 %) | 8 822 |

De `foto1`, 14 referencias (**0.09 %**) resultan ambiguas porque el mismo
nombre existe en varias carpetas. Se desempatarán cruzando la fecha del
artículo con la carpeta de año y mes.

## Lo recibido

```text
Lineas del inventario       428 566
Archivos reales             214 140    44.37 GB
Ruido de macOS (._*)        214 426     0.82 GB   descartado
```

```text
CORRECCION DE UNA CIFRA QUE SE DIO POR BUENA: se hablo de 82 GB. Son 44.37. La
mitad de las lineas son archivos "._nombre" de 4096 bytes, la bifurcacion de
recursos que macOS escribe al copiar hacia un volumen que no es HFS/APFS. No
son contenido: son metadato del sistema de archivos de ORIGEN.
```

Producción reportó 84.23 GB, así que **faltan unos 40 GB**.

## El patrón de lo que falta: un corte cronológico

Catorce carpetas dan **100 %** de cobertura. Eso es lo que demuestra que el
emparejamiento de rutas funciona: si fuera un error de comparación, fallarían
todas por igual.

```text
Todo lo anterior a 2020/09 esta COMPLETO.
Desde 2020/09 no hay nada, salvo 2026/01 y 2026/02, que si llegaron.
```

2005 y 2017 salen bajos, pero son escasos **en origen**, no truncados:
`2017/10` tiene 52 archivos en disco para 135 referencias, es decir dos
originales con sus derivados.

## Lo que hay que pedir, y nada más

Para no volver a mover los 44 GB ya recibidos:

| Qué pedir | Adjuntos que recupera |
|---|---:|
| `2021/` `2022/` `2023/` `2024/` `2025/` completas | 29 212 |
| `2026/03` a `2026/09` | 2 665 |
| `2020/09` a `2020/12` | 971 |
| `2017/` completa (es pequeña) | 301 |
| `2005/` completa (es diminuta) | 27 |
| Dispersos en carpetas ya recibidas | 119 |
| **Total** | **33 295** |

```text
Reconciliacion: 33 176 + 119 = 33 295 = AUSENTES. Cuadra exacto.
```

## Lo que sigue bloqueado, y por qué

§26 pide hash SHA-256 de cada archivo relevante. Eso exige **leer el contenido**
de los archivos, y están en otro equipo.

```text
BLOQUEADO hasta recibir los archivos:
  - hashes SHA-256 (§26)
  - deteccion de derivados por comparacion real
  - identificacion de huerfanos
  - creacion de las entidades Media (FASE 6)
```

Lo que **sí** está hecho sin los archivos:

```text
HECHO:
  - manifiesto de los 48 369 adjuntos con sus metadatos y su ruta de destino
  - relacion attachment -> archivo para el 31.1 % que existe
  - metodo de resolucion de foto1, demostrado: 14 ambiguas de 16 102 (D-17)
  - peticion concreta de lo que falta, carpeta por carpeta
```

## Por qué esto no bloquea el resto de la migración

El modelo separa el texto de los archivos. La migración de artículos escribe la
referencia sin necesitar el archivo, y los 36 666 artículos están migrados con
conciliación exacta.

Cuando lleguen los 40 GB, el árbol se copia **una vez** desde donde esté a
`sites/default/files/migrado/`, preservando la estructura de carpetas, y las
entidades Media se crean contra las rutas que el manifiesto ya calculó.

```text
NO hay que tocar las fotos donde estan ni remigrar contenido.
```

## Veredicto

```text
BLOCKED - DEPENDENCIA EXTERNA DOCUMENTADA
```

§48 admite exactamente este estado: «Toda media requerida fue migrada **o está
bloqueada por una dependencia externa documentada**.» Ésta lo está, con la
petición concreta de lo que falta y la reconciliación cuadrada.
