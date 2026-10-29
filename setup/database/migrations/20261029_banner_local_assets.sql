-- Replace placeholder banner URLs in existing data with a shipped local image.
UPDATE banners
SET image_url = '/img/banner-fresh.svg'
WHERE image_url IS NULL
   OR TRIM(image_url) = ''
   OR LOWER(image_url) LIKE '%example.com%';
