<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseOrderService
{
    public function __construct(
        protected StockService $stockService,
        protected AuditService $auditService
    ) {}

    /**
     * Create a new purchase order.
     */
    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $po = PurchaseOrder::create([
                'po_number' => PurchaseOrder::generatePoNumber(),
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'] ?? Warehouse::getDefault()?->id,
                'created_by' => Auth::id(),
                'status' => PurchaseOrder::STATUS_DRAFT,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_date' => $data['expected_date'] ?? null,
                'reference' => $data['reference'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'shipping_cost' => $data['shipping_cost'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            // Add items
            foreach ($data['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                
                $item = PurchaseOrderItem::createFromProduct(
                    $product,
                    (int) $itemData['quantity'],
                    (float) $itemData['unit_cost']
                );
                
                if (isset($itemData['discount_amount'])) {
                    $item->discount_amount = $itemData['discount_amount'];
                    $item->calculateLineTotal();
                }
                
                $item->purchase_order_id = $po->id;
                $item->notes = $itemData['notes'] ?? null;
                $item->save();
            }

            // Calculate totals
            $po->refresh();
            $po->calculateTotals();
            $po->save();

            // Log audit
            $this->auditService->logCreate($po);

            return $po->load(['items.product', 'supplier', 'warehouse', 'creator']);
        });
    }

    /**
     * Update a purchase order.
     */
    public function updatePurchaseOrder(PurchaseOrder $po, array $data): PurchaseOrder
    {
        if (!$po->canEdit()) {
            throw new RuntimeException('This purchase order cannot be edited');
        }

        return DB::transaction(function () use ($po, $data) {
            $oldData = $po->toArray();

            $po->update([
                'supplier_id' => $data['supplier_id'] ?? $po->supplier_id,
                'warehouse_id' => $data['warehouse_id'] ?? $po->warehouse_id,
                'order_date' => $data['order_date'] ?? $po->order_date,
                'expected_date' => $data['expected_date'] ?? $po->expected_date,
                'reference' => $data['reference'] ?? $po->reference,
                'discount_amount' => $data['discount_amount'] ?? $po->discount_amount,
                'shipping_cost' => $data['shipping_cost'] ?? $po->shipping_cost,
                'notes' => $data['notes'] ?? $po->notes,
            ]);

            // Update items if provided
            if (isset($data['items'])) {
                // Remove existing items
                $po->items()->delete();

                // Add new items
                foreach ($data['items'] as $itemData) {
                    $product = Product::findOrFail($itemData['product_id']);
                    
                    $item = PurchaseOrderItem::createFromProduct(
                        $product,
                        (int) $itemData['quantity'],
                        (float) $itemData['unit_cost']
                    );
                    
                    if (isset($itemData['discount_amount'])) {
                        $item->discount_amount = $itemData['discount_amount'];
                        $item->calculateLineTotal();
                    }
                    
                    $item->purchase_order_id = $po->id;
                    $item->notes = $itemData['notes'] ?? null;
                    $item->save();
                }
            }

            // Recalculate totals
            $po->refresh();
            $po->calculateTotals();
            $po->save();

            // Log audit
            $this->auditService->logUpdate($po, $oldData);

            return $po->load(['items.product', 'supplier', 'warehouse', 'creator']);
        });
    }

    /**
     * Send a purchase order to supplier.
     */
    public function sendPurchaseOrder(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_DRAFT) {
            throw new RuntimeException('Only draft purchase orders can be sent');
        }

        $oldData = $po->toArray();
        $po->status = PurchaseOrder::STATUS_SENT;
        $po->save();

        $this->auditService->logUpdate($po, $oldData);

        return $po;
    }

    /**
     * Receive goods from a purchase order.
     * 
     * CRITICAL: This method uses DB transaction to ensure atomicity.
     */
    public function receiveGoods(PurchaseOrder $po, array $receivedItems): PurchaseOrder
    {
        if (!$po->canReceive()) {
            throw new RuntimeException('This purchase order cannot receive goods');
        }

        return DB::transaction(function () use ($po, $receivedItems) {
            $oldData = $po->toArray();
            $warehouse = $po->warehouse;

            foreach ($receivedItems as $receivedItem) {
                $poItem = $po->items()->findOrFail($receivedItem['item_id']);
                $quantityToReceive = (int) $receivedItem['quantity'];

                // Validate quantity
                $maxReceivable = $poItem->quantity_ordered - $poItem->quantity_received;
                if ($quantityToReceive > $maxReceivable) {
                    throw new RuntimeException(
                        "Cannot receive {$quantityToReceive} units for {$poItem->product->name}. " .
                        "Maximum receivable: {$maxReceivable}"
                    );
                }

                if ($quantityToReceive <= 0) {
                    continue;
                }

                // Update received quantity
                $poItem->quantity_received += $quantityToReceive;
                $poItem->save();

                // Increase stock
                $this->stockService->recordPurchase(
                    product: $poItem->product,
                    warehouse: $warehouse,
                    quantity: $quantityToReceive,
                    referenceType: 'purchase_order',
                    referenceId: $po->id,
                    user: Auth::user()
                );
            }

            // Update PO status
            $po->refresh();
            if ($po->isFullyReceived()) {
                $po->status = PurchaseOrder::STATUS_RECEIVED;
                $po->received_at = now();
                $po->received_by = Auth::id();
            } elseif ($po->isPartiallyReceived()) {
                $po->status = PurchaseOrder::STATUS_PARTIAL;
            }
            $po->save();

            // Log audit
            $this->auditService->logUpdate($po, $oldData);

            return $po->load(['items.product', 'supplier', 'warehouse']);
        });
    }

    /**
     * Cancel a purchase order.
     */
    public function cancelPurchaseOrder(PurchaseOrder $po, ?string $reason = null): PurchaseOrder
    {
        if (!$po->canCancel()) {
            throw new RuntimeException('This purchase order cannot be cancelled');
        }

        $oldData = $po->toArray();
        $po->status = PurchaseOrder::STATUS_CANCELLED;
        
        if ($reason) {
            $po->notes = $po->notes 
                ? $po->notes . "\n\nCancellation reason: " . $reason 
                : "Cancellation reason: " . $reason;
        }
        
        $po->save();

        $this->auditService->logUpdate($po, $oldData);

        return $po;
    }

    /**
     * Create a supplier return.
     * 
     * CRITICAL: Uses DB transaction for stock decrease atomicity.
     */
    public function createSupplierReturn(array $data): SupplierReturn
    {
        return DB::transaction(function () use ($data) {
            $warehouseId = $data['warehouse_id'] ?? Warehouse::getDefault()?->id;
            $warehouse = Warehouse::findOrFail($warehouseId);

            $return = SupplierReturn::create([
                'return_number' => SupplierReturn::generateReturnNumber(),
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'warehouse_id' => $warehouseId,
                'created_by' => Auth::id(),
                'status' => SupplierReturn::STATUS_PENDING,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $quantity = (int) $itemData['quantity'];

                // Validate stock availability
                if ($product->stock_tracked) {
                    $currentStock = $this->stockService->getStockLevel($product, $warehouse);
                    if ($currentStock < $quantity) {
                        throw new RuntimeException(
                            "Insufficient stock for {$product->name} to return. " .
                            "Available: {$currentStock}, Requested: {$quantity}"
                        );
                    }
                }

                $returnItem = new SupplierReturnItem([
                    'product_id' => $product->id,
                    'purchase_order_item_id' => $itemData['purchase_order_item_id'] ?? null,
                    'quantity' => $quantity,
                    'unit_cost' => $itemData['unit_cost'] ?? $product->cost ?? 0,
                    'notes' => $itemData['notes'] ?? null,
                ]);
                $returnItem->calculateLineTotal();
                $returnItem->supplier_return_id = $return->id;
                $returnItem->save();

                // Decrease stock immediately
                $this->stockService->recordSupplierReturn(
                    product: $product,
                    warehouse: $warehouse,
                    quantity: $quantity,
                    referenceType: 'supplier_return',
                    referenceId: $return->id,
                    user: Auth::user()
                );

                // Update PO item if linked
                if (isset($itemData['purchase_order_item_id'])) {
                    $poItem = PurchaseOrderItem::find($itemData['purchase_order_item_id']);
                    if ($poItem) {
                        $poItem->quantity_returned += $quantity;
                        $poItem->save();
                    }
                }
            }

            // Calculate total
            $return->refresh();
            $return->calculateTotal();
            $return->save();

            // Log audit
            $this->auditService->logCreate($return);

            return $return->load(['items.product', 'supplier', 'warehouse', 'creator']);
        });
    }

    /**
     * Approve a supplier return.
     */
    public function approveSupplierReturn(SupplierReturn $return): SupplierReturn
    {
        if ($return->status !== SupplierReturn::STATUS_PENDING) {
            throw new RuntimeException('Only pending returns can be approved');
        }

        $oldData = $return->toArray();
        $return->status = SupplierReturn::STATUS_APPROVED;
        $return->save();

        $this->auditService->logUpdate($return, $oldData);

        return $return;
    }

    /**
     * Mark supplier return as shipped.
     */
    public function shipSupplierReturn(SupplierReturn $return): SupplierReturn
    {
        if ($return->status !== SupplierReturn::STATUS_APPROVED) {
            throw new RuntimeException('Only approved returns can be shipped');
        }

        $oldData = $return->toArray();
        $return->status = SupplierReturn::STATUS_SHIPPED;
        $return->shipped_at = now();
        $return->save();

        $this->auditService->logUpdate($return, $oldData);

        return $return;
    }

    /**
     * Complete a supplier return.
     */
    public function completeSupplierReturn(SupplierReturn $return): SupplierReturn
    {
        if ($return->status !== SupplierReturn::STATUS_SHIPPED) {
            throw new RuntimeException('Only shipped returns can be completed');
        }

        $oldData = $return->toArray();
        $return->status = SupplierReturn::STATUS_COMPLETED;
        $return->completed_at = now();
        $return->save();

        $this->auditService->logUpdate($return, $oldData);

        return $return;
    }

    /**
     * Cancel a supplier return.
     * Note: Stock was already decreased, so we need to add it back.
     */
    public function cancelSupplierReturn(SupplierReturn $return, ?string $reason = null): SupplierReturn
    {
        if (!$return->canCancel()) {
            throw new RuntimeException('This return cannot be cancelled');
        }

        return DB::transaction(function () use ($return, $reason) {
            $oldData = $return->toArray();
            $warehouse = $return->warehouse;

            // Restore stock for each item
            foreach ($return->items as $item) {
                $product = $item->product;
                
                if ($product && $product->stock_tracked) {
                    $this->stockService->recordAdjustment(
                        product: $product,
                        warehouse: $warehouse,
                        quantity: $item->quantity, // Positive to restore stock
                        reason: 'Supplier return cancelled: ' . ($reason ?? 'No reason provided'),
                        user: Auth::user()
                    );
                }

                // Update PO item if linked
                if ($item->purchase_order_item_id) {
                    $poItem = PurchaseOrderItem::find($item->purchase_order_item_id);
                    if ($poItem) {
                        $poItem->quantity_returned -= $item->quantity;
                        $poItem->save();
                    }
                }
            }

            $return->status = SupplierReturn::STATUS_CANCELLED;
            if ($reason) {
                $return->notes = $return->notes 
                    ? $return->notes . "\n\nCancellation reason: " . $reason 
                    : "Cancellation reason: " . $reason;
            }
            $return->save();

            $this->auditService->logUpdate($return, $oldData);

            return $return;
        });
    }
}
