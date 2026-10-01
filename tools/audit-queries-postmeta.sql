-- ---------------------------------------------------------------------------
-- audit-queries-postmeta.sql
--
-- Auditoría SQL de dc8_postmeta (etapa 2 de D-02). SÓLO LECTURA.
--
-- dc8_postmeta ocupa el 68 % del dump y contiene lo que el contrato marca
-- como riesgo crítico: Elementor (§18), las referencias de media (§25), los
-- slugs históricos (§24) y los datos SEO por contenido (§23).
--
-- Uso:
--   mysql -u root --default-character-set=utf8mb4 --table gaceta_auditoria \
--         < tools/audit-queries-postmeta.sql
--
-- IMPORTANTE: --default-character-set=utf8mb4 es obligatorio. Sin él el texto
-- acentuado se lee mal (ver reports/audit/content-counts.md).
--
-- No ejecuta ningún DELETE, UPDATE, INSERT, ALTER ni DROP.
-- ---------------------------------------------------------------------------

SELECT '===== 1. VOLUMEN POR meta_key (resuelve el CONFLICTO de cifras) =====' AS seccion;

SELECT
    meta_key,
    COUNT(*)                                      AS filas,
    ROUND(SUM(LENGTH(meta_value)) / 1048576, 1)   AS mb_total,
    ROUND(AVG(LENGTH(meta_value)))                AS bytes_medio,
    MAX(LENGTH(meta_value))                       AS bytes_max
FROM dc8_postmeta
GROUP BY meta_key
ORDER BY SUM(LENGTH(meta_value)) DESC
LIMIT 30;

SELECT '===== 2. ELEMENTOR: EL RIESGO CRITICO, DIMENSIONADO =====' AS seccion;
-- La consulta mas importante del proyecto. Si post_content esta vacio y el
-- contenido real vive en _elementor_data, migrar post_content produciria
-- contenidos vacios CON LOS CONTEOS SALIENDO CORRECTOS.
-- Ver reports/audit/elementor-audit.md.

SELECT
    p.post_type,
    p.post_status,
    COUNT(*)                                              AS con_elementor,
    SUM(CHAR_LENGTH(TRIM(p.post_content)) = 0)            AS content_vacio,
    SUM(CHAR_LENGTH(TRIM(p.post_content)) BETWEEN 1 AND 50)  AS content_casi_vacio,
    SUM(CHAR_LENGTH(TRIM(p.post_content)) > 50)           AS content_con_texto
FROM dc8_posts p
JOIN dc8_postmeta m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
GROUP BY p.post_type, p.post_status
ORDER BY con_elementor DESC;

SELECT '===== 3. ELEMENTOR: cuanto pesa su JSON =====' AS seccion;

SELECT
    COUNT(*)                                     AS filas,
    ROUND(SUM(LENGTH(meta_value)) / 1048576, 1)  AS mb_total,
    ROUND(AVG(LENGTH(meta_value)))               AS bytes_medio,
    MAX(LENGTH(meta_value))                      AS bytes_max,
    SUM(meta_value IN ('', '[]'))                AS vacios_o_lista_vacia
FROM dc8_postmeta
WHERE meta_key = '_elementor_data';

SELECT '===== 4. ELEMENTOR en REVISIONES (explica el exceso de cifras) =====' AS seccion;

SELECT
    CASE WHEN p.post_type = 'revision' THEN 'revision' ELSE 'contenido real' END AS clase,
    COUNT(*) AS filas
FROM dc8_postmeta m
JOIN dc8_posts p ON p.ID = m.post_id
WHERE m.meta_key = '_elementor_data'
GROUP BY clase;

SELECT '===== 5. URLs HISTORICAS: _wp_old_slug (§24) =====' AS seccion;

SELECT
    COUNT(*)                        AS slugs_historicos,
    COUNT(DISTINCT post_id)         AS contenidos_afectados,
    COUNT(DISTINCT meta_value)      AS slugs_distintos
FROM dc8_postmeta
WHERE meta_key = '_wp_old_slug';

SELECT '===== 6. CONTENIDOS CON MAS DE UN SLUG HISTORICO =====' AS seccion;

