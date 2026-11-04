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

    public function test_admin_view_renders_the_sales_report_page(): void
    {
        $response = $this->actingAs($this->makeAdmin(), 'admin')->get(route('admin-view'))->assertOk();

        $response->assertSee('id="nav-report"', false)
            ->assertSee("showView('report')", false)
            ->assertSee('id="view-report"', false)
            ->assertSee('id="report-date"', false)
            ->assertSee('id="report-month"', false)
            ->assertSee('id="report-days-tbody"', false)
            ->assertSee('id="report-orders-tbody"', false)
            ->assertSee('id="report-products-tbody"', false)
            ->assertSee('id="admin-main"', false)
            ->assertSee('@media print', false)
            ->assertSee('assets/js/sales-report.js', false);
    }

    /**
     * Found in review and confirmed by printing the page in Chrome: in dark mode the page behind the
     * report printed dark grey under near-black text, and the flex/overflow layout is a known way for
     * some browsers to cut a long printout after page one. These rules cannot be proven by a feature
     * test (only that they are present); the real check is a print preview, see the plan's checklist.
     */
    public function test_print_rules_keep_a_long_report_complete_and_readable(): void
    {
        $html = $this->actingAs($this->makeAdmin(), 'admin')->get(route('admin-view'))->assertOk()->getContent();

        preg_match('/@media print\s*\{(.*?)\n  \}/s', $html, $matches);
        $this->assertNotEmpty($matches, 'The page should have an @media print block.');
        $print = preg_replace('/\s+/', ' ', $matches[1]);

        foreach ([
            'html, body{',                                    // plain block layout instead of the flex body
            'display:block !important',
            'overflow:visible !important',
            'max-width:none !important',
            '#admin-main, #admin-main main{background:#fff !important;}', // dark mode must not print a dark page
            'backdrop-filter:none !important',
            '#view-report .overflow-hidden, #view-report .overflow-x-auto{overflow:visible !important;}',
            '#view-report tr{break-inside:avoid;}',
            '#view-report thead{display:table-header-group;}', // header row repeats on every printed page
        ] as $rule) {
            $this->assertStringContainsString($rule, $print);
        }
    }
}
