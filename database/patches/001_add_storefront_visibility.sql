-- Public storefront visibility is independent from the operational `active` flag.
-- Apply with: composer storefront:visibility-schema

ALTER TABLE categories
    ADD COLUMN storefront_visible TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE subcategories
    ADD COLUMN storefront_visible TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE products
    ADD COLUMN storefront_visible TINYINT(1) NOT NULL DEFAULT 1;
