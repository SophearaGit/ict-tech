<?php

/*
 * Shop categories — single source of truth for the nav "Shop" dropdown, the
 * /shop/{category} routes, and the category pills on the shop page. The slug
 * must match the `products.category` column values.
 */
return [
    // The shop runs on Phnom Penh time (UTC+7, no daylight saving); the app and the database
    // stay on UTC. Day boundaries in the dashboard chart and the sales report use this.
    'timezone' => 'Asia/Phnom_Penh',

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
