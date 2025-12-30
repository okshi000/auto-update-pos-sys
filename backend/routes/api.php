<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LocaleController;
use App\Http\Controllers\Api\OfflineSyncController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\PrintBarcodeController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseInvoiceController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\ReconciliationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierReturnController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\TaxClassController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => __('messages.api_running'),
        'timestamp' => now()->toISOString(),
    ]);
});

// Locale endpoints (public)
Route::get('/locales', [LocaleController::class, 'index']);
Route::get('/translations', [LocaleController::class, 'translations']);

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    // Public routes with rate limiting
    Route::middleware('throttle:login')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::middleware('throttle:auth')->group(function () {
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Require Authentication)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'metrics']);

    /*
    |--------------------------------------------------------------------------
    | User Management Routes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('users', UserController::class);
    Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive']);
    Route::post('/users/{user}/roles', [UserController::class, 'syncRoles']);

    /*
    |--------------------------------------------------------------------------
    | Role & Permission Routes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('roles', RoleController::class);
    Route::get('/permissions', [RoleController::class, 'permissions']);

    /*
    |--------------------------------------------------------------------------
    | Category Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/categories/tree', [CategoryController::class, 'tree']);
    Route::post('/categories/reorder', [CategoryController::class, 'reorder'])
        ->middleware('permission:products.edit');
    Route::apiResource('categories', CategoryController::class);

    /*
    |--------------------------------------------------------------------------
    | Tax Class Routes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('tax-classes', TaxClassController::class);

    /*
    |--------------------------------------------------------------------------
    | Product Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::post('/products/barcode', [ProductController::class, 'findByBarcode']);
    Route::patch('/products/{product}/toggle-active', [ProductController::class, 'toggleActive'])
        ->middleware('permission:products.edit');
    Route::post('/products/{product}/duplicate', [ProductController::class, 'duplicate'])
        ->middleware('permission:products.create');
    Route::post('/products/{product}/images', [ProductController::class, 'uploadImage'])
        ->middleware('permission:products.edit');
    Route::delete('/products/{product}/images/{imageId}', [ProductController::class, 'deleteImage'])
        ->middleware('permission:products.edit');
    Route::patch('/products/{product}/images/{imageId}/primary', [ProductController::class, 'setPrimaryImage'])
        ->middleware('permission:products.edit');
    
    // Barcode printing routes
    Route::post('/products/{product}/print-barcode', [PrintBarcodeController::class, 'print'])
        ->middleware('permission:products.print');
    Route::get('/printer/test', [PrintBarcodeController::class, 'testConnection'])
        ->middleware('permission:products.print');
    Route::get('/printer/configuration', [PrintBarcodeController::class, 'configuration'])
        ->middleware('permission:products.print');
    
    Route::apiResource('products', ProductController::class);

    /*
    |--------------------------------------------------------------------------
    | POS Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/pos/products', [ProductController::class, 'posProducts']);

    /*
    |--------------------------------------------------------------------------
    | Warehouse Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/warehouses/{warehouse}/stock', [WarehouseController::class, 'stock']);
    Route::get('/warehouses/{warehouse}/low-stock', [WarehouseController::class, 'lowStock']);
    Route::get('/warehouses/{warehouse}/movements', [WarehouseController::class, 'movements']);
    Route::apiResource('warehouses', WarehouseController::class);

    /*
    |--------------------------------------------------------------------------
    | Inventory Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/inventory', [StockController::class, 'index'])
        ->middleware('permission:inventory.view');
    Route::get('/inventory/adjustments', [StockController::class, 'movements'])
        ->middleware('permission:inventory.view');
    Route::get('/inventory/transfers', [StockController::class, 'movements'])
        ->middleware('permission:inventory.view');

    /*
    |--------------------------------------------------------------------------
    | Stock Management Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('stock')->group(function () {
        Route::post('/adjust', [StockController::class, 'adjust'])
            ->middleware('permission:inventory.adjust');
        Route::post('/set', [StockController::class, 'setStock'])
            ->middleware('permission:inventory.adjust');
        Route::post('/transfer', [StockController::class, 'transfer'])
            ->middleware('permission:inventory.transfer');
        Route::get('/product/{productId}', [StockController::class, 'productStock']);
        Route::get('/low-stock', [StockController::class, 'lowStock']);
        Route::get('/out-of-stock', [StockController::class, 'outOfStock']);
        Route::get('/movements', [StockController::class, 'movements']);
        Route::get('/movement-types', [StockController::class, 'movementTypes']);
    });

    /*
    |--------------------------------------------------------------------------
    | Sales & POS Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('sales')->group(function () {
        Route::get('/', [SaleController::class, 'index'])
            ->middleware('permission:sales.view');
        Route::post('/pos', [SaleController::class, 'createPosSale'])
            ->middleware('permission:sales.create');
        Route::get('/daily-summary', [SaleController::class, 'dailySummary'])
            ->middleware('permission:sales.view');
        Route::post('/find-by-invoice', [SaleController::class, 'findByInvoice'])
            ->middleware('permission:sales.view');
        Route::get('/{id}', [SaleController::class, 'show'])
            ->middleware('permission:sales.view');
        Route::post('/{sale}/refund', [SaleController::class, 'refund'])
            ->middleware('permission:sales.refund');
        Route::get('/{sale}/receipt', [SaleController::class, 'receipt'])
            ->middleware('permission:sales.view');
        Route::get('/{sale}/receipt/pdf', [SaleController::class, 'receiptPdf'])
            ->middleware('permission:sales.view');
    });

    /*
    |--------------------------------------------------------------------------
    | Payment Method Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/payment-methods/types', [PaymentMethodController::class, 'types']);
    Route::apiResource('payment-methods', PaymentMethodController::class);

    /*
    |--------------------------------------------------------------------------
    | Offline Sync & Local Data Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('local')->group(function () {
        Route::post('/sync', [OfflineSyncController::class, 'sync'])
            ->middleware('permission:sales.create');
        Route::get('/sync/status', [OfflineSyncController::class, 'status']);
        Route::get('/cache-data', [OfflineSyncController::class, 'getCacheData']);
        Route::get('/products', [OfflineSyncController::class, 'getProducts']);
        Route::get('/categories', [OfflineSyncController::class, 'getCategories']);
        Route::get('/settings', [OfflineSyncController::class, 'getSettings']);
        Route::get('/conflicts', [OfflineSyncController::class, 'getConflicts'])
            ->middleware('permission:inventory.view');
        Route::post('/conflicts/{id}/resolve', [OfflineSyncController::class, 'resolveConflict'])
            ->middleware('permission:inventory.adjust');
    });

    /*
    |--------------------------------------------------------------------------
    | Supplier Routes (Phase 4)
    |--------------------------------------------------------------------------
    */
    Route::prefix('suppliers')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])
            ->middleware('permission:suppliers.view');
        Route::post('/', [SupplierController::class, 'store'])
            ->middleware('permission:suppliers.manage');
        Route::get('/{supplier}', [SupplierController::class, 'show'])
            ->middleware('permission:suppliers.view');
        Route::put('/{supplier}', [SupplierController::class, 'update'])
            ->middleware('permission:suppliers.manage');
        Route::delete('/{supplier}', [SupplierController::class, 'destroy'])
            ->middleware('permission:suppliers.manage');
        Route::post('/{id}/restore', [SupplierController::class, 'restore'])
            ->middleware('permission:suppliers.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | DEPRECATED: Purchase Order Routes
    |--------------------------------------------------------------------------
    | These routes are DEPRECATED and should NOT be used.
    | The system now uses Purchase Invoices which immediately update stock.
    | Purchase Orders were a procurement workflow that has been replaced.
    |
    | Kept for backward compatibility - will be removed in future versions.
    |--------------------------------------------------------------------------
    */
    // Route::prefix('purchase-orders')->group(function () {
    //     Route::get('/', [PurchaseOrderController::class, 'index'])
    //         ->middleware('permission:purchases.view');
    //     Route::post('/', [PurchaseOrderController::class, 'store'])
    //         ->middleware('permission:purchases.manage');
    //     Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
    //         ->middleware('permission:purchases.view');
    //     Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
    //         ->middleware('permission:purchases.manage');
    //     Route::delete('/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])
    //         ->middleware('permission:purchases.manage');
    //     Route::post('/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])
    //         ->middleware('permission:purchases.manage');
    //     Route::post('/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])
    //         ->middleware('permission:purchases.receive');
    //     Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
    //         ->middleware('permission:purchases.manage');
    // });

    /*
    |--------------------------------------------------------------------------
    | Purchase Invoice Routes - PRIMARY PURCHASE WORKFLOW
    |--------------------------------------------------------------------------
    | Purchase Invoices represent actual supplier invoices that:
    | - Immediately increase inventory stock on creation
    | - Track payment status (unpaid, partial, paid)
    | - Record costs for financial reporting
    |
    | There is NO purchase order workflow (draft → send → receive).
    | Stock updates happen atomically within the create transaction.
    |--------------------------------------------------------------------------
    */
    Route::prefix('purchase-invoices')->group(function () {
        Route::get('/', [PurchaseInvoiceController::class, 'index'])
            ->middleware('permission:purchases.view');
        Route::post('/', [PurchaseInvoiceController::class, 'store'])
            ->middleware('permission:purchases.manage');
        Route::get('/{id}', [PurchaseInvoiceController::class, 'show'])
            ->middleware('permission:purchases.view');
        Route::post('/{id}/payment', [PurchaseInvoiceController::class, 'recordPayment'])
            ->middleware('permission:purchases.manage');
        Route::delete('/{id}', [PurchaseInvoiceController::class, 'destroy'])
            ->middleware('permission:purchases.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | Supplier Return Routes (Phase 4)
    |--------------------------------------------------------------------------
    */
    Route::prefix('supplier-returns')->group(function () {
        Route::get('/', [SupplierReturnController::class, 'index'])
            ->middleware('permission:purchases.view');
        Route::post('/', [SupplierReturnController::class, 'store'])
            ->middleware('permission:purchases.return');
        Route::get('/{supplierReturn}', [SupplierReturnController::class, 'show'])
            ->middleware('permission:purchases.view');
        Route::post('/{supplierReturn}/approve', [SupplierReturnController::class, 'approve'])
            ->middleware('permission:purchases.return');
        Route::post('/{supplierReturn}/ship', [SupplierReturnController::class, 'ship'])
            ->middleware('permission:purchases.return');
        Route::post('/{supplierReturn}/complete', [SupplierReturnController::class, 'complete'])
            ->middleware('permission:purchases.return');
        Route::post('/{supplierReturn}/cancel', [SupplierReturnController::class, 'cancel'])
            ->middleware('permission:purchases.return');
    });

    /*
    |--------------------------------------------------------------------------
    | Reports Routes (Phase 5)
    |--------------------------------------------------------------------------
    */
    Route::prefix('reports')->group(function () {
        Route::get('/sales', [ReportController::class, 'sales'])
            ->middleware('permission:reports.view');
        Route::get('/inventory', [ReportController::class, 'stockLevels'])
            ->middleware('permission:reports.view');
        Route::get('/cash-register', [ReportController::class, 'cashReconciliation'])
            ->middleware('permission:reports.view');
        Route::get('/daily-sales', [ReportController::class, 'dailySales'])
            ->middleware('permission:reports.view');
        Route::get('/sales-by-product', [ReportController::class, 'salesByProduct'])
            ->middleware('permission:reports.view');
        Route::get('/sales-by-category', [ReportController::class, 'salesByCategory'])
            ->middleware('permission:reports.view');
        Route::get('/cash-reconciliation', [ReportController::class, 'cashReconciliation'])
            ->middleware('permission:reports.view');
        Route::get('/stock-levels', [ReportController::class, 'stockLevels'])
            ->middleware('permission:reports.view');
        Route::get('/stock-valuation', [ReportController::class, 'stockValuation'])
            ->middleware('permission:reports.view');

        // CSV Export routes
        Route::get('/export/daily-sales', [ReportController::class, 'exportDailySales'])
            ->middleware('permission:reports.view');
        Route::get('/export/sales-by-product', [ReportController::class, 'exportSalesByProduct'])
            ->middleware('permission:reports.view');
        Route::get('/export/stock-levels', [ReportController::class, 'exportStockLevels'])
            ->middleware('permission:reports.view');
        Route::get('/export/stock-valuation', [ReportController::class, 'exportStockValuation'])
            ->middleware('permission:reports.view');
    });

    /*
    |--------------------------------------------------------------------------
    | Reconciliation Routes (Phase 5)
    |--------------------------------------------------------------------------
    */
    Route::prefix('reconciliation')->group(function () {
        Route::get('/conflicts', [ReconciliationController::class, 'index'])
            ->middleware('permission:reconciliation.manage');
        Route::get('/{id}', [ReconciliationController::class, 'show'])
            ->middleware('permission:reconciliation.manage');
        Route::post('/{id}/accept', [ReconciliationController::class, 'accept'])
            ->middleware('permission:reconciliation.manage');
        Route::post('/{id}/adjust', [ReconciliationController::class, 'adjust'])
            ->middleware('permission:reconciliation.manage');
        Route::post('/{id}/void', [ReconciliationController::class, 'void'])
            ->middleware('permission:reconciliation.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | Audit Log Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])
            ->middleware('permission:audit.view');
        Route::get('/entity-types', [AuditLogController::class, 'entityTypes'])
            ->middleware('permission:audit.view');
        Route::get('/actions', [AuditLogController::class, 'actions'])
            ->middleware('permission:audit.view');
        Route::get('/{id}', [AuditLogController::class, 'show'])
            ->middleware('permission:audit.view');
    });

    /*
    |--------------------------------------------------------------------------
    | System Management Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('system')->group(function () {
        // Public system info
        Route::get('/info', [SystemController::class, 'info']);

        // Protected system management routes
        Route::middleware('permission:system.manage')->group(function () {
            // Version & Updates
            Route::get('/check-updates', [SystemController::class, 'checkForUpdates']);
            Route::get('/update-progress', [SystemController::class, 'getUpdateProgress']);
            Route::post('/update', [SystemController::class, 'update']);
            Route::post('/download-update', [SystemController::class, 'downloadUpdate']);
            Route::post('/rollback', [SystemController::class, 'rollback']);
            Route::get('/update-log', [SystemController::class, 'updateLog']);

            // Database & Backups
            Route::post('/backup', [SystemController::class, 'backup']);
            Route::get('/backups', [SystemController::class, 'listBackups']);
            Route::post('/restore', [SystemController::class, 'restore']);
            Route::post('/migrate', [SystemController::class, 'migrate']);

            // Maintenance
            Route::post('/clear-cache', [SystemController::class, 'clearCache']);
            Route::get('/check-requirements', [SystemController::class, 'checkRequirements']);
        });
    });
});
