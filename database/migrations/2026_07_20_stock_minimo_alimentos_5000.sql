BEGIN;

UPDATE stock s
SET stock_minimo = 5000,
    actualizado_en = CURRENT_TIMESTAMP
FROM productos p
INNER JOIN categorias c ON c.id_categoria = p.id_categoria
WHERE s.id_producto = p.id_producto
  AND c.maneja_fraccionamiento = TRUE
  AND (s.stock_minimo IS NULL OR s.stock_minimo = 0);

CREATE OR REPLACE FUNCTION aplicar_stock_minimo_fraccionable()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF (NEW.stock_minimo IS NULL OR NEW.stock_minimo = 0)
       AND EXISTS (
           SELECT 1
           FROM productos p
           INNER JOIN categorias c ON c.id_categoria = p.id_categoria
           WHERE p.id_producto = NEW.id_producto
             AND c.maneja_fraccionamiento = TRUE
       ) THEN
        NEW.stock_minimo := 5000;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_aplicar_stock_minimo_fraccionable ON stock;

CREATE TRIGGER trg_aplicar_stock_minimo_fraccionable
BEFORE INSERT ON stock
FOR EACH ROW
EXECUTE FUNCTION aplicar_stock_minimo_fraccionable();

COMMIT;
