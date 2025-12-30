<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected Role $adminRole;
    protected Role $userRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            ['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'users'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'group' => 'users'],
            ['name' => 'users.edit', 'display_name' => 'Edit Users', 'group' => 'users'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'group' => 'users'],
            ['name' => 'users.manage_roles', 'display_name' => 'Manage User Roles', 'group' => 'users'],
        ];

        foreach ($permissions as $perm) {
            Permission::create($perm);
        }

        // Create roles
        $this->adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach(Permission::pluck('id'));

        $this->userRole = Role::create([
            'name' => 'user',
            'display_name' => 'Regular User',
            'is_system' => false,
        ]);
        $this->userRole->permissions()->attach(
            Permission::where('name', 'users.view')->pluck('id')
        );

        // Create admin user
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->admin->roles()->attach($this->adminRole->id);

        // Create regular user
        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->regularUser->roles()->attach($this->userRole->id);
    }

    /** @test */
    public function admin_can_list_all_users(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/users');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'email', 'is_active', 'roles'],
                    ],
                    'meta',
                ],
            ]);

        // Should have 2 users
        $this->assertCount(2, $response->json('data.data'));
    }

    /** @test */
    public function user_with_view_permission_can_list_users(): void
    {
        $this->actingAs($this->regularUser);

        $response = $this->getJson('/api/users');

        $response->assertOk();
    }

    /** @test */
    public function admin_can_create_user(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'Password123', // Must have mixed case + numbers
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'New User',
                    'email' => 'newuser@example.com',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
        ]);
    }

    /** @test */
    public function user_without_create_permission_cannot_create_user(): void
    {
        $this->actingAs($this->regularUser);

        $response = $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function admin_can_view_specific_user(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson("/api/users/{$this->regularUser->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->regularUser->id,
                    'email' => 'user@example.com',
                ],
            ]);
    }

    /** @test */
    public function admin_can_update_user(): void
    {
        $this->actingAs($this->admin);

        $response = $this->putJson("/api/users/{$this->regularUser->id}", [
            'name' => 'Updated Name',
            'email' => 'user@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Updated Name',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'name' => 'Updated Name',
        ]);
    }

    /** @test */
    public function admin_can_delete_user(): void
    {
        $this->actingAs($this->admin);

        $response = $this->deleteJson("/api/users/{$this->regularUser->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'User deleted successfully',
            ]);

        // User should be soft deleted
        $this->assertSoftDeleted('users', [
            'id' => $this->regularUser->id,
        ]);
    }

    /** @test */
    public function admin_can_toggle_user_active_status(): void
    {
        $this->actingAs($this->admin);

        // Deactivate
        $response = $this->patchJson("/api/users/{$this->regularUser->id}/toggle-active");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_active' => false,
                ],
            ]);

        // Activate again
        $response = $this->patchJson("/api/users/{$this->regularUser->id}/toggle-active");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'is_active' => true,
                ],
            ]);
    }

    /** @test */
    public function admin_can_sync_user_roles(): void
    {
        $this->actingAs($this->admin);

        $newRole = Role::create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'is_system' => false,
        ]);

        $response = $this->postJson("/api/users/{$this->regularUser->id}/roles", [
            'roles' => [$this->adminRole->id, $newRole->id],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertTrue($this->regularUser->fresh()->roles->contains($this->adminRole));
        $this->assertTrue($this->regularUser->fresh()->roles->contains($newRole));
    }

    /** @test */
    public function user_without_manage_roles_permission_cannot_sync_roles(): void
    {
        $this->actingAs($this->regularUser);

        $response = $this->postJson("/api/users/{$this->regularUser->id}/roles", [
            'roles' => [$this->adminRole->id],
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function create_user_validates_required_fields(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/users', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    /** @test */
    public function create_user_validates_unique_email(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/users', [
            'name' => 'Another User',
            'email' => 'admin@example.com', // Already exists
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_user_endpoints(): void
    {
        $response = $this->getJson('/api/users');
        $response->assertUnauthorized();

        $response = $this->postJson('/api/users', []);
        $response->assertUnauthorized();

        $response = $this->getJson('/api/users/1');
        $response->assertUnauthorized();

        $response = $this->putJson('/api/users/1', []);
        $response->assertUnauthorized();

        $response = $this->deleteJson('/api/users/1');
        $response->assertUnauthorized();
    }
}
