<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseInvoice;
use App\Services\PurchaseInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        protected PurchaseInvoiceService $purchaseInvoiceService
    ) {}

    /**
     * List all purchase invoices.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'supplier_id',
            'warehouse_id',
            'payment_status',
            'from_date',
            'to_date',
            'search',
            'sort_by',
            'sort_dir',
            'per_page',
        ]);

        $invoices = $this->purchaseInvoiceService->getInvoices($filters);

        return $this->success([
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /**
     * Create a new purchase invoice.
     * 
     * This immediately:
     * - Creates the invoice
     * - Increases inventory stock for all items
     * - Records stock movements
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'supplier_invoice_number' => 'nullable|string|max:100',
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'discount_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.update_cost_price' => 'nullable|boolean',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        $invoice = $this->purchaseInvoiceService->createPurchaseInvoice($validated);

        return $this->success(
            $invoice,
            __('messages.purchase_invoices.created'),
            201
        );
    }

    /**
     * Get a specific purchase invoice.
     */
    public function show(int $id): JsonResponse
    {
        $invoice = $this->purchaseInvoiceService->getInvoiceWithDetails($id);
        
        return $this->success($invoice);
    }

    /**
     * Record a payment against an invoice.
     */
    public function recordPayment(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.001',
            'notes' => 'nullable|string|max:500',
        ]);

        $invoice = PurchaseInvoice::findOrFail($id);
        
        $invoice = $this->purchaseInvoiceService->recordPayment(
            $invoice,
            $validated['amount'],
            $validated['notes'] ?? null
        );

        return $this->success(
            $invoice->load(['supplier', 'warehouse']),
            __('messages.purchase_invoices.payment_recorded')
        );
    }

    /**
     * Delete a purchase invoice (soft delete).
     * 
     * NOTE: Stock is NOT reversed. Use adjustments for corrections.
     */
    public function destroy(int $id): JsonResponse
    {
        $invoice = PurchaseInvoice::findOrFail($id);
        
        $this->purchaseInvoiceService->deleteInvoice($invoice);

        return $this->success(
            null,
            __('messages.purchase_invoices.deleted')
        );
    }
}
