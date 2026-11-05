-- Agrega marca, utilidad y referencia para el nivel de stock.
-- Ejecutar una sola vez sobre una base existente.
ALTER TABLE medications
    ADD COLUMN brand VARCHAR(180) NULL AFTER name,
    ADD COLUMN purpose TEXT NULL AFTER brand,
    ADD COLUMN last_restock_quantity INT NOT NULL DEFAULT 0 AFTER quantity;

-- Para el inventario ya existente, toma la cantidad actual como referencia inicial.
UPDATE medications
SET last_restock_quantity = quantity;
