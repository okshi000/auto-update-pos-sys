<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $warehouseUser;
    protected Warehouse $warehouse1;
    protected Warehouse $warehouse2;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            ['name' => 'inventory.view', 'display_name' => 'View Inventory', 'group' => 'inventory'],
            ['name' => 'inventory.adjust', 'display_name' => 'Adjust Inventory', 'group' => 'inventory'],
            ['name' => 'inventory.transfer', 'display_name' => 'Transfer Inventory', 'group' => 'inventory'],
        ];

        foreach ($permissions as $perm) {
            Permission::create($perm);
        }

        // Create admin role
        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'is_system' => true,
        ]);
        $adminRole->permissions()->attach(Permission::pluck('id'));

        // Create warehouse role with limited permissions
        $warehouseRole = Role::create([
            'name' => 'warehouse',
            'display_name' => 'Warehouse',
            'is_system' => false,
        ]);
        $warehouseRole->permissions()->attach(Permission::where('name', 'inventory.view')->pluck('id'));

        // Create admin user
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->admin->roles()->attach($adminRole->id);

        // Create warehouse user
        $this->warehouseUser = User::create([
            'name' => 'Warehouse User',
            'email' => 'warehouse@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->warehouseUser->roles()->attach($warehouseRole->id);

        // Create warehouses
        $this->warehouse1 = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'is_default' => true,
        ]);
        $this->warehouse2 = Warehouse::create([
            'name' => 'Secondary Warehouse',
            'code' => 'SEC',
            'is_default' => false,
        ]);

        // Create product
        $this->product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'price' => 19.99,
            'stock_tracked' => true,
            'min_stock_level' => 10,
        ]);

        // Create initial stock
        StockLevel::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 100,
        ]);
    }

    /** @test */
    public function can_adjust_stock(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/stock/adjust', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 50,
            'reason' => 'Stock count adjustment',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'product_id' => $this->product->id,
                    'warehouse_id' => $this->warehouse1->id,
                    'quantity_change' => 50,
                    'quantity_before' => 100,
                    'quantity_after' => 150,
                    'type' => 'adjustment',
                ],
            ]);

        $this->assertEquals(150, StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse1->id)
            ->first()->quantity);
    }

    /** @test */
    public function can_deduct_stock(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/stock/adjust', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => -30,
            'reason' => 'Damaged goods',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'quantity_change' => -30,
                    'quantity_after' => 70,
                    'type' => 'adjustment',
                ],
            ]);
    }

    /** @test */
    public function cannot_deduct_more_than_available(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/stock/adjust', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => -150, // More than 100 available
            'reason' => 'Test',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function can_set_absolute_stock_level(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/stock/set', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 75,
            'reason' => 'Inventory count',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'quantity_after' => 75,
                ],
            ]);
    }

    /** @test */
    public function can_transfer_stock_between_warehouses(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/stock/transfer', [
            'product_id' => $this->product->id,
            'from_warehouse_id' => $this->warehouse1->id,
            'to_warehouse_id' => $this->warehouse2->id,
            'quantity' => 30,
            'reason' => 'Restock secondary location',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'out' => ['quantity_change', 'type'],
                    'in' => ['quantity_change', 'type'],
                ],
            ]);

        // Check stock levels
        $this->assertEquals(70, StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse1->id)
            ->first()->quantity);
        
        $this->assertEquals(30, StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse2->id)
            ->first()->quantity);
    }

    /** @test */
    public function cannot_transfer_to_same_warehouse(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/stock/transfer', [
            'product_id' => $this->product->id,
            'from_warehouse_id' => $this->warehouse1->id,
            'to_warehouse_id' => $this->warehouse1->id,
            'quantity' => 10,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['to_warehouse_id']);
    }

    /** @test */
    public function can_get_product_stock_across_warehouses(): void
    {
        StockLevel::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse2->id,
            'quantity' => 50,
        ]);

        $this->actingAs($this->admin);

        $response = $this->getJson("/api/stock/product/{$this->product->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function can_get_low_stock_products(): void
    {
        // Create a product with low stock
        $lowStockProduct = Product::create([
            'name' => 'Low Stock Product',
            'sku' => 'LOW-001',
            'price' => 9.99,
            'stock_tracked' => true,
            'min_stock_level' => 20,
        ]);

        StockLevel::create([
            'product_id' => $lowStockProduct->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 5, // Below min_stock_level of 20
        ]);

        $this->actingAs($this->admin);

        $response = $this->getJson('/api/stock/low-stock');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    /** @test */
    public function can_get_stock_movements(): void
    {
        // Create some movements
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity_change' => 10,
            'quantity_before' => 90,
            'quantity_after' => 100,
            'type' => 'adjustment',
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin);

        $response = $this->getJson('/api/stock/movements');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => ['id', 'product_id', 'quantity_change', 'type'],
                    ],
                    'meta',
                ],
            ]);
    }

    /** @test */
    public function can_filter_movements_by_product(): void
    {
        $otherProduct = Product::create([
            'name' => 'Other Product',
            'sku' => 'OTH-001',
            'price' => 15.99,
        ]);

        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity_change' => 10,
            'quantity_before' => 90,
            'quantity_after' => 100,
            'type' => 'adjustment',
        ]);

        StockMovement::create([
            'product_id' => $otherProduct->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity_change' => 5,
            'quantity_before' => 0,
            'quantity_after' => 5,
            'type' => 'adjustment',
        ]);

        $this->actingAs($this->admin);

        $response = $this->getJson("/api/stock/movements?product_id={$this->product->id}");

        $response->assertOk();
        foreach ($response->json('data.data') as $movement) {
            $this->assertEquals($this->product->id, $movement['product_id']);
        }
    }

    /** @test */
    public function can_get_movement_types(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/stock/movement-types');

        $response->assertOk();
        $this->assertContains('adjustment', $response->json('data'));
        $this->assertContains('sale', $response->json('data'));
        $this->assertContains('purchase', $response->json('data'));
    }

    /** @test */
    public function user_without_adjust_permission_cannot_adjust_stock(): void
    {
        $this->actingAs($this->warehouseUser);

        $response = $this->postJson('/api/stock/adjust', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 10,
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function user_without_transfer_permission_cannot_transfer_stock(): void
    {
        $this->actingAs($this->warehouseUser);

        $response = $this->postJson('/api/stock/transfer', [
            'product_id' => $this->product->id,
            'from_warehouse_id' => $this->warehouse1->id,
            'to_warehouse_id' => $this->warehouse2->id,
            'quantity' => 10,
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function adjusting_stock_creates_movement_record(): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/api/stock/adjust', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 25,
            'reason' => 'Test adjustment',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity_change' => 25,
            'type' => 'adjustment',
            'reason' => 'Test adjustment',
            'user_id' => $this->admin->id,
        ]);
    }
}
