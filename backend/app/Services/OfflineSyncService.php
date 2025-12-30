<?php

namespace App\Services;

use App\Models\OfflineSyncLog;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OfflineSyncService
{
    public function __construct(
        protected SaleService $saleService
    ) {}

    /**
     * Process batch sync from offline client.
     * 
     * @param array $batch Array of offline sales to sync
     * @param string $clientUuid Client identifier
     * @return array Sync results
     */
    public function processBatchSync(array $batch, string $clientUuid): array
    {
        $results = [
            'synced' => [],
            'duplicates' => [],
            'failed' => [],
            'conflicts' => [],
        ];

        foreach ($batch as $saleData) {
            try {
                $result = $this->syncSingleSale($saleData, $clientUuid);
                
                if ($result['status'] === 'synced') {
                    $results['synced'][] = $result;
                    
                    if (!empty($result['conflicts'])) {
                        $results['conflicts'][] = $result;
                    }
                } elseif ($result['status'] === 'duplicate') {
                    $results['duplicates'][] = $result;
                }
            } catch (\Exception $e) {
                Log::error('Offline sync failed', [
                    'client_uuid' => $clientUuid,
                    'idempotency_key' => $saleData['idempotency_key'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);

                $results['failed'][] = [
                    'idempotency_key' => $saleData['idempotency_key'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];

                // Log the failure
                $this->logSyncAttempt($saleData, $clientUuid, 'failed', null, $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Sync a single offline sale.
     */
    protected function syncSingleSale(array $saleData, string $clientUuid): array
    {
        $idempotencyKey = $saleData['idempotency_key'];

        // Check if already synced
        $existingLog = OfflineSyncLog::findByIdempotencyKey($idempotencyKey);
        if ($existingLog && $existingLog->status === OfflineSyncLog::STATUS_SYNCED) {
            return [
                'status' => 'duplicate',
                'idempotency_key' => $idempotencyKey,
                'sale_id' => $existingLog->entity_id,
                'invoice_number' => Sale::find($existingLog->entity_id)?->invoice_number,
                'message' => 'Sale already synced',
            ];
        }

        // Check if sale already exists
        $existingSale = Sale::findByIdempotencyKey($idempotencyKey);
        if ($existingSale) {
            // Log as duplicate
            $this->logSyncAttempt($saleData, $clientUuid, 'duplicate', $existingSale->id);
            
            return [
                'status' => 'duplicate',
                'idempotency_key' => $idempotencyKey,
                'sale_id' => $existingSale->id,
                'invoice_number' => $existingSale->invoice_number,
                'message' => 'Sale already exists',
            ];
        }

        // Create sync log entry
        $syncLog = OfflineSyncLog::create([
            'client_uuid' => $clientUuid,
            'idempotency_key' => $idempotencyKey,
            'entity_type' => OfflineSyncLog::ENTITY_SALE,
            'status' => OfflineSyncLog::STATUS_PENDING,
            'request_payload' => $saleData,
        ]);

        // Process the sale with allowNegativeStock = true for offline sync
        $saleData['client_uuid'] = $clientUuid;
        $saleData['is_synced'] = true; // Marking as synced now
        
        $sale = $this->saleService->createPosSale($saleData, allowNegativeStock: true);

        $conflicts = [];
        if ($sale->has_stock_conflict) {
            $conflicts = $this->extractStockConflicts($sale);
            $syncLog->recordConflicts($conflicts);
        }

        // Mark sync as complete
        $syncLog->markAsSynced($sale->id, [
            'invoice_number' => $sale->invoice_number,
            'total' => $sale->total,
        ]);

        return [
            'status' => 'synced',
            'idempotency_key' => $idempotencyKey,
            'sale_id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'total' => $sale->total,
            'has_conflicts' => $sale->has_stock_conflict,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Log sync attempt.
     */
    protected function logSyncAttempt(
        array $saleData,
        string $clientUuid,
        string $status,
        ?int $entityId = null,
        ?string $errorMessage = null
    ): OfflineSyncLog {
        return OfflineSyncLog::updateOrCreate(
            ['idempotency_key' => $saleData['idempotency_key']],
            [
                'client_uuid' => $clientUuid,
                'entity_type' => OfflineSyncLog::ENTITY_SALE,
                'entity_id' => $entityId,
                'status' => $status,
                'request_payload' => $saleData,
                'error_message' => $errorMessage,
                'synced_at' => $status === OfflineSyncLog::STATUS_SYNCED || $status === OfflineSyncLog::STATUS_DUPLICATE 
                    ? now() 
                    : null,
            ]
        );
    }

    /**
     * Extract stock conflicts from a sale.
     */
    protected function extractStockConflicts(Sale $sale): array
    {
        // This would be populated during sale creation
        // For now, we return conflicts based on negative stock levels
        return [];
    }

    /**
     * Get sync status for a client.
     */
    public function getClientSyncStatus(string $clientUuid): array
    {
        $logs = OfflineSyncLog::forClient($clientUuid)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return [
            'total' => $logs->count(),
            'synced' => $logs->where('status', OfflineSyncLog::STATUS_SYNCED)->count(),
            'pending' => $logs->where('status', OfflineSyncLog::STATUS_PENDING)->count(),
            'failed' => $logs->where('status', OfflineSyncLog::STATUS_FAILED)->count(),
            'duplicates' => $logs->where('status', OfflineSyncLog::STATUS_DUPLICATE)->count(),
            'with_conflicts' => $logs->where('has_conflicts', true)->count(),
            'recent' => $logs->take(10)->map(function ($log) {
                return [
                    'idempotency_key' => $log->idempotency_key,
                    'status' => $log->status,
                    'entity_id' => $log->entity_id,
                    'has_conflicts' => $log->has_conflicts,
                    'synced_at' => $log->synced_at?->toISOString(),
                    'created_at' => $log->created_at->toISOString(),
                ];
            }),
        ];
    }

    /**
     * Get all unresolved conflicts.
     */
    public function getUnresolvedConflicts(): Collection
    {
        return OfflineSyncLog::withConflicts()
            ->with('sale')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Resolve a conflict (mark as handled).
     */
    public function resolveConflict(int $syncLogId, string $resolution): bool
    {
        $log = OfflineSyncLog::findOrFail($syncLogId);
        
        $conflicts = $log->conflicts ?? [];
        $conflicts['resolution'] = $resolution;
        $conflicts['resolved_at'] = now()->toISOString();
        $conflicts['resolved_by'] = auth()->id();

        $log->update([
            'conflicts' => $conflicts,
        ]);

        return true;
    }

    /**
     * Get products data for offline caching.
     */
    public function getOfflineProductsData(): array
    {
        return \App\Models\Product::with(['category', 'taxClass', 'stockLevels'])
            ->active()
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'price' => $product->price,
                    'cost_price' => $product->cost_price,
                    'category_id' => $product->category_id,
                    'category_name' => $product->category?->name,
                    'tax_rate' => $product->taxClass?->rate ?? 0,
                    'stock_tracked' => $product->stock_tracked,
                    'total_stock' => $product->total_stock,
                ];
            })
            ->toArray();
    }

    /**
     * Get categories data for offline caching.
     */
    public function getOfflineCategoriesData(): array
    {
        return \App\Models\Category::active()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'parent_id'])
            ->toArray();
    }

    /**
     * Get settings for offline caching.
     */
    public function getOfflineSettings(): array
    {
        return [
            'currency' => 'LYD',
            'currency_decimals' => 3,
            'tax_inclusive' => false,
            'default_warehouse_id' => \App\Models\Warehouse::getDefault()?->id,
            'company_name' => \App\Models\Setting::get('company_name', 'POS System'),
            'receipt_footer' => \App\Models\Setting::get('receipt_footer', 'Thank you for your purchase!'),
        ];
    }
}
