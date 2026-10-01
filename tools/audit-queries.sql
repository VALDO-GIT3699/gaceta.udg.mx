-- ---------------------------------------------------------------------------
-- audit-queries.sql
--
-- Auditoría SQL de la base `gaceta_auditoria`.
--
-- Son las consultas que CLAUDE.md §13 declara obligatorias, más las que la
-- auditoría del dump reveló como necesarias. Todas son de SÓLO LECTURA.
--
-- CLAUDE.md §14 declara NO CONFIABLES los conteos obtenidos por parser de
-- texto. Las cifras definitivas del proyecto son las que produce este archivo.
--
-- Uso:
--   mysql -u root gaceta_auditoria < tools/audit-queries.sql
--
-- No ejecuta ningún DELETE, UPDATE, INSERT, ALTER ni DROP.
-- ---------------------------------------------------------------------------

SELECT '===== 1. ESTRUCTURA REAL DE LAS TABLAS CARGADAS =====' AS seccion;

SELECT
    TABLE_NAME            AS tabla,
    ENGINE                AS motor,
    TABLE_COLLATION       AS collation,
    TABLE_ROWS            AS filas_aprox,
    ROUND(DATA_LENGTH  / 1048576, 1) AS datos_mb,
    ROUND(INDEX_LENGTH / 1048576, 1) AS indices_mb
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY DATA_LENGTH DESC;

SELECT '===== 2. CHARSET POR COLUMNA DE TEXTO DE dc8_posts =====' AS seccion;

SELECT
    COLUMN_NAME           AS columna,
    DATA_TYPE             AS tipo,
    CHARACTER_SET_NAME    AS charset,
    COLLATION_NAME        AS collation
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'dc8_posts'
  AND CHARACTER_SET_NAME IS NOT NULL
ORDER BY ORDINAL_POSITION;

SELECT '===== 3. CONTEO POR post_type (CIFRA DEFINITIVA) =====' AS seccion;

SELECT post_type, COUNT(*) AS total
FROM dc8_posts
GROUP BY post_type
ORDER BY total DESC;

SELECT '===== 4. CONTEO POR post_status =====' AS seccion;

SELECT post_status, COUNT(*) AS total
FROM dc8_posts
GROUP BY post_status
ORDER BY total DESC;

SELECT '===== 5. MATRIZ post_type x post_status =====' AS seccion;

SELECT post_type, post_status, COUNT(*) AS total
FROM dc8_posts
GROUP BY post_type, post_status
ORDER BY total DESC;

SELECT '===== 6. RANGO DE FECHAS DEL CORPUS =====' AS seccion;

SELECT
    MIN(post_date) AS primera,
    MAX(post_date) AS ultima,
    SUM(post_date = '0000-00-00 00:00:00') AS fechas_cero,
    SUM(post_date IS NULL)                 AS fechas_nulas
FROM dc8_posts;

SELECT '===== 7. CONTENIDO PUBLICADO POR AÑO =====' AS seccion;

SELECT YEAR(post_date) AS anio, COUNT(*) AS total
FROM dc8_posts
WHERE post_type = 'post' AND post_status = 'publish'
GROUP BY YEAR(post_date)
ORDER BY anio;

-- ---------------------------------------------------------------------------
-- Columnas NO ESTÁNDAR de dc8_posts. Son el corazón del modelo de Gaceta y
-- ninguna herramienta genérica de migración las conoce.
-- Ver docs/content-model.md.
-- ---------------------------------------------------------------------------

SELECT '===== 8. USO DE LAS COLUMNAS NO ESTANDAR =====' AS seccion;

SELECT
    COUNT(*)                                                   AS filas_total,
    SUM(original_id IS NOT NULL AND original_id <> 0)          AS con_original_id,
    SUM(term_id     IS NOT NULL AND term_id     <> 0)          AS con_term_id,
    SUM(foto1       IS NOT NULL AND foto1       <> '')         AS con_foto1,
    SUM(balazo      IS NOT NULL AND balazo      <> '')         AS con_balazo,
    SUM(cita        IS NOT NULL AND cita        <> '')         AS con_cita,
    SUM(seccion     IS NOT NULL AND seccion     <> '')         AS con_seccion,
    SUM(subseccion  IS NOT NULL AND subseccion  <> '')         AS con_subseccion
FROM dc8_posts;

