<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * What a customer sees of the categories: the menu, the cards and tabs, the /shop/{code} pages, and
 * the one rule that a category switched OFF disappears everywhere (like a switched-off brand).
 */
class ShopCategoriesTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private const OLD_FOUR = ['phones', 'earphones', 'powerbanks', 'laptops'];

    private function setActive(string $slug, bool $on): void
    {
        Category::where('slug', $slug)->firstOrFail()->forceFill(['is_active' => $on])->save();
    }

    private function productNames($response): array
    {
        return collect($response->viewData('products'))->pluck('name')->all();
    }

    private function brandLabels($response): array
    {
        return collect($response->viewData('brands'))->pluck('label')->all();
    }

    public function test_the_menu_cards_and_tabs_come_from_the_database(): void
    {
        Category::create(['slug' => 'gaming-mice', 'name' => 'Gaming Mice', 'icon' => '🖱️']);
        $this->makeProduct(['name' => 'Mouse X', 'category' => 'gaming-mice']);

        $this->get('/')->assertOk()
            ->assertSee('Gaming Mice')
            ->assertSee(route('shop.category', 'gaming-mice'), false);

        $shop = $this->get(route('shop'))->assertOk();
        $this->assertSame([...self::OLD_FOUR, 'gaming-mice'], $shop->viewData('categories')->pluck('slug')->all());
        $shop->assertSee("setCategory('gaming-mice')", false);
        $shop->assertSee('slug: "gaming-mice"', false); // the tabs script (CATEGORIES)
        foreach (self::OLD_FOUR as $slug) {
            $shop->assertSee("setCategory('{$slug}')", false);
        }
        $this->assertSame('Acme', $shop->viewData('categories')->firstWhere('slug', 'gaming-mice')['desc']);
    }

    public function test_a_renamed_category_keeps_its_address_and_shows_the_new_name(): void
    {
        $this->makeProduct(['name' => 'iPhone', 'category' => 'phones']);
        Category::where('slug', 'phones')->update(['name' => 'Mobiles']);

        $page = $this->get('/shop/phones')->assertOk()->assertSee('Mobiles');
        $this->assertSame('Mobiles', $page->viewData('category')['label']);
        $this->assertSame(['iPhone'], $this->productNames($page));
        $this->get('/')->assertSee('Mobiles');
    }

    public function test_an_off_category_disappears_everywhere_customers_look(): void
    {
        $customer = $this->makeCustomer();
        $phone = $this->makeProduct(['name' => 'iPhone', 'category' => 'phones']);
        $laptop = $this->makeProduct(['name' => 'Dell XPS', 'category' => 'laptops']);
        $customer->favoriteProducts()->attach([$phone->id, $laptop->id]);

        $this->setActive('laptops', false);

        $this->get('/')->assertDontSee(route('shop.category', 'laptops'), false);
        $shop = $this->get(route('shop'))->assertOk();
        $this->assertSame(['phones', 'earphones', 'powerbanks'], $shop->viewData('categories')->pluck('slug')->all());
        $shop->assertDontSee("setCategory('laptops')", false);
        $this->assertSame(['iPhone'], $this->productNames($shop));
        $favorites = $this->actingAs($customer)->getJson(route('favorites'))->assertOk()->json();
        $this->assertSame(['iPhone'], array_column($favorites, 'name'));
        $this->get('/shop/laptops')->assertNotFound();

        // Switched back ON, everything comes back (the favorite was never lost).
        $this->setActive('laptops', true);

        $this->get('/')->assertSee(route('shop.category', 'laptops'), false);
        $shop = $this->get(route('shop'))->assertOk()->assertSee("setCategory('laptops')", false);
        $this->assertEqualsCanonicalizing(['iPhone', 'Dell XPS'], $this->productNames($shop));
        $favorites = $this->actingAs($customer)->getJson(route('favorites'))->assertOk()->json();
        $this->assertEqualsCanonicalizing(['iPhone', 'Dell XPS'], array_column($favorites, 'name'));
        $this->assertSame(['Dell XPS'], $this->productNames($this->get('/shop/laptops')->assertOk()));
    }

    public function test_an_unknown_category_address_is_404_not_500(): void
    {
        $this->makeProduct(['name' => 'Old Thing', 'category' => 'Apple']);

        foreach (['/shop/nope', '/shop/Apple', '/shop/Phones', '/shop/phones-2'] as $address) {
            $this->get($address)->assertNotFound();
        }
    }

    public function test_a_product_with_an_unknown_old_category_is_still_shown_in_the_shop(): void
    {
        $this->makeProduct(['name' => 'Old Thing', 'category' => 'Apple']);

        $this->assertSame(['Old Thing'], $this->productNames($this->get(route('shop'))->assertOk()));
    }

    public function test_a_category_description_never_names_a_switched_off_brand(): void
    {
        $this->makeProduct(['name' => 'Qux One', 'category' => 'phones', 'brand_id' => $this->makeBrand('Qux')->id]);
        $this->makeProduct(['name' => 'Zorbo One', 'category' => 'phones', 'brand_id' => $this->makeBrand('Zorbo')->id]);
        $this->makeBrand('Zorbo')->forceFill(['is_active' => false])->save();

        $shop = $this->get(route('shop'))->assertOk()->assertDontSee('Zorbo');
        $this->assertSame('Qux', $shop->viewData('categories')->firstWhere('slug', 'phones')['desc']);
        $this->get('/')->assertSee('Qux')->assertDontSee('Zorbo');
        $this->get('/shop/phones')->assertOk()->assertDontSee('Zorbo');
    }

    public function test_a_brand_whose_only_products_are_in_an_off_category_has_no_button(): void
    {
        $this->makeProduct(['name' => 'Qux Phone', 'category' => 'phones', 'brand_id' => $this->makeBrand('Qux')->id]);
        $this->makeProduct(['name' => 'Gizmo Laptop', 'category' => 'laptops', 'brand_id' => $this->makeBrand('Gizmo')->id]);

        $this->setActive('laptops', false);

        $this->assertSame(['Qux'], $this->brandLabels($this->get(route('shop'))->assertOk()));
        $this->assertSame(['Qux'], $this->brandLabels($this->get('/shop/phones')->assertOk()));

        $this->setActive('laptops', true);

        $this->assertSame(['Gizmo', 'Qux'], $this->brandLabels($this->get(route('shop'))->assertOk()));
    }

    public function test_a_product_of_an_off_category_cannot_be_favorited_or_bought(): void
    {
        $customer = $this->makeCustomer();
        $laptop = $this->makeProduct(['name' => 'Dell XPS', 'category' => 'laptops', 'qty' => 5]);
        $this->setActive('laptops', false);

        $this->actingAs($customer)->postJson(route('favorites.toggle'), ['product_id' => $laptop->id])
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'error' => 'This product is no longer available.']);
        $this->assertSame(0, $customer->favoriteProducts()->count());

        $this->actingAs($customer)->postJson(route('checkout'), [
            'phone' => '012 345 678',
            'address' => 'Street 1, Phnom Penh',
            'payment_method' => 'cod',
            'lat' => 11.5564,
            'lng' => 104.9282,
            'items' => [['product_id' => $laptop->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertExactJson(['ok' => false, 'error' => 'Dell XPS is no longer available.']);

        $this->assertSame(0, Order::count());
        $this->assertSame(5, $laptop->fresh()->qty);
    }
}
