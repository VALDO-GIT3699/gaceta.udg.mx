# Accesibilidad

Fecha: 2026-09-30
Fase del roadmap: FASE 12
Requisito que atiende: CLAUDE.md §12 y FASE 12

## El punto de partida real

CLAUDE.md FASE 12 dice: *"El template institucional contiene mecanismos de
accesibilidad. No eliminarlos. Mantener y mejorar accesibilidad. No sacrificar
accesibilidad por parecido visual."*

La auditoría encontró dos cosas que obligan a matizar ese encuadre.

### Lo que el template aporta, y que estuvo roto

```text
CONFIRMADO: el template trae controles de Sepia, Grises, Invertir de color,
            +A/-A, skip-link, visually-hidden y atributos ARIA.
CONFIRMADO: el docroot había PERDIDO la librería que los implementa.
CONFIRMADO: corregido y verificado. Ver MIGRATION_CONTRACT.md, B-04.
```

El detalle importa como lección de método: el sitio servía los **botones**
visibles sin el JavaScript que los hace funcionar. Un reporte de este proyecto
llegó a certificar que "la accesibilidad funciona" porque encontró las
etiquetas en el HTML. El auditor lo detectó.

```text
Encontrar el control en el HTML no prueba que el control funcione.
```

La verificación válida es que `accesibilityUdg.js` se cargue, y además que cada
botón produzca el efecto esperado en un navegador real. Lo primero está
confirmado; lo segundo sigue pendiente.

### Lo que el contenido de origen NO aporta

```text
CONFIRMADO: 661 de 48 358 adjuntos tienen la clave de texto alternativo.
CONFIRMADO: de ellos, 645 tienen texto real y 16 están vacíos.
CONFIRMADO: 46 713 imágenes sin texto alternativo. El 98.67 %.
Evidencia: reports/audit/postmeta-audit.md
```

```text
Aquí no hay accesibilidad que "mantener": hay que decidir qué hacer con ella.
```

Ésta es la tensión central de la FASE 12 en este proyecto: el mandato dice
preservar y mejorar, pero el origen no tiene casi nada que preservar en lo que
más importa para lectores de pantalla.

## La decisión que hay que tomar (D-18)

Cuatro opciones para las 46 713 imágenes sin `alt`:

**A. Dejar el atributo vacío (`alt=""`).**
Técnicamente es lo que declara una imagen como decorativa. Es correcto en HTML
y los lectores de pantalla la omiten.

**B. Usar el pie de foto o el título del adjunto como `alt`.**
Hay datos: `post_excerpt` del adjunto suele contener el pie. Pero un pie de
foto y un texto alternativo cumplen funciones distintas: el pie añade contexto
para quien ve la imagen, el `alt` describe la imagen para quien no la ve.
Copiarlo produce `alt` de calidad desigual.

**C. Generar descripciones automáticamente.**
Produciría texto plausible para 46 713 imágenes. Pero describe lo que un
modelo cree ver, no lo que la fotografía documenta periodísticamente, y en un
medio informativo eso puede ser directamente falso. Además requiere los
archivos, que no están (B-02).

**D. Marcar para revisión editorial y dejar vacío mientras tanto.**
Honesto y trazable, pero 46 713 revisiones manuales no se harán.

```text
RECOMENDACIÓN: A para la migración, con B sólo donde el pie exista y sea
descriptivo, y un informe que liste las imágenes afectadas por si la redacción
quiere abordar las más visibles.
```

Razón: `alt=""` no empeora nada respecto al origen y es válido. Inventar
descripciones sí puede empeorarlo, porque un `alt` incorrecto es peor que
ninguno: engaña en lugar de omitir.

```text
DECISIÓN PENDIENTE del responsable. No se aplica nada por iniciativa propia.
```

## Un defecto heredado del template

```text
HALLAZGO: el HTML servido contiene el atributo "aria-democratizando", que no
es un atributo ARIA válido.
```

Es preexistente en el template institucional, no introducido por este
proyecto. Corregirlo toca `drudg8b3` o `udg_liston`, lo que CLAUDE.md §44
advierte no hacer arbitrariamente.

```text
PENDIENTE: acordar con el responsable del template institucional si se corrige
allí. Un atributo inválido no rompe nada, pero ensucia la validación.
```

## Qué hay que verificar antes de cerrar la FASE 12

La FASE 12 no se cierra con una inspección visual. Mínimo exigible:

```text
- [ ] Los controles del listón funcionan en navegador: sepia, grises,
      invertir, +A, -A y volver a normal.
- [ ] El skip-link lleva al contenido principal con el teclado.
- [ ] Navegación completa por teclado: menú, listados, detalle, formularios.
- [ ] Foco visible en todos los elementos interactivos.
- [ ] Contraste suficiente en el tema y en los estados de accesibilidad.
- [ ] Encabezados en orden jerárquico correcto (un solo h1 por página).
- [ ] Las imágenes migradas emiten el alt que la decisión D-18 determine.
- [ ] Los 3 033 PDF: decidir si se exige accesibilidad documental.
- [ ] Formularios de Webform con etiquetas asociadas y errores anunciados.
- [ ] Validación automática (axe o equivalente) sobre portada, listado,
      detalle y formulario.
```

El último punto debe ser un script en `tools/` con salida a
`reports/validation/`, no una comprobación manual.

## Los 3 033 PDF

```text
CONFIRMADO: 3 033 adjuntos son application/pdf.
CONFIRMADO: el plugin pdf-embedder está ACTIVO, así que hay PDF incrustados en
            el contenido.
```

Probablemente son las ediciones impresas de la Gaceta, lo que los convierte en
patrimonio documental. Un PDF escaneado sin capa de texto es **inaccesible** y
tampoco es indexable.

```text
DESCONOCIDO: si esos PDF tienen capa de texto. No se puede saber sin los
archivos (B-02).
```

No se propone acción: se registra para que la decisión exista cuando los
archivos estén disponibles.

## Relación con otras decisiones

```text
D-15 (media diferida) bloquea la verificación de alt sobre imágenes reales:
     sin archivos no hay nada que describir ni que comprobar visualmente.
D-12 (udg_media) es independiente: el muro de redes sociales no afecta a los
     mecanismos de accesibilidad del listón.
B-04 queda cerrado en su parte de accesibilidad, con la verificación funcional
     en navegador pendiente.
```

## Principio que se mantiene

```text
No se sacrifica accesibilidad por parecido visual (CLAUDE.md FASE 12).
```

En la práctica, para la FASE 11 eso significa que si reproducir un componente
de Newspaper exigiera romper el foco, el orden de lectura o el contraste del
template institucional, **no se reproduce**: se busca el equivalente accesible
con los componentes de `drudg8b3`.
