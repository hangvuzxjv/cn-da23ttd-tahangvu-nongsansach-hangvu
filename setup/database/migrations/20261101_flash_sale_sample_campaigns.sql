-- Back up the database before applying. This migration is safe to run repeatedly.
-- Do not run schema.sql against a database containing existing data.

INSERT INTO flash_sales (name, starts_at, ends_at, status)
SELECT 'Flash Sale rau củ cuối tuần',
       DATE_SUB(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR), INTERVAL 1 HOUR),
       DATE_ADD(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR), INTERVAL 1 DAY),
       'active'
WHERE NOT EXISTS (
    SELECT 1 FROM flash_sales WHERE name = 'Flash Sale rau củ cuối tuần'
);

INSERT INTO flash_sales (name, starts_at, ends_at, status)
SELECT 'Flash Sale nông sản tuần tới',
       DATE_ADD(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR), INTERVAL 1 DAY),
       DATE_ADD(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR), INTERVAL 2 DAY),
       'scheduled'
WHERE NOT EXISTS (
    SELECT 1 FROM flash_sales WHERE name = 'Flash Sale nông sản tuần tới'
);

INSERT INTO flash_sale_items
    (flash_sale_id, product_id, sale_price, quota, sold_quantity, per_user_limit, sort_order)
SELECT fs.id, p.id, GREATEST(1, ROUND(COALESCE(NULLIF(p.sale_price, 0), p.price) * 0.8)),
       20, 0, 2, 0
FROM flash_sales fs
JOIN products p ON p.id = 2 AND p.status = 'active' AND p.deleted_at IS NULL
WHERE fs.name = 'Flash Sale rau củ cuối tuần'
  AND GREATEST(1, ROUND(COALESCE(NULLIF(p.sale_price, 0), p.price) * 0.8))
      < COALESCE(NULLIF(p.sale_price, 0), p.price)
  AND NOT EXISTS (
      SELECT 1 FROM flash_sale_items fsi
      WHERE fsi.flash_sale_id = fs.id AND fsi.product_id = p.id
  );

INSERT INTO flash_sale_items
    (flash_sale_id, product_id, sale_price, quota, sold_quantity, per_user_limit, sort_order)
SELECT fs.id, p.id, GREATEST(1, ROUND(COALESCE(NULLIF(p.sale_price, 0), p.price) * 0.8)),
       20, 0, 2, 0
FROM flash_sales fs
JOIN products p ON p.id = 3 AND p.status = 'active' AND p.deleted_at IS NULL
WHERE fs.name = 'Flash Sale nông sản tuần tới'
  AND GREATEST(1, ROUND(COALESCE(NULLIF(p.sale_price, 0), p.price) * 0.8))
      < COALESCE(NULLIF(p.sale_price, 0), p.price)
  AND NOT EXISTS (
      SELECT 1 FROM flash_sale_items fsi
      WHERE fsi.flash_sale_id = fs.id AND fsi.product_id = p.id
  );
