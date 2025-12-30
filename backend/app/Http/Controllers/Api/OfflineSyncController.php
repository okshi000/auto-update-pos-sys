<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfflineSyncRequest;
use App\Services\OfflineSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfflineSyncController extends Controller
{
    public function __construct(
        protected OfflineSyncService $syncService
    ) {}

    /**
     * Process batch sync from offline client.
     * 
     * POST /api/local/sync
     */
    public function sync(OfflineSyncRequest $request): JsonResponse
    {
        $results = $this->syncService->processBatchSync(
            $request->input('sales'),
            $request->input('client_uuid')
        );

        $message = sprintf(
            'Sync completed: %d synced, %d duplicates, %d failed',
            count($results['synced']),
            count($results['duplicates']),
            count($results['failed'])
        );

        return $this->success([
            'synced' => $results['synced'],
            'duplicates' => $results['duplicates'],
            'failed' => $results['failed'],
            'conflicts' => $results['conflicts'],
            'summary' => [
                'total_processed' => count($results['synced']) + count($results['duplicates']) + count($results['failed']),
                'synced_count' => count($results['synced']),
                'duplicate_count' => count($results['duplicates']),
                'failed_count' => count($results['failed']),
                'conflict_count' => count($results['conflicts']),
            ],
        ], $message);
    }

    /**
     * Get sync status for a client.
     * 
     * GET /api/local/sync/status
     */
    public function status(Request $request): JsonResponse
    {
        $request->validate([
            'client_uuid' => 'required|string',
        ]);

        $status = $this->syncService->getClientSyncStatus(
            $request->input('client_uuid')
        );

        return $this->success($status);
    }

    /**
     * Get data for offline caching.
     * 
     * GET /api/local/cache-data
     */
    public function getCacheData(): JsonResponse
    {
        return $this->success([
            'products' => $this->syncService->getOfflineProductsData(),
            'categories' => $this->syncService->getOfflineCategoriesData(),
            'settings' => $this->syncService->getOfflineSettings(),
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get products data for offline use.
     * 
     * GET /api/local/products
     */
    public function getProducts(): JsonResponse
    {
        return $this->success([
            'products' => $this->syncService->getOfflineProductsData(),
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get categories data for offline use.
     * 
     * GET /api/local/categories
     */
    public function getCategories(): JsonResponse
    {
        return $this->success([
            'categories' => $this->syncService->getOfflineCategoriesData(),
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get settings for offline use.
     * 
     * GET /api/local/settings
     */
    public function getSettings(): JsonResponse
    {
        return $this->success([
            'settings' => $this->syncService->getOfflineSettings(),
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get unresolved conflicts.
     * 
     * GET /api/local/conflicts
     */
    public function getConflicts(): JsonResponse
    {
        $conflicts = $this->syncService->getUnresolvedConflicts();

        return $this->success([
            'conflicts' => $conflicts,
            'total' => $conflicts->count(),
        ]);
    }

    /**
     * Resolve a conflict.
     * 
     * POST /api/local/conflicts/{id}/resolve
     */
    public function resolveConflict(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'resolution' => 'required|string|max:500',
        ]);

        $this->syncService->resolveConflict($id, $request->input('resolution'));

        return $this->success(null, 'Conflict resolved');
    }
}
