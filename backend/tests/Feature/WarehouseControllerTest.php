<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            ['name' => 'inventory.view', 'display_name' => 'View Inventory', 'group' => 'inventory'],
            ['name' => 'inventory.adjust', 'display_name' => 'Adjust Inventory', 'group' => 'inventory'],
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

        // Create admin user
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->admin->roles()->attach($adminRole->id);
    }

    /** @test */
    public function can_list_warehouses(): void
    {
        Warehouse::create(['name' => 'Warehouse 1', 'code' => 'WH1']);
        Warehouse::create(['name' => 'Warehouse 2', 'code' => 'WH2']);

        $this->actingAs($this->admin);

        $response = $this->getJson('/api/warehouses');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'code', 'is_active'],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function can_create_warehouse(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/warehouses', [
            'name' => 'New Warehouse',
            'code' => 'NEW',
            'location' => 'Downtown',
            'address' => '123 Main St',
            'phone' => '555-1234',
            'is_active' => true,
            'is_default' => false,
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'New Warehouse',
                    'code' => 'NEW',
                    'location' => 'Downtown',
                ],
            ]);

        $this->assertDatabaseHas('warehouses', [
            'name' => 'New Warehouse',
            'code' => 'NEW',
        ]);
    }

    /** @test */
    public function can_view_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'TEST',
            'location' => 'Test Location',
        ]);

        $this->actingAs($this->admin);

        $response = $this->getJson("/api/warehouses/{$warehouse->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $warehouse->id,
                    'name' => 'Test Warehouse',
                    'code' => 'TEST',
                ],
            ]);
    }

    /** @test */
    public function can_update_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Original',
            'code' => 'ORIG',
        ]);

        $this->actingAs($this->admin);

        $response = $this->putJson("/api/warehouses/{$warehouse->id}", [
            'name' => 'Updated Warehouse',
            'location' => 'New Location',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Updated Warehouse',
                    'location' => 'New Location',
                ],
            ]);
    }

    /** @test */
    public function can_delete_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'To Delete',
            'code' => 'DEL',
        ]);

        $this->actingAs($this->admin);

        $response = $this->deleteJson("/api/warehouses/{$warehouse->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Warehouse deleted successfully',
            ]);

        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
    }

    /** @test */
    public function code_must_be_unique(): void
    {
        Warehouse::create(['name' => 'Existing', 'code' => 'UNIQUE']);

        $this->actingAs($this->admin);

        $response = $this->postJson('/api/warehouses', [
            'name' => 'New Warehouse',
            'code' => 'UNIQUE',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    /** @test */
    public function validates_required_fields(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/warehouses', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code']);
    }

    /** @test */
    public function only_one_warehouse_can_be_default(): void
    {
        $warehouse1 = Warehouse::create([
            'name' => 'First',
            'code' => 'FIRST',
            'is_default' => true,
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson('/api/warehouses', [
            'name' => 'Second',
            'code' => 'SECOND',
            'is_default' => true,
        ]);

        $response->assertCreated();

        // First warehouse should no longer be default
        $warehouse1->refresh();
        $this->assertFalse($warehouse1->is_default);
    }
}
