<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    /**
     * Resolve ID from model or int.
     */
    protected function resolveId(Model|int $modelOrId): int
    {
        return $modelOrId instanceof Model ? $modelOrId->getKey() : $modelOrId;
    }

    /**
     * Adjust stock for a product at a warehouse.
     */
    public function adjust(
        Product|int $product,
        Warehouse|int $warehouse,
        int $quantityChange,
        ?string $reason = null,
        ?User $user = null
    ): StockMovement {
        $productId = $this->resolveId($product);
        $warehouseId = $this->resolveId($warehouse);
        $userId = $user?->getKey() ?? Auth::id();

        return DB::transaction(function () use (
            $productId,
            $warehouseId,
            $quantityChange,
            $reason,
            $userId
        ) {
            // Get or create stock level
            $stockLevel = StockLevel::findOrCreateForProductAndWarehouse($productId, $warehouseId);

            $quantityBefore = $stockLevel->quantity;
            $quantityAfter = $quantityBefore + $quantityChange;

            // Prevent negative stock
            if ($quantityAfter < 0) {
                throw new RuntimeException(
                    "Insufficient stock. Available: {$quantityBefore}, Requested: " . abs($quantityChange)
                );
            }

            // Update stock level
            $stockLevel->quantity = $quantityAfter;
            $stockLevel->save();
            
            // Clear POS products cache since stock changed
            $this->clearPosProductsCache();

            // Record movement
            return StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity_change' => $quantityChange,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stockLevel->quantity,
                'type' => StockMovement::TYPE_ADJUSTMENT,
                'reason' => $reason,
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * Set absolute stock level (creates adjustment).
     */
    public function setStock(
        Product|int $product,
        Warehouse|int $warehouse,
        int $newQuantity,
        ?string $reason = null,
        ?User $user = null
    ): StockMovement {
        $productId = $this->resolveId($product);
        $warehouseId = $this->resolveId($warehouse);
        $userId = $user?->getKey() ?? Auth::id();

        return DB::transaction(function () use ($productId, $warehouseId, $newQuantity, $reason, $userId) {
            $stockLevel = StockLevel::findOrCreateForProductAndWarehouse($productId, $warehouseId);
            $quantityBefore = $stockLevel->quantity;
            $difference = $newQuantity - $quantityBefore;

            // Update stock level
            $stockLevel->quantity = $newQuantity;
            $stockLevel->save();
            
            // Clear POS products cache since stock changed
            $this->clearPosProductsCache();

            // Record movement
            return StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity_change' => $difference,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $newQuantity,
                'type' => StockMovement::TYPE_CORRECTION,
                'reason' => $reason ?? 'Stock level set to ' . $newQuantity,
                'user_id' => $userId,
            ]);
        });
    }
    
    /**
     * Clear POS products cache when stock levels change.
     */
    protected function clearPosProductsCache(): void
    {
        Cache::forget('pos_products');
        
        // Clear category-specific caches
        $categories = \App\Models\Category::pluck('id');
        foreach ($categories as $catId) {
            Cache::forget("pos_products_cat_{$catId}");
        }
    }

    /**
     * Transfer stock between warehouses.
     */
    public function transfer(
        Product|int $product,
        Warehouse|int $fromWarehouse,
        Warehouse|int $toWarehouse,
        int $quantity,
        ?string $reason = null,
        ?User $user = null
    ): array {
        $productId = $this->resolveId($product);
        $fromWarehouseId = $this->resolveId($fromWarehouse);
        $toWarehouseId = $this->resolveId($toWarehouse);
        $userId = $user?->getKey() ?? Auth::id();

        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Transfer quantity must be positive');
        }

        if ($fromWarehouseId === $toWarehouseId) {
            throw new \InvalidArgumentException('Cannot transfer to the same warehouse');
        }

        return DB::transaction(function () use ($productId, $fromWarehouseId, $toWarehouseId, $quantity, $reason, $userId) {
            // Get source stock level
            $sourceStock = StockLevel::findOrCreateForProductAndWarehouse($productId, $fromWarehouseId);
            
            if ($sourceStock->quantity < $quantity) {
                throw new RuntimeException(
                    "Insufficient stock. Available: {$sourceStock->quantity}, Requested: {$quantity}"
                );
            }

            $sourceQuantityBefore = $sourceStock->quantity;
            $sourceStock->quantity -= $quantity;
            $sourceStock->save();

            // Get destination stock level
            $destStock = StockLevel::findOrCreateForProductAndWarehouse($productId, $toWarehouseId);
            $destQuantityBefore = $destStock->quantity;
            $destStock->quantity += $quantity;
            $destStock->save();

            // Record outgoing movement
            $outMovement = StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $fromWarehouseId,
                'quantity_change' => -$quantity,
                'quantity_before' => $sourceQuantityBefore,
                'quantity_after' => $sourceStock->quantity,
                'type' => StockMovement::TYPE_TRANSFER_OUT,
                'reason' => $reason ?? "Transfer to warehouse ID: {$toWarehouseId}",
                'user_id' => $userId,
            ]);

            // Record incoming movement
            $inMovement = StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $toWarehouseId,
                'quantity_change' => $quantity,
                'quantity_before' => $destQuantityBefore,
                'quantity_after' => $destStock->quantity,
                'type' => StockMovement::TYPE_TRANSFER_IN,
                'reason' => $reason ?? "Transfer from warehouse ID: {$fromWarehouseId}",
                'user_id' => $userId,
            ]);

            return ['out' => $outMovement, 'in' => $inMovement];
        });
    }

    /**
     * Force deduct stock (allows negative stock for offline sync).
     */
    public function forceDeductStock(
        Product|int $product,
        Warehouse|int $warehouse,
        int $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?User $user = null
    ): StockMovement {
        $productId = $this->resolveId($product);
        $warehouseId = $this->resolveId($warehouse);
        $userId = $user?->getKey() ?? Auth::id();

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $referenceType, $referenceId, $userId) {
            $stockLevel = StockLevel::findOrCreateForProductAndWarehouse($productId, $warehouseId);
            $quantityBefore = $stockLevel->quantity;

            // Allow negative stock
            $stockLevel->quantity -= $quantity;
            $stockLevel->save();

            return StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity_change' => -$quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stockLevel->quantity,
                'type' => StockMovement::TYPE_SALE,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reason' => 'Offline sale (stock conflict)',
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * Record a sale (deduct stock).
     */
    public function recordSale(
        Product|int $product,
        Warehouse|int $warehouse,
        int $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?User $user = null
    ): StockMovement {
        $productId = $this->resolveId($product);
        $warehouseId = $this->resolveId($warehouse);
        $userId = $user?->getKey() ?? Auth::id();

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $referenceType, $referenceId, $userId) {
            $stockLevel = StockLevel::findOrCreateForProductAndWarehouse($productId, $warehouseId);
            $quantityBefore = $stockLevel->quantity;
            
            if ($quantityBefore < $quantity) {
                throw new RuntimeException(
                    "Insufficient stock. Available: {$quantityBefore}, Requested: {$quantity}"
                );
            }

            $stockLevel->quantity -= $quantity;
            $stockLevel->save();

            return StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity_change' => -$quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stockLevel->quantity,
                'type' => StockMovement::TYPE_SALE,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * Record a purchase (add stock).
     */
    public function recordPurchase(
        Product|int $product,
        Warehouse|int $warehouse,
        int $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?User $user = null
    ): StockMovement {
        $productId = $this->resolveId($product);
        $warehouseId = $this->resolveId($warehouse);
        $userId = $user?->getKey() ?? Auth::id();

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $referenceType, $referenceId, $userId) {
            $stockLevel = StockLevel::findOrCreateForProductAndWarehouse($productId, $warehouseId);
            $quantityBefore = $stockLevel->quantity;

            $stockLevel->quantity += $quantity;
            $stockLevel->save();

            return StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity_change' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stockLevel->quantity,
                'type' => StockMovement::TYPE_PURCHASE,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * Record a return (add stock back).
     */
    public function recordReturn(
        Product|int $product,
        Warehouse|int $warehouse,
        int $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?User $user = null
    ): StockMovement {
        $productId = $this->resolveId($product);
        $warehouseId = $this->resolveId($warehouse);
        $userId = $user?->getKey() ?? Auth::id();

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $referenceType, $referenceId, $userId) {
            $stockLevel = StockLevel::findOrCreateForProductAndWarehouse($productId, $warehouseId);
            $quantityBefore = $stockLevel->quantity;

            $stockLevel->quantity += $quantity;
            $stockLevel->save();

            return StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity_change' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stockLevel->quantity,
                'type' => StockMovement::TYPE_RETURN,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * Record damage (deduct stock).
     */
    public function recordDamage(
        Product|int $product,
        Warehouse|int $warehouse,
        int $quantity,
        ?string $reason = null,
        ?User $user = null
    ): StockMovement {
        $productId = $this->resolveId($product);
        $warehouseId = $this->resolveId($warehouse);
        $userId = $user?->getKey() ?? Auth::id();

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $reason, $userId) {
            $stockLevel = StockLevel::findOrCreateForProductAndWarehouse($productId, $warehouseId);
            $quantityBefore = $stockLevel->quantity;

            $stockLevel->quantity = max(0, $stockLevel->quantity - $quantity);
            $stockLevel->save();

            return StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity_change' => -$quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stockLevel->quantity,
                'type' => StockMovement::TYPE_DAMAGE,
                'reason' => $reason,
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * Record a stock adjustment (add or subtract stock).
     */
    public function recordAdjustment(
        Product|int $product,
        Warehouse|int $warehouse,
        int $quantity,
        ?string $reason = null,
        ?User $user = null
    ): StockMovement {
        $productId = $this->resolveId($product);
        $warehouseId = $this->resolveId($warehouse);
        $userId = $user?->getKey() ?? Auth::id();

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $reason, $userId) {
            $stockLevel = StockLevel::findOrCreateForProductAndWarehouse($productId, $warehouseId);
            $quantityBefore = $stockLevel->quantity;

            $stockLevel->quantity += $quantity; // Can be positive or negative
            $stockLevel->quantity = max(0, $stockLevel->quantity); // Prevent negative stock
            $stockLevel->save();

            return StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity_change' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stockLevel->quantity,
                'type' => StockMovement::TYPE_ADJUSTMENT,
                'reason' => $reason,
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * Record a supplier return (deduct stock).
     */
    public function recordSupplierReturn(
        Product|int $product,
        Warehouse|int $warehouse,
        int $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?User $user = null
    ): StockMovement {
        $productId = $this->resolveId($product);
        $warehouseId = $this->resolveId($warehouse);
        $userId = $user?->getKey() ?? Auth::id();

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $referenceType, $referenceId, $userId) {
            $stockLevel = StockLevel::findOrCreateForProductAndWarehouse($productId, $warehouseId);
            $quantityBefore = $stockLevel->quantity;

            $stockLevel->quantity = max(0, $stockLevel->quantity - $quantity);
            $stockLevel->save();

            return StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity_change' => -$quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stockLevel->quantity,
                'type' => StockMovement::TYPE_SUPPLIER_RETURN,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * Get stock level for product at warehouse.
     */
    public function getStockLevel(Product|int $product, Warehouse|int $warehouse): int
    {
        $productId = $this->resolveId($product);
        $warehouseId = $this->resolveId($warehouse);

        $stockLevel = StockLevel::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return $stockLevel ? $stockLevel->quantity : 0;
    }

    /**
     * Get all stock levels for a product across warehouses.
     */
    public function getProductStock(Product|int $product): \Illuminate\Database\Eloquent\Collection
    {
        $productId = $this->resolveId($product);

        return StockLevel::with('warehouse')
            ->where('product_id', $productId)
            ->get();
    }

    /**
     * Get all stock levels for a warehouse.
     */
    public function getWarehouseStock(Warehouse|int $warehouse, bool $includeZeroStock = false): \Illuminate\Database\Eloquent\Collection
    {
        $warehouseId = $this->resolveId($warehouse);

        $query = StockLevel::with('product')
            ->where('warehouse_id', $warehouseId);

        if (!$includeZeroStock) {
            $query->where('quantity', '>', 0);
        }

        return $query->get();
    }

    /**
     * Get low stock products.
     */
    public function getLowStockProducts(Warehouse|int|null $warehouse = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = StockLevel::with(['product', 'warehouse'])
            ->lowStock();

        if ($warehouse !== null) {
            $warehouseId = $this->resolveId($warehouse);
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->get();
    }

    /**
     * Get out of stock products.
     */
    public function getOutOfStockProducts(Warehouse|int|null $warehouse = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = StockLevel::with(['product', 'warehouse'])
            ->outOfStock();

        if ($warehouse !== null) {
            $warehouseId = $this->resolveId($warehouse);
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->get();
    }

    /**
     * Get stock movement history.
     */
    public function getMovementHistory(
        Product|int|null $product = null,
        int $perPage = 15
    ) {
        $query = StockMovement::with(['product', 'warehouse', 'user']);

        if ($product !== null) {
            $productId = $this->resolveId($product);
            $query->where('product_id', $productId);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    /**
     * Reserve stock for an order.
     */
    public function reserveStock(Product|int $product, Warehouse|int $warehouse, int $quantity): bool
    {
        $productId = $this->resolveId($product);
        $warehouseId = $this->resolveId($warehouse);

        $stockLevel = StockLevel::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (!$stockLevel) {
            return false;
        }

        return $stockLevel->reserve($quantity);
    }

    /**
     * Release reserved stock.
     */
    public function releaseReservation(Product|int $product, Warehouse|int $warehouse, int $quantity): bool
    {
        $productId = $this->resolveId($product);
        $warehouseId = $this->resolveId($warehouse);

        $stockLevel = StockLevel::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (!$stockLevel) {
            return false;
        }

        return $stockLevel->releaseReservation($quantity);
    }
}
