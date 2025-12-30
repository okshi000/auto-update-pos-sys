<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
// use App\Models\Customer;
use App\Models\User;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function metrics(): JsonResponse
    {
        $today = Carbon::today();
        
        // Today's metrics
        $todaySales = Sale::whereDate('created_at', $today)->count();
        $todayRevenue = Sale::whereDate('created_at', $today)->sum('total');
        // $newCustomers = Customer::whereDate('created_at', $today)->count();
        $newCustomers = 0;
        
        // Low stock
        $lowStockCount = Product::where('stock_tracked', true)
            ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM stock_levels WHERE stock_levels.product_id = products.id) <= min_stock_level')
            ->count();
        
        $activeUsers = User::count(); 

        // Pending orders
        $pendingOrders = PurchaseOrder::where('status', 'pending')->count();

        // Trends (Last 7 days)
        $salesTrend = [];
        $revenueTrend = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $daySales = Sale::whereDate('created_at', $date);
            
            $salesTrend[] = $daySales->count();
            $revenueTrend[] = (float) $daySales->sum('total');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'today' => [
                    'sales_count' => $todaySales,
                    'revenue' => (float) $todayRevenue,
                    'new_customers' => $newCustomers,
                ],
                'today_sales' => (float) $todayRevenue,
                'today_orders' => $todaySales,
                'low_stock_count' => $lowStockCount,
                'active_users' => $activeUsers,
                'trends' => [
                    'sales_7_days' => $salesTrend,
                    'revenue_7_days' => $revenueTrend,
                ],
                'low_stock_alert' => $lowStockCount,
                'pending_orders' => $pendingOrders,
            ]
        ]);
    }
}
