<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Safe to run on every deploy (`php artisan db:seed --force`): it never duplicates, overwrites or
 * brings back anything on a shop that already has data.
 *
 * - The catalog (categories, brands, products) goes in only while the shop has NO products, so a shop
 *   whose owner has edited prices, deleted a product or renamed a category keeps exactly what it has.
 * - The owner account is created only when its email is not an admin yet (see AdminSeeder).
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (Product::exists()) {
            $this->command?->info('The shop already has products, so the catalog was left as it is.');
        } else {
            $this->call([
                CategorySeeder::class,
                BrandSeeder::class,
                ProductSeeder::class,
            ]);
        }

        $this->call(AdminSeeder::class);
    }
}
