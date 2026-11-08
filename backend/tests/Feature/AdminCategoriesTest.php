<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * The admin API behind the Categories page. The four starting categories (phones, earphones,
 * powerbanks, laptops) come from the migration, so every test starts with them.
 */
class AdminCategoriesTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private const ICON_MESSAGE = 'Use a single emoji, for example 📱.';

    private ?Admin $admin = null;

    /** One admin per test, even when a test makes several requests. */
    private function actingAsAdmin(): static
    {
        $this->admin ??= $this->makeAdmin();

        return $this->actingAs($this->admin, 'admin');
    }

    private function category(string $slug): Category
    {
        return Category::where('slug', $slug)->firstOrFail();
    }

    public function test_index_lists_categories_in_creation_order_with_product_counts_by_code(): void
    {
        $off = $this->makeBrand('Nokia');
        $off->forceFill(['is_active' => false])->save();
        $this->makeProduct(['category' => 'phones']);
        $this->makeProduct(['category' => 'phones', 'brand_id' => $off->id]); // hidden from customers, still counted
        $this->makeProduct(['category' => 'laptops']);
        $this->makeProduct(['category' => 'Apple']); // a legacy code: counts for no category

        $response = $this->actingAsAdmin()->getJson(route('admin-categories.index'))->assertOk();

        $this->assertSame(['phones', 'earphones', 'powerbanks', 'laptops'], array_column($response->json(), 'slug'));
        $this->assertSame([2, 0, 0, 1], array_column($response->json(), 'productCount'));
        $this->assertSame(
            ['id' => $this->category('powerbanks')->id, 'slug' => 'powerbanks', 'name' => 'Power Banks', 'icon' => '🔋', 'isActive' => true, 'productCount' => 0],
            $response->json('2'),
        );
    }

    public function test_store_creates_a_category_with_an_icon(): void
    {
        $response = $this->actingAsAdmin()->postJson(route('admin-categories.store'), ['name' => 'Gaming Mice', 'icon' => '🖱️'])
            ->assertCreated();

        $this->assertSame('Gaming Mice', $response->json('name'));
        $this->assertSame('gaming-mice', $response->json('slug'));
        $this->assertSame('🖱️', $response->json('icon'));
        $this->assertTrue($response->json('isActive'));
        $this->assertSame(0, $response->json('productCount'));
        $this->assertSame(5, Category::count());
    }

    public function test_store_without_an_icon_uses_the_default_box(): void
    {
        $this->actingAsAdmin()->postJson(route('admin-categories.store'), ['name' => 'Cables'])
            ->assertCreated()->assertJsonPath('icon', '📦');
        $this->actingAsAdmin()->postJson(route('admin-categories.store'), ['name' => 'Chargers', 'icon' => ''])
            ->assertCreated()->assertJsonPath('icon', '📦');
    }

    public function test_the_code_comes_from_the_name_and_is_made_unique(): void
    {
        $first = $this->actingAsAdmin()->postJson(route('admin-categories.store'), ['name' => 'Gaming Mice'])
            ->assertCreated()->json('slug');
        $second = $this->actingAsAdmin()->postJson(route('admin-categories.store'), ['name' => 'Gaming-Mice'])
            ->assertCreated()->json('slug');
        $third = $this->actingAsAdmin()->postJson(route('admin-categories.store'), ['name' => 'Gaming  Mice.'])
            ->assertCreated()->json('slug');

        $this->assertSame('gaming-mice', $first);
        $this->assertSame('gaming-mice-2', $second);
        $this->assertSame('gaming-mice-3', $third);
    }

    public function test_a_name_with_no_latin_letters_gets_a_random_code(): void
    {
        $slug = $this->actingAsAdmin()->postJson(route('admin-categories.store'), ['name' => 'ខ្មែរ'])
            ->assertCreated()->json('slug');

        $this->assertMatchesRegularExpression('/^c[0-9a-f]{8}$/', $slug);
    }

    public function test_a_long_name_gives_a_code_of_at_most_50_characters_even_with_a_suffix(): void
    {
        // Both names are 50 characters and make the same 50-character code (a space and a hyphen both become "-").
        $spaced = str_repeat('a', 24).' '.str_repeat('a', 25);
        $hyphened = str_repeat('a', 24).'-'.str_repeat('a', 25);
        $first = $this->actingAsAdmin()->postJson(route('admin-categories.store'), ['name' => $spaced])
            ->assertCreated()->json('slug');
        $second = $this->actingAsAdmin()->postJson(route('admin-categories.store'), ['name' => $hyphened])
            ->assertCreated()->json('slug');

        $this->assertSame($hyphened, $first);
        $this->assertSame(50, strlen($first));
        $this->assertSame(substr($hyphened, 0, 48).'-2', $second);
        $this->assertSame(50, strlen($second));
    }

    public function test_store_rejects_duplicates_ignoring_case_and_padding(): void
    {
        foreach (['phones', '  PHONES ', 'Power Banks'] as $duplicate) {
            $this->actingAsAdmin()->postJson(route('admin-categories.store'), ['name' => $duplicate])
                ->assertUnprocessable()->assertJsonValidationErrors(['name' => 'That category already exists.']);
        }

        $this->assertSame(4, Category::count());
    }

    public function test_store_rejects_bad_names(): void
    {
        foreach (['', str_repeat('a', 51), '<b>x', "O'Neil", 'a_b'] as $badName) {
            $this->actingAsAdmin()->postJson(route('admin-categories.store'), ['name' => $badName])
                ->assertUnprocessable()->assertJsonValidationErrors(['name']);
        }

        $this->assertSame(4, Category::count());
    }

    public function test_store_rejects_bad_icons_with_a_plain_message(): void
    {
        foreach (['<script>alert(1)</script>', 'abc', '7', 'a&b', '"x"', str_repeat('📱', 17)] as $badIcon) {
            $this->actingAsAdmin()->postJson(route('admin-categories.store'), ['name' => 'Cables', 'icon' => $badIcon])
                ->assertUnprocessable()->assertJsonValidationErrors(['icon' => self::ICON_MESSAGE]);
        }

        $this->assertSame(4, Category::count());
    }

    public function test_renaming_keeps_the_code(): void
    {
        $phones = $this->category('phones');

        $this->actingAsAdmin()->putJson(route('admin-categories.update', $phones), ['name' => 'Mobiles'])
            ->assertOk()->assertJsonPath('name', 'Mobiles')->assertJsonPath('slug', 'phones');

        $this->assertSame('phones', $phones->fresh()->slug);
        $this->assertSame('Mobiles', $phones->fresh()->name);
    }

    public function test_rename_allows_a_case_only_change_but_not_a_name_that_is_taken(): void
    {
        $phones = $this->category('phones');

        $this->actingAsAdmin()->putJson(route('admin-categories.update', $phones), ['name' => 'PHONES'])
            ->assertOk()->assertJsonPath('name', 'PHONES');
        $this->actingAsAdmin()->putJson(route('admin-categories.update', $phones), ['name' => 'laptops'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name' => 'That category already exists.']);

        $this->assertSame('PHONES', $phones->fresh()->name);
    }

    public function test_the_icon_can_be_changed_and_reset(): void
    {
        $phones = $this->category('phones');

        $this->actingAsAdmin()->putJson(route('admin-categories.update', $phones), ['icon' => '📲'])
            ->assertOk()->assertJsonPath('icon', '📲')->assertJsonPath('name', 'Phones');
        $this->assertSame('📲', $phones->fresh()->icon);

        $this->actingAsAdmin()->putJson(route('admin-categories.update', $phones), ['icon' => ''])
            ->assertOk()->assertJsonPath('icon', '📦');

        $this->actingAsAdmin()->putJson(route('admin-categories.update', $phones), ['icon' => '<img src=x>'])
            ->assertUnprocessable()->assertJsonValidationErrors(['icon' => self::ICON_MESSAGE]);
        $this->assertSame('📦', $phones->fresh()->icon);
    }

    public function test_a_category_can_be_switched_off_and_on_without_touching_anything_else(): void
    {
        $phones = $this->category('phones');

        $this->actingAsAdmin()->putJson(route('admin-categories.update', $phones), ['is_active' => false])
            ->assertOk()->assertJsonPath('isActive', false)->assertJsonPath('name', 'Phones')->assertJsonPath('icon', '📱');
        $this->assertFalse($phones->fresh()->is_active);

        $this->actingAsAdmin()->putJson(route('admin-categories.update', $phones), ['is_active' => true])
            ->assertOk()->assertJsonPath('isActive', true);
        $this->assertTrue($phones->fresh()->is_active);
    }

    public function test_the_switch_must_be_a_boolean(): void
    {
        $phones = $this->category('phones');

        $this->actingAsAdmin()->putJson(route('admin-categories.update', $phones), ['is_active' => 'maybe'])
            ->assertUnprocessable()->assertJsonValidationErrors(['is_active']);
        $this->assertTrue($phones->fresh()->is_active);
    }

    public function test_update_needs_something_to_change(): void
    {
        $this->actingAsAdmin()->putJson(route('admin-categories.update', $this->category('phones')), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_the_code_can_never_be_changed_from_the_outside(): void
    {
        $phones = $this->category('phones');

        $this->actingAsAdmin()->putJson(route('admin-categories.update', $phones), ['name' => 'Mobiles', 'slug' => 'hacked'])
            ->assertOk()->assertJsonPath('slug', 'phones');
        $this->assertSame('phones', $phones->fresh()->slug);
    }

    public function test_destroy_removes_an_empty_category(): void
    {
        $laptops = $this->category('laptops');

        $this->actingAsAdmin()->deleteJson(route('admin-categories.destroy', $laptops))
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(['phones', 'earphones', 'powerbanks'], Category::orderBy('id')->pluck('slug')->all());
    }

    public function test_destroy_is_blocked_while_any_product_uses_the_code_even_a_hidden_one(): void
    {
        $off = $this->makeBrand('Nokia');
        $off->forceFill(['is_active' => false])->save();
        $this->makeProduct(['category' => 'laptops', 'brand_id' => $off->id]);
        $this->makeProduct(['category' => 'phones']);
        $this->makeProduct(['category' => 'phones']);

        $this->actingAsAdmin()->deleteJson(route('admin-categories.destroy', $this->category('laptops')))
            ->assertUnprocessable()
            ->assertJson(['ok' => false, 'error' => 'Laptops still has 1 product. Move or delete them first.']);
        $this->actingAsAdmin()->deleteJson(route('admin-categories.destroy', $this->category('phones')))
            ->assertUnprocessable()
            ->assertJson(['ok' => false, 'error' => 'Phones still has 2 products. Move or delete them first.']);

        $this->assertSame(4, Category::count());
    }

    public function test_a_renamed_category_is_still_protected_by_its_code(): void
    {
        $phones = $this->category('phones');
        $this->makeProduct(['category' => 'phones']);
        $this->actingAsAdmin()->putJson(route('admin-categories.update', $phones), ['name' => 'Mobiles'])->assertOk();

        $this->actingAsAdmin()->deleteJson(route('admin-categories.destroy', $phones))
            ->assertUnprocessable()->assertJsonPath('ok', false);
    }

    public function test_guests_and_customers_are_redirected_to_admin_login(): void
    {
        $phones = $this->category('phones');
        $customer = $this->makeCustomer();

        $this->getJson(route('admin-categories.index'))->assertRedirect(route('admin-login'));
        $this->postJson(route('admin-categories.store'), ['name' => 'X'])->assertRedirect(route('admin-login'));
        $this->putJson(route('admin-categories.update', $phones), ['name' => 'Y'])->assertRedirect(route('admin-login'));
        $this->deleteJson(route('admin-categories.destroy', $phones))->assertRedirect(route('admin-login'));

        $this->actingAs($customer)->getJson(route('admin-categories.index'))->assertRedirect(route('admin-login'));
        $this->actingAs($customer)->postJson(route('admin-categories.store'), ['name' => 'X'])
            ->assertRedirect(route('admin-login'));
        $this->actingAs($customer)->putJson(route('admin-categories.update', $phones), ['name' => 'Y'])
            ->assertRedirect(route('admin-login'));
        $this->actingAs($customer)->deleteJson(route('admin-categories.destroy', $phones))
            ->assertRedirect(route('admin-login'));

        $this->assertSame('Phones', $phones->fresh()->name);
        $this->assertSame(4, Category::count());
    }
}
