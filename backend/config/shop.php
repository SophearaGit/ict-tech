<?php

/*
 * Shop settings. (The categories are no longer listed here: they live in the `categories`
 * table and are managed from the admin's Categories page.)
 */
return [
    // The shop runs on Phnom Penh time (UTC+7, no daylight saving); the app and the database
    // stay on UTC. Day boundaries in the dashboard chart and the sales report use this.
    'timezone' => 'Asia/Phnom_Penh',

    // Brand logos are saved straight into public/ (not Laravel's storage/ folder), so no
    // `storage:link` is needed on the server. `path` is the public URL path of `dir`.
    'brand_logos' => [
        'dir' => public_path('uploads/brands'),
        'path' => 'uploads/brands',
    ],
];
