<?php

namespace Tests\Feature;

use App\Models\Brand;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * DatabaseMigrations (not RefreshDatabase) so a test can roll the three brand
 * migrations back, insert legacy rows with a text brand, and migrate forward again
 * without a wrapping transaction.
 */
class BrandsMigrationTest extends TestCase
{
    use DatabaseMigrations, MakesShopData;

    private const CREATE_MIGRATION = '2026_11_04_100000_create_brands_table_and_add_brand_id_to_products.php';

    private const BACKFILL_MIGRATION = '2026_11_04_100100_backfill_product_brands.php';

    /** Back to the legacy shape: products.brand is a text column and there is no brands table. */
    private function rollBackBrandMigrations(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 3])->assertExitCode(0);

        $this->assertTrue(Schema::hasColumn('products', 'brand'));
        $this->assertFalse(Schema::hasTable('brands'));
    }

    private function migrateForward(): void
    {
        $this->artisan('migrate')->assertExitCode(0);
    }

    private function insertLegacyProduct(string $brand): int
    {
        return DB::table('products')->insertGetId([
            'name' => 'Legacy '.$brand,
            'brand' => $brand,
            'category' => 'phones',
            'price' => '100.00',
            'qty' => 1,
            'trending' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_backfill_merges_case_variants_and_links_every_product(): void
    {
        $this->rollBackBrandMigrations();
        $ids = [
            $this->insertLegacyProduct('Apple'),
            $this->insertLegacyProduct('Apple'),
            $this->insertLegacyProduct('apple'),
            $this->insertLegacyProduct('Sony'),
        ];

        $this->migrateForward();

        $this->assertSame(['Apple', 'Sony'], Brand::orderBy('name')->pluck('name')->all());
        $this->assertSame(0, DB::table('products')->whereNull('brand_id')->count());
        $lowercaseRow = DB::table('products')->find($ids[2]);
        $this->assertSame('Apple', Brand::find($lowercaseRow->brand_id)->name);
        $this->assertFalse(Schema::hasColumn('products', 'brand'));
    }

    public function test_a_tie_goes_to_the_spelling_on_the_lowest_product_id(): void
    {
        $this->rollBackBrandMigrations();
        $this->insertLegacyProduct('apple');
        $this->insertLegacyProduct('Apple');

        $this->migrateForward();

        $this->assertSame(['apple'], Brand::pluck('name')->all());
    }

    public function test_names_are_trimmed_before_grouping(): void
    {
        $this->rollBackBrandMigrations();
        $this->insertLegacyProduct('  Sony ');
        $this->insertLegacyProduct('Sony');

        $this->migrateForward();

        $this->assertSame(['Sony'], Brand::pluck('name')->all());
        $this->assertSame(0, DB::table('products')->whereNull('brand_id')->count());
    }

    public function test_backfill_refuses_a_legacy_brand_name_longer_than_fifty_characters_and_changes_nothing(): void
    {
        $this->rollBackBrandMigrations();
        // Only the first migration: brands exists and products.brand_id is still empty.
        $this->artisan('migrate', ['--path' => 'database/migrations/'.self::CREATE_MIGRATION])->assertExitCode(0);
        $this->insertLegacyProduct('Apple');
        $this->insertLegacyProduct(str_repeat('x', 51));

        $stopped = null;
        try {
            (require database_path('migrations/'.self::BACKFILL_MIGRATION))->up();
        } catch (RuntimeException $e) {
            $stopped = $e;
        }

        $this->assertNotNull($stopped, 'A brand name that does not fit in brands.name should stop the backfill.');
        $this->assertStringContainsString('longer than 50 characters', $stopped->getMessage());
        $this->assertSame(0, Brand::count());
        $this->assertSame(0, DB::table('products')->whereNotNull('brand_id')->count());
    }

    public function test_legacy_names_outside_the_allowed_characters_are_kept(): void
    {
        $this->rollBackBrandMigrations();
        $this->insertLegacyProduct('Black & Decker');

        $this->migrateForward();

        $this->assertSame(['Black & Decker'], Brand::pluck('name')->all());
    }

    public function test_down_restores_the_text_column(): void
    {
        $dell = $this->makeBrand('Dell');
        $this->makeProduct(['brand_id' => $dell->id]);

        $this->rollBackBrandMigrations();

        $this->assertSame('Dell', DB::table('products')->value('brand'));
    }

    public function test_the_database_refuses_to_delete_a_brand_that_has_products(): void
    {
        $product = $this->makeProduct();

        try {
            Brand::findOrFail($product->brand_id)->delete();
            $this->fail('Deleting a brand that still has products should have been refused by the database.');
        } catch (QueryException) {
            // expected: restrictOnDelete
        }

        $this->assertNotNull($product->fresh());
    }
}
