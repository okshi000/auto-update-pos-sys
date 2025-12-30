<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * GET /api/reports/sales
     * Comprehensive sales report
     */
    public function sales(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        // Default to last 30 days if not provided
        $startDate = $request->has('date_from') 
            ? Carbon::parse($request->date_from)->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();
            
        $endDate = $request->has('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : Carbon::now()->endOfDay();

        $report = $this->reportService->getComprehensiveSalesReport($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * GET /api/reports/daily-sales
     */
    public function dailySales(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'sometimes|date',
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
        ]);

        $date = $request->has('date') 
            ? Carbon::parse($request->date) 
            : Carbon::today();

        $report = $this->reportService->getDailySalesSummary(
            date: $date,
            warehouseId: $request->warehouse_id
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * GET /api/reports/sales-by-product
     */
    public function salesByProduct(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
            'category_id' => 'sometimes|integer|exists:categories,id',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        // Limit date range to prevent huge queries
        if ($startDate->diffInDays($endDate) > 365) {
            return response()->json([
                'success' => false,
                'message' => 'Date range cannot exceed 365 days',
            ], 422);
        }

        $report = $this->reportService->getSalesByProduct(
            startDate: $startDate,
            endDate: $endDate,
            warehouseId: $request->warehouse_id,
            categoryId: $request->category_id
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * GET /api/reports/sales-by-category
     */
    public function salesByCategory(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        if ($startDate->diffInDays($endDate) > 365) {
            return response()->json([
                'success' => false,
                'message' => 'Date range cannot exceed 365 days',
            ], 422);
        }

        $report = $this->reportService->getSalesByCategory(
            startDate: $startDate,
            endDate: $endDate,
            warehouseId: $request->warehouse_id
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * GET /api/reports/cash-reconciliation
     */
    public function cashReconciliation(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'sometimes|date',
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
        ]);

        $date = $request->has('date') 
            ? Carbon::parse($request->date) 
            : Carbon::today();

        $report = $this->reportService->getCashReconciliation(
            date: $date,
            warehouseId: $request->warehouse_id
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * GET /api/reports/stock-levels
     */
    public function stockLevels(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'low_stock' => 'sometimes|boolean',
            'out_of_stock' => 'sometimes|boolean',
        ]);

        $report = $this->reportService->getStockLevels(
            warehouseId: $request->warehouse_id,
            categoryId: $request->category_id,
            lowStockOnly: (bool) $request->low_stock,
            outOfStockOnly: (bool) $request->out_of_stock
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * GET /api/reports/stock-valuation
     */
    public function stockValuation(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
            'category_id' => 'sometimes|integer|exists:categories,id',
        ]);

        $report = $this->reportService->getStockValuation(
            warehouseId: $request->warehouse_id,
            categoryId: $request->category_id
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * GET /api/reports/export/daily-sales
     */
    public function exportDailySales(Request $request): Response
    {
        $request->validate([
            'date' => 'sometimes|date',
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
        ]);

        $date = $request->has('date') 
            ? Carbon::parse($request->date) 
            : Carbon::today();

        $report = $this->reportService->getDailySalesSummary(
            date: $date,
            warehouseId: $request->warehouse_id
        );

        // Flatten report for CSV
        $data = [
            [
                'Date' => $report['date'],
                'Total Transactions' => $report['summary']['total_transactions'],
                'Total Revenue' => $report['summary']['total_revenue'],
                'Total Tax' => $report['summary']['total_tax'],
                'Total Discount' => $report['summary']['total_discount'],
                'Net Sales' => $report['summary']['net_sales'],
                'Average Transaction' => $report['summary']['average_transaction'],
                'Refund Count' => $report['refunds']['count'],
                'Refund Total' => $report['refunds']['total'],
            ],
        ];

        $headers = array_keys($data[0]);
        $csv = $this->reportService->exportToCsv($headers, $data, "daily-sales-{$date->format('Y-m-d')}.csv");

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=daily-sales-{$date->format('Y-m-d')}.csv");
    }

    /**
     * GET /api/reports/export/sales-by-product
     */
    public function exportSalesByProduct(Request $request): Response
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
            'category_id' => 'sometimes|integer|exists:categories,id',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $report = $this->reportService->getSalesByProduct(
            startDate: $startDate,
            endDate: $endDate,
            warehouseId: $request->warehouse_id,
            categoryId: $request->category_id
        );

        $headers = ['Product ID', 'Product Name', 'SKU', 'Quantity Sold', 'Total Revenue', 'Transaction Count'];
        $data = $report->map(fn($item) => [
            $item->id,
            $item->name,
            $item->sku,
            $item->quantity_sold,
            $item->total_revenue,
            $item->transaction_count,
        ]);

        $csv = $this->reportService->exportToCsv($headers, $data->toArray(), 'sales-by-product.csv');

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename=sales-by-product.csv');
    }

    /**
     * GET /api/reports/export/stock-levels
     */
    public function exportStockLevels(Request $request): Response
    {
        $request->validate([
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'low_stock' => 'sometimes|boolean',
            'out_of_stock' => 'sometimes|boolean',
        ]);

        $report = $this->reportService->getStockLevels(
            warehouseId: $request->warehouse_id,
            categoryId: $request->category_id,
            lowStockOnly: (bool) $request->low_stock,
            outOfStockOnly: (bool) $request->out_of_stock
        );

        $headers = ['SKU', 'Product Name', 'Warehouse', 'Quantity', 'Min Stock Level', 'Status'];
        $data = $report->map(fn($item) => [
            $item->sku,
            $item->product_name,
            $item->warehouse->name ?? '',
            $item->quantity,
            $item->min_stock_level ?? 0,
            $item->quantity <= 0 ? 'Out of Stock' : ($item->quantity <= ($item->min_stock_level ?? 0) ? 'Low Stock' : 'In Stock'),
        ]);

        $csv = $this->reportService->exportToCsv($headers, $data->toArray(), 'stock-levels.csv');

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename=stock-levels.csv');
    }

    /**
     * GET /api/reports/export/stock-valuation
     */
    public function exportStockValuation(Request $request): Response
    {
        $request->validate([
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
            'category_id' => 'sometimes|integer|exists:categories,id',
        ]);

        $report = $this->reportService->getStockValuation(
            warehouseId: $request->warehouse_id,
            categoryId: $request->category_id
        );

        $headers = ['Product ID', 'Product Name', 'SKU', 'Category', 'Quantity', 'Unit Cost', 'Line Value'];
        $data = collect($report['items'])->map(fn($item) => [
            $item->id,
            $item->name,
            $item->sku,
            $item->category_name ?? 'Uncategorized',
            $item->quantity,
            $item->cost_price,
            $item->line_value,
        ]);

        $csv = $this->reportService->exportToCsv($headers, $data->toArray(), 'stock-valuation.csv');

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename=stock-valuation.csv');
    }
}
