<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected Warehouse $warehouse;
    protected Product $product;
    protected PaymentMethod $cashMethod;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $reconcilePermission = Permission::create([
            'name' => 'reconciliation.manage',
            'display_name' => 'Manage Reconciliation',
            'group' => 'reconciliation',
        ]);

        // Create admin role with permissions
        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'is_system' => true,
        ]);
        $adminRole->permissions()->attach($reconcilePermission->id);

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

        // Create product
        $this->product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TST-001',
            'price' => '100.000',
            'cost_price' => '60.000',
            'is_active' => true,
            'stock_tracked' => true,
            'min_stock_level' => 10,
        ]);

        // Create stock
        StockLevel::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50,
        ]);

        // Create cash payment method
        $this->cashMethod = PaymentMethod::create([
            'name' => 'Cash',
            'code' => 'CASH',
            'type' => 'cash',
            'is_active' => true,
        ]);
    }

    protected function createConflictedSale(int $quantity = 5): Sale
    {
        $sale = Sale::create([
            'sale_number' => 'SALE-' . uniqid(),
            'invoice_number' => 'INV-' . uniqid(),
            'idempotency_key' => 'KEY-' . uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'user_id' => $this->admin->id,
            'status' => Sale::STATUS_COMPLETED,
            'subtotal' => $this->product->price * $quantity,
            'tax_total' => '0.000',
            'discount_amount' => '0.000',
            'total' => $this->product->price * $quantity,
            'has_stock_conflict' => true,
            'completed_at' => now(),
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_sku' => $this->product->sku,
            'quantity' => $quantity,
            'unit_price' => $this->product->price,
            'line_total' => $this->product->price * $quantity,
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

    public function test_unauthenticated_user_cannot_access_reconciliation(): void
    {
        $response = $this->getJson('/api/reconciliation/conflicts');

        $response->assertStatus(401);
    }

    public function test_user_without_permission_cannot_access_reconciliation(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/reconciliation/conflicts');

        $response->assertStatus(403);
    }

    public function test_user_with_permission_can_access_reconciliation(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/reconciliation/conflicts');

        $response->assertStatus(200);
    }

    /*
    |--------------------------------------------------------------------------
    | List Conflicts Tests
    |--------------------------------------------------------------------------
    */

    public function test_list_conflicts_returns_only_conflicted_sales(): void
    {
        Sanctum::actingAs($this->admin);

        // Create a normal sale
        $normalSale = Sale::create([
            'sale_number' => 'SALE-NORMAL',
            'invoice_number' => 'INV-NORMAL',
            'idempotency_key' => 'KEY-NORMAL',
            'warehouse_id' => $this->warehouse->id,
            'user_id' => $this->admin->id,
            'status' => Sale::STATUS_COMPLETED,
            'subtotal' => '100.000',
            'tax_total' => '0.000',
            'discount_amount' => '0.000',
            'total' => '100.000',
            'has_stock_conflict' => false,
            'completed_at' => now(),
        ]);

        // Create conflicted sales
        $conflict1 = $this->createConflictedSale(3);
        $conflict2 = $this->createConflictedSale(2);

        $response = $this->getJson('/api/reconciliation/conflicts');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $this->assertEquals(2, $response->json('count'));
    }

    public function test_list_conflicts_filters_by_warehouse(): void
    {
        Sanctum::actingAs($this->admin);

        // Create another warehouse
        $otherWarehouse = Warehouse::create([
            'name' => 'Other Warehouse',
            'code' => 'OTH',
            'is_active' => true,
        ]);

        // Create conflict for other warehouse
        Sale::create([
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
            'has_stock_conflict' => true,
            'completed_at' => now(),
        ]);

        // Create conflict for main warehouse
        $this->createConflictedSale(2);

        $response = $this->getJson('/api/reconciliation/conflicts?warehouse_id=' . $this->warehouse->id);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    /*
    |--------------------------------------------------------------------------
    | Show Conflict Tests
    |--------------------------------------------------------------------------
    */

    public function test_show_conflict_returns_sale_details(): void
    {
        Sanctum::actingAs($this->admin);

        $sale = $this->createConflictedSale(5);

        $response = $this->getJson("/api/reconciliation/{$sale->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $sale->id)
            ->assertJsonPath('data.has_stock_conflict', true);
    }

    public function test_show_nonexistent_conflict_returns_404(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/reconciliation/99999');

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Accept Conflict Tests
    |--------------------------------------------------------------------------
    */

    public function test_accept_conflict_clears_flag(): void
    {
        Sanctum::actingAs($this->admin);

        $sale = $this->createConflictedSale(5);
        $this->assertTrue($sale->has_stock_conflict);

        $response = $this->postJson("/api/reconciliation/{$sale->id}/accept", [
            'notes' => 'Accepted as valid sale',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Conflict resolved - sale accepted');

        $sale->refresh();
        $this->assertFalse($sale->has_stock_conflict);
        $this->assertStringContainsString('Accepted as valid sale', $sale->notes);
    }

    public function test_accept_nonconflict_sale_returns_error(): void
    {
        Sanctum::actingAs($this->admin);

        $sale = Sale::create([
            'sale_number' => 'SALE-OK',
            'invoice_number' => 'INV-OK',
            'idempotency_key' => 'KEY-OK',
            'warehouse_id' => $this->warehouse->id,
            'user_id' => $this->admin->id,
            'status' => Sale::STATUS_COMPLETED,
            'subtotal' => '100.000',
            'tax_total' => '0.000',
            'discount_amount' => '0.000',
            'total' => '100.000',
            'has_stock_conflict' => false,
            'completed_at' => now(),
        ]);

        $response = $this->postJson("/api/reconciliation/{$sale->id}/accept");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'This sale does not have a stock conflict');
    }

    /*
    |--------------------------------------------------------------------------
    | Adjust Conflict Tests
    |--------------------------------------------------------------------------
    */

    public function test_adjust_reduces_quantities_and_restores_stock(): void
    {
        Sanctum::actingAs($this->admin);

        $sale = $this->createConflictedSale(5);
        $saleItem = $sale->items->first();
        $initialStock = StockLevel::where([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ])->first()->quantity;

        // Adjust quantity from 5 to 3 (reduce by 2)
        $response = $this->postJson("/api/reconciliation/{$sale->id}/adjust", [
            'items' => [
                [
                    'sale_item_id' => $saleItem->id,
                    'new_quantity' => 3,
                ],
            ],
            'notes' => 'Reduced quantity to match available stock',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Conflict resolved - sale adjusted');

        // Verify sale updated
        $sale->refresh();
        $this->assertFalse($sale->has_stock_conflict);
        $this->assertEquals(3, $sale->items->first()->quantity);
        $this->assertEquals(300.0, $sale->total); // 3 × 100

        // Verify stock restored (2 units returned)
        $newStock = StockLevel::where([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ])->first()->quantity;

        $this->assertEquals($initialStock + 2, $newStock);
    }

    public function test_adjust_requires_items_array(): void
    {
        Sanctum::actingAs($this->admin);

        $sale = $this->createConflictedSale(5);

        $response = $this->postJson("/api/reconciliation/{$sale->id}/adjust", [
            'notes' => 'Test',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    /*
    |--------------------------------------------------------------------------
    | Void Conflict Tests
    |--------------------------------------------------------------------------
    */

    public function test_void_refunds_sale_and_restores_stock(): void
    {
        Sanctum::actingAs($this->admin);

        $sale = $this->createConflictedSale(5);
        $initialStock = StockLevel::where([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ])->first()->quantity;

        $response = $this->postJson("/api/reconciliation/{$sale->id}/void", [
            'reason' => 'Stock not available',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Conflict resolved - sale voided');

        // Verify sale voided
        $sale->refresh();
        $this->assertEquals(Sale::STATUS_REFUNDED, $sale->status);
        $this->assertFalse($sale->has_stock_conflict);

        // Verify stock restored (5 units returned)
        $newStock = StockLevel::where([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ])->first()->quantity;

        $this->assertEquals($initialStock + 5, $newStock);

        // Verify payments marked as refunded
        foreach ($sale->payments as $payment) {
            $this->assertEquals(Payment::STATUS_REFUNDED, $payment->status);
        }
    }

    public function test_void_requires_reason(): void
    {
        Sanctum::actingAs($this->admin);

        $sale = $this->createConflictedSale(5);

        $response = $this->postJson("/api/reconciliation/{$sale->id}/void");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_void_already_refunded_returns_error(): void
    {
        Sanctum::actingAs($this->admin);

        $sale = $this->createConflictedSale(5);
        $sale->update(['status' => Sale::STATUS_REFUNDED]);

        $response = $this->postJson("/api/reconciliation/{$sale->id}/void", [
            'reason' => 'Test',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'This sale has already been refunded');
    }

    public function test_void_nonconflict_sale_returns_error(): void
    {
        Sanctum::actingAs($this->admin);

        $sale = Sale::create([
            'sale_number' => 'SALE-OK',
            'invoice_number' => 'INV-OK-VOID',
            'idempotency_key' => 'KEY-OK-VOID',
            'warehouse_id' => $this->warehouse->id,
            'user_id' => $this->admin->id,
            'status' => Sale::STATUS_COMPLETED,
            'subtotal' => '100.000',
            'tax_total' => '0.000',
            'discount_amount' => '0.000',
            'total' => '100.000',
            'has_stock_conflict' => false,
            'completed_at' => now(),
        ]);

        $response = $this->postJson("/api/reconciliation/{$sale->id}/void", [
            'reason' => 'Test',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'This sale does not have a stock conflict');
    }
}
