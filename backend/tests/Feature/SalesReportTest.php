<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/** Shop time is Phnom Penh, UTC+7 all year: 17:00 UTC is midnight of the next day. */
class SalesReportTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private ?Admin $admin = null;

    /** One admin per test, even when a test makes several requests. */
    private function actingAsAdmin(): static
    {
        $this->admin ??= $this->makeAdmin();

        return $this->actingAs($this->admin, 'admin');
    }

    private function report(string $period, ?string $date = null): array
    {
        $query = ['period' => $period] + ($date === null ? [] : ['date' => $date]);

        return $this->actingAsAdmin()->getJson(route('admin-reports.sales', $query))->assertOk()->json();
    }

    /** A one-line sale whose price is its total. */
    private function sale(string $placedAtUtc, string $total, string $status = Order::STATUS_PENDING): Order
    {
        return $this->makeSale($placedAtUtc, [['name' => 'Item', 'brand' => 'Acme', 'price' => $total, 'qty' => 1]], $status);
    }

    public function test_week_totals_use_shop_time_and_monday_to_sunday_boundaries(): void
    {
        $this->sale('2026-11-04 16:59:59', '10.00');                                  // A: Wed 4 Nov 23:59:59 local
        $this->sale('2026-11-04 17:00:00', '20.00', Order::STATUS_PAID);              // B: Thu 5 Nov 00:00:00 local
        $this->sale('2026-11-08 16:59:59', '30.00', Order::STATUS_DELIVERED);         // C: Sun 8 Nov 23:59:59 local
        $this->sale('2026-11-08 17:00:00', '40.00');                                  // D: Mon 9 Nov 00:00:00 local (next week)
        $this->sale('2026-11-05 03:00:00', '50.00', Order::STATUS_CANCELLED);         // E: cancelled

        $data = $this->report('week', '2026-11-04');

        $this->assertSame('week', $data['period']);
        $this->assertSame('2026-11-04', $data['date']);
        $this->assertSame('2026-11-02', $data['from']);
        $this->assertSame('2026-11-08', $data['to']);
        $this->assertSame(3, $data['totals']['orders']);
        $this->assertSame(3, $data['totals']['items']);
        $this->assertEquals(60, $data['totals']['revenue']);
        $this->assertEquals(20, $data['totals']['averageOrder']);
        $this->assertCount(7, $data['days']);
        $this->assertSame('2026-11-02', $data['days'][0]['date']);
        $this->assertSame([0, 0, 1, 1, 0, 0, 1], array_column($data['days'], 'orders'));
        $this->assertEquals([0, 0, 10, 20, 0, 0, 30], array_column($data['days'], 'revenue'));
        $this->assertSame([], $data['orders']);
    }

    public function test_day_report_lists_orders_oldest_first_in_shop_time(): void
    {
        $this->sale('2026-11-04 16:59:59', '1.00');   // 4 Nov local: not in this day
        $this->makeSale('2026-11-05 03:15:00', [
            ['name' => 'Phone X', 'brand' => 'Acme', 'price' => '100.00', 'qty' => 2],
            ['name' => 'Cable', 'brand' => 'Acme', 'price' => '5.00', 'qty' => 3],
        ], Order::STATUS_PAID, 'aba');                  // 10:15 local, 5 items
        $first = $this->sale('2026-11-04 17:00:00', '20.00'); // 00:00 local
        $this->sale('2026-11-05 05:00:00', '99.00', Order::STATUS_CANCELLED);

        $data = $this->report('day', '2026-11-05');

        $this->assertCount(1, $data['days']);
        $this->assertSame('2026-11-05', $data['days'][0]['date']);
        $this->assertSame(['00:00', '10:15'], array_column($data['orders'], 'time'));
        $this->assertSame(['2026-11-05', '2026-11-05'], array_column($data['orders'], 'date'));
        $this->assertSame($first->number, $data['orders'][0]['number']);
        $this->assertMatchesRegularExpression('/^ORD-\d{6}$/', $data['orders'][1]['number']);
        $this->assertSame('paid', $data['orders'][1]['status']);
        $this->assertSame('aba', $data['orders'][1]['payment_method']);
        $this->assertSame(5, $data['orders'][1]['items']);
        $this->assertEquals(215, $data['orders'][1]['total']);
        $this->assertSame(2, $data['totals']['orders']);
        $this->assertSame(6, $data['totals']['items']);
    }

    public function test_month_covers_every_day_and_shop_time_month_edges(): void
    {
        $leap = $this->report('month', '2028-02-10');
        $this->assertSame('2028-02-01', $leap['from']);
        $this->assertSame('2028-02-29', $leap['to']);
        $this->assertCount(29, $leap['days']);

        $this->sale('2026-10-31 16:59:59', '1.00');   // 31 Oct 23:59:59 local: previous month
        $this->sale('2026-10-31 17:00:00', '2.00');   // 1 Nov 00:00:00 local: in
        $this->sale('2026-11-30 16:59:59', '4.00');   // 30 Nov 23:59:59 local: in
        $this->sale('2026-11-30 17:00:00', '8.00');   // 1 Dec 00:00:00 local: next month

        $data = $this->report('month', '2026-11-15');

        $this->assertSame('2026-11-01', $data['from']);
        $this->assertSame('2026-11-30', $data['to']);
        $this->assertCount(30, $data['days']);
        $this->assertSame(2, $data['totals']['orders']);
        $this->assertEquals(6, $data['totals']['revenue']);
        $this->assertSame(1, $data['days'][0]['orders']);
        $this->assertSame(1, $data['days'][29]['orders']);
    }

    public function test_cancelled_is_excluded_and_pending_paid_delivered_count(): void
    {
        $this->sale('2026-11-05 03:00:00', '1.00', Order::STATUS_PENDING);
        $this->sale('2026-11-05 03:01:00', '2.00', Order::STATUS_PAID);
        $this->sale('2026-11-05 03:02:00', '4.00', Order::STATUS_DELIVERED);
        $this->sale('2026-11-05 03:03:00', '8.00', Order::STATUS_CANCELLED);

        $data = $this->report('day', '2026-11-05');

        $this->assertSame(3, $data['totals']['orders']);
        $this->assertEquals(7, $data['totals']['revenue']);
        $this->assertSame(['pending', 'paid', 'delivered'], array_column($data['orders'], 'status'));
    }

    public function test_money_is_summed_in_whole_cents_and_the_average_is_rounded(): void
    {
        $this->makeSale('2026-11-05 03:00:00', [
            ['name' => 'Sticker', 'brand' => 'Acme', 'price' => '0.10', 'qty' => 3],
            ['name' => 'Pin', 'brand' => 'Acme', 'price' => '0.20', 'qty' => 1],
        ]);

        $data = $this->report('day', '2026-11-05');

        $this->assertEquals(0.5, $data['totals']['revenue']);
        $byName = array_column($data['products'], 'revenue', 'name');
        $this->assertSame(0.3, (float) $byName['Sticker']);
        $this->assertSame(0.2, (float) $byName['Pin']);

        $this->sale('2026-11-06 03:00:00', '3.33');
        $this->sale('2026-11-06 03:01:00', '3.33');
        $this->sale('2026-11-06 03:02:00', '3.34');

        $next = $this->report('day', '2026-11-06');
        $this->assertEquals(10, $next['totals']['revenue']);
        $this->assertSame(3.33, (float) $next['totals']['averageOrder']);
    }

    public function test_empty_period_returns_zeros(): void
    {
        $data = $this->report('week', '2026-11-04');

        $this->assertSame(0, $data['totals']['orders']);
        $this->assertSame(0, $data['totals']['items']);
        $this->assertEquals(0, $data['totals']['revenue']);
        $this->assertEquals(0, $data['totals']['averageOrder']);
        $this->assertCount(7, $data['days']);
        $this->assertSame(array_fill(0, 7, 0), array_column($data['days'], 'orders'));
        $this->assertSame([], $data['orders']);
        $this->assertSame([], $data['products']);
    }

    public function test_products_are_grouped_by_name_and_brand_and_sorted(): void
    {
        $this->makeSale('2026-11-05 03:00:00', [['name' => 'Phone X', 'brand' => 'Acme', 'price' => '100.00', 'qty' => 1]]);
        $this->makeSale('2026-11-05 03:01:00', [
            ['name' => 'Phone X', 'brand' => 'Acme', 'price' => '100.00', 'qty' => 2],
            ['name' => 'Phone X', 'brand' => 'Other', 'price' => '100.00', 'qty' => 1],
            ['name' => 'Cable', 'brand' => 'Acme', 'price' => '5.00', 'qty' => 10],
        ]);

        $products = $this->report('day', '2026-11-05')['products'];

        $this->assertSame(
            [['Phone X', 'Acme', 3, 300.0], ['Phone X', 'Other', 1, 100.0], ['Cable', 'Acme', 10, 50.0]],
            array_map(fn (array $p) => [$p['name'], $p['brand'], $p['quantity'], (float) $p['revenue']], $products),
        );
    }

    public function test_products_with_equal_revenue_and_quantity_sort_by_name(): void
    {
        $this->makeSale('2026-11-05 03:00:00', [
            ['name' => 'Zeta', 'brand' => 'Acme', 'price' => '10.00', 'qty' => 1],
            ['name' => 'Alpha', 'brand' => 'Acme', 'price' => '10.00', 'qty' => 1],
        ]);

        $names = array_column($this->report('day', '2026-11-05')['products'], 'name');

        $this->assertSame(['Alpha', 'Zeta'], $names);
    }

    public function test_a_product_deleted_after_the_sale_is_still_reported(): void
    {
        $this->travelTo('2026-11-04 10:00:00');
        $product = $this->makeProduct();
        $this->placeOrder($this->makeCustomer(), $product);
        $product->delete();

        $products = $this->report('day', '2026-11-04')['products'];

        $this->assertSame('Test Phone', $products[0]['name']);
        $this->assertSame('Acme', $products[0]['brand']);
        $this->assertSame(2, $products[0]['quantity']);
        $this->assertEquals(200, $products[0]['revenue']);
    }

    public function test_default_date_is_today_in_shop_time(): void
    {
        $this->travelTo('2026-11-04 18:30:00'); // already 01:30 on 5 Nov in Phnom Penh
        $this->sale('2026-11-04 17:10:00', '15.00'); // 00:10 on 5 Nov local

        $data = $this->report('day');

        $this->assertSame('2026-11-05', $data['date']);
        $this->assertSame(1, $data['totals']['orders']);
    }

    public static function badRequests(): array
    {
        return [
            'missing period' => [[]],
            'unknown period' => [['period' => 'year']],
            'upper-case period' => [['period' => 'DAY']],
            'impossible date' => [['period' => 'day', 'date' => '2026-02-30']],
            'month 13' => [['period' => 'day', 'date' => '2026-13-01']],
            'no dashes' => [['period' => 'day', 'date' => '20261104']],
            'trailing text' => [['period' => 'day', 'date' => '2026-11-04abc']],
            'too early' => [['period' => 'day', 'date' => '1999-12-31']],
            'too late' => [['period' => 'day', 'date' => '2101-01-01']],
            'array date' => [['period' => 'day', 'date' => ['x']]],
        ];
    }

    #[DataProvider('badRequests')]
    public function test_invalid_requests_get_422_json(array $query): void
    {
        $this->actingAsAdmin()->getJson(route('admin-reports.sales', $query))
            ->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
    }

    public function test_guests_and_customers_are_redirected_to_admin_login(): void
    {
        $url = route('admin-reports.sales', ['period' => 'day']);

        $this->getJson($url)->assertRedirect(route('admin-login'));
        $this->actingAs($this->makeCustomer())->getJson($url)->assertRedirect(route('admin-login'));
    }
}
