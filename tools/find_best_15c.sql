-- Coordenadas de la aldea desde la que se quiere buscar.
-- El wrapper find_best_15c.sh las inicializa; estos son los valores por defecto.
SET @x = COALESCE(@x, 0);
SET @y = COALESCE(@y, 0);

-- Este servidor usa coordenadas de -100 a 100 (201 casillas por eje).
SET @world_max = 100;
SET @map_width = 2 * @world_max + 1;

WITH candidates AS (
    SELECT
        id,
        x,
        y,
        ROUND(
            SQRT(
                POW(LEAST(ABS(x - @x), @map_width - ABS(x - @x)), 2)
                + POW(LEAST(ABS(y - @y), @map_width - ABS(y - @y)), 2)
            ),
            2
        ) AS distance
    FROM s1_wdata
    WHERE fieldtype = 6
      AND oasistype = 0
      AND occupied = 0
),
oasis_counts AS (
    SELECT
        c.id,
        c.x,
        c.y,
        c.distance,
        COALESCE(SUM(o.oasistype = 12), 0) AS oasis_50,
        COALESCE(SUM(o.oasistype IN (10, 11)), 0) AS oasis_25,
        COALESCE(SUM(
            o.oasistype = 12
            AND COALESCE(od.conqured, 0) = 0
        ), 0) AS oasis_50_free,
        COALESCE(SUM(
            o.oasistype IN (10, 11)
            AND COALESCE(od.conqured, 0) = 0
        ), 0) AS oasis_25_free
    FROM candidates c
    LEFT JOIN s1_wdata o
        ON o.oasistype IN (10, 11, 12)
       -- Coincide con el alcance usado por canConquerOasis().
       AND ABS(o.x - c.x) <= 3
       AND ABS(o.y - c.y) <= 3
    LEFT JOIN s1_odata od
        ON od.wref = o.id
    GROUP BY c.id, c.x, c.y, c.distance
),
scored AS (
    SELECT
        *,
        LEAST(oasis_50, 3) * 50
        + LEAST(
            oasis_25,
            GREATEST(3 - LEAST(oasis_50, 3), 0)
        ) * 25 AS bonus_possible,
        LEAST(oasis_50_free, 3) * 50
        + LEAST(
            oasis_25_free,
            GREATEST(3 - LEAST(oasis_50_free, 3), 0)
        ) * 25 AS bonus_free_now
    FROM oasis_counts
)
SELECT
    id AS wref,
    x,
    y,
    distance,
    oasis_50,
    oasis_25,
    bonus_possible,
    oasis_50_free,
    oasis_25_free,
    bonus_free_now
FROM scored
ORDER BY
    bonus_possible DESC,
    bonus_free_now DESC,
    distance ASC
LIMIT 20;
