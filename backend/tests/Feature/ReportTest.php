<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected Warehouse $warehouse;
    protected Category $category;
    protected Product $product1;
    protected Product $product2;
    protected PaymentMethod $cashMethod;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $reportPermission = Permission::create([
            'name' => 'reports.view',
            'display_name' => 'View Reports',
            'group' => 'reports',
        ]);

        // Create admin role with permissions
        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'is_system' => true,
        ]);
        $adminRole->permissions()->attach($reportPermission->id);

        // Create admin user
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->roles()->attach($adminRole->id);

        // Create regular user without permissions
        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Create warehouse
        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'is_active' => true,
            'is_default' => true,
        ]);

        // Create category
        $this->category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        // Create products
        $this->product1 = Product::create([
            'name' => 'Test Product 1',
            'sku' => 'TST-001',
            'price' => '100.000',
            'cost_price' => '60.000',
            'category_id' => $this->category->id,
            'is_active' => true,
            'stock_tracked' => true,
            'min_stock_level' => 10,
        ]);

        $this->product2 = Product::create([
            'name' => 'Test Product 2',
            'sku' => 'TST-002',
            'price' => '50.000',
            'cost_price' => '30.000',
            'category_id' => $this->category->id,
            'is_active' => true,
            'stock_tracked' => true,
            'min_stock_level' => 10,
        ]);

        // Create stock levels
        StockLevel::create([
            'product_id' => $this->product1->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
        ]);

        StockLevel::create([
            'product_id' => $this->product2->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5, // Low stock - below min_stock_level of 10
        ]);

        // Create cash payment method
        $this->cashMethod = PaymentMethod::create([
            'name' => 'Cash',
            'code' => 'CASH',
            'type' => 'cash',
            'is_active' => true,
        ]);
    }

    protected function createSale(int $quantity = 2, ?Carbon $completedAt = null): Sale
    {
        $sale = Sale::create([
            'sale_number' => 'SALE-' . uniqid(),
            'invoice_number' => 'INV-' . uniqid(),
            'idempotency_key' => 'KEY-' . uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'user_id' => $this->admin->id,
            'status' => Sale::STATUS_COMPLETED,
            'subtotal' => $this->product1->price * $quantity,
            'tax_total' => '0.000',
            'discount_amount' => '0.000',
            'total' => $this->product1->price * $quantity,
            'completed_at' => $completedAt ?? now(),
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'product_sku' => $this->product1->sku,
            'quantity' => $quantity,
            'unit_price' => $this->product1->price,
            'line_total' => $this->product1->price * $quantity,
        ]);

        Payment::create([
            'sale_id' => $sale->id,
            'payment_method_id' => $this->cashMethod->id,
            'amount' => $sale->total,
            'tendered' => $sale->total,
            'change' => '0.000',
            'status' => Payment::STATUS_COMPLETED,
        ]);

        return $sale;
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization Tests
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_user_cannot_access_reports(): void
    {
        $response = $this->getJson('/api/reports/daily-sales');

        $response->assertStatus(401);
    }

    public function test_user_without_permission_cannot_access_reports(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/reports/daily-sales');

        $response->assertStatus(403);
    }

    public function test_user_with_permission_can_access_reports(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/reports/daily-sales');

        $response->assertStatus(200);
    }

    /*
    |--------------------------------------------------------------------------
    | Daily Sales Report Tests
    |--------------------------------------------------------------------------
    */

    public function test_daily_sales_report_returns_correct_structure(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createSale(2);
        $this->createSale(3);

        $response = $this->getJson('/api/reports/daily-sales');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'date',
                    'warehouse_id',
                    'summary' => [
                        'total_transactions',
                        'total_revenue',
                        'total_tax',
                        'total_discount',
                        'net_sales',
                        'average_transaction',
                    ],
                    'sales_by_hour',
                    'payment_methods',
                    'refunds',
                ],
            ]);
    }

    public function test_daily_sales_report_calculates_correctly(): void
    {
        Sanctum::actingAs($this->admin);

        // Create 2 sales: 2 units = 200.000, 3 units = 300.000
        $this->createSale(2);
        $this->createSale(3);

        $response = $this->getJson('/api/reports/daily-sales');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(2, $data['summary']['total_transactions']);
        $this->assertEquals(500.0, $data['summary']['total_revenue']);
        $this->assertEquals(250.0, $data['summary']['average_transaction']);
    }

    public function test_daily_sales_filters_by_date(): void
    {
        Sanctum::actingAs($this->admin);

        // Create sale for yesterday
        $this->createSale(2, now()->subDay());
        // Create sale for today
        $this->createSale(3);

        $response = $this->getJson('/api/reports/daily-sales?date=' . now()->toDateString());

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(1, $data['summary']['total_transactions']);
        $this->assertEquals(300.0, $data['summary']['total_revenue']);
    }

    public function test_daily_sales_filters_by_warehouse(): void
    {
        Sanctum::actingAs($this->admin);

        // Create another warehouse
        $otherWarehouse = Warehouse::create([
            'name' => 'Other Warehouse',
            'code' => 'OTH',
            'is_active' => true,
        ]);

        // Create sale for other warehouse
        $otherSale = Sale::create([
            'sale_number' => 'SALE-OTHER',
            'invoice_number' => 'INV-OTHER',
            'idempotency_key' => 'KEY-OTHER',
            'warehouse_id' => $otherWarehouse->id,
            'user_id' => $this->admin->id,
            'status' => Sale::STATUS_COMPLETED,
            'subtotal' => '100.000',
            'tax_total' => '0.000',
            'discount_amount' => '0.000',
            'total' => '100.000',
            'completed_at' => now(),
        ]);

        // Create sale for main warehouse
        $this->createSale(2);

        $response = $this->getJson('/api/reports/daily-sales?warehouse_id=' . $this->warehouse->id);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(1, $data['summary']['total_transactions']);
        $this->assertEquals($this->warehouse->id, $data['warehouse_id']);
    }

    /*
    |--------------------------------------------------------------------------
    | Sales by Product Report Tests
    |--------------------------------------------------------------------------
    */

    public function test_sales_by_product_requires_date_range(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/reports/sales-by-product');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date', 'end_date']);
    }

    public function test_sales_by_product_returns_aggregated_data(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createSale(2);
        $this->createSale(3);

        $response = $this->getJson('/api/reports/sales-by-product?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data); // Only product1
        $this->assertEquals(5, $data[0]['quantity_sold']); // 2 + 3
        $this->assertEquals(500.0, $data[0]['total_revenue']);
        $this->assertEquals(2, $data[0]['transaction_count']);
    }

    public function test_sales_by_product_rejects_too_long_date_range(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/reports/sales-by-product?' . http_build_query([
            'start_date' => now()->subDays(400)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Date range cannot exceed 365 days']);
    }

    /*
    |--------------------------------------------------------------------------
    | Sales by Category Report Tests
    |--------------------------------------------------------------------------
    */

    public function test_sales_by_category_returns_grouped_data(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createSale(2);
        $this->createSale(3);

        $response = $this->getJson('/api/reports/sales-by-category?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data); // Only Electronics category
        $this->assertEquals('Electronics', $data[0]['category_name']);
        $this->assertEquals(5, $data[0]['quantity_sold']);
    }

    /*
    |--------------------------------------------------------------------------
    | Cash Reconciliation Report Tests
    |--------------------------------------------------------------------------
    */

    public function test_cash_reconciliation_returns_correct_totals(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createSale(2); // 200.000
        $this->createSale(3); // 300.000

        $response = $this->getJson('/api/reports/cash-reconciliation');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'date',
                    'cash_transactions',
                    'total_cash_received',
                    'total_cash_sales',
                    'total_change_given',
                    'expected_cash_in_drawer',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(2, $data['cash_transactions']);
        $this->assertEquals(500.0, $data['total_cash_sales']);
    }

    /*
    |--------------------------------------------------------------------------
    | Stock Levels Report Tests
    |--------------------------------------------------------------------------
    */

    public function test_stock_levels_returns_all_products(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/reports/stock-levels');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(2, $data);
    }

    public function test_stock_levels_filters_low_stock(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/reports/stock-levels?low_stock=1');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Only product2 has low stock (5 <= 10 reorder level)
        $this->assertCount(1, $data);
        $this->assertEquals('TST-002', $data[0]['sku']);
    }

    public function test_stock_levels_filters_by_warehouse(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/reports/stock-levels?warehouse_id=' . $this->warehouse->id);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(2, $data);
    }

    /*
    |--------------------------------------------------------------------------
    | Stock Valuation Report Tests
    |--------------------------------------------------------------------------
    */

    public function test_stock_valuation_calculates_correctly(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/reports/stock-valuation');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'warehouse_id',
                    'generated_at',
                    'summary' => [
                        'total_units',
                        'total_value',
                        'item_count',
                    ],
                    'by_category',
                    'items',
                ],
            ]);

        $data = $response->json('data');

        // Product 1: 100 units × 60.000 = 6000.000
        // Product 2: 5 units × 30.000 = 150.000
        // Total: 6150.000
        $this->assertEquals(105, $data['summary']['total_units']);
        $this->assertEquals(6150.0, $data['summary']['total_value']);
    }

    /*
    |--------------------------------------------------------------------------
    | CSV Export Tests
    |--------------------------------------------------------------------------
    */

    public function test_export_daily_sales_returns_csv(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createSale(2);

        $response = $this->get('/api/reports/export/daily-sales');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition');

        // Verify CSV content
        $content = $response->getContent();
        $this->assertStringContainsString('Date', $content);
        $this->assertStringContainsString('Total Transactions', $content);
    }

    public function test_export_stock_levels_returns_csv(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->get('/api/reports/export/stock-levels');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();
        $this->assertStringContainsString('SKU', $content);
        $this->assertStringContainsString('TST-001', $content);
    }

    public function test_export_stock_valuation_returns_csv(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->get('/api/reports/export/stock-valuation');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();
        $this->assertStringContainsString('Unit Cost', $content);
        $this->assertStringContainsString('Line Value', $content);
    }
}
