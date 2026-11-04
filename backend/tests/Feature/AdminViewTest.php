<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class AdminViewTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    public function test_admin_view_renders_the_brands_page_and_the_category_selects(): void
    {
        $response = $this->actingAs($this->makeAdmin(), 'admin')->get(route('admin-view'))->assertOk();

        $response->assertSee('id="nav-brands"', false)
            ->assertSee("showView('brands')", false)
            ->assertSee('id="view-brands"', false)
            ->assertSee('id="m-brand"', false)
            ->assertSee('id="m-category"', false)
            ->assertSee('<option value="phones">Phones</option>', false)
            ->assertSee('<option value="powerbanks">Power Banks</option>', false)
            ->assertDontSee('<option value="Apple">', false);
    }
}
