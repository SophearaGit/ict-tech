<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * `php artisan db:seed` is run on every deploy, so it must be safe on a shop that already has data:
 * a new shop gets the catalog and the owner account, an existing shop is left exactly as it is.
 */
class DatabaseSeederTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    /** What the server's .env gives the seeder (ADMIN_NAME / ADMIN_EMAIL / ADMIN_PASSWORD). */
    private function ownerSettings(?string $email, ?string $password, string $name = 'Boss'): void
    {
        config(['shop.owner' => ['name' => $name, 'email' => $email, 'password' => $password]]);
    }

    public function test_a_new_shop_gets_the_categories_brands_and_products(): void
    {
        $this->ownerSettings(null, null);

        $this->seed();

        // The four categories the migration makes, then the extra Charger one, in that order, all ON.
        $categories = Category::orderBy('id')->get();
        $this->assertSame(['phones', 'earphones', 'powerbanks', 'laptops', 'charger'], $categories->pluck('slug')->all());
        $this->assertTrue($categories->every(fn (Category $c) => $c->is_active));

        // Brands are ON and have no logo (logo files are uploads, not code).
        $brands = Brand::orderBy('id')->get();
        $this->assertSame(['Apple', 'Samsung', 'Xiaomi', 'Sony', 'Anker', 'Asus', 'Dell', 'ICT'], $brands->pluck('name')->all());
        $this->assertTrue($brands->every(fn (Brand $b) => $b->is_active && $b->logo === null));

        // 23 real products, none of the test ones, every one linked to a brand and to a real category.
        $this->assertSame(23, Product::count());
        $this->assertSame(0, Product::whereIn('name', ['Iphone 17 pro max', 'Iphone 18 pro max', 'Anker 18 pro max'])->count());
        $this->assertTrue(Product::with('brand')->get()->every(fn (Product $p) => $p->brand !== null));
        $this->assertEmpty(array_diff(Product::pluck('category')->unique()->all(), $categories->pluck('slug')->all()));

        // The shop can show and sell all of them.
        $this->assertSame(23, Product::visible()->count());

        // The shop's own numbers and details come through intact.
        $max = Product::where('name', 'iPhone 15 Pro Max')->firstOrFail();
        $this->assertSame(41, $max->qty);
        $this->assertSame('1199.00', $max->price);
        $this->assertTrue($max->trending);
        $this->assertSame('New', $max->badge);
        $this->assertSame(['A17 Pro', '48MP', 'USB-C', 'Titanium'], $max->specs);
        $this->assertContains('1" sensor', Product::where('name', 'Xiaomi 14 Ultra')->firstOrFail()->specs);

        // No sample customer account is left behind on a real server.
        $this->assertSame(0, User::count());
    }

    public function test_seeding_again_adds_nothing(): void
    {
        $this->ownerSettings('boss@example.test', 'Real-pass-1');

        $this->seed();
        $this->seed();

        $this->assertSame([23, 8, 5, 1], [Product::count(), Brand::count(), Category::count(), Admin::count()]);
    }

    public function test_a_shop_that_already_has_products_keeps_its_own_data(): void
    {
        $this->ownerSettings(null, null);
        $this->seed();

        // The owner has been running the shop: removed things, changed a price.
        Product::where('name', 'iPhone 14')->delete();
        Product::where('name', 'iPhone 15 Pro Max')->update(['price' => 1, 'qty' => 2]);
        Category::where('slug', 'charger')->delete();
        Brand::where('name', 'ICT')->delete();

        $this->seed();

        $this->assertSame(22, Product::count());
        $this->assertNull(Product::where('name', 'iPhone 14')->first());
        $this->assertSame('1.00', Product::where('name', 'iPhone 15 Pro Max')->value('price'));
        $this->assertSame(2, Product::where('name', 'iPhone 15 Pro Max')->value('qty'));
        $this->assertNull(Category::where('slug', 'charger')->first());
        $this->assertNull(Brand::where('name', 'ICT')->first());
    }

    public function test_an_existing_admin_is_never_changed_even_when_the_env_names_the_same_email(): void
    {
        $owner = $this->makeAdmin(); // owner@example.test / S3cret-pass!
        $hash = $owner->password;
        $this->ownerSettings('OWNER@example.test', 'Different-pass-9');

        $this->seed();

        $this->assertSame(1, Admin::count());
        $this->assertSame($hash, $owner->fresh()->password);
        $this->assertTrue(Hash::check('S3cret-pass!', $owner->fresh()->password));
    }

    public function test_no_owner_is_added_when_the_server_already_has_any_admin_whatever_the_env_says(): void
    {
        // The live server's own admin, under a different email than the one in .env.
        $existing = $this->makeAdmin(); // owner@example.test / S3cret-pass!
        $hash = $existing->password;
        $this->ownerSettings('boss@example.test', 'Real-pass-1');

        $this->seed();

        $this->assertSame(1, Admin::count());
        $this->assertNull(Admin::where('email', 'boss@example.test')->first());
        $this->assertSame($hash, $existing->fresh()->password);

        // The same when the only admin is a staff member.
        Admin::query()->delete();
        $this->makeStaff();
        $this->seed();

        $this->assertSame(1, Admin::count());
        $this->assertNull(Admin::where('email', 'boss@example.test')->first());
    }

    public function test_the_owner_from_the_env_is_created_once_with_a_lower_case_email(): void
    {
        $this->ownerSettings('  Boss@Example.TEST ', 'Real-pass-1');
        $this->seed();

        $admin = Admin::firstOrFail();
        $this->assertSame(1, Admin::count());
        $this->assertSame(['Boss', 'boss@example.test'], [$admin->name, $admin->email]);
        $this->assertTrue($admin->isOwner());
        $this->assertTrue($admin->is_active);
        $this->assertNotSame('Real-pass-1', $admin->password);
        $this->assertTrue(Hash::check('Real-pass-1', $admin->password));

        // A second deploy with a different password in .env does not reset the password.
        $this->ownerSettings('boss@example.test', 'Another-pass-2');
        $this->seed();

        $this->assertSame(1, Admin::count());
        $this->assertTrue(Hash::check('Real-pass-1', $admin->fresh()->password));
    }

    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function unusableOwnerSettings(): array
    {
        return [
            'nothing set' => [null, null],
            'no password' => ['boss@example.test', null],
            'no email' => [null, 'Real-pass-1'],
            'the placeholder password from .env.example' => ['boss@example.test', 'change-me'],
            'a password under 8 characters' => ['boss@example.test', 'Short-1'],
        ];
    }

    #[DataProvider('unusableOwnerSettings')]
    public function test_no_owner_is_created_without_usable_settings_but_the_catalog_still_is(?string $email, ?string $password): void
    {
        $this->ownerSettings($email, $password);

        $this->seed();

        $this->assertSame(0, Admin::count());
        $this->assertSame(23, Product::count());
    }
}
