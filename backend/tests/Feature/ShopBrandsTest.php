<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class ShopBrandsTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    public function test_shop_lists_only_brands_that_have_products(): void
    {
        $apple = $this->makeBrand('Apple');
        $sony = $this->makeBrand('Sony');
        $this->makeBrand('Nokia');
        $this->makeProduct(['brand_id' => $apple->id]);
        $this->makeProduct(['brand_id' => $sony->id]);

        $this->get(route('shop'))->assertOk()->assertViewHas('brands', function ($brands) {
            $brands = collect($brands);

            return $brands->pluck('label')->all() === ['Apple', 'Sony']
                && $brands->pluck('id')->all() === ['apple', 'sony'];
        });
    }

    public function test_brand_pills_are_sorted_ignoring_case(): void
    {
        foreach (['sony', 'Apple', 'dell'] as $name) {
            $this->makeProduct(['brand_id' => $this->makeBrand($name)->id]);
        }

        $this->get(route('shop'))->assertViewHas('brands', fn ($brands) => collect($brands)->pluck('label')->all()
            === ['Apple', 'dell', 'sony']);
    }

    public function test_category_page_lists_only_that_categorys_brands(): void
    {
        $apple = $this->makeBrand('Apple');
        $sony = $this->makeBrand('Sony');
        $this->makeProduct(['brand_id' => $apple->id, 'category' => 'phones']);
        $this->makeProduct(['brand_id' => $sony->id, 'category' => 'earphones']);

        $this->get(route('shop.category', 'phones'))->assertOk()
            ->assertViewHas('brands', fn ($brands) => collect($brands)->pluck('label')->all() === ['Apple']);
        $this->get(route('shop.category', 'earphones'))->assertOk()
            ->assertViewHas('brands', fn ($brands) => collect($brands)->pluck('label')->all() === ['Sony']);
    }

    private function switchOff(string $brandName): void
    {
        $this->makeBrand($brandName)->forceFill(['is_active' => false])->save();
    }

    public function test_an_off_brand_has_no_button_and_none_of_its_products_show(): void
    {
        $this->makeProduct(['name' => 'iPhone', 'brand_id' => $this->makeBrand('Apple')->id]);
        $this->makeProduct(['name' => 'Galaxy', 'brand_id' => $this->makeBrand('Samsung')->id]);
        $this->switchOff('Samsung');

        foreach ([route('shop'), route('shop.category', 'phones')] as $url) {
            $this->get($url)->assertOk()
                ->assertViewHas('products', fn ($products) => collect($products)->pluck('name')->all() === ['iPhone'])
                ->assertViewHas('brands', fn ($brands) => collect($brands)->pluck('label')->all() === ['Apple']);
        }
    }

    public function test_switching_a_brand_back_on_brings_its_products_back(): void
    {
        $this->makeProduct(['name' => 'Galaxy', 'brand_id' => $this->makeBrand('Samsung')->id]);
        $this->switchOff('Samsung');
        $this->get(route('shop'))->assertViewHas('products', fn ($products) => count($products) === 0);

        $this->makeBrand('Samsung')->forceFill(['is_active' => true])->save();

        $this->get(route('shop'))
            ->assertViewHas('products', fn ($products) => collect($products)->pluck('name')->all() === ['Galaxy'])
            ->assertViewHas('brands', fn ($brands) => collect($brands)->pluck('label')->all() === ['Samsung']);
    }

    public function test_the_button_data_carries_the_logo_url_or_null(): void
    {
        $withLogo = $this->makeBrand('Apple');
        $withLogo->forceFill(['logo' => 'abc123.png'])->save();
        $this->makeProduct(['brand_id' => $withLogo->id]);
        $this->makeProduct(['brand_id' => $this->makeBrand('Sony')->id]);

        $this->get(route('shop'))->assertViewHas('brands', fn ($brands) => collect($brands)->map(fn ($b) => ['label' => $b['label'], 'logo' => $b['logo']])->all() === [
            ['label' => 'Apple', 'logo' => asset('uploads/brands/abc123.png')],
            ['label' => 'Sony', 'logo' => null],
        ]);
    }

    public function test_product_json_carries_the_brand_name(): void
    {
        $this->makeProduct(['brand_id' => $this->makeBrand('Apple')->id]);

        $this->get(route('shop'))->assertViewHas('products', fn ($products) => collect($products)->first()['brand'] === 'Apple');
    }

    public function test_renaming_a_brand_updates_the_shop_but_not_placed_orders(): void
    {
        $apple = $this->makeBrand('Apple');
        $product = $this->makeProduct(['brand_id' => $apple->id]);
        $this->placeOrder($this->makeCustomer(), $product);

        $apple->update(['name' => 'Apple Inc']);

        $this->get(route('shop'))
            ->assertViewHas('products', fn ($products) => collect($products)->first()['brand'] === 'Apple Inc')
            ->assertViewHas('brands', fn ($brands) => collect($brands)->pluck('label')->all() === ['Apple Inc']);
        $this->assertSame('Apple', OrderItem::firstOrFail()->brand);
    }
}
