<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Warehouse $warehouse;
    protected PaymentMethod $cashMethod;
    protected Product $product;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin role with sync permissions
        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'is_system' => true,
        ]);

        $permissions = [
            'sales.create',
            'sales.view',
            'offline.sync',
            'offline.resolve_conflicts',
        ];

        foreach ($permissions as $perm) {
            $permission = Permission::create([
                'name' => $perm,
                'display_name' => ucfirst(str_replace('.', ' ', $perm)),
                'group' => explode('.', $perm)[0],
            ]);
            $adminRole->permissions()->attach($permission);
        }

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->admin->roles()->attach($adminRole);

        // Create warehouse that allows negative stock (for offline sync conflicts)
        $this->warehouse = Warehouse::create([
            'name' => 'Main Store',
            'code' => 'MAIN',
            'type' => 'store',
            'is_active' => true,
            'is_default' => true,
            'allows_negative_stock' => true, // Important for offline sync
        ]);

        $this->cashMethod = PaymentMethod::create([
            'name' => 'Cash',
            'code' => 'cash',
            'type' => PaymentMethod::TYPE_CASH,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'sku' => 'SYNC-001',
            'name' => 'Sync Test Product',
            'slug' => 'sync-test-product',
            'category_id' => $this->category->id,
            'price' => 15.00,
            'cost_price' => 10.00,
            'stock_tracked' => true,
            'is_active' => true,
        ]);

        StockLevel::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10, // Limited stock to test conflicts
            'reserved_quantity' => 0,
        ]);
    }

    /** @test */
    public function can_sync_single_offline_sale()
    {
        $clientUuid = 'client-' . uniqid();
        $idempotencyKey = 'offline-sale-' . uniqid();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/local/sync', [
                'client_uuid' => $clientUuid,
                'sales' => [
                    [
                        'idempotency_key' => $idempotencyKey,
                        'warehouse_id' => $this->warehouse->id,
                        'items' => [
                            [
                                'product_id' => $this->product->id,
                                'quantity' => 2,
                            ],
                        ],
                        'payment' => [
                            'payment_method_id' => $this->cashMethod->id,
                            'amount' => 30.00,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'synced',
                'duplicates',
                'failed',
                'conflicts',
                'summary',
            ],
        ]);

        $this->assertEquals(1, $response->json('data.summary.synced_count'));

        // Verify sale was created
        $this->assertDatabaseHas('sales', [
            'idempotency_key' => $idempotencyKey,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function can_sync_multiple_offline_sales_in_batch()
    {
        $clientUuid = 'batch-client-' . uniqid();

        $sales = [];
        for ($i = 1; $i <= 3; $i++) {
            $sales[] = [
                'idempotency_key' => "batch-sale-{$i}-" . uniqid(),
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 1,
                    ],
                ],
                'payment' => [
                    'payment_method_id' => $this->cashMethod->id,
                    'amount' => 15.00,
                ],
            ];
        }

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/local/sync', [
                'client_uuid' => $clientUuid,
                'sales' => $sales,
            ]);

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('data.summary.synced_count'));
    }

    /** @test */
    public function duplicate_offline_sales_are_detected()
    {
        $clientUuid = 'dup-client-' . uniqid();
        $idempotencyKey = 'duplicate-offline-' . uniqid();

        $saleData = [
            'idempotency_key' => $idempotencyKey,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                ],
            ],
            'payment' => [
                'payment_method_id' => $this->cashMethod->id,
                'amount' => 15.00,
            ],
        ];

        // First sync
        $response1 = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/local/sync', [
                'client_uuid' => $clientUuid,
                'sales' => [$saleData],
            ]);

        $response1->assertStatus(200);
        $this->assertEquals(1, $response1->json('data.summary.synced_count'));

        // Second sync with same idempotency key
        $response2 = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/local/sync', [
                'client_uuid' => $clientUuid,
                'sales' => [$saleData],
            ]);

        $response2->assertStatus(200);
        $this->assertEquals(0, $response2->json('data.summary.synced_count'));
        $this->assertEquals(1, $response2->json('data.summary.duplicate_count'));

        // Only one sale should exist
        $this->assertEquals(1, Sale::where('idempotency_key', $idempotencyKey)->count());
    }

    /** @test */
    public function can_get_cache_data_for_offline_mode()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/local/cache-data');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'products',
                'categories',
                'settings',
                'generated_at',
            ],
        ]);

        // Should include our test product
        $products = collect($response->json('data.products'));
        $this->assertTrue($products->contains('id', $this->product->id));
    }

    /** @test */
    public function sync_validates_required_fields()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/local/sync', [
                'sales' => [
                    [
                        // Missing idempotency_key and client_uuid
                        'warehouse_id' => $this->warehouse->id,
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function unauthenticated_user_cannot_sync()
    {
        $response = $this->postJson('/api/local/sync', [
            'client_uuid' => 'test-client',
            'sales' => [],
        ]);

        $response->assertStatus(401);
    }
}
