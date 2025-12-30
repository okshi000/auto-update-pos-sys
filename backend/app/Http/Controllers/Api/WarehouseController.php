<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Http\Resources\StockLevelResource;
use App\Http\Resources\StockMovementResource;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use App\Services\AuditService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(
        protected StockService $stockService,
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of warehouses.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Warehouse::query();

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $warehouses = $query->orderBy('name')->get();

        return $this->success(WarehouseResource::collection($warehouses));
    }

    /**
     * Store a newly created warehouse.
     */
    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::create($request->validated());

        $this->auditService->logCreate($warehouse);

        return $this->created(
            new WarehouseResource($warehouse),
            'Warehouse created successfully'
        );
    }

    /**
     * Display the specified warehouse.
     */
    public function show(Request $request, Warehouse $warehouse): JsonResponse
    {
        return $this->success(new WarehouseResource($warehouse));
    }

    /**
     * Update the specified warehouse.
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $oldValues = $warehouse->toArray();

        $warehouse->update($request->validated());

        $this->auditService->logUpdate($warehouse, $oldValues);

        return $this->success(
            new WarehouseResource($warehouse),
            'Warehouse updated successfully'
        );
    }

    /**
     * Remove the specified warehouse.
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        // Check if warehouse has stock
        if ($warehouse->stockLevels()->where('quantity', '>', 0)->exists()) {
            return $this->error(
                'Cannot delete warehouse with existing stock. Please transfer stock first.',
                400
            );
        }

        // Check if it's the default warehouse
        if ($warehouse->is_default) {
            return $this->error('Cannot delete the default warehouse.', 400);
        }

        $this->auditService->logDelete($warehouse);
        $warehouse->delete();

        return $this->success(null, 'Warehouse deleted successfully');
    }

    /**
     * Get stock levels for a warehouse.
     */
    public function stock(Request $request, Warehouse $warehouse): JsonResponse
    {
        $includeZero = $request->boolean('include_zero');

        $stockLevels = $this->stockService->getWarehouseStock($warehouse->id, $includeZero);

        return $this->success(StockLevelResource::collection($stockLevels));
    }

    /**
     * Get low stock products in warehouse.
     */
    public function lowStock(Warehouse $warehouse): JsonResponse
    {
        $lowStock = $this->stockService->getLowStockProducts($warehouse->id);

        return $this->success(StockLevelResource::collection($lowStock));
    }

    /**
     * Get stock movements for a warehouse.
     */
    public function movements(Request $request, Warehouse $warehouse): JsonResponse
    {
        $movements = $this->stockService->getMovementHistory(
            productId: $request->input('product_id'),
            warehouseId: $warehouse->id,
            type: $request->input('type'),
            startDate: $request->input('start_date'),
            endDate: $request->input('end_date'),
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
}
