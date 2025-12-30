<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\TaxClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxClassControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $viewer;
    protected Role $adminRole;
    protected Role $viewerRole;

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

        // Create viewer role with view permission only
        $this->viewerRole = Role::create([
            'name' => 'viewer',
            'display_name' => 'Viewer',
            'is_system' => false,
        ]);
        $viewPermission = Permission::where('name', 'products.view')->first();
        $this->viewerRole->permissions()->attach($viewPermission->id);

        // Create admin user
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->admin->roles()->attach($this->adminRole);

        // Create viewer user
        $this->viewer = User::create([
            'name' => 'Viewer User',
            'email' => 'viewer@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->viewer->roles()->attach($this->viewerRole);
    }

    /** @test */
    public function can_list_tax_classes()
    {
        TaxClass::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/tax-classes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'code', 'rate', 'is_active', 'is_default']
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function can_filter_active_tax_classes()
    {
        TaxClass::factory()->count(2)->create(['is_active' => true]);
        TaxClass::factory()->count(1)->create(['is_active' => false]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/tax-classes?active_only=true');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function can_create_tax_class()
    {
        $taxClassData = [
            'name' => 'Standard VAT',
            'code' => 'VAT15',
            'rate' => 15.00,
            'description' => 'Standard VAT rate',
            'is_active' => true,
            'is_default' => false,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/tax-classes', $taxClassData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Standard VAT',
                    'code' => 'VAT15',
                    'rate' => '15.00',
                ]
            ]);

        $this->assertDatabaseHas('tax_classes', [
            'name' => 'Standard VAT',
            'code' => 'VAT15',
        ]);
    }

    /** @test */
    public function cannot_create_tax_class_with_duplicate_code()
    {
        TaxClass::factory()->create(['code' => 'VAT15']);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/tax-classes', [
                'name' => 'Another VAT',
                'code' => 'VAT15',
                'rate' => 10.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    /** @test */
    public function can_view_single_tax_class()
    {
        $taxClass = TaxClass::factory()->create([
            'name' => 'Luxury Tax',
            'code' => 'LUX20',
            'rate' => 20.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/tax-classes/{$taxClass->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $taxClass->id,
                    'name' => 'Luxury Tax',
                    'code' => 'LUX20',
                    'rate' => '20.00',
                ]
            ]);
    }

    /** @test */
    public function can_update_tax_class()
    {
        $taxClass = TaxClass::factory()->create([
            'name' => 'Old Name',
            'code' => 'OLD01',
            'rate' => 10.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/tax-classes/{$taxClass->id}", [
                'name' => 'New Name',
                'rate' => 12.50,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'New Name',
                    'rate' => '12.50',
                ]
            ]);

        $this->assertDatabaseHas('tax_classes', [
            'id' => $taxClass->id,
            'name' => 'New Name',
            'rate' => 12.50,
        ]);
    }

    /** @test */
    public function can_delete_tax_class_without_products()
    {
        $taxClass = TaxClass::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/tax-classes/{$taxClass->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('tax_classes', ['id' => $taxClass->id]);
    }

    /** @test */
    public function cannot_delete_tax_class_with_products()
    {
        $taxClass = TaxClass::factory()->create();
        
        // Create a product with this tax class
        Product::factory()->create(['tax_class_id' => $taxClass->id]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/tax-classes/{$taxClass->id}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseHas('tax_classes', ['id' => $taxClass->id]);
    }

    /** @test */
    public function only_one_default_tax_class_allowed()
    {
        $taxClass1 = TaxClass::factory()->create(['is_default' => true]);
        
        // Create another default tax class
        $response = $this->actingAs($this->admin)
            ->postJson('/api/tax-classes', [
                'name' => 'New Default',
                'code' => 'DEFAULT2',
                'rate' => 5.00,
                'is_default' => true,
            ]);

        $response->assertStatus(201);

        // Refresh and check that the first one is no longer default
        $taxClass1->refresh();
        $this->assertFalse($taxClass1->is_default);

        // The new one should be default
        $newTaxClass = TaxClass::where('code', 'DEFAULT2')->first();
        $this->assertTrue($newTaxClass->is_default);
    }

    /** @test */
    public function validates_rate_range()
    {
        // Rate too high
        $response = $this->actingAs($this->admin)
            ->postJson('/api/tax-classes', [
                'name' => 'Invalid Tax',
                'code' => 'INV01',
                'rate' => 150.00, // Over 100%
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rate']);

        // Negative rate
        $response = $this->actingAs($this->admin)
            ->postJson('/api/tax-classes', [
                'name' => 'Invalid Tax',
                'code' => 'INV02',
                'rate' => -5.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rate']);
    }

    /** @test */
    public function validates_required_fields()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/tax-classes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code', 'rate']);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_tax_classes()
    {
        $response = $this->getJson('/api/tax-classes');

        $response->assertStatus(401);
    }

    /** @test */
    public function tax_classes_are_ordered_by_name()
    {
        TaxClass::factory()->create(['name' => 'Zebra Tax']);
        TaxClass::factory()->create(['name' => 'Alpha Tax']);
        TaxClass::factory()->create(['name' => 'Middle Tax']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/tax-classes');

        $response->assertStatus(200);
        
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['Alpha Tax', 'Middle Tax', 'Zebra Tax'], $names);
    }
}
