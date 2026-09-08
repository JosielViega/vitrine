<?php

declare(strict_types=1);

/* Dados temporários da vitrine: único ponto a substituir na integração com o banco. */
return [
    'isOpen' => true,
    'categories' => [
        'porcoes' => 'Porções',
        'bebidas' => 'Bebidas',
        'acrescimos' => 'Acréscimos',
    ],
    'products' => [
        [
            'id' => 'camarao-batata-aipim', 'name' => 'Camarão c/ Batata ou Aipim',
            'description' => 'Camarões dourados acompanhados de batata frita ou aipim.', 'category' => 'porcoes',
            'variants' => [
                ['id' => 'meia', 'label' => 'Meia', 'price' => 62.00],
                ['id' => 'inteira', 'label' => 'Inteira', 'price' => 75.00],
            ],
        ],
        [
            'id' => 'carne-sol-aipim', 'name' => 'Carne de Sol com Aipim',
            'description' => 'Carne de sol acebolada com aipim macio.', 'category' => 'porcoes',
            'variants' => [
                ['id' => 'meia', 'label' => 'Meia', 'price' => 42.00],
                ['id' => 'inteira', 'label' => 'Inteira', 'price' => 58.00],
            ],
        ],
        [
            'id' => 'calabresa-acebolada', 'name' => 'Calabresa Acebolada',
            'description' => 'Calabresa grelhada com cebola e farofa da casa.', 'category' => 'porcoes',
            'variants' => [['id' => 'unica', 'label' => '', 'price' => 32.00]],
        ],
        [
            'id' => 'batata-frita', 'name' => 'Batata Frita', 'category' => 'porcoes',
            'variants' => [
                ['id' => 'meia', 'label' => 'Meia', 'price' => 18.00],
                ['id' => 'inteira', 'label' => 'Inteira', 'price' => 28.00],
            ],
        ],
        [
            'id' => 'frango-passarinho', 'name' => 'Frango a Passarinho',
            'description' => 'Porção bem sequinha, finalizada com alho crocante.', 'category' => 'porcoes',
            'variants' => [['id' => 'unica', 'label' => '', 'price' => 38.00]],
        ],
        [
            'id' => 'cerveja-lata', 'name' => 'Cerveja em Lata',
            'description' => 'Consulte as opções disponíveis no balcão.', 'category' => 'bebidas',
            'variants' => [['id' => 'unica', 'label' => '', 'price' => 6.00]],
        ],
        [
            'id' => 'refrigerante-lata', 'name' => 'Refrigerante em Lata', 'category' => 'bebidas',
            'variants' => [['id' => 'unica', 'label' => '', 'price' => 5.00]],
        ],
        [
            'id' => 'agua-mineral', 'name' => 'Água Mineral', 'category' => 'bebidas',
            'variants' => [
                ['id' => 'sem-gas', 'label' => 'Sem gás', 'price' => 3.00],
                ['id' => 'com-gas', 'label' => 'Com gás', 'price' => 4.00],
            ],
        ],
        [
            'id' => 'suco-polpa', 'name' => 'Suco de Polpa',
            'description' => 'Sabores disponíveis no dia.', 'category' => 'bebidas',
            'variants' => [['id' => 'unica', 'label' => '', 'price' => 7.00]],
        ],
        [
            'id' => 'porcao-farofa', 'name' => 'Porção de Farofa', 'category' => 'acrescimos',
            'variants' => [['id' => 'unica', 'label' => '', 'price' => 4.00]],
        ],
        [
            'id' => 'molho-casa', 'name' => 'Molho da Casa',
            'description' => 'Pote extra do nosso molho especial.', 'category' => 'acrescimos',
            'variants' => [['id' => 'unica', 'label' => '', 'price' => 3.00]],
        ],
        [
            'id' => 'batata-extra', 'name' => 'Batata Extra', 'category' => 'acrescimos',
            'variants' => [['id' => 'unica', 'label' => '', 'price' => 10.00]],
        ],
    ],
];
