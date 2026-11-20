<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_brands_and_links_every_product(): void
    {
        $this->seed(ProductSeeder::class);

        $this->assertSame(23, Product::count());
        $this->assertSame(
            ['Anker', 'Apple', 'Asus', 'Dell', 'Samsung', 'Sony', 'Xiaomi'],
            Brand::orderBy('name')->pluck('name')->all(),
        );
        $this->assertTrue(Product::with('brand')->get()->every(fn (Product $p) => $p->brand !== null));
    }

    public function test_running_the_seeder_twice_never_duplicates_a_product(): void
    {
        $this->seed(ProductSeeder::class);
        $this->seed(ProductSeeder::class);

        $this->assertSame(23, Product::count());
        $this->assertSame(7, Brand::count());
    }
}