SELECT '===== 9. SECCIONES EDITORIALES REALES =====' AS seccion;
-- La fuente de verdad del vocabulario de secciones, NO el menú del sitio.
-- CLAUDE.md §46 prohibe derivar el modelo de datos de una observacion visual.

SELECT seccion, COUNT(*) AS total
FROM dc8_posts
WHERE seccion IS NOT NULL AND seccion <> ''
GROUP BY seccion
ORDER BY total DESC;

SELECT '===== 10. SUBSECCIONES Y SU SECCION PADRE =====' AS seccion;
-- Decide si seccion/subseccion es UN vocabulario jerarquico o DOS planos:
-- si alguna subseccion aparece bajo mas de una seccion, no hay jerarquia pura.

SELECT seccion, subseccion, COUNT(*) AS total
FROM dc8_posts
WHERE subseccion IS NOT NULL AND subseccion <> ''
GROUP BY seccion, subseccion
ORDER BY seccion, total DESC;

SELECT '===== 11. SUBSECCIONES CON MAS DE UNA SECCION PADRE =====' AS seccion;

SELECT subseccion, COUNT(DISTINCT seccion) AS secciones_padre
FROM dc8_posts
WHERE subseccion IS NOT NULL AND subseccion <> ''
GROUP BY subseccion
HAVING secciones_padre > 1
ORDER BY secciones_padre DESC;

SELECT '===== 12. HIPOTESIS DE LA SEGUNDA MIGRACION =====' AS seccion;
-- original_id sugiere que este contenido ya fue migrado una vez.
-- Ver docs/content-model.md.

SELECT
    COUNT(*)                        AS filas,
    MIN(original_id)                AS min_original,
    MAX(original_id)                AS max_original,
    COUNT(DISTINCT original_id)     AS originales_distintos,
    SUM(original_id = ID)           AS coincide_con_id
FROM dc8_posts
WHERE original_id IS NOT NULL AND original_id <> 0;

SELECT '===== 13. AUTORIA: post_author (alimenta D-14) =====' AS seccion;

SELECT
    p.post_author                   AS wp_user_id,
    u.user_login                    AS login,
    u.display_name                  AS nombre,
    COUNT(*)                        AS contenidos
FROM dc8_posts p
LEFT JOIN dc8_users u ON u.ID = p.post_author
WHERE p.post_type = 'post'
GROUP BY p.post_author, u.user_login, u.display_name
ORDER BY contenidos DESC;

SELECT '===== 14. USUARIOS SIN CONTENIDO ATRIBUIDO =====' AS seccion;
-- D-14: no se crean cuentas. Esto dimensiona cuantos terminos de
-- credito_editorial hacen falta realmente.

SELECT
    (SELECT COUNT(*) FROM dc8_users)                              AS usuarios_total,
    (SELECT COUNT(DISTINCT post_author) FROM dc8_posts)           AS con_algun_contenido,
    (SELECT COUNT(DISTINCT post_author) FROM dc8_posts
      WHERE post_type = 'post')                                   AS con_entradas;

SELECT '===== 15. NOMBRES DE AUTOR DUPLICADOS O VARIANTES =====' AS seccion;

SELECT display_name, COUNT(*) AS veces
FROM dc8_users
GROUP BY display_name
HAVING veces > 1
ORDER BY veces DESC;

SELECT '===== 16. URLs: slugs y duplicados (alimenta docs/url-strategy.md) =====' AS seccion;

SELECT
    COUNT(*)                                     AS filas,
    SUM(post_name IS NULL OR post_name = '')     AS sin_slug,
    COUNT(DISTINCT post_name)                    AS slugs_distintos
FROM dc8_posts
WHERE post_status = 'publish';

SELECT '===== 17. SLUGS REPETIDOS ENTRE CONTENIDO PUBLICADO =====' AS seccion;

SELECT post_name, COUNT(*) AS veces
FROM dc8_posts
WHERE post_status = 'publish' AND post_name <> ''
GROUP BY post_name
HAVING veces > 1
ORDER BY veces DESC
LIMIT 50;

SELECT '===== 18. TAXONOMIAS =====' AS seccion;

SELECT tt.taxonomy, COUNT(*) AS terminos
FROM dc8_term_taxonomy tt
GROUP BY tt.taxonomy
ORDER BY terminos DESC;

SELECT '===== 19. TERMINOS CON JERARQUIA =====' AS seccion;

