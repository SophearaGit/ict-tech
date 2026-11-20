<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

/**
 * The shop's brands, in display order, all ON. A brand that already exists is skipped, so running this
 * twice adds nothing. No logos: those are image files uploaded from the admin (they live in
 * public/uploads, outside git), so each brand starts without one.
 */
class BrandSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Apple',
            'Samsung',
            'Xiaomi',
            'Sony',
            'Anker',
            'Asus',
            'Dell',
            'ICT',
        ] as $name) {
            Brand::firstOrCreate(['name' => $name]);
        }
    }
}
