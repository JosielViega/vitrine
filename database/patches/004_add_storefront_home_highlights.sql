CREATE TABLE IF NOT EXISTS storefront_home_highlights (
    id TINYINT UNSIGNED NOT NULL,
    featured_product_slug VARCHAR(255) NOT NULL,
    popular_product_1_slug VARCHAR(255) NULL,
    popular_product_2_slug VARCHAR(255) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
