<?php

namespace Tests\Feature;

use App\Models\Admin;
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
            ->assertSee('id="f-category"', false)
            // The category options are filled by categories.js from /admin/categories, not written by the server.
            ->assertDontSee('<option value="phones">Phones</option>', false)
            ->assertDontSee('<option value="powerbanks">Power Banks</option>', false)
            ->assertDontSee('<option value="Apple">', false);
    }

    public function test_admin_view_renders_the_categories_page(): void
    {
        $response = $this->actingAs($this->makeAdmin(), 'admin')->get(route('admin-view'))->assertOk();

        $response->assertSee('id="nav-categories"', false)
            ->assertSee("showView('categories')", false)
            ->assertSee('id="view-categories"', false)
            ->assertSee('id="category-add-name"', false)
            ->assertSee('id="category-add-icon"', false)
            ->assertSee('id="categories-tbody"', false)
            ->assertSee('id="categories-empty"', false)
            ->assertSee('id="new-category-box"', false)
            ->assertSee('id="new-category-name"', false)
            ->assertSee('value="__new__">+ Add new category…</option>', false)
            ->assertSee('assets/js/categories.js', false)
            ->assertSeeInOrder([
                'id="view-categories"', '>Icon</th>', '>Category</th>', '>Code</th>', '>Products</th>', '>Status</th>', '>Actions</th>',
            ], false);
    }

    public function test_admin_view_renders_the_brand_switch_and_logo_controls(): void
    {
        $response = $this->actingAs($this->makeAdmin(), 'admin')->get(route('admin-view'))->assertOk();

        $response->assertSee('id="brand-add-logo"', false)
            ->assertSee('id="brand-add-preview"', false)
            ->assertSee('id="brand-logo-input"', false)
            ->assertSeeInOrder([
                'id="view-brands"', '>Logo</th>', '>Brand</th>', '>Products</th>', '>Status</th>', '>Actions</th>',
            ], false);
    }

    public function test_admin_view_renders_the_sales_report_page(): void
    {
        $response = $this->actingAs($this->makeAdmin(), 'admin')->get(route('admin-view'))->assertOk();

        $response->assertSee('id="nav-report"', false)
            ->assertSee("showView('report')", false)
            ->assertSee('id="view-report"', false)
            ->assertSee('id="report-date"', false)
            ->assertSee('id="report-month"', false)
            ->assertSee('id="report-brand"', false)
            ->assertSee('aria-label="Report brand"', false)
            ->assertSee('<option value="">All brands</option>', false)
            ->assertSee('id="report-days-tbody"', false)
            ->assertSee('id="report-orders-tbody"', false)
            ->assertSee('id="report-products-tbody"', false)
            ->assertSee('id="admin-main"', false)
            ->assertSee('@media print', false)
            ->assertSee('assets/js/sales-report.js', false);
    }

    /**
     * The dark palette lives in its own stylesheet. It must reach the admin dashboard, only for
     * screens (a printed report stays plain black on white), and never the customer pages. How it
     * looks is checked in a real browser (screenshots plus a contrast scan), not here.
     */
    public function test_admin_view_loads_the_dark_theme_for_screens_only(): void
    {
        $html = $this->actingAs($this->makeAdmin(), 'admin')->get(route('admin-view'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<link[^>]*href="[^"]*assets/css/admin-dark\.css"[^>]*media="screen"#',
            $html,
            'The dashboard must load admin-dark.css with media="screen" so printing is unaffected.'
        );
        $this->assertFileExists(public_path('assets/css/admin-dark.css'));
    }

    public function test_customer_pages_do_not_load_the_admin_dark_theme(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('admin-dark.css', false);
        $this->get(route('shop'))->assertOk()->assertDontSee('admin-dark.css', false);
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

    public function test_an_owners_page_has_the_admins_page_and_shows_the_real_role(): void
    {
        $response = $this->actingAs($this->makeAdmin(), 'admin')->get(route('admin-view'))->assertOk();

        $response->assertSee('id="nav-admins"', false)
            ->assertSee("showView('admins')", false)
            ->assertSee('id="view-admins"', false)
            ->assertSee('id="admin-add-name"', false)
            ->assertSee('id="admin-add-email"', false)
            ->assertSee('id="admin-add-password"', false)
            ->assertSee('id="admin-add-password-toggle"', false)
            ->assertSee('id="admins-tbody"', false)
            ->assertSee('assets/js/admins.js', false)
            ->assertSeeInOrder([
                'id="view-admins"', '>Name</th>', '>Email</th>', '>Role</th>', '>Last sign-in</th>', '>Status</th>', '>Actions</th>',
            ], false)
            ->assertSee('id="admin-role">Owner</p>', false)
            ->assertDontSee('Super Admin');
    }

    public function test_a_staff_members_page_has_no_admins_page_and_says_staff(): void
    {
        $response = $this->actingAs($this->makeStaff(), 'admin')->get(route('admin-view'))->assertOk();

        $response->assertSee('id="admin-role">Staff</p>', false)
            ->assertDontSee('Super Admin')
            // Everything else is still there (staff can use every other page).
            ->assertSee('id="nav-products"', false)
            ->assertSee('id="nav-brands"', false)
            ->assertSee('id="nav-categories"', false)
            ->assertSee('id="nav-history"', false)
            ->assertSee('id="nav-report"', false)
            ->assertSee('id="view-brands"', false)
            // The Admins item and page are not even sent to them.
            ->assertDontSee('id="nav-admins"', false)
            ->assertDontSee("showView('admins')", false)
            ->assertDontSee('id="view-admins"', false)
            ->assertDontSee('id="admin-add-email"', false);
    }

    public function test_the_products_and_inventory_pages_each_have_a_brand_filter(): void
    {
        foreach ([$this->makeAdmin(), $this->makeStaff()] as $admin) {
            $this->actingAs($admin, 'admin')->get(route('admin-view'))->assertOk()
                // Each drop-down sits in its own page, above that page's table.
                ->assertSeeInOrder([
                    'id="view-products"', 'id="products-brand-filter"', 'id="products-tbody"', 'id="products-empty-title"',
                    'id="view-inventory"', 'id="inventory-brand-filter"', 'id="inventory-tbody"', 'id="inventory-empty"', 'id="inventory-empty-title"',
                ], false)
                ->assertSee('onchange="setBrandFilter(this.value)"', false)
                ->assertSee('aria-label="Filter products by brand"', false)
                ->assertSee('aria-label="Filter inventory by brand"', false);
        }
    }

    public function test_every_admin_has_the_my_password_button_and_window(): void
    {
        foreach ([$this->makeAdmin(), $this->makeStaff()] as $admin) {
            $this->actingAs($admin, 'admin')->get(route('admin-view'))->assertOk()
                ->assertSee('id="mypw-btn"', false)
                ->assertSee('id="mypw-modal"', false)
                ->assertSee('id="mypw-current"', false)
                ->assertSee('id="mypw-new"', false)
                ->assertSee('id="mypw-repeat"', false)
                ->assertSee('id="mypw-error"', false)
                ->assertSee('openMyPassword()', false)
                ->assertSee('assets/js/admins.js', false);
        }
    }

    public function test_each_of_the_three_my_password_boxes_has_its_own_show_button(): void
    {
        foreach ([$this->makeAdmin(), $this->makeStaff()] as $admin) {
            $response = $this->actingAs($admin, 'admin')->get(route('admin-view'))->assertOk();

            // Each button sits right after its own box, so Show on one box never opens another.
            $response->assertSeeInOrder([
                'id="mypw-current"', 'id="mypw-current-toggle"',
                'id="mypw-new"', 'id="mypw-new-toggle"',
                'id="mypw-repeat"', 'id="mypw-repeat-toggle"',
                'id="mypw-error"',
            ], false);

            foreach (['current', 'new', 'repeat'] as $box) {
                $response->assertSee("onclick=\"togglePasswordField('mypw-{$box}', this)\"", false)
                    ->assertSee("aria-controls=\"mypw-{$box}\"", false);
            }
        }
    }

    public function test_an_admin_name_with_markup_is_shown_as_plain_text_in_the_sidebar(): void
    {
        $zed = Admin::create(['name' => '<i>Zed</i>', 'email' => 'zed@example.test', 'password' => 'S3cret-pass!'])->fresh();

        $response = $this->actingAs($zed, 'admin')->get(route('admin-view'))->assertOk();

        $response->assertSee('&lt;i&gt;Zed&lt;/i&gt;', false)->assertDontSee('<i>Zed</i>', false);
    }
}
