<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * The two columns that give a brand an ON/OFF switch and a logo, and the one rule that decides
 * which products a customer may see. DatabaseMigrations (not RefreshDatabase) because one test
 * runs the migration's down().
 */
class BrandStatusAndLogoColumnsTest extends TestCase
{
    use DatabaseMigrations, MakesShopData;

    private function switchOff(Brand $brand): Brand
    {
        $brand->forceFill(['is_active' => false])->save();

        return $brand;
    }

    public function test_a_new_brand_is_on_with_no_logo(): void
    {
        $brand = Brand::create(['name' => 'Acme'])->fresh();

        $this->assertTrue($brand->is_active);
        $this->assertNull($brand->logo);
        $this->assertNull($brand->logo_url);
    }

    public function test_logo_url_points_into_the_uploads_folder(): void
    {
        $brand = Brand::create(['name' => 'Acme']);
        $brand->forceFill(['logo' => 'abc123.png'])->save();

        $this->assertSame(asset('uploads/brands/abc123.png'), $brand->fresh()->logo_url);
    }

    public function test_active_scope_selects_only_brands_that_are_on(): void
    {
        $this->makeBrand('Apple');
        $this->switchOff($this->makeBrand('Nokia'));

        $this->assertSame(['Apple'], Brand::active()->pluck('name')->all());
    }

    public function test_visible_scope_selects_only_products_of_brands_that_are_on(): void
    {
        $on = $this->makeBrand('Apple');
        $off = $this->switchOff($this->makeBrand('Nokia'));
        $this->makeProduct(['name' => 'iPhone', 'brand_id' => $on->id]);
        $this->makeProduct(['name' => '3310', 'brand_id' => $off->id]);

        $this->assertSame(['iPhone'], Product::visible()->pluck('name')->all());
    }

    public function test_down_removes_both_columns(): void
    {
        // Through the real rollback command, which records it: DatabaseMigrations rolls everything back
        // again when the test ends, and a hand-called down() would make that second rollback fail.
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

        $this->assertFalse(Schema::hasColumn('brands', 'is_active'));
        $this->assertFalse(Schema::hasColumn('brands', 'logo'));
    }
}
