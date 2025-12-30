<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Warehouse $warehouse;
    protected Supplier $supplier;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create warehouse
        $this->warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'WH001',
            'is_default' => true,
            'is_active' => true,
        ]);

        // Create supplier
        $this->supplier = Supplier::create([
            'code' => 'SUP-0001',
            'name' => 'Test Supplier',
            'is_active' => true,
        ]);

        // Create product
        $this->product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TST-001',
            'price' => 10.000,
            'cost' => 5.000,
            'stock_tracked' => true,
            'is_active' => true,
        ]);

        // Create stock level
        StockLevel::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
        ]);

        // Create user with permissions
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Create permissions
        $permissions = [
            ['name' => 'purchases.view', 'display_name' => 'View Purchases', 'group' => 'purchases'],
            ['name' => 'purchases.manage', 'display_name' => 'Manage Purchases', 'group' => 'purchases'],
            ['name' => 'purchases.receive', 'display_name' => 'Receive Goods', 'group' => 'purchases'],
            ['name' => 'purchases.return', 'display_name' => 'Supplier Returns', 'group' => 'purchases'],
            ['name' => 'suppliers.view', 'display_name' => 'View Suppliers', 'group' => 'suppliers'],
            ['name' => 'suppliers.manage', 'display_name' => 'Manage Suppliers', 'group' => 'suppliers'],
        ];

        $permissionIds = [];
        foreach ($permissions as $permData) {
            $perm = Permission::create($permData);
            $permissionIds[] = $perm->id;
        }

        // Create role with permissions
        $role = Role::create(['name' => 'purchaser', 'display_name' => 'Purchaser']);
        $role->permissions()->attach($permissionIds);
        $this->user->roles()->attach($role->id);
    }

    public function test_can_create_purchase_order(): void
    {
        $data = [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addDays(7)->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 50,
                    'unit_cost' => 4.500,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/purchase-orders', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.supplier.name', 'Test Supplier');

        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $this->supplier->id,
            'status' => 'draft',
        ]);
    }

    public function test_purchase_order_calculates_totals(): void
    {
        $data = [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_cost' => 5.000,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/purchase-orders', $data);

        $response->assertStatus(201);
        
        // Total should be 10 * 5.000 = 50.000
        $this->assertEquals('50.000', $response->json('data.total'));
    }

    public function test_can_send_purchase_order(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-20240101-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'total' => 100.000,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/purchase-orders/{$po->id}/send");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'sent');
    }

    public function test_cannot_send_already_sent_purchase_order(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-20240101-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'sent',
            'order_date' => now()->toDateString(),
            'total' => 100.000,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/purchase-orders/{$po->id}/send");

        $response->assertStatus(422);
    }

    public function test_can_receive_goods(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-20240101-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'sent',
            'order_date' => now()->toDateString(),
            'total' => 50.000,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_cost' => 5.000,
            'line_total' => 50.000,
        ]);

        $initialStock = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first()
            ->quantity;

        $response = $this->actingAs($this->user)
            ->postJson("/api/purchase-orders/{$po->id}/receive", [
                'items' => [
                    [
                        'item_id' => $poItem->id,
                        'quantity' => 10,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'received');

        // Check stock increased
        $newStock = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first()
            ->quantity;

        $this->assertEquals($initialStock + 10, $newStock);
    }

    public function test_partial_receive_sets_partial_status(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-20240101-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'sent',
            'order_date' => now()->toDateString(),
            'total' => 50.000,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_cost' => 5.000,
            'line_total' => 50.000,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/purchase-orders/{$po->id}/receive", [
                'items' => [
                    [
                        'item_id' => $poItem->id,
                        'quantity' => 5, // Only receiving 5 of 10
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'partial');
    }

    public function test_cannot_receive_more_than_ordered(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-20240101-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'sent',
            'order_date' => now()->toDateString(),
            'total' => 50.000,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_cost' => 5.000,
            'line_total' => 50.000,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/purchase-orders/{$po->id}/receive", [
                'items' => [
                    [
                        'item_id' => $poItem->id,
                        'quantity' => 20, // Trying to receive more than ordered
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_can_cancel_draft_purchase_order(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-20240101-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'total' => 100.000,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/purchase-orders/{$po->id}/cancel", [
                'reason' => 'Changed requirements',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cannot_cancel_received_purchase_order(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-20240101-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'received',
            'order_date' => now()->toDateString(),
            'total' => 100.000,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/purchase-orders/{$po->id}/cancel");

        $response->assertStatus(422);
    }

    public function test_can_view_purchase_order_details(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-20240101-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'total' => 100.000,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 20,
            'unit_cost' => 5.000,
            'line_total' => 100.000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/purchase-orders/{$po->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.po_number', 'PO-20240101-0001')
            ->assertJsonCount(1, 'data.items');
    }

    public function test_can_list_purchase_orders(): void
    {
        PurchaseOrder::create([
            'po_number' => 'PO-20240101-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'total' => 100.000,
        ]);

        PurchaseOrder::create([
            'po_number' => 'PO-20240101-0002',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'sent',
            'order_date' => now()->toDateString(),
            'total' => 200.000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/purchase-orders');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_unauthenticated_user_cannot_access_purchase_orders(): void
    {
        $response = $this->getJson('/api/purchase-orders');
        $response->assertStatus(401);
    }
}
