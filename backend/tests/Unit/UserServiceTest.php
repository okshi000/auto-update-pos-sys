<?php

namespace Tests\Unit;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UserService $userService;
    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userService = app(UserService::class);

        // Create a role for testing
        $this->adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'is_system' => true,
        ]);
    }

    /** @test */
    public function it_can_create_a_user(): void
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'phone' => '1234567890',
            'locale' => 'en',
            'is_active' => true,
        ];

        $user = $this->userService->create($userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertTrue($user->is_active);
    }

    /** @test */
    public function it_hashes_password_when_creating_user(): void
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'plaintext_password',
        ];

        $user = $this->userService->create($userData);

        $this->assertNotEquals('plaintext_password', $user->password);
        $this->assertTrue(password_verify('plaintext_password', $user->password));
    }

    /** @test */
    public function it_can_update_a_user(): void
    {
        $user = User::create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $updatedUser = $this->userService->update($user, [
            'name' => 'Updated Name',
            'phone' => '0987654321',
        ]);

        $this->assertEquals('Updated Name', $updatedUser->name);
        $this->assertEquals('0987654321', $updatedUser->phone);
        $this->assertEquals('original@example.com', $updatedUser->email); // Unchanged
    }

    /** @test */
    public function it_can_update_user_password(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('old_password'),
            'is_active' => true,
        ]);

        $this->userService->update($user, [
            'password' => 'new_password',
        ]);

        $user->refresh();
        $this->assertTrue(password_verify('new_password', $user->password));
    }

    /** @test */
    public function it_can_soft_delete_a_user(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $result = $this->userService->delete($user);

        $this->assertTrue($result);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    /** @test */
    public function it_can_toggle_user_active_status(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Toggle to inactive
        $updatedUser = $this->userService->toggleActive($user);
        $this->assertFalse($updatedUser->is_active);

        // Toggle back to active
        $updatedUser = $this->userService->toggleActive($user);
        $this->assertTrue($updatedUser->is_active);
    }

    /** @test */
    public function it_can_sync_user_roles(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $managerRole = Role::create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'is_system' => false,
        ]);

        $this->userService->syncRoles($user, [$this->adminRole->id, $managerRole->id]);

        $user->refresh();
        $this->assertCount(2, $user->roles);
        $this->assertTrue($user->roles->contains($this->adminRole));
        $this->assertTrue($user->roles->contains($managerRole));
    }

    /** @test */
    public function it_can_paginate_users(): void
    {
        // Create multiple users
        for ($i = 1; $i <= 15; $i++) {
            User::create([
                'name' => "User $i",
                'email' => "user$i@example.com",
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);
        }

        $result = $this->userService->getPaginated(perPage: 10);

        $this->assertCount(10, $result->items());
        $this->assertEquals(15, $result->total());
        $this->assertEquals(2, $result->lastPage());
    }

    /** @test */
    public function it_can_filter_users_by_search(): void
    {
        User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $result = $this->userService->getPaginated(search: 'john');

        $this->assertCount(1, $result->items());
        $this->assertEquals('John Doe', $result->items()[0]->name);
    }
}
