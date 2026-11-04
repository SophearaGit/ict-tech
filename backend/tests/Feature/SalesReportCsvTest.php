<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Reports\SalesReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/** Shop time is Phnom Penh, UTC+7: 03:15 UTC is 10:15 the same day, 17:00 UTC is midnight of the next. */
class SalesReportCsvTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private ?Admin $admin = null;

    /** One admin per test, even when a test makes several requests. */
    private function actingAsAdmin(): static
    {
        $this->admin ??= $this->makeAdmin();

        return $this->actingAs($this->admin, 'admin');
    }

    private function download(string $period, ?string $date = null): TestResponse
    {
        $query = ['period' => $period] + ($date === null ? [] : ['date' => $date]);

        return $this->actingAsAdmin()->get(route('admin-reports.sales.export', $query))->assertOk();
    }

    /** All rows (header first) of a downloaded body; fgetcsv so quoted line breaks parse correctly. */
    private function rows(TestResponse $response): array
    {
        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'The file should start with a UTF-8 byte-order mark.');

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, substr($body, 3));
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /** Only the products column of a sale that has one line per name. */
    private function productCells(string $date): array
    {
        return array_column(array_slice($this->rows($this->download('day', $date)), 1), 5);
    }

    public function test_download_has_csv_headers_filename_and_bom(): void
    {
        $week = $this->download('week', '2026-11-04');

        $this->assertStringContainsString('text/csv', $week->headers->get('Content-Type'));
        $this->assertStringContainsString('charset=UTF-8', $week->headers->get('Content-Type'));
        $this->assertMatchesRegularExpression(
            '/^attachment; filename="?sales-week-2026-11-02-to-2026-11-08\.csv"?$/',
            $week->headers->get('Content-Disposition'),
        );
        $this->assertSame(SalesReport::CSV_HEADER, $this->rows($week)[0]);
        $this->assertSame(
            ['Date', 'Time', 'Order', 'Status', 'Payment', 'Product', 'Brand', 'Quantity', 'Unit price', 'Line total'],
            SalesReport::CSV_HEADER,
        );

        $day = $this->download('day', '2026-11-05');
        $this->assertMatchesRegularExpression(
            '/filename="?sales-day-2026-11-05-to-2026-11-05\.csv"?$/',
            $day->headers->get('Content-Disposition'),
        );
    }

    public function test_one_row_per_item_with_shop_time_and_exact_cents(): void
    {
        $order = $this->makeSale('2026-11-05 03:15:00', [
            ['name' => 'Phone X', 'brand' => 'Acme', 'price' => '100.00', 'qty' => 2],
            ['name' => 'Cable', 'brand' => 'Acme', 'price' => '0.10', 'qty' => 3],
        ]);

        $rows = $this->rows($this->download('day', '2026-11-05'));

        $this->assertCount(3, $rows);
        $this->assertSame(
            ['2026-11-05', '10:15', $order->number, 'pending', 'cod', 'Phone X', 'Acme', '2', '100.00', '200.00'],
            $rows[1],
        );
        $this->assertSame(
            ['2026-11-05', '10:15', $order->number, 'pending', 'cod', 'Cable', 'Acme', '3', '0.10', '0.30'],
            $rows[2],
        );
    }

    public function test_rows_are_ordered_by_order_time_then_order_then_item(): void
    {
        $line = fn (string $name) => ['name' => $name, 'brand' => 'Acme', 'price' => '1.00', 'qty' => 1];
        $this->makeSale('2026-11-05 05:00:00', [$line('A1'), $line('A2')]); // placed last, created first
        $this->makeSale('2026-11-05 03:00:00', [$line('B1')]);
        $this->makeSale('2026-11-05 03:00:00', [$line('C1')]);               // same minute as B: lower order id first

        $this->assertSame(['B1', 'C1', 'A1', 'A2'], $this->productCells('2026-11-05'));
    }

    public function test_no_customer_data_and_no_order_total_column(): void
    {
        $this->travelTo('2026-11-04 10:00:00');
        $customer = $this->makeCustomer(['name' => 'Sok Dara', 'email' => 'dara.secret@example.test']);
        $this->placeOrder($customer, $this->makeProduct());

        $response = $this->download('day', '2026-11-04');
        $body = $response->streamedContent();

        $this->assertCount(2, $this->rows($response), 'The order should be exported (header + one item).');
        foreach (['Sok Dara', 'dara.secret@example.test', '012 345 678', 'Street 1, Phnom Penh'] as $private) {
            $this->assertStringNotContainsString($private, $body);
        }
        $this->assertNotContains('Total', SalesReport::CSV_HEADER);
        $this->assertNotContains('Phone', SalesReport::CSV_HEADER);
        $this->assertNotContains('Customer', SalesReport::CSV_HEADER);
    }

    public function test_formula_prefixes_are_neutralised(): void
    {
        $line = fn (string $name, string $brand = 'Acme') => ['name' => $name, 'brand' => $brand, 'price' => '1.00', 'qty' => 1];
        $this->makeSale('2026-11-05 03:00:00', [
            $line('=HYPERLINK("x")'),
            $line('+1'),
            $line('-2'),
            $line('@SUM(1)'),
            $line("\tTabbed"),
            $line("\rCarriage"),
            $line('Normal', '=cmd'),
        ]);

        $rows = array_slice($this->rows($this->download('day', '2026-11-05')), 1);

        $this->assertSame(
            ['\'=HYPERLINK("x")', "'+1", "'-2", "'@SUM(1)", "'\tTabbed", "'\rCarriage", 'Normal'],
            array_column($rows, 5),
        );
        $this->assertSame("'=cmd", $rows[6][6]);
    }

    public function test_commas_quotes_and_line_breaks_in_names_round_trip(): void
    {
        $name = "Cable, \"USB-C\"\n2m";
        $this->makeSale('2026-11-05 03:00:00', [['name' => $name, 'brand' => 'Acme', 'price' => '5.00', 'qty' => 1]]);

        $this->assertSame([$name], $this->productCells('2026-11-05'));
    }

    public function test_empty_period_returns_bom_and_header_only(): void
    {
        $rows = $this->rows($this->download('week', '2026-11-04'));

        $this->assertSame([SalesReport::CSV_HEADER], $rows);
    }

    public function test_cancelled_orders_are_not_exported(): void
    {
        $line = fn (string $name) => ['name' => $name, 'brand' => 'Acme', 'price' => '1.00', 'qty' => 1];
        $this->makeSale('2026-11-05 03:00:00', [$line('Kept')], Order::STATUS_PAID);
        $this->makeSale('2026-11-05 03:01:00', [$line('Dropped')], Order::STATUS_CANCELLED);

        $this->assertSame(['Kept'], $this->productCells('2026-11-05'));
    }

    public function test_invalid_requests_get_422_json(): void
    {
        foreach ([['period' => 'year'], ['period' => 'day', 'date' => '2026-02-30'], ['period' => 'day', 'date' => ['x']]] as $query) {
            $this->actingAsAdmin()->get(route('admin-reports.sales.export', $query))
                ->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
        }
    }

    /** Kept apart from the admin tests: actingAs() stays in effect for the rest of a test. */
    public function test_guests_and_customers_are_redirected_to_admin_login(): void
    {
        $url = route('admin-reports.sales.export', ['period' => 'day']);
        $this->get($url)->assertRedirect(route('admin-login'));
        $this->actingAs($this->makeCustomer())->get($url)->assertRedirect(route('admin-login'));
    }
}
