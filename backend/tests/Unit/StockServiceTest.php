<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $stockService;
    protected Product $product;
    protected Warehouse $warehouse;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockService = app(StockService::class);

        $this->warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'TEST',
            'is_default' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'price' => 19.99,
            'stock_tracked' => true,
            'min_stock_level' => 10,
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Create initial stock
        StockLevel::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
        ]);
    }

    /** @test */
    public function can_adjust_stock_positively(): void
    {
        $movement = $this->stockService->adjust(
            $this->product,
            $this->warehouse,
            50,
            'Restocking',
            $this->user
        );

        $this->assertEquals(50, $movement->quantity_change);
        $this->assertEquals(100, $movement->quantity_before);
        $this->assertEquals(150, $movement->quantity_after);
        $this->assertEquals('adjustment', $movement->type);
    }

    /** @test */
    public function can_adjust_stock_negatively(): void
    {
        $movement = $this->stockService->adjust(
            $this->product,
            $this->warehouse,
            -30,
            'Stock count correction',
            $this->user
        );

        $this->assertEquals(-30, $movement->quantity_change);
        $this->assertEquals(100, $movement->quantity_before);
        $this->assertEquals(70, $movement->quantity_after);
    }

    /** @test */
    public function throws_exception_when_deducting_more_than_available(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->stockService->adjust(
            $this->product,
            $this->warehouse,
            -150,
            'Too much',
            $this->user
        );
    }

    /** @test */
    public function can_set_absolute_stock_level(): void
    {
        $movement = $this->stockService->setStock(
            $this->product,
            $this->warehouse,
            75,
            'Inventory count',
            $this->user
        );

        $this->assertEquals(-25, $movement->quantity_change);
        $this->assertEquals(75, $movement->quantity_after);
        $this->assertEquals('correction', $movement->type);
    }

    /** @test */
    public function can_transfer_stock_between_warehouses(): void
    {
        $warehouse2 = Warehouse::create([
            'name' => 'Warehouse 2',
            'code' => 'WH2',
        ]);

        $movements = $this->stockService->transfer(
            $this->product,
            $this->warehouse,
            $warehouse2,
            30,
            'Transfer for display',
            $this->user
        );

        $this->assertArrayHasKey('out', $movements);
        $this->assertArrayHasKey('in', $movements);

        $this->assertEquals(-30, $movements['out']->quantity_change);
        $this->assertEquals('transfer_out', $movements['out']->type);

        $this->assertEquals(30, $movements['in']->quantity_change);
        $this->assertEquals('transfer_in', $movements['in']->type);

        // Check stock levels
        $this->assertEquals(70, $this->stockService->getStockLevel($this->product, $this->warehouse));
        $this->assertEquals(30, $this->stockService->getStockLevel($this->product, $warehouse2));
    }

    /** @test */
    public function can_record_sale(): void
    {
        $movement = $this->stockService->recordSale(
            $this->product,
            $this->warehouse,
            5,
            'sale',
            123,
            $this->user
        );

        $this->assertEquals(-5, $movement->quantity_change);
        $this->assertEquals('sale', $movement->type);
        $this->assertEquals(123, $movement->reference_id);
        $this->assertEquals('sale', $movement->reference_type);
    }

    /** @test */
    public function can_record_purchase(): void
    {
        $movement = $this->stockService->recordPurchase(
            $this->product,
            $this->warehouse,
            50,
            'purchase_order',
            456,
            $this->user
        );

        $this->assertEquals(50, $movement->quantity_change);
        $this->assertEquals('purchase', $movement->type);
        $this->assertEquals(150, $movement->quantity_after);
    }

    /** @test */
    public function can_record_return(): void
    {
        $movement = $this->stockService->recordReturn(
            $this->product,
            $this->warehouse,
            2,
            'sale',
            789,
            $this->user
        );

        $this->assertEquals(2, $movement->quantity_change);
        $this->assertEquals('return', $movement->type);
    }

    /** @test */
    public function can_record_damage(): void
    {
        $movement = $this->stockService->recordDamage(
            $this->product,
            $this->warehouse,
            3,
            'Damaged during shipping',
            $this->user
        );

        $this->assertEquals(-3, $movement->quantity_change);
        $this->assertEquals('damage', $movement->type);
        $this->assertEquals(97, $movement->quantity_after);
    }

    /** @test */
    public function can_get_stock_level(): void
    {
        $level = $this->stockService->getStockLevel($this->product, $this->warehouse);

        $this->assertEquals(100, $level);
    }

    /** @test */
    public function returns_zero_for_no_stock_record(): void
    {
        $newProduct = Product::create([
            'name' => 'New Product',
            'sku' => 'NEW-001',
            'price' => 9.99,
        ]);

        $level = $this->stockService->getStockLevel($newProduct, $this->warehouse);

        $this->assertEquals(0, $level);
    }

    /** @test */
    public function can_get_product_stock_across_warehouses(): void
    {
        $warehouse2 = Warehouse::create([
            'name' => 'Warehouse 2',
            'code' => 'WH2',
        ]);

        StockLevel::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouse2->id,
            'quantity' => 50,
        ]);

        $stock = $this->stockService->getProductStock($this->product);

        $this->assertCount(2, $stock);
        $this->assertEquals(150, $stock->sum('quantity'));
    }

    /** @test */
    public function can_get_warehouse_stock(): void
    {
        $product2 = Product::create([
            'name' => 'Product 2',
            'sku' => 'PRD-002',
            'price' => 29.99,
        ]);

        StockLevel::create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 75,
        ]);

        $stock = $this->stockService->getWarehouseStock($this->warehouse);

        $this->assertCount(2, $stock);
    }

    /** @test */
    public function can_get_low_stock_products(): void
    {
        // Current product has 100 with min 10 - not low
        // Create a low stock product
        $lowProduct = Product::create([
            'name' => 'Low Stock',
            'sku' => 'LOW-001',
            'price' => 5.99,
            'stock_tracked' => true,
            'min_stock_level' => 20,
        ]);

        StockLevel::create([
            'product_id' => $lowProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5, // Below min of 20
        ]);

        $lowStockProducts = $this->stockService->getLowStockProducts($this->warehouse);

        $this->assertTrue($lowStockProducts->contains('id', $lowProduct->id));
        $this->assertFalse($lowStockProducts->contains('id', $this->product->id));
    }

    /** @test */
    public function can_get_out_of_stock_products(): void
    {
        $outOfStock = Product::create([
            'name' => 'Out of Stock',
            'sku' => 'OOS-001',
            'price' => 15.99,
            'stock_tracked' => true,
        ]);

        StockLevel::create([
            'product_id' => $outOfStock->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 0,
        ]);

        $outOfStockProducts = $this->stockService->getOutOfStockProducts($this->warehouse);

        $this->assertTrue($outOfStockProducts->contains('id', $outOfStock->id));
    }

    /** @test */
    public function can_reserve_stock(): void
    {
        $reserved = $this->stockService->reserveStock(
            $this->product,
            $this->warehouse,
            10
        );

        $this->assertTrue($reserved);

        $stockLevel = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertEquals(10, $stockLevel->reserved_quantity);
        $this->assertEquals(90, $stockLevel->available_quantity);
    }

    /** @test */
    public function cannot_reserve_more_than_available(): void
    {
        $reserved = $this->stockService->reserveStock(
            $this->product,
            $this->warehouse,
            150
        );

        $this->assertFalse($reserved);
    }

    /** @test */
    public function can_release_reservation(): void
    {
        // First reserve
        $this->stockService->reserveStock($this->product, $this->warehouse, 20);

        // Then release
        $this->stockService->releaseReservation($this->product, $this->warehouse, 20);

        $stockLevel = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertEquals(0, $stockLevel->reserved_quantity);
    }

    /** @test */
    public function can_get_movement_history(): void
    {
        // Create some movements
        $this->stockService->adjust($this->product, $this->warehouse, 10, 'Adjustment 1', $this->user);
        $this->stockService->adjust($this->product, $this->warehouse, 5, 'Adjustment 2', $this->user);

        $history = $this->stockService->getMovementHistory($this->product, 10);

        $this->assertCount(2, $history);
        $this->assertEquals(5, $history->first()->quantity_change); // Most recent first
    }

    /** @test */
    public function movement_records_user(): void
    {
        $movement = $this->stockService->adjust(
            $this->product,
            $this->warehouse,
            10,
            'Test',
            $this->user
        );

        $this->assertEquals($this->user->id, $movement->user_id);
    }
}
