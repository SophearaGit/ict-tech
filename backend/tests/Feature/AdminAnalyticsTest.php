<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 18:30 UTC on the 15th is already 01:30 on the 16th in Phnom Penh (UTC+7)
        $this->travelTo('2026-03-15 18:30:00');
    }

    private function orderAt(string $utc, string $total = '100.00', string $status = Order::STATUS_PENDING): Order
    {
        return Order::unguarded(fn () => Order::create([
            'status' => $status,
            'payment_method' => 'cod',
            'phone' => '012 345 678',
            'address' => 'Street 1, Phnom Penh',
            'total' => $total,
            'created_at' => $utc,
        ]));
    }

    private function summary()
    {
        return $this->actingAs($this->makeAdmin(), 'admin')->getJson(route('admin-analytics.summary'))->assertOk();
    }

    public function test_returns_fourteen_zero_filled_days_ending_today_in_phnom_penh(): void
    {
        $data = $this->summary()->json();

        $this->assertCount(14, $data['labels']);
        $this->assertSame('2026-03-03', $data['labels'][0]);
        $this->assertSame('2026-03-16', $data['labels'][13]);
        $this->assertSame(array_fill(0, 14, 0), $data['orders']);
        $this->assertSame(array_fill(0, 14, 0), $data['revenue']);
    }

    public function test_day_boundary_follows_phnom_penh_time(): void
    {
        $this->orderAt('2026-03-15 16:59:59', '10.00'); // 23:59:59 on the 15th in Phnom Penh
        $this->orderAt('2026-03-15 17:00:00', '20.00'); // 00:00:00 on the 16th in Phnom Penh

        $data = $this->summary()->json();

        $this->assertSame('2026-03-15', $data['labels'][12]);
        $this->assertSame(1, $data['orders'][12]);
        $this->assertEquals(10, $data['revenue'][12]);
        $this->assertSame(1, $data['orders'][13]);
        $this->assertEquals(20, $data['revenue'][13]);
    }

    public function test_cancelled_orders_are_excluded_and_pending_are_counted(): void
    {
        $this->orderAt('2026-03-15 10:00:00', '100.00', Order::STATUS_PENDING);
        $this->orderAt('2026-03-15 10:00:00', '50.50', Order::STATUS_PAID);
        $this->orderAt('2026-03-15 10:00:00', '20.00', Order::STATUS_DELIVERED);
        $this->orderAt('2026-03-15 10:00:00', '999.00', Order::STATUS_CANCELLED);

        $data = $this->summary()->json();

        $this->assertSame(3, array_sum($data['orders']));
        $this->assertEquals(170.5, array_sum($data['revenue']));
    }

    public function test_revenue_is_summed_in_cents_not_floats(): void
    {
        $this->orderAt('2026-03-15 10:00:00', '0.10');
        $this->orderAt('2026-03-15 10:00:00', '0.20');

        $this->assertSame(0.3, $this->summary()->json('revenue.12'));
    }

    public function test_orders_older_than_fourteen_days_are_excluded(): void
    {
        $this->orderAt('2026-03-02 16:59:59'); // 23:59:59 on 2 March in Phnom Penh: the day before the window
        $this->orderAt('2026-03-02 17:00:00'); // 00:00:00 on 3 March in Phnom Penh: first day of the window

        $data = $this->summary()->json();

        $this->assertSame(1, array_sum($data['orders']));
        $this->assertSame(1, $data['orders'][0]);
    }

    public function test_non_admin_is_redirected_to_admin_login(): void
    {
        $this->getJson(route('admin-analytics.summary'))->assertRedirect(route('admin-login'));

        $this->actingAs($this->makeCustomer())->getJson(route('admin-analytics.summary'))
            ->assertRedirect(route('admin-login'));
    }
}
