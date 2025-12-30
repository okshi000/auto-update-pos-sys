<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            ['name' => 'products.view', 'display_name' => 'View Products', 'group' => 'products'],
            ['name' => 'products.create', 'display_name' => 'Create Products', 'group' => 'products'],
            ['name' => 'products.edit', 'display_name' => 'Edit Products', 'group' => 'products'],
            ['name' => 'products.delete', 'display_name' => 'Delete Products', 'group' => 'products'],
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
    }

    /** @test */
    public function can_list_categories(): void
    {
        Category::create(['name' => 'Category 1', 'slug' => 'category-1']);
        Category::create(['name' => 'Category 2', 'slug' => 'category-2']);

        $this->actingAs($this->admin);

        $response = $this->getJson('/api/categories');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'slug', 'is_active'],
                    ],
                    'meta',
                ],
            ]);

        $this->assertCount(2, $response->json('data.data'));
    }

    /** @test */
    public function can_get_category_tree(): void
    {
        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent']);
        Category::create(['name' => 'Child 1', 'slug' => 'child-1', 'parent_id' => $parent->id]);
        Category::create(['name' => 'Child 2', 'slug' => 'child-2', 'parent_id' => $parent->id]);

        $this->actingAs($this->admin);

        $response = $this->getJson('/api/categories/tree');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'all_children' => [
                            '*' => ['id', 'name'],
                        ],
                    ],
                ],
            ]);

        // Should only have 1 root category
        $this->assertCount(1, $response->json('data'));
        // Root should have 2 children
        $this->assertCount(2, $response->json('data.0.all_children'));
    }

    /** @test */
    public function can_create_category(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/categories', [
            'name' => 'New Category',
            'description' => 'Test description',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'New Category',
                    'slug' => 'new-category',
                    'description' => 'Test description',
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'New Category',
            'slug' => 'new-category',
        ]);
    }

    /** @test */
    public function can_create_child_category(): void
    {
        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent']);

        $this->actingAs($this->admin);

        $response = $this->postJson('/api/categories', [
            'name' => 'Child Category',
            'parent_id' => $parent->id,
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Child Category',
                    'parent_id' => $parent->id,
                ],
            ]);
    }

    /** @test */
    public function can_view_category(): void
    {
        $category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'description' => 'Test description',
        ]);

        $this->actingAs($this->admin);

        $response = $this->getJson("/api/categories/{$category->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $category->id,
                    'name' => 'Test Category',
                    'slug' => 'test-category',
                ],
            ]);
    }

    /** @test */
    public function can_update_category(): void
    {
        $category = Category::create(['name' => 'Original', 'slug' => 'original']);

        $this->actingAs($this->admin);

        $response = $this->putJson("/api/categories/{$category->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Updated Name',
                    'description' => 'Updated description',
                ],
            ]);
    }

    /** @test */
    public function cannot_set_category_as_its_own_parent(): void
    {
        $category = Category::create(['name' => 'Category', 'slug' => 'category']);

        $this->actingAs($this->admin);

        $response = $this->putJson("/api/categories/{$category->id}", [
            'parent_id' => $category->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    }

    /** @test */
    public function can_delete_category(): void
    {
        $category = Category::create(['name' => 'To Delete', 'slug' => 'to-delete']);

        $this->actingAs($this->admin);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Category deleted successfully',
            ]);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    /** @test */
    public function deleting_parent_moves_children_to_grandparent(): void
    {
        $grandparent = Category::create(['name' => 'Grandparent', 'slug' => 'grandparent']);
        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent', 'parent_id' => $grandparent->id]);
        $child = Category::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id]);

        $this->actingAs($this->admin);

        $response = $this->deleteJson("/api/categories/{$parent->id}");

        $response->assertOk();

        $child->refresh();
        $this->assertEquals($grandparent->id, $child->parent_id);
    }

    /** @test */
    public function can_filter_categories_by_search(): void
    {
        Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        Category::create(['name' => 'Food', 'slug' => 'food']);
        Category::create(['name' => 'Electronic Accessories', 'slug' => 'electronic-accessories']);

        $this->actingAs($this->admin);

        $response = $this->getJson('/api/categories?search=electr');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
    }

    /** @test */
    public function can_filter_root_categories(): void
    {
        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent']);
        Category::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id]);

        $this->actingAs($this->admin);

        $response = $this->getJson('/api/categories?parent_id=0');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Parent', $response->json('data.data.0.name'));
    }

    /** @test */
    public function generates_unique_slug_automatically(): void
    {
        Category::create(['name' => 'Test', 'slug' => 'test']);

        $this->actingAs($this->admin);

        $response = $this->postJson('/api/categories', [
            'name' => 'Test',
        ]);

        $response->assertCreated();
        $this->assertNotEquals('test', $response->json('data.slug'));
        $this->assertStringStartsWith('test-', $response->json('data.slug'));
    }

    /** @test */
    public function unauthenticated_user_cannot_access_categories(): void
    {
        $response = $this->getJson('/api/categories');
        $response->assertUnauthorized();
    }
}
