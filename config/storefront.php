<?php

declare(strict_types=1);

return [
    // Defaults used only to seed storefront_home_highlights on first installation.
    'featured' => 'camarao-c-batata-e-aipim',
    'popular' => ['carne-c-aipim', 'batata'],
    'variant_aliases' => [
        'porcao-carne' => 'porcao-de-carne',
    ],
    'addons' => [
        'product_ids' => [117, 74],
        'eligible_subcategories' => ['porcoes'],
    ],
    'fallback_image' => '/assets/images/products/mixed-portion-placeholder.jpg',
    'fallback_images' => [
        'bebidas' => '/assets/images/storefront/drinks-placeholder.jpg',
        'comidas' => '/assets/images/products/mixed-portion-placeholder.jpg',
        'cigarro' => '/assets/images/products/mixed-portion-placeholder.jpg',
        'outros' => '/assets/images/products/mixed-portion-placeholder.jpg',
    ],
    'products' => [
        'camarao-c-batata-e-aipim' => [
            'image' => '/assets/images/storefront/hero-shrimp-placeholder.jpg',
            'description' => 'Camarão temperado e dourado, servido com batata frita e aipim.',
            'related' => ['camarao-c-batata', 'camarao-c-aipim'],
        ],
        'carne-c-aipim' => [
            'image' => '/assets/images/products/mixed-portion-placeholder.jpg',
            'description' => 'Uma das porções da casa, preparada na cozinha no momento do pedido.',
        ],
        'batata' => [
            'image' => '/assets/images/products/fries-placeholder.jpg',
            'description' => 'Porção preparada na cozinha no momento do pedido.',
        ],
    ],
];