SELECT
    tt.taxonomy,
    SUM(tt.parent <> 0) AS con_padre,
    SUM(tt.parent =  0) AS raiz,
    SUM(tt.count)       AS usos_declarados
FROM dc8_term_taxonomy tt
GROUP BY tt.taxonomy
ORDER BY tt.taxonomy;

SELECT '===== 20. term_id de dc8_posts CONTRA term_relationships =====' AS seccion;
-- Determina cual de los dos mecanismos es la fuente de verdad de la taxonomia.

SELECT
    (SELECT COUNT(*) FROM dc8_posts
      WHERE term_id IS NOT NULL AND term_id <> 0)      AS posts_con_term_id,
    (SELECT COUNT(DISTINCT object_id)
       FROM dc8_term_relationships)                    AS objetos_en_relationships,
    (SELECT COUNT(*) FROM dc8_term_relationships)      AS filas_relationships;

SELECT '===== 21. COMENTARIOS: CIFRA DEFINITIVA (§28) =====' AS seccion;
-- La auditoria del dump indico que la cola de moderacion es spam en ingles.
-- Ver reports/audit/encoding-audit.md.

SELECT comment_approved, COUNT(*) AS total
FROM dc8_comments
GROUP BY comment_approved
ORDER BY total DESC;

SELECT '===== 22. COMENTARIOS POR AÑO =====' AS seccion;

SELECT YEAR(comment_date) AS anio, COUNT(*) AS total
FROM dc8_comments
GROUP BY YEAR(comment_date)
ORDER BY anio;

SELECT '===== 23. COMENTARIOS HUERFANOS =====' AS seccion;

SELECT COUNT(*) AS huerfanos
FROM dc8_comments c
LEFT JOIN dc8_posts p ON p.ID = c.comment_post_ID
WHERE p.ID IS NULL;

SELECT '===== 24. PLUGINS REALMENTE ACTIVOS (§16) =====' AS seccion;
-- Un plugin en disco no es un plugin activo. §16 exige migrar funcionalidad
-- real, no plugins.

SELECT option_name, LENGTH(option_value) AS bytes
FROM dc8_options
WHERE option_name IN ('active_plugins', 'template', 'stylesheet',
                      'permalink_structure', 'blog_charset', 'home', 'siteurl');

SELECT '===== 25. PLANTILLAS DE TITULO DE YOAST (§23) =====' AS seccion;
-- El SEO por contenido es minimo: solo 2 titulos y 910 descripciones propias.
-- Lo demas se genera con estas plantillas.

SELECT option_name, LENGTH(option_value) AS bytes
FROM dc8_options
WHERE option_name LIKE 'wpseo%'
ORDER BY option_name;

SELECT '===== 26. EVENTOS Y LUGARES (§21) =====' AS seccion;

SELECT post_type, post_status, COUNT(*) AS total
FROM dc8_posts
WHERE post_type IN ('tribe_events', 'tribe_venue', 'tribe_organizer')
GROUP BY post_type, post_status
ORDER BY post_type, total DESC;

SELECT '===== 27. ADJUNTOS POR TIPO MIME (alimenta el manifiesto de media) =====' AS seccion;

SELECT post_mime_type, COUNT(*) AS total
FROM dc8_posts
WHERE post_type = 'attachment'
GROUP BY post_mime_type
ORDER BY total DESC;

SELECT '===== 28. ADJUNTOS POR AÑO =====' AS seccion;
-- Permite pedir a produccion los uploads por lotes anuales (D-15).

SELECT YEAR(post_date) AS anio, COUNT(*) AS adjuntos
FROM dc8_posts
WHERE post_type = 'attachment'
GROUP BY YEAR(post_date)
ORDER BY anio;

SELECT '===== 29. REVISIONES =====' AS seccion;

SELECT
    COUNT(*)                        AS revisiones,
    COUNT(DISTINCT post_parent)     AS contenidos_con_revision
FROM dc8_posts
WHERE post_type = 'revision';

SELECT '===== 30. INTEGRIDAD: post_parent HUERFANOS =====' AS seccion;

SELECT COUNT(*) AS huerfanos
FROM dc8_posts h
LEFT JOIN dc8_posts p ON p.ID = h.post_parent
WHERE h.post_parent <> 0 AND p.ID IS NULL;

SELECT '===== FIN =====' AS seccion;
