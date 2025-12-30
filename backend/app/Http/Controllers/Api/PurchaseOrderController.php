<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchaseOrderService $purchaseOrderService
    ) {}

    /**
     * List all purchase orders.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrder::with(['supplier', 'warehouse', 'creator']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by supplier
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        // Filter by warehouse
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('order_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('order_date', '<=', $request->input('to_date'));
        }

        // Search by PO number or reference
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = $request->input('per_page', 15);
        $purchaseOrders = $query->paginate($perPage);

        return $this->success([
            'data' => $purchaseOrders->items(),
            'meta' => [
                'current_page' => $purchaseOrders->currentPage(),
                'last_page' => $purchaseOrders->lastPage(),
                'per_page' => $purchaseOrders->perPage(),
                'total' => $purchaseOrders->total(),
            ],
        ]);
    }

    /**
     * Create a new purchase order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'order_date' => 'nullable|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'reference' => 'nullable|string|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        $po = $this->purchaseOrderService->createPurchaseOrder($validated);

        return $this->created($po, 'Purchase order created successfully');
    }

    /**
     * Show a single purchase order.
     */
    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load([
            'items.product',
            'supplier',
            'warehouse',
            'creator',
            'receiver',
        ]);

        return $this->success($purchaseOrder);
    }

    /**
     * Update a purchase order.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'sometimes|required|exists:suppliers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'order_date' => 'nullable|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'reference' => 'nullable|string|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'items' => 'sometimes|required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        try {
            $po = $this->purchaseOrderService->updatePurchaseOrder($purchaseOrder, $validated);

            return $this->success($po, 'Purchase order updated successfully');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Delete a purchase order (soft delete).
     */
    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (!$purchaseOrder->canEdit()) {
            return $this->error('Only draft purchase orders can be deleted', 422);
        }

        $purchaseOrder->delete();

        return $this->success(null, 'Purchase order deleted successfully');
    }

    /**
     * Send a purchase order to supplier.
     */
    public function send(PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $po = $this->purchaseOrderService->sendPurchaseOrder($purchaseOrder);

            return $this->success($po, 'Purchase order sent to supplier');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Receive goods from a purchase order.
     */
    public function receive(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity' => 'required|integer|min:0',
        ]);

        try {
            $po = $this->purchaseOrderService->receiveGoods($purchaseOrder, $validated['items']);

            return $this->success($po, 'Goods received successfully');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Cancel a purchase order.
     */
    public function cancel(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $po = $this->purchaseOrderService->cancelPurchaseOrder(
                $purchaseOrder,
                $validated['reason'] ?? null
            );

            return $this->success($po, 'Purchase order cancelled');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
