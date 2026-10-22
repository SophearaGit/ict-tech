<?php

/*
 * Shop categories — single source of truth for the nav "Shop" dropdown, the
 * /shop/{category} routes, and the category pills on the shop page. The slug
 * must match the `products.category` column values.
 */
return [
    'categories' => [
        'phones' => [
            'label' => 'Phones',
            'icon' => '📱',
            'desc' => 'Apple · Samsung · Xiaomi',
        ],
        'earphones' => [
            'label' => 'Earphones',
            'icon' => '🎧',
            'desc' => 'Apple · Samsung · Sony · Anker',
        ],
        'powerbanks' => [
            'label' => 'Power Banks',
            'icon' => '🔋',
            'desc' => 'Anker · Xiaomi · Jtrust',
        ],
        'laptops' => [
            'label' => 'Laptops',
            'icon' => '💻',
            'desc' => 'Apple · Asus · Dell',
        ],
    ],
];
