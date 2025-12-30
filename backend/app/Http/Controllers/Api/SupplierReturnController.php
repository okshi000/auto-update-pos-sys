<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierReturn;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierReturnController extends Controller
{
    public function __construct(
        protected PurchaseOrderService $purchaseOrderService
    ) {}

    /**
     * List all supplier returns.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SupplierReturn::with(['supplier', 'warehouse', 'creator']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by supplier
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        // Filter by reason
        if ($request->filled('reason')) {
            $query->where('reason', $request->input('reason'));
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        // Search by return number
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('return_number', 'like', "%{$search}%");
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = $request->input('per_page', 15);
        $returns = $query->paginate($perPage);

        return response()->json($returns);
    }

    /**
     * Create a new supplier return.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'reason' => ['required', 'string', Rule::in(SupplierReturn::REASONS)],
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        try {
            $return = $this->purchaseOrderService->createSupplierReturn($validated);

            return response()->json([
                'message' => 'Supplier return created successfully',
                'data' => $return,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Show a single supplier return.
     */
    public function show(SupplierReturn $supplierReturn): JsonResponse
    {
        $supplierReturn->load([
            'items.product',
            'supplier',
            'warehouse',
            'creator',
            'purchaseOrder',
        ]);

        return response()->json([
            'data' => $supplierReturn,
        ]);
    }

    /**
     * Approve a supplier return.
     */
    public function approve(SupplierReturn $supplierReturn): JsonResponse
    {
        try {
            $return = $this->purchaseOrderService->approveSupplierReturn($supplierReturn);

            return response()->json([
                'message' => 'Supplier return approved',
                'data' => $return,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Mark supplier return as shipped.
     */
    public function ship(SupplierReturn $supplierReturn): JsonResponse
    {
        try {
            $return = $this->purchaseOrderService->shipSupplierReturn($supplierReturn);

            return response()->json([
                'message' => 'Supplier return marked as shipped',
                'data' => $return,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Complete a supplier return.
     */
    public function complete(SupplierReturn $supplierReturn): JsonResponse
    {
        try {
            $return = $this->purchaseOrderService->completeSupplierReturn($supplierReturn);

            return response()->json([
                'message' => 'Supplier return completed',
                'data' => $return,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel a supplier return.
     */
    public function cancel(Request $request, SupplierReturn $supplierReturn): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $return = $this->purchaseOrderService->cancelSupplierReturn(
                $supplierReturn,
                $validated['reason'] ?? null
            );

            return response()->json([
                'message' => 'Supplier return cancelled',
                'data' => $return,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
