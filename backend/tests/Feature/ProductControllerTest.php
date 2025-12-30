<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockLevel;
use App\Models\TaxClass;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;
    protected TaxClass $taxClass;
    protected Warehouse $warehouse;

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

        // Create test data
        $this->category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        $this->taxClass = TaxClass::create([
            'name' => 'Standard',
            'code' => 'STD',
            'rate' => 10.00,
            'is_default' => true,
        ]);
        $this->warehouse = Warehouse::create([
            'name' => 'Main',
            'code' => 'MAIN',
            'is_default' => true,
        ]);
    }

    /** @test */
    public function can_list_products(): void
    {
        Product::create([
            'name' => 'Product 1',
            'sku' => 'SKU-001',
            'price' => 19.99,
        ]);
        Product::create([
            'name' => 'Product 2',
            'sku' => 'SKU-002',
            'price' => 29.99,
        ]);

        $this->actingAs($this->admin);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'sku', 'price'],
                    ],
                    'meta',
                ],
            ]);

        $this->assertCount(2, $response->json('data.data'));
    }

    /** @test */
    public function can_create_product(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/products', [
            'name' => 'New Product',
            'price' => 49.99,
            'cost_price' => 30.00,
            'category_id' => $this->category->id,
            'tax_class_id' => $this->taxClass->id,
            'stock_tracked' => true,
            'min_stock_level' => 10,
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'New Product',
                    'price' => 49.99,
                    'category_id' => $this->category->id,
                ],
            ]);

        // SKU should be auto-generated
        $this->assertNotNull($response->json('data.sku'));
    }

    /** @test */
    public function can_create_product_with_barcode(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/products', [
            'name' => 'Barcoded Product',
            'price' => 19.99,
            'barcode' => '5449000000996', // Valid EAN-13
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => [
                    'barcode' => '5449000000996',
                ],
            ]);
    }

    /** @test */
    public function can_view_product(): void
    {
        $product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'price' => 29.99,
            'category_id' => $this->category->id,
        ]);

        $this->actingAs($this->admin);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $product->id,
                    'name' => 'Test Product',
                    'sku' => 'TEST-001',
                ],
            ]);
    }

    /** @test */
    public function can_update_product(): void
    {
        $product = Product::create([
            'name' => 'Original',
            'sku' => 'ORIG-001',
            'price' => 19.99,
        ]);

        $this->actingAs($this->admin);

        $response = $this->putJson("/api/products/{$product->id}", [
            'name' => 'Updated Product',
            'price' => 24.99,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Updated Product',
                    'price' => 24.99,
                ],
            ]);
    }

    /** @test */
    public function can_delete_product(): void
    {
        $product = Product::create([
            'name' => 'To Delete',
            'sku' => 'DEL-001',
            'price' => 9.99,
        ]);

        $this->actingAs($this->admin);

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Product deleted successfully',
            ]);

        // Soft deleted
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    /** @test */
    public function can_search_products(): void
    {
        Product::create(['name' => 'Coca Cola', 'sku' => 'CC-001', 'price' => 1.50]);
        Product::create(['name' => 'Pepsi', 'sku' => 'PEP-001', 'price' => 1.50]);
        Product::create(['name' => 'Cola Zero', 'sku' => 'CZ-001', 'price' => 1.60]);

        $this->actingAs($this->admin);

        $response = $this->getJson('/api/products/search?query=cola');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function can_find_product_by_barcode(): void
    {
        Product::create([
            'name' => 'Barcoded Item',
            'sku' => 'BAR-001',
            'barcode' => '5449000000996',
            'price' => 2.99,
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson('/api/products/barcode', [
            'barcode' => '5449000000996',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'barcode' => '5449000000996',
                    'name' => 'Barcoded Item',
                ],
            ]);
    }

    /** @test */
    public function barcode_search_returns_404_when_not_found(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/products/barcode', [
            'barcode' => '0000000000000',
        ]);

        $response->assertNotFound();
    }

    /** @test */
    public function can_filter_products_by_category(): void
    {
        $otherCategory = Category::create(['name' => 'Food', 'slug' => 'food']);

        Product::create([
            'name' => 'Electronics Item',
            'sku' => 'EL-001',
            'price' => 99.99,
            'category_id' => $this->category->id,
        ]);
        Product::create([
            'name' => 'Food Item',
            'sku' => 'FD-001',
            'price' => 5.99,
            'category_id' => $otherCategory->id,
        ]);

        $this->actingAs($this->admin);

        $response = $this->getJson("/api/products?category_id={$this->category->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Electronics Item', $response->json('data.data.0.name'));
    }

    /** @test */
    public function can_toggle_product_active_status(): void
    {
        $product = Product::create([
            'name' => 'Toggle Me',
            'sku' => 'TGL-001',
            'price' => 9.99,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin);

        $response = $this->patchJson("/api/products/{$product->id}/toggle-active");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_active' => false,
                ],
            ]);

        // Toggle back
        $response = $this->patchJson("/api/products/{$product->id}/toggle-active");
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'is_active' => true,
                ],
            ]);
    }

    /** @test */
    public function can_duplicate_product(): void
    {
        $product = Product::create([
            'name' => 'Original Product',
            'sku' => 'ORIG-001',
            'price' => 29.99,
            'category_id' => $this->category->id,
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson("/api/products/{$product->id}/duplicate");

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Original Product (Copy)',
                    'category_id' => $this->category->id,
                ],
            ]);

        // SKU should be different
        $this->assertNotEquals($product->sku, $response->json('data.sku'));
    }

    /** @test */
    public function can_upload_product_image(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        Storage::fake('public');

        $product = Product::create([
            'name' => 'Image Product',
            'sku' => 'IMG-001',
            'price' => 19.99,
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson("/api/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('product.jpg'),
            'is_primary' => true,
        ]);

        $response->assertCreated();
        $this->assertEquals(1, $product->fresh()->images()->count());
    }

    /** @test */
    public function validates_required_fields_on_create(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/products', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price']);
    }

    /** @test */
    public function validates_unique_sku(): void
    {
        Product::create([
            'name' => 'Existing',
            'sku' => 'UNIQUE-SKU',
            'price' => 9.99,
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson('/api/products', [
            'name' => 'New Product',
            'sku' => 'UNIQUE-SKU',
            'price' => 19.99,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sku']);
    }

    /** @test */
    public function product_includes_stock_information(): void
    {
        $product = Product::create([
            'name' => 'Stocked Product',
            'sku' => 'STK-001',
            'price' => 15.99,
            'stock_tracked' => true,
            'min_stock_level' => 10,
        ]);

        StockLevel::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50,
        ]);

        $this->actingAs($this->admin);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.total_stock', 50)
            ->assertJsonPath('data.is_low_stock', false);
    }
}
