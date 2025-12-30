<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'group' => 'roles'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'group' => 'roles'],
            ['name' => 'roles.edit', 'display_name' => 'Edit Roles', 'group' => 'roles'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'group' => 'roles'],
            ['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'users'],
        ];

        foreach ($permissions as $perm) {
            Permission::create($perm);
        }

        // Create admin role with all permissions
        $this->adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach(Permission::pluck('id'));

        // Create admin user
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->admin->roles()->attach($this->adminRole->id);

        // Create regular user without role permissions
        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    /** @test */
    public function admin_can_list_all_roles(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/roles');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'display_name', 'is_system', 'permissions'],
                ],
            ]);
    }

    /** @test */
    public function user_without_permission_cannot_list_roles(): void
    {
        $this->actingAs($this->regularUser);

        $response = $this->getJson('/api/roles');

        $response->assertForbidden();
    }

    /** @test */
    public function admin_can_create_role(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/roles', [
            'name' => 'manager',
            'display_name' => 'Store Manager',
            'description' => 'Manages the store',
            'permissions' => [Permission::where('name', 'users.view')->first()->id],
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'manager',
                    'display_name' => 'Store Manager',
                    'is_system' => false,
                ],
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'manager',
        ]);
    }

    /** @test */
    public function admin_can_view_specific_role(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson("/api/roles/{$this->adminRole->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->adminRole->id,
                    'name' => 'admin',
                ],
            ]);
    }

    /** @test */
    public function admin_can_update_non_system_role(): void
    {
        $this->actingAs($this->admin);

        $role = Role::create([
            'name' => 'custom',
            'display_name' => 'Custom Role',
            'is_system' => false,
        ]);

        $response = $this->putJson("/api/roles/{$role->id}", [
            'display_name' => 'Updated Custom Role',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'custom',
                    'display_name' => 'Updated Custom Role',
                ],
            ]);
    }

    /** @test */
    public function cannot_update_system_role_name(): void
    {
        $this->actingAs($this->admin);

        $response = $this->putJson("/api/roles/{$this->adminRole->id}", [
            'display_name' => 'Super Administrator',
        ]);

        // System roles cannot be modified at all
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'System roles cannot be modified.',
            ]);
        
        // Name should remain unchanged for system roles
        $this->assertDatabaseHas('roles', [
            'id' => $this->adminRole->id,
            'name' => 'admin', // Original name
        ]);
    }

    /** @test */
    public function admin_can_delete_non_system_role(): void
    {
        $this->actingAs($this->admin);

        $role = Role::create([
            'name' => 'deletable',
            'display_name' => 'Deletable Role',
            'is_system' => false,
        ]);

        $response = $this->deleteJson("/api/roles/{$role->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Role deleted successfully',
            ]);

        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
        ]);
    }

    /** @test */
    public function cannot_delete_system_role(): void
    {
        $this->actingAs($this->admin);

        $response = $this->deleteJson("/api/roles/{$this->adminRole->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'System roles cannot be deleted.',
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $this->adminRole->id,
        ]);
    }

    /** @test */
    public function admin_can_list_all_permissions(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/permissions');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data', // Grouped by permission group
            ]);

        $this->assertTrue($response->json('success'));
    }

    /** @test */
    public function create_role_validates_required_fields(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/roles', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'display_name']);
    }

    /** @test */
    public function create_role_validates_unique_name(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/roles', [
            'name' => 'admin', // Already exists
            'display_name' => 'Another Admin',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function role_can_have_permissions_synced(): void
    {
        $this->actingAs($this->admin);

        $role = Role::create([
            'name' => 'test-role',
            'display_name' => 'Test Role',
            'is_system' => false,
        ]);

        $permissionIds = Permission::whereIn('name', ['roles.view', 'users.view'])->pluck('id')->toArray();

        $response = $this->putJson("/api/roles/{$role->id}", [
            'name' => 'test-role',
            'display_name' => 'Test Role',
            'permissions' => $permissionIds,
        ]);

        $response->assertOk();

        $role->refresh();
        $this->assertCount(2, $role->permissions);
    }
}
