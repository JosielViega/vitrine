-- One managed image can be shared by multiple real product variants.
-- Apply idempotently with: composer storefront:images-schema

CREATE TABLE storefront_product_images (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(50) NOT NULL,
    width INT UNSIGNED NOT NULL,
    height INT UNSIGNED NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_storefront_product_images_path (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE storefront_product_image_products (
    product_id INT UNSIGNED NOT NULL,
    image_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id),
    KEY idx_storefront_product_image_products_image_id (image_id),
    CONSTRAINT fk_storefront_product_image_products_image
        FOREIGN KEY (image_id) REFERENCES storefront_product_images (id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