SELECT n_slugs, COUNT(*) AS contenidos
FROM (
    SELECT post_id, COUNT(*) AS n_slugs
    FROM dc8_postmeta
    WHERE meta_key = '_wp_old_slug'
    GROUP BY post_id
) t
GROUP BY n_slugs
ORDER BY n_slugs;

SELECT '===== 7. COLISIONES: un slug historico reclamado por varios =====' AS seccion;

SELECT meta_value AS slug_historico, COUNT(DISTINCT post_id) AS contenidos
FROM dc8_postmeta
WHERE meta_key = '_wp_old_slug'
GROUP BY meta_value
HAVING contenidos > 1
ORDER BY contenidos DESC
LIMIT 20;

SELECT '===== 8. MEDIA: _wp_attached_file (base del manifiesto) =====' AS seccion;

SELECT
    COUNT(*)                    AS rutas,
    COUNT(DISTINCT post_id)     AS adjuntos,
    COUNT(DISTINCT meta_value)  AS rutas_distintas
FROM dc8_postmeta
WHERE meta_key = '_wp_attached_file';

SELECT '===== 9. MEDIA: distribucion de rutas por carpeta inicial =====' AS seccion;
-- Revela si las rutas siguen el patron anio/mes de WordPress o algo heredado.

SELECT
    SUBSTRING_INDEX(meta_value, '/', 1) AS primer_segmento,
    COUNT(*)                            AS archivos
FROM dc8_postmeta
WHERE meta_key = '_wp_attached_file'
GROUP BY primer_segmento
ORDER BY archivos DESC
LIMIT 20;

SELECT '===== 10. MEDIA: imagen destacada =====' AS seccion;

SELECT
    COUNT(*)                 AS asignaciones,
    COUNT(DISTINCT post_id)  AS contenidos_con_destacada
FROM dc8_postmeta
WHERE meta_key = '_thumbnail_id';

SELECT '===== 11. MEDIA: texto alternativo (accesibilidad, FASE 12) =====' AS seccion;

SELECT
    COUNT(*)                                        AS con_alt,
    SUM(CHAR_LENGTH(TRIM(meta_value)) = 0)          AS alt_vacio,
    SUM(CHAR_LENGTH(TRIM(meta_value)) > 0)          AS alt_con_texto
FROM dc8_postmeta
WHERE meta_key = '_wp_attachment_image_alt';

SELECT '===== 12. foto1 CONTRA el sistema de adjuntos =====' AS seccion;
-- foto1 cubre el contenido anterior a 2019, que no tiene adjuntos.
-- Ver reports/audit/content-counts.md.

SELECT
    SUM(foto1 IS NOT NULL AND foto1 <> '')                     AS con_foto1,
    SUM(foto1 LIKE '%/%')                                      AS foto1_con_ruta,
    SUM(foto1 NOT LIKE '%/%' AND foto1 <> '' AND foto1 IS NOT NULL) AS foto1_solo_nombre
FROM dc8_posts
WHERE post_type = 'post';

SELECT '===== 13. SEO por contenido: Yoast (§23) =====' AS seccion;

SELECT meta_key, COUNT(*) AS filas
FROM dc8_postmeta
WHERE meta_key LIKE '\_yoast%'
GROUP BY meta_key
ORDER BY filas DESC;

SELECT '===== 14. CLAVES DE CREDITO EDITORIAL (D-14) =====' AS seccion;
-- Busca cualquier clave que pueda contener autoria o fotografia.

SELECT meta_key, COUNT(*) AS filas
FROM dc8_postmeta
WHERE meta_key LIKE '%autor%'
   OR meta_key LIKE '%foto%'
   OR meta_key LIKE '%credit%'
   OR meta_key LIKE '%colabora%'
GROUP BY meta_key
ORDER BY filas DESC;

SELECT '===== 15. INTEGRIDAD: postmeta huerfano =====' AS seccion;

SELECT COUNT(*) AS huerfanos
FROM dc8_postmeta m
LEFT JOIN dc8_posts p ON p.ID = m.post_id
WHERE p.ID IS NULL;

SELECT '===== FIN =====' AS seccion;
