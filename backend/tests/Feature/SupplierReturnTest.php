<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierReturnTest extends TestCase
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

    public function test_can_create_supplier_return(): void
    {
        $initialStock = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first()
            ->quantity;

        $data = [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'reason' => 'damaged',
            'notes' => 'Product received damaged',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'unit_cost' => 5.000,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/supplier-returns', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.reason', 'damaged');

        // Stock should be decreased immediately
        $newStock = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first()
            ->quantity;

        $this->assertEquals($initialStock - 5, $newStock);
    }

    public function test_cannot_return_more_than_available_stock(): void
    {
        $data = [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'reason' => 'defective',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1000, // More than available (100)
                    'unit_cost' => 5.000,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/supplier-returns', $data);

        $response->assertStatus(422);
    }

    public function test_can_approve_supplier_return(): void
    {
        $return = SupplierReturn::create([
            'return_number' => 'RET-20240101-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
            'reason' => 'damaged',
            'total' => 25.000,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/supplier-returns/{$return->id}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_can_ship_approved_return(): void
    {
        $return = SupplierReturn::create([
            'return_number' => 'RET-20240101-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'approved',
            'reason' => 'damaged',
            'total' => 25.000,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/supplier-returns/{$return->id}/ship");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'shipped');
    }

    public function test_can_complete_shipped_return(): void
    {
        $return = SupplierReturn::create([
            'return_number' => 'RET-20240101-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'shipped',
            'reason' => 'damaged',
            'total' => 25.000,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/supplier-returns/{$return->id}/complete");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_cannot_ship_pending_return(): void
    {
        $return = SupplierReturn::create([
            'return_number' => 'RET-20240101-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
            'reason' => 'damaged',
            'total' => 25.000,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/supplier-returns/{$return->id}/ship");

        $response->assertStatus(422);
    }

    public function test_supplier_return_requires_valid_reason(): void
    {
        $data = [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'reason' => 'invalid_reason', // Not in allowed list
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'unit_cost' => 5.000,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/supplier-returns', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_can_list_supplier_returns(): void
    {
        SupplierReturn::create([
            'return_number' => 'RET-20240101-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
            'reason' => 'damaged',
            'total' => 25.000,
        ]);

        SupplierReturn::create([
            'return_number' => 'RET-20240101-0002',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'completed',
            'reason' => 'defective',
            'total' => 50.000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/supplier-returns');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_returns_by_status(): void
    {
        SupplierReturn::create([
            'return_number' => 'RET-20240101-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
            'reason' => 'damaged',
            'total' => 25.000,
        ]);

        SupplierReturn::create([
            'return_number' => 'RET-20240101-0002',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'completed',
            'reason' => 'defective',
            'total' => 50.000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/supplier-returns?status=pending');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_unauthenticated_user_cannot_access_supplier_returns(): void
    {
        $response = $this->getJson('/api/supplier-returns');
        $response->assertStatus(401);
    }
}
