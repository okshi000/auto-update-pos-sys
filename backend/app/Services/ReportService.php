<?php

namespace App\Services;

use App\Models\Category;
use App\Models\OfflineSyncLog;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Get daily sales summary.
     */
    public function getDailySalesSummary(
        Carbon $date,
        ?int $warehouseId = null
    ): array {
        $query = Sale::whereDate('completed_at', $date)
            ->where('status', '!=', Sale::STATUS_REFUNDED);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $sales = $query->get();

        // Calculate totals
        $totalSales = $sales->count();
        $totalRevenue = $sales->sum('total');
        $totalDiscount = $sales->sum('discount_amount');
        $netSales = $totalRevenue;

        // Sales by hour
        $salesByHour = Sale::whereDate('completed_at', $date)
            ->where('status', '!=', Sale::STATUS_REFUNDED)
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->selectRaw("strftime('%H', completed_at) as hour, COUNT(*) as count, SUM(total) as total")
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour')
            ->toArray();

        // Sales by payment method
        $paymentMethodTotals = Payment::whereHas('sale', function ($q) use ($date, $warehouseId) {
            $q->whereDate('completed_at', $date)
                ->where('status', '!=', Sale::STATUS_REFUNDED);
            if ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            }
        })
        ->where('status', Payment::STATUS_COMPLETED)
        ->join('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
        ->selectRaw('payment_methods.name, payment_methods.type, SUM(payments.amount) as total')
        ->groupBy('payment_methods.id', 'payment_methods.name', 'payment_methods.type')
        ->get()
        ->toArray();

        // Refunds
        $refunds = Sale::whereDate('updated_at', $date)
            ->where('status', Sale::STATUS_REFUNDED)
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->selectRaw('COUNT(*) as count, SUM(total) as total')
            ->first();

        return [
            'date' => $date->toDateString(),
            'warehouse_id' => $warehouseId,
            'summary' => [
                'total_transactions' => $totalSales,
                'total_revenue' => round($totalRevenue, 3),
                'total_discount' => round($totalDiscount, 3),
                'net_sales' => round($netSales, 3),
                'average_transaction' => $totalSales > 0 ? round($totalRevenue / $totalSales, 3) : 0,
            ],
            'sales_by_hour' => $salesByHour,
            'payment_methods' => $paymentMethodTotals,
            'refunds' => [
                'count' => $refunds->count ?? 0,
                'total' => round($refunds->total ?? 0, 3),
            ],
        ];
    }

    /**
     * Get sales by product report.
     */
    public function getSalesByProduct(
        Carbon $startDate,
        Carbon $endDate,
        ?int $warehouseId = null,
        ?int $categoryId = null
    ): Collection {
        $query = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereBetween('sales.completed_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->where('sales.status', '!=', Sale::STATUS_REFUNDED);

        if ($warehouseId) {
            $query->where('sales.warehouse_id', $warehouseId);
        }

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        return $query->selectRaw('
                products.id,
                products.name,
                products.sku,
                SUM(sale_items.quantity) as quantity_sold,
                SUM(sale_items.line_total) as total_revenue,
                COUNT(DISTINCT sales.id) as transaction_count
            ')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_revenue')
            ->get();
    }

    /**
     * Get sales by category report.
     */
    public function getSalesByCategory(
        Carbon $startDate,
        Carbon $endDate,
        ?int $warehouseId = null
    ): Collection {
        return SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->whereBetween('sales.completed_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->where('sales.status', '!=', Sale::STATUS_REFUNDED)
            ->when($warehouseId, fn($q) => $q->where('sales.warehouse_id', $warehouseId))
            ->selectRaw('
                categories.id,
                COALESCE(categories.name, ?) as category_name,
                SUM(sale_items.quantity) as quantity_sold,
                SUM(sale_items.line_total) as total_revenue,
                COUNT(DISTINCT sales.id) as transaction_count
            ', ['Uncategorized'])
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_revenue')
            ->get();
    }

    /**
     * Get cash reconciliation report.
     */
    public function getCashReconciliation(
        Carbon $date,
        ?int $warehouseId = null
    ): array {
        // Get cash payment method
        $cashMethod = PaymentMethod::where('type', 'cash')->first();
        
        if (!$cashMethod) {
            return [
                'date' => $date->toDateString(),
                'error' => 'No cash payment method configured',
            ];
        }

        // Cash sales
        $cashSales = Payment::whereHas('sale', function ($q) use ($date, $warehouseId) {
            $q->whereDate('completed_at', $date)
                ->where('status', Sale::STATUS_COMPLETED);
            if ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            }
        })
        ->where('payment_method_id', $cashMethod->id)
        ->where('status', Payment::STATUS_COMPLETED)
        ->get();

        $totalCashReceived = $cashSales->sum('tendered');
        $totalCashSales = $cashSales->sum('amount');
        $totalChangeGiven = $cashSales->sum('change');

        // Refunded cash
        $refundedCash = Payment::whereHas('sale', function ($q) use ($date, $warehouseId) {
            $q->whereDate('updated_at', $date)
                ->where('status', Sale::STATUS_REFUNDED);
            if ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            }
        })
        ->where('payment_method_id', $cashMethod->id)
        ->sum('amount');

        $expectedCash = $totalCashSales - $refundedCash;

        return [
            'date' => $date->toDateString(),
            'warehouse_id' => $warehouseId,
            'cash_transactions' => $cashSales->count(),
            'total_cash_received' => round($totalCashReceived, 3),
            'total_cash_sales' => round($totalCashSales, 3),
            'total_change_given' => round($totalChangeGiven, 3),
            'refunds' => round($refundedCash, 3),
            'expected_cash_in_drawer' => round($expectedCash, 3),
            'breakdown' => [
                'sales' => round($totalCashSales, 3),
                'minus_refunds' => round($refundedCash, 3),
                'net' => round($expectedCash, 3),
            ],
        ];
    }

    /**
     * Get current stock levels report.
     */
    public function getStockLevels(
        ?int $warehouseId = null,
        ?int $categoryId = null,
        bool $lowStockOnly = false,
        bool $outOfStockOnly = false
    ): Collection {
        $query = StockLevel::with(['product', 'warehouse'])
            ->join('products', 'stock_levels.product_id', '=', 'products.id')
            ->where('products.stock_tracked', true)
            ->where('products.is_active', true);

        if ($warehouseId) {
            $query->where('stock_levels.warehouse_id', $warehouseId);
        }

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        if ($outOfStockOnly) {
            $query->where('stock_levels.quantity', '<=', 0);
        } elseif ($lowStockOnly) {
            $query->whereColumn('stock_levels.quantity', '<=', 'products.min_stock_level')
                ->where('stock_levels.quantity', '>', 0);
        }

        return $query->select([
            'stock_levels.*',
            'products.name as product_name',
            'products.sku',
            'products.category_id',
            'products.min_stock_level',
        ])
        ->orderBy('products.name')
        ->get();
    }

    /**
     * Get stock valuation report.
     * Uses quantity × last purchase cost (no FIFO/LIFO).
     */
    public function getStockValuation(
        ?int $warehouseId = null,
        ?int $categoryId = null
    ): array {
        $query = StockLevel::join('products', 'stock_levels.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('products.stock_tracked', true)
            ->where('products.is_active', true)
            ->where('stock_levels.quantity', '>', 0);

        if ($warehouseId) {
            $query->where('stock_levels.warehouse_id', $warehouseId);
        }

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        $items = $query->select([
            'products.id',
            'products.name',
            'products.sku',
            'products.cost_price',
            'categories.name as category_name',
            'stock_levels.warehouse_id',
            'stock_levels.quantity',
        ])
        ->get()
        ->map(function ($item) {
            $cost = (float) ($item->cost_price ?? 0);
            $quantity = (int) $item->quantity;
            $item->line_value = round($cost * $quantity, 3);
            return $item;
        });

        $totalValue = $items->sum('line_value');
        $totalUnits = $items->sum('quantity');

        // Group by category
        $byCategory = $items->groupBy('category_name')->map(function ($categoryItems) {
            return [
                'quantity' => $categoryItems->sum('quantity'),
                'value' => round($categoryItems->sum('line_value'), 3),
            ];
        });

        return [
            'warehouse_id' => $warehouseId,
            'generated_at' => now()->toISOString(),
            'summary' => [
                'total_units' => $totalUnits,
                'total_value' => round($totalValue, 3),
                'item_count' => $items->count(),
            ],
            'by_category' => $byCategory,
            'items' => $items,
        ];
    }

    /**
     * Get offline sync conflicts for reconciliation.
     */
    public function getConflicts(?int $warehouseId = null): Collection
    {
        $query = Sale::where('has_stock_conflict', true)
            ->with(['items.product', 'warehouse', 'user', 'payments.paymentMethod']);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Get conflict details.
     */
    public function getConflictDetails(int $saleId): ?Sale
    {
        return Sale::with([
            'items.product',
            'warehouse',
            'user',
            'payments.paymentMethod',
        ])->find($saleId);
    }

    /**
     * Accept a conflict (keep the sale as-is).
     */
    public function acceptConflict(Sale $sale, ?string $notes = null): Sale
    {
        $sale->has_stock_conflict = false;
        if ($notes) {
            $sale->notes = $sale->notes 
                ? $sale->notes . "\n\nConflict resolved (accepted): " . $notes 
                : "Conflict resolved (accepted): " . $notes;
        }
        $sale->save();

        return $sale;
    }

    /**
     * Void a conflicted sale (mark as refunded, restore stock).
     */
    public function voidConflictedSale(Sale $sale, StockService $stockService, ?string $reason = null): Sale
    {
        return DB::transaction(function () use ($sale, $stockService, $reason) {
            $warehouse = $sale->warehouse;

            // Restore stock for each item
            foreach ($sale->items as $item) {
                $product = $item->product;
                
                if ($product && $product->stock_tracked) {
                    $stockService->recordReturn(
                        product: $product,
                        warehouse: $warehouse,
                        quantity: $item->quantity,
                        referenceType: 'sale',
                        referenceId: $sale->id,
                        user: null
                    );
                }
            }

            // Mark payments as refunded
            foreach ($sale->payments as $payment) {
                $payment->update(['status' => Payment::STATUS_REFUNDED]);
            }

            // Update sale
            $sale->status = Sale::STATUS_REFUNDED;
            $sale->has_stock_conflict = false;
            $sale->notes = $sale->notes 
                ? $sale->notes . "\n\nVoided due to conflict: " . ($reason ?? 'No reason provided')
                : "Voided due to conflict: " . ($reason ?? 'No reason provided');
            $sale->save();

            return $sale;
        });
    }

    /**
     * Export data to CSV format.
     */
    public function exportToCsv(array $headers, Collection|array $data, string $filename): string
    {
        $output = fopen('php://temp', 'r+');
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write data rows
        foreach ($data as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            fputcsv($output, array_values($row));
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Get comprehensive sales report for dashboard.
     */
    public function getComprehensiveSalesReport(Carbon $startDate, Carbon $endDate): array
    {
        // Summary
        $sales = Sale::whereBetween('created_at', [$startDate, $endDate]);
        
        $summary = [
            'total_sales' => $sales->count(),
            'total_revenue' => (float) $sales->sum('total'),
            'total_discounts' => (float) $sales->sum('discount_amount'),
            'average_sale' => $sales->count() > 0 ? (float) $sales->avg('total') : 0,
            'total_items_sold' => (int) DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->whereBetween('sales.created_at', [$startDate, $endDate])
                ->sum('quantity'),
        ];

        // By Date
        $byDate = Sale::selectRaw('DATE(created_at) as date, COUNT(*) as sales_count, SUM(total) as revenue')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'sales_count' => $item->sales_count,
                    'revenue' => (float) $item->revenue,
                    'items_sold' => 0, 
                ];
            });

        // By Payment Method
        $byPaymentMethod = DB::table('payments')
            ->join('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
            ->whereBetween('payments.created_at', [$startDate, $endDate])
            ->selectRaw('payment_methods.name as method, COUNT(*) as count, SUM(payments.amount) as amount')
            ->groupBy('payment_methods.name')
            ->get();

        // By Category
        $byCategory = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->selectRaw('categories.id, categories.name, COUNT(DISTINCT sales.id) as sales_count, SUM(sale_items.line_total) as revenue, SUM(sale_items.quantity) as items_sold')
            ->groupBy('categories.id', 'categories.name')
            ->get();

        // Top Products
        $topProducts = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->selectRaw('products.id, products.name, products.sku, SUM(sale_items.quantity) as quantity, SUM(sale_items.line_total) as revenue')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('quantity')
            ->limit(10)
            ->get();

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'summary' => $summary,
            'by_date' => $byDate,
            'by_payment_method' => $byPaymentMethod,
            'by_category' => $byCategory,
            'sales_by_category' => $byCategory,
            'top_products' => $topProducts,
        ];
    }
}
