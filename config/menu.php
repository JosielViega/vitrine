<?php

declare(strict_types=1);

/* Dados temporários: único ponto a substituir na futura integração com o banco. */
return [
    'isOpen' => true,
    'categories' => [
        'porcoes' => 'Porções',
        'bebidas' => 'Bebidas',
        'sucos' => 'Sucos',
    ],
    'products' => [
        [
            'id' => 'camarao-batata-aipim', 'name' => 'Camarão c/ Batata ou Aipim',
            'description' => 'Camarão temperado e dourado, servido com batata frita ou aipim.',
            'category' => 'porcoes', 'image' => '/assets/images/storefront/hero-shrimp-placeholder.jpg',
            'variants' => [
                ['id' => 'inteira', 'label' => 'Inteira', 'price' => 75.00],
                ['id' => 'meia', 'label' => 'Meia', 'price' => 62.00],
            ],
        ],
        [
            'id' => 'carne-sol-aipim', 'name' => 'Carne de Sol com Aipim',
            'description' => 'Carne de sol acebolada com aipim macio.', 'category' => 'porcoes',
            'image' => '/assets/images/products/mixed-portion-placeholder.jpg',
            'variants' => [
                ['id' => 'inteira', 'label' => 'Inteira', 'price' => 58.00],
                ['id' => 'meia', 'label' => 'Meia', 'price' => 42.00],
            ],
        ],
        [
            'id' => 'calabresa-acebolada', 'name' => 'Calabresa Acebolada',
            'description' => 'Calabresa grelhada com cebola e farofa da casa.', 'category' => 'porcoes',
            'image' => '/assets/images/products/mixed-portion-placeholder.jpg',
            'variants' => [['id' => 'unica', 'label' => '', 'price' => 32.00]],
        ],
        [
            'id' => 'batata-frita', 'name' => 'Porção de Batata', 'category' => 'porcoes',
            'description' => 'Batatas rústicas, douradas e crocantes.',
            'image' => '/assets/images/products/fries-placeholder.jpg',
            'variants' => [
                ['id' => 'inteira', 'label' => 'Inteira', 'price' => 28.00],
                ['id' => 'meia', 'label' => 'Meia', 'price' => 18.00],
            ],
        ],
        [
            'id' => 'frango-passarinho', 'name' => 'Frango a Passarinho',
            'description' => 'Porção bem sequinha, finalizada com alho crocante.', 'category' => 'porcoes',
            'image' => '/assets/images/products/mixed-portion-placeholder.jpg',
            'variants' => [['id' => 'unica', 'label' => '', 'price' => 38.00]],
        ],
        [
            'id' => 'cerveja-lata', 'name' => 'Cerveja em Lata',
            'description' => 'Consulte as opções disponíveis no balcão.', 'category' => 'bebidas',
            'image' => '/assets/images/storefront/drinks-placeholder.jpg',
            'variants' => [['id' => 'unica', 'label' => '', 'price' => 6.00]],
        ],
        [
            'id' => 'refrigerante-lata', 'name' => 'Refrigerante em Lata', 'category' => 'bebidas',
            'image' => '/assets/images/storefront/drinks-placeholder.jpg',
            'variants' => [['id' => 'unica', 'label' => '', 'price' => 5.00]],
        ],
        [
            'id' => 'agua-mineral', 'name' => 'Água Mineral', 'category' => 'bebidas',
            'image' => '/assets/images/storefront/drinks-placeholder.jpg',
            'variants' => [
                ['id' => 'sem-gas', 'label' => 'Sem gás', 'price' => 3.00],
                ['id' => 'com-gas', 'label' => 'Com gás', 'price' => 4.00],
            ],
        ],
        [
            'id' => 'suco-polpa', 'name' => 'Suco de Polpa',
            'description' => 'Sabores disponíveis no dia.', 'category' => 'sucos',
            'image' => '/assets/images/storefront/drinks-placeholder.jpg',
            'variants' => [['id' => 'unica', 'label' => '', 'price' => 7.00]],
        ],
        [
            'id' => 'suco-laranja', 'name' => 'Suco de Laranja', 'category' => 'sucos',
            'image' => '/assets/images/storefront/drinks-placeholder.jpg',
            'variants' => [['id' => 'unica', 'label' => '', 'price' => 8.00]],
        ],
        [
            'id' => 'suco-maracuja', 'name' => 'Suco de Maracujá', 'category' => 'sucos',
            'image' => '/assets/images/storefront/drinks-placeholder.jpg',
            'variants' => [['id' => 'unica', 'label' => '', 'price' => 8.00]],
        ],
    ],
];
