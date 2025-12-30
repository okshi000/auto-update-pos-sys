<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Warehouse $warehouse;

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

        // Create user with permissions
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Create permissions
        $viewPermission = Permission::create(['name' => 'suppliers.view', 'display_name' => 'View Suppliers', 'group' => 'suppliers']);
        $managePermission = Permission::create(['name' => 'suppliers.manage', 'display_name' => 'Manage Suppliers', 'group' => 'suppliers']);

        // Create role with permissions
        $role = Role::create(['name' => 'manager', 'display_name' => 'Manager']);
        $role->permissions()->attach([$viewPermission->id, $managePermission->id]);
        $this->user->roles()->attach($role->id);
    }

    public function test_can_list_suppliers(): void
    {
        Supplier::create([
            'code' => 'SUP-0001',
            'name' => 'Supplier One',
            'is_active' => true,
        ]);

        Supplier::create([
            'code' => 'SUP-0002',
            'name' => 'Supplier Two',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/suppliers');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_suppliers_by_active_status(): void
    {
        Supplier::create([
            'code' => 'SUP-0001',
            'name' => 'Active Supplier',
            'is_active' => true,
        ]);

        Supplier::create([
            'code' => 'SUP-0002',
            'name' => 'Inactive Supplier',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/suppliers?active=true');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Supplier');
    }

    public function test_can_create_supplier(): void
    {
        $data = [
            'name' => 'New Supplier',
            'contact_person' => 'John Doe',
            'email' => 'supplier@example.com',
            'phone' => '+218-91-1234567',
            'address' => '123 Main St',
            'city' => 'Tripoli',
            'country' => 'Libya',
            'tax_number' => 'TAX123456',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/suppliers', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Supplier')
            ->assertJsonPath('data.email', 'supplier@example.com');

        $this->assertDatabaseHas('suppliers', [
            'name' => 'New Supplier',
            'email' => 'supplier@example.com',
        ]);
    }

    public function test_supplier_code_is_auto_generated_if_not_provided(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/suppliers', [
                'name' => 'Auto Code Supplier',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Auto Code Supplier');

        // Code should be generated
        $this->assertMatchesRegularExpression('/^SUP-\d{4}$/', $response->json('data.code'));
    }

    public function test_cannot_create_supplier_with_duplicate_code(): void
    {
        Supplier::create([
            'code' => 'SUP-0001',
            'name' => 'Existing Supplier',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/suppliers', [
                'name' => 'New Supplier',
                'code' => 'SUP-0001',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_can_view_single_supplier(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUP-0001',
            'name' => 'Test Supplier',
            'email' => 'test@supplier.com',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Test Supplier')
            ->assertJsonPath('data.email', 'test@supplier.com');
    }

    public function test_can_update_supplier(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUP-0001',
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/suppliers/{$supplier->id}", [
                'name' => 'New Name',
                'email' => 'new@email.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'new@email.com');

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'New Name',
        ]);
    }

    public function test_can_delete_supplier_without_orders(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUP-0001',
            'name' => 'Delete Me',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Supplier deleted successfully');

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_can_search_suppliers(): void
    {
        Supplier::create(['code' => 'SUP-0001', 'name' => 'ABC Supplies']);
        Supplier::create(['code' => 'SUP-0002', 'name' => 'XYZ Corporation']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/suppliers?search=ABC');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'ABC Supplies');
    }

    public function test_unauthenticated_user_cannot_access_suppliers(): void
    {
        $response = $this->getJson('/api/suppliers');
        $response->assertStatus(401);
    }
}
