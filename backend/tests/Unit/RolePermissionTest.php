<?php

namespace Tests\Unit;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_has_permission_through_role(): void
    {
        $permission = Permission::create([
            'name' => 'users.view',
            'display_name' => 'View Users',
            'group' => 'users',
        ]);

        $role = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'is_system' => true,
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->roles()->attach($role->id);

        $this->assertTrue($user->hasPermission('users.view'));
        $this->assertFalse($user->hasPermission('users.delete'));
    }

    /** @test */
    public function user_has_role(): void
    {
        $role = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'is_system' => true,
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->roles()->attach($role->id);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('manager'));
    }

    /** @test */
    public function user_get_all_permissions(): void
    {
        $permissions = [
            Permission::create(['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'users']),
            Permission::create(['name' => 'users.create', 'display_name' => 'Create Users', 'group' => 'users']),
            Permission::create(['name' => 'roles.view', 'display_name' => 'View Roles', 'group' => 'roles']),
        ];

        $role1 = Role::create(['name' => 'role1', 'display_name' => 'Role 1', 'is_system' => false]);
        $role1->permissions()->attach([$permissions[0]->id, $permissions[1]->id]);

        $role2 = Role::create(['name' => 'role2', 'display_name' => 'Role 2', 'is_system' => false]);
        $role2->permissions()->attach([$permissions[1]->id, $permissions[2]->id]); // Overlapping permission

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->roles()->attach([$role1->id, $role2->id]);

        $userPermissions = $user->getAllPermissions();

        // Should have 3 unique permissions
        $this->assertCount(3, $userPermissions);
        $this->assertTrue($userPermissions->contains('name', 'users.view'));
        $this->assertTrue($userPermissions->contains('name', 'users.create'));
        $this->assertTrue($userPermissions->contains('name', 'roles.view'));
    }

    /** @test */
    public function admin_user_is_identified_correctly(): void
    {
        $role = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'is_system' => true,
        ]);

        $adminUser = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $adminUser->roles()->attach($role->id);

        $regularUser = User::create([
            'name' => 'Regular',
            'email' => 'regular@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->assertTrue($adminUser->isAdmin());
        $this->assertFalse($regularUser->isAdmin());
    }

    /** @test */
    public function role_can_sync_permissions(): void
    {
        $permissions = [
            Permission::create(['name' => 'perm1', 'display_name' => 'Permission 1', 'group' => 'test']),
            Permission::create(['name' => 'perm2', 'display_name' => 'Permission 2', 'group' => 'test']),
            Permission::create(['name' => 'perm3', 'display_name' => 'Permission 3', 'group' => 'test']),
        ];

        $role = Role::create([
            'name' => 'test-role',
            'display_name' => 'Test Role',
            'is_system' => false,
        ]);

        // Initially sync first two permissions
        $role->syncPermissions([$permissions[0]->id, $permissions[1]->id]);
        $this->assertCount(2, $role->permissions);

        // Sync to only third permission
        $role->syncPermissions([$permissions[2]->id]);
        $role->refresh();
        $this->assertCount(1, $role->permissions);
        $this->assertTrue($role->permissions->contains($permissions[2]));
    }

    /** @test */
    public function role_can_grant_permission(): void
    {
        $permission = Permission::create([
            'name' => 'test.permission',
            'display_name' => 'Test Permission',
            'group' => 'test',
        ]);

        $role = Role::create([
            'name' => 'test-role',
            'display_name' => 'Test Role',
            'is_system' => false,
        ]);

        $role->grantPermission($permission);

        $this->assertTrue($role->hasPermission('test.permission'));
    }

    /** @test */
    public function role_has_permission_check(): void
    {
        $permission = Permission::create([
            'name' => 'test.permission',
            'display_name' => 'Test Permission',
            'group' => 'test',
        ]);

        $role = Role::create([
            'name' => 'test-role',
            'display_name' => 'Test Role',
            'is_system' => false,
        ]);

        $this->assertFalse($role->hasPermission('test.permission'));

        $role->permissions()->attach($permission->id);

        $this->assertTrue($role->hasPermission('test.permission'));
    }

    /** @test */
    public function permission_can_be_grouped(): void
    {
        Permission::create(['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'users']);
        Permission::create(['name' => 'users.create', 'display_name' => 'Create Users', 'group' => 'users']);
        Permission::create(['name' => 'roles.view', 'display_name' => 'View Roles', 'group' => 'roles']);

        $userPermissions = Permission::byGroup('users')->get();
        $rolePermissions = Permission::byGroup('roles')->get();

        $this->assertCount(2, $userPermissions);
        $this->assertCount(1, $rolePermissions);
    }

    /** @test */
    public function permission_all_grouped_returns_correct_structure(): void
    {
        Permission::create(['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'users']);
        Permission::create(['name' => 'users.create', 'display_name' => 'Create Users', 'group' => 'users']);
        Permission::create(['name' => 'roles.view', 'display_name' => 'View Roles', 'group' => 'roles']);

        $grouped = Permission::allGrouped();

        $this->assertArrayHasKey('users', $grouped->toArray());
        $this->assertArrayHasKey('roles', $grouped->toArray());
        $this->assertCount(2, $grouped['users']);
        $this->assertCount(1, $grouped['roles']);
    }

    /** @test */
    public function active_user_scope_works(): void
    {
        User::create([
            'name' => 'Active User',
            'email' => 'active@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => bcrypt('password'),
            'is_active' => false,
        ]);

        $activeUsers = User::active()->get();

        $this->assertCount(1, $activeUsers);
        $this->assertEquals('Active User', $activeUsers->first()->name);
    }
}
