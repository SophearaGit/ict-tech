<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The shop's categories: [code, name, icon], in display order (a new category gets the next id).
 * The migration already creates the first four, so on a normal server only the extra ones are added.
 * A category whose code or name is already taken is skipped, so running this twice adds nothing.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['phones', 'Phones', '📱'],
            ['earphones', 'Earphones', '🎧'],
            ['powerbanks', 'Power Banks', '🔋'],
            ['laptops', 'Laptops', '💻'],
            ['charger', 'Charger', '📦'],
        ] as [$slug, $name, $icon]) {
            if (Category::where('slug', $slug)->orWhere('name', $name)->exists()) {
                continue;
            }

            Category::create(['slug' => $slug, 'name' => $name, 'icon' => $icon]); // is_active defaults to ON
        }
    }
}
