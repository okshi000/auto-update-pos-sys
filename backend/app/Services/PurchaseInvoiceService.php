<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseInvoiceService
{
    public function __construct(
        protected StockService $stockService,
        protected AuditService $auditService
    ) {}

    /**
     * Create a new purchase invoice and immediately update stock.
     * 
     * CRITICAL: This is a finalized transaction that:
     * 1. Creates the invoice record
     * 2. Creates invoice items
     * 3. IMMEDIATELY increases inventory stock for each item
     * 4. Records stock movements for audit trail
     */
    public function createPurchaseInvoice(array $data): PurchaseInvoice
    {
        return DB::transaction(function () use ($data) {
            $warehouseId = $data['warehouse_id'] ?? Warehouse::getDefault()?->id;
            
            if (!$warehouseId) {
                throw new RuntimeException('No warehouse specified and no default warehouse configured');
            }

            $warehouse = Warehouse::findOrFail($warehouseId);

            // Create the invoice
            $invoice = PurchaseInvoice::create([
                'invoice_number' => PurchaseInvoice::generateInvoiceNumber(),
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $warehouseId,
                'created_by' => Auth::id(),
                'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'shipping_cost' => $data['shipping_cost'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            // Add items and update stock
            foreach ($data['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $quantity = (int) $itemData['quantity'];
                $unitCost = (float) $itemData['unit_cost'];
                $taxRate = (float) ($itemData['tax_rate'] ?? 0);
                $discountAmount = (float) ($itemData['discount_amount'] ?? 0);
                
                // Create invoice item
                $item = PurchaseInvoiceItem::createFromProduct(
                    $product,
                    $quantity,
                    $unitCost,
                    $taxRate,
                    $discountAmount
                );
                
                $item->purchase_invoice_id = $invoice->id;
                $item->notes = $itemData['notes'] ?? null;
                $item->save();

                // *** CRITICAL: Immediately increase stock ***
                $this->stockService->recordPurchase(
                    product: $product,
                    warehouse: $warehouse,
                    quantity: $quantity,
                    referenceType: 'purchase_invoice',
                    referenceId: $invoice->id,
                    user: Auth::user()
                );

                // Update product cost price if provided (optional feature)
                if (isset($itemData['update_cost_price']) && $itemData['update_cost_price']) {
                    $product->cost_price = $unitCost;
                    $product->save();
                }
            }

            // Calculate totals
            $invoice->refresh();
            $invoice->calculateTotals();
            $invoice->updatePaymentStatus();
            $invoice->save();

            // Log audit
            $this->auditService->logCreate($invoice);

            return $invoice->load(['items.product', 'supplier', 'warehouse', 'creator']);
        });
    }

    /**
     * Record a payment against a purchase invoice.
     */
    public function recordPayment(PurchaseInvoice $invoice, float $amount, ?string $notes = null): PurchaseInvoice
    {
        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero');
        }

        $oldData = $invoice->toArray();
        
        $invoice->paid_amount += $amount;
        $invoice->updatePaymentStatus();
        
        if ($notes) {
            $invoice->notes = $invoice->notes 
                ? $invoice->notes . "\n\nPayment: {$amount} - {$notes}" 
                : "Payment: {$amount} - {$notes}";
        }
        
        $invoice->save();

        $this->auditService->logUpdate($invoice, $oldData);

        return $invoice;
    }

    /**
     * Get purchase invoice with full details.
     */
    public function getInvoiceWithDetails(int $id): PurchaseInvoice
    {
        return PurchaseInvoice::with(['items.product', 'supplier', 'warehouse', 'creator'])
            ->findOrFail($id);
    }

    /**
     * Delete a purchase invoice (soft delete).
     * 
     * NOTE: Stock adjustments are NOT reversed on deletion.
     * Use stock adjustments/returns for inventory corrections.
     */
    public function deleteInvoice(PurchaseInvoice $invoice): void
    {
        $this->auditService->logDelete($invoice);
        $invoice->delete();
    }

    /**
     * Get invoices list with filters.
     */
    public function getInvoices(array $filters = [])
    {
        $query = PurchaseInvoice::with(['supplier', 'warehouse', 'creator']);

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('invoice_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('invoice_date', '<=', $filters['to_date']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('supplier_invoice_number', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }
}
