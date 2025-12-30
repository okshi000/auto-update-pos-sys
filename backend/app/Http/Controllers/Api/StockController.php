<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustmentRequest;
use App\Http\Resources\StockLevelResource;
use App\Http\Resources\StockMovementResource;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    /**
     * Get inventory listing with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockLevel::query()
            ->with(['product', 'warehouse'])
            ->whereHas('product', function ($q) {
                $q->where('is_active', true);
            });

        // Filter by warehouse
        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        // Filter by low stock
        if ($request->boolean('low_stock_only')) {
            $query->whereRaw('quantity <= COALESCE((SELECT min_stock_level FROM products WHERE products.id = stock_levels.product_id), 0)');
        }

        // Search by product name/sku
        if ($search = $request->input('search')) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 15);
        $stockLevels = $query->paginate($perPage);

        return $this->success([
            'data' => StockLevelResource::collection($stockLevels),
            'meta' => [
                'current_page' => $stockLevels->currentPage(),
                'last_page' => $stockLevels->lastPage(),
                'per_page' => $stockLevels->perPage(),
                'total' => $stockLevels->total(),
                'from' => $stockLevels->firstItem(),
                'to' => $stockLevels->lastItem(),
            ],
        ]);
    }

    /**
     * Adjust stock.
     */
    public function adjust(StockAdjustmentRequest $request): JsonResponse
    {
        try {
            $movement = $this->stockService->adjust(
                product: $request->input('product_id'),
                warehouse: $request->input('warehouse_id'),
                quantityChange: $request->input('quantity'),
                reason: $request->input('reason')
            );

            return $this->success(
                new StockMovementResource($movement->load(['product', 'warehouse', 'user'])),
                'Stock adjusted successfully'
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Set absolute stock level.
     */
    public function setStock(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:500',
        ]);

        $movement = $this->stockService->setStock(
            product: $request->input('product_id'),
            warehouse: $request->input('warehouse_id'),
            newQuantity: $request->input('quantity'),
            reason: $request->input('reason')
        );

        return $this->success(
            new StockMovementResource($movement->load(['product', 'warehouse', 'user'])),
            'Stock level set successfully'
        );
    }

    /**
     * Transfer stock between warehouses.
     */
    public function transfer(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $movements = $this->stockService->transfer(
                product: $request->input('product_id'),
                fromWarehouse: $request->input('from_warehouse_id'),
                toWarehouse: $request->input('to_warehouse_id'),
                quantity: $request->input('quantity'),
                reason: $request->input('reason')
            );

            return $this->success([
                'out' => new StockMovementResource($movements['out']->load(['product', 'warehouse'])),
                'in' => new StockMovementResource($movements['in']->load(['product', 'warehouse'])),
            ], 'Stock transferred successfully');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Get stock level for a product.
     */
    public function productStock(int $productId): JsonResponse
    {
        $stockLevels = $this->stockService->getProductStock($productId);

        return $this->success(StockLevelResource::collection($stockLevels));
    }

    /**
     * Get all low stock products.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $warehouseId = $request->input('warehouse_id');
        $lowStock = $this->stockService->getLowStockProducts($warehouseId);

        return $this->success(StockLevelResource::collection($lowStock));
    }

    /**
     * Get all out of stock products.
     */
    public function outOfStock(Request $request): JsonResponse
    {
        $warehouseId = $request->input('warehouse_id');
        $outOfStock = $this->stockService->getOutOfStockProducts($warehouseId);

        return $this->success(StockLevelResource::collection($outOfStock));
    }

    /**
     * Get stock movement history.
     */
    public function movements(Request $request): JsonResponse
    {
        $movements = $this->stockService->getMovementHistory(
            product: $request->input('product_id'),
            perPage: $request->input('per_page', 15)
        );

        return $this->success([
            'data' => StockMovementResource::collection($movements),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    /**
     * Get available movement types.
     */
    public function movementTypes(): JsonResponse
    {
        return $this->success(StockMovement::getTypes());
    }
}
