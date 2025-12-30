<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\OfflineSyncLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SaleService
{
    public function __construct(
        protected StockService $stockService,
        protected AuditService $auditService
    ) {}

    /**
     * Create a new POS sale.
     * 
     * @param array $data Sale data including items and payment
     * @param bool $allowNegativeStock Allow stock to go negative (for offline sync)
     * @return Sale
     * @throws RuntimeException
     */
    public function createPosSale(array $data, bool $allowNegativeStock = false): Sale
    {
        // Check for duplicate via idempotency key
        if (Sale::existsByIdempotencyKey($data['idempotency_key'])) {
            $existingSale = Sale::findByIdempotencyKey($data['idempotency_key']);
            return $existingSale->load(['items', 'payments.paymentMethod', 'user', 'warehouse']);
        }

        return DB::transaction(function () use ($data, $allowNegativeStock) {
            $warehouseId = $data['warehouse_id'] ?? Warehouse::getDefault()?->id;
            
            if (!$warehouseId) {
                throw new RuntimeException('No warehouse specified and no default warehouse found');
            }

            $warehouse = Warehouse::findOrFail($warehouseId);
            $stockConflicts = [];

            // Create the sale
            $sale = Sale::create([
                'invoice_number' => Sale::generateInvoiceNumber(),
                'idempotency_key' => $data['idempotency_key'],
                'client_uuid' => $data['client_uuid'] ?? null,
                'user_id' => Auth::id() ?? $data['user_id'],
                'warehouse_id' => $warehouseId,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'discount_type' => $data['discount_type'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => Sale::STATUS_COMPLETED,
                'is_synced' => $data['is_synced'] ?? true,
                'completed_at' => now(),
            ]);

            // Process items
            $subtotal = 0;

            foreach ($data['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $quantity = (int) $itemData['quantity'];

                // Validate stock (soft check - can still proceed if allowNegativeStock)
                if ($product->stock_tracked) {
                    $currentStock = $this->stockService->getStockLevel($product, $warehouse);
                    
                    if ($currentStock < $quantity) {
                        if (!$allowNegativeStock) {
                            throw new RuntimeException(
                                "Insufficient stock for {$product->name}. Available: {$currentStock}, Requested: {$quantity}"
                            );
                        }
                        // Record conflict for later reconciliation
                        $stockConflicts[] = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'requested' => $quantity,
                            'available' => $currentStock,
                            'deficit' => $quantity - $currentStock,
                        ];
                    }
                }

                // Create sale item
                $saleItem = SaleItem::createFromProduct($product, $quantity);
                
                // Apply item-level discount if provided
                if (isset($itemData['discount_amount'])) {
                    $saleItem->discount_amount = $itemData['discount_amount'];
                    $saleItem->calculateLineTotal();
                }
                
                $saleItem->sale_id = $sale->id;
                $saleItem->save();

                $subtotal += $saleItem->unit_price * $saleItem->quantity;

                // Deduct stock
                if ($product->stock_tracked) {
                    try {
                        $this->stockService->recordSale(
                            product: $product,
                            warehouse: $warehouse,
                            quantity: $quantity,
                            referenceType: 'sale',
                            referenceId: $sale->id,
                            user: Auth::user()
                        );
                    } catch (RuntimeException $e) {
                        if (!$allowNegativeStock) {
                            throw $e;
                        }
                        // For offline sync, force negative stock
                        $this->stockService->forceDeductStock(
                            product: $product,
                            warehouse: $warehouse,
                            quantity: $quantity,
                            referenceType: 'sale',
                            referenceId: $sale->id
                        );
                    }
                }
            }

            // Update sale totals
            $sale->subtotal = $subtotal;
            
            // Calculate discount value
            $discountValue = 0;
            if ($sale->discount_type === Sale::DISCOUNT_TYPE_PERCENTAGE && $sale->discount_amount > 0) {
                $discountValue = ($subtotal * $sale->discount_amount) / 100;
            } else {
                $discountValue = $sale->discount_amount ?? 0;
            }
            
            $sale->total = $subtotal - $discountValue;
            
            // Mark stock conflict if any
            if (!empty($stockConflicts)) {
                $sale->has_stock_conflict = true;
            }
            
            $sale->save();

            // Process payment
            $paymentsData = $data['payments'] ?? [];
            if (empty($paymentsData) && isset($data['payment'])) {
                $paymentsData = [$data['payment']];
            }

            if (empty($paymentsData)) {
                // Fallback to default if no payments provided
                $this->processPayment($sale, []);
            } else {
                foreach ($paymentsData as $paymentData) {
                    $this->processPayment($sale, $paymentData);
                }
            }

            // Log audit
            $this->auditService->logCreate($sale);

            return $sale->fresh(['items', 'payments.paymentMethod', 'user', 'warehouse']);
        });
    }

    /**
     * Process payment for a sale.
     */
    protected function processPayment(Sale $sale, array $paymentData): Payment
    {
        $paymentMethodId = $paymentData['payment_method_id'] 
            ?? PaymentMethod::getDefault()?->id;

        if (!$paymentMethodId) {
            throw new RuntimeException('No payment method specified and no default found');
        }

        $paymentMethod = PaymentMethod::findOrFail($paymentMethodId);
        $amount = $paymentData['amount'] ?? $sale->total;
        $tendered = $paymentData['tendered'] ?? null;

        $payment = Payment::create([
            'sale_id' => $sale->id,
            'payment_method_id' => $paymentMethodId,
            'amount' => $amount,
            'tendered' => $tendered,
            'change' => $tendered ? max(0, $tendered - $amount) : null,
            'reference' => $paymentData['reference'] ?? null,
            'status' => Payment::STATUS_COMPLETED,
        ]);

        return $payment;
    }

    /**
     * Get sale by ID with relationships.
     */
    public function getSale(int $saleId): Sale
    {
        return Sale::with([
            'items.product',
            'payments.paymentMethod',
            'user',
            'warehouse',
        ])->findOrFail($saleId);
    }

    /**
     * Process a full refund.
     */
    public function processFullRefund(Sale $sale, ?string $reason = null): Sale
    {
        if (!$sale->can_refund) {
            throw new RuntimeException('This sale cannot be refunded');
        }

        return DB::transaction(function () use ($sale, $reason) {
            $warehouse = $sale->warehouse;

            // Restore stock for each item
            foreach ($sale->items as $item) {
                $product = $item->product;
                
                if ($product && $product->stock_tracked) {
                    $this->stockService->recordReturn(
                        product: $product,
                        warehouse: $warehouse,
                        quantity: $item->quantity,
                        referenceType: 'sale',
                        referenceId: $sale->id,
                        user: Auth::user()
                    );
                }
            }

            // Mark payments as refunded
            foreach ($sale->payments as $payment) {
                $payment->update(['status' => Payment::STATUS_REFUNDED]);
            }

            // Update sale status
            $sale->status = Sale::STATUS_REFUNDED;
            $sale->notes = $sale->notes 
                ? $sale->notes . "\n\nRefund reason: " . $reason 
                : "Refund reason: " . $reason;
            $sale->save();

            // Log audit
            $this->auditService->logUpdate($sale, ['status' => Sale::STATUS_COMPLETED]);

            return $sale->fresh(['items', 'payments.paymentMethod']);
        });
    }

    /**
     * Get paginated sales list.
     */
    public function getSales(array $filters = [], int $perPage = 15)
    {
        $query = Sale::with(['user', 'warehouse', 'payments.paymentMethod'])
            ->withCount('items');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->betweenDates($filters['start_date'], $filters['end_date']);
        }

        if (!empty($filters['has_conflicts'])) {
            $query->withConflicts();
        }

        if (!empty($filters['unsynced'])) {
            $query->unsynced();
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('invoice_number', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get receipt data for a sale.
     */
    public function getReceiptData(Sale $sale): array
    {
        $sale->load(['items.product', 'payments.paymentMethod', 'user', 'warehouse']);

        // Calculate totals
        $discountTotal = 0;
        if ($sale->discount_type === Sale::DISCOUNT_TYPE_PERCENTAGE && $sale->discount_amount > 0) {
            $discountTotal = ($sale->subtotal * $sale->discount_amount) / 100;
        } else {
            $discountTotal = $sale->discount_amount ?? 0;
        }

        return [
            'invoice_number' => $sale->invoice_number,
            'sale_date' => $sale->created_at->format('Y-m-d H:i:s'),
            'date' => $sale->created_at->format('Y-m-d H:i:s'),
            'cashier' => $sale->user->name,
            'warehouse' => $sale->warehouse->name,
            'store' => [
                'name' => config('app.name', 'POS System'),
                'address' => config('app.store_address', ''),
                'phone' => config('app.store_phone', ''),
                'email' => config('app.store_email', ''),
            ],
            'items' => $sale->items->map(function ($item) {
                return [
                    'name' => $item->product_name,
                    'sku' => $item->product_sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount_amount,
                    'discount_amount' => $item->discount_amount,
                    'line_total' => $item->line_total,
                ];
            })->toArray(),
            'subtotal' => $sale->subtotal,
            'discount_total' => $discountTotal,
            'discount' => [
                'type' => $sale->discount_type,
                'amount' => $sale->discount_amount,
            ],
            'total' => $sale->total,
            'grand_total' => $sale->total,
            'payments' => $sale->payments->map(function ($payment) {
                return [
                    'method' => $payment->paymentMethod->name,
                    'amount' => $payment->amount,
                    'tendered' => $payment->tendered,
                    'change' => $payment->change,
                ];
            })->toArray(),
            'amount_tendered' => $sale->payments->sum('tendered'),
            'change_due' => $sale->payments->sum('change'),
            'currency' => 'LYD',
            'currency_decimals' => 3,
        ];
    }
}
