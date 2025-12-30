<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\AuditService;
use App\Services\ReportService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function __construct(
        protected ReportService $reportService,
        protected StockService $stockService,
        protected AuditService $auditService
    ) {}

    /**
     * GET /api/reconciliation/conflicts
     * List all sales with stock conflicts.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
        ]);

        $conflicts = $this->reportService->getConflicts(
            warehouseId: $request->warehouse_id
        );

        return response()->json([
            'success' => true,
            'data' => $conflicts,
            'count' => $conflicts->count(),
        ]);
    }

    /**
     * GET /api/reconciliation/{id}
     * Get details of a specific conflicted sale.
     */
    public function show(int $id): JsonResponse
    {
        $sale = $this->reportService->getConflictDetails($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $sale,
        ]);
    }

    /**
     * POST /api/reconciliation/{id}/accept
     * Accept the conflict - keep the sale as-is.
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => 'sometimes|string|max:500',
        ]);

        $sale = Sale::find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found',
            ], 404);
        }

        if (!$sale->has_stock_conflict) {
            return response()->json([
                'success' => false,
                'message' => 'This sale does not have a stock conflict',
            ], 422);
        }

        $sale = $this->reportService->acceptConflict($sale, $request->notes);

        $this->auditService->log(
            action: 'reconciliation.accept',
            auditable: $sale,
            newValues: [
                'sale_number' => $sale->sale_number,
                'notes' => $request->notes,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Conflict resolved - sale accepted',
            'data' => $sale->load(['items.product', 'warehouse']),
        ]);
    }

    /**
     * POST /api/reconciliation/{id}/adjust
     * Adjust the sale items to resolve conflict.
     */
    public function adjust(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|integer|exists:sale_items,id',
            'items.*.new_quantity' => 'required|integer|min:0',
            'notes' => 'sometimes|string|max:500',
        ]);

        $sale = Sale::with('items')->find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found',
            ], 404);
        }

        if (!$sale->has_stock_conflict) {
            return response()->json([
                'success' => false,
                'message' => 'This sale does not have a stock conflict',
            ], 422);
        }

        $adjustments = collect($request->items)->keyBy('sale_item_id');
        $originalTotal = $sale->total;
        $newSubtotal = 0;

        foreach ($sale->items as $item) {
            if ($adjustments->has($item->id)) {
                $adjustment = $adjustments[$item->id];
                $oldQuantity = $item->quantity;
                $newQuantity = $adjustment['new_quantity'];

                if ($newQuantity !== $oldQuantity) {
                    $quantityDiff = $newQuantity - $oldQuantity;
                    
                    // If quantity reduced, restore stock
                    if ($quantityDiff < 0 && $item->product && $item->product->stock_tracked) {
                        $this->stockService->recordReturn(
                            product: $item->product,
                            warehouse: $sale->warehouse,
                            quantity: abs($quantityDiff),
                            referenceType: 'sale',
                            referenceId: $sale->id,
                            user: $request->user()
                        );
                    }

                    // Update item
                    $item->quantity = $newQuantity;
                    $item->line_total = $item->unit_price * $newQuantity;
                    $item->save();
                }
            }
            
            $newSubtotal += $item->line_total;
        }

        // Recalculate sale totals
        $sale->subtotal = $newSubtotal;
        $sale->total = $newSubtotal + $sale->tax_total - $sale->discount_amount;
        $sale->has_stock_conflict = false;
        
        if ($request->notes) {
            $sale->notes = $sale->notes 
                ? $sale->notes . "\n\nConflict resolved (adjusted): " . $request->notes 
                : "Conflict resolved (adjusted): " . $request->notes;
        }
        
        $sale->save();

        $this->auditService->log(
            action: 'reconciliation.adjust',
            auditable: $sale,
            oldValues: ['original_total' => $originalTotal],
            newValues: [
                'sale_number' => $sale->sale_number,
                'new_total' => $sale->total,
                'adjustments' => $request->items,
                'notes' => $request->notes,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Conflict resolved - sale adjusted',
            'data' => $sale->fresh(['items.product', 'warehouse']),
        ]);
    }

    /**
     * POST /api/reconciliation/{id}/void
     * Void the conflicted sale entirely.
     */
    public function void(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $sale = Sale::with(['items.product', 'warehouse', 'payments'])->find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found',
            ], 404);
        }

        if (!$sale->has_stock_conflict) {
            return response()->json([
                'success' => false,
                'message' => 'This sale does not have a stock conflict',
            ], 422);
        }

        if ($sale->status === Sale::STATUS_REFUNDED) {
            return response()->json([
                'success' => false,
                'message' => 'This sale has already been refunded',
            ], 422);
        }

        $sale = $this->reportService->voidConflictedSale(
            sale: $sale,
            stockService: $this->stockService,
            reason: $request->reason
        );

        $this->auditService->log(
            action: 'reconciliation.void',
            auditable: $sale,
            newValues: [
                'sale_number' => $sale->sale_number,
                'total' => $sale->total,
                'reason' => $request->reason,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Conflict resolved - sale voided',
            'data' => $sale,
        ]);
    }
}
