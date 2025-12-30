<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SystemUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SystemController extends Controller
{
    protected SystemUpdateService $updateService;

    public function __construct(SystemUpdateService $updateService)
    {
        $this->updateService = $updateService;
    }

    /**
     * Get system information
     */
    public function info(): JsonResponse
    {
        $versionInfo = $this->updateService->getCurrentVersion();

        return response()->json([
            'success' => true,
            'data' => [
                'version' => $versionInfo['version'],
                'build' => $versionInfo['build'] ?? 'dev',
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => app()->environment(),
                'debug_mode' => config('app.debug'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
            ],
        ]);
    }

    /**
     * Check for available updates
     */
    public function checkForUpdates(): JsonResponse
    {
        try {
            $versionInfo = $this->updateService->checkForUpdates();

            return response()->json([
                'success' => true,
                'data' => $versionInfo,
            ]);

        } catch (\Exception $e) {
            Log::error('Check for updates failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to check for updates: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current update progress
     */
    public function getUpdateProgress(): JsonResponse
    {
        $progress = $this->updateService->getUpdateProgress();

        return response()->json([
            'success' => true,
            'data' => $progress,
        ]);
    }

    /**
     * Trigger system update
     */
    public function update(): JsonResponse
    {
        try {
            $result = $this->updateService->executeUpdate();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => [
                    'backup' => $result['backup'] ?? null,
                ],
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('System update failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download update package without installing
     */
    public function downloadUpdate(Request $request): JsonResponse
    {
        try {
            // For now, this returns success as updates are typically
            // downloaded and installed in one step via update.bat
            // This endpoint can be extended for staged updates

            $version = $request->input('version');

            return response()->json([
                'success' => true,
                'message' => 'Update package ready.',
                'data' => [
                    'version' => $version,
                    'ready' => true,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Download update failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Download failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rollback to previous version
     */
    public function rollback(): JsonResponse
    {
        try {
            $result = $this->updateService->rollback();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Rollback failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Rollback failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get latest update log
     */
    public function updateLog(): JsonResponse
    {
        try {
            $logs = $this->updateService->getUpdateLogs();

            if (empty($logs)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'log' => 'No update logs found.',
                        'file' => null,
                    ],
                ]);
            }

            $latest = $logs[0];

            return response()->json([
                'success' => true,
                'data' => [
                    'log' => $latest['content'],
                    'file' => $latest['name'],
                    'date' => $latest['date'],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to read log: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear application cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            $result = $this->updateService->clearAllCaches();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Cache clear failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create database backup
     */
    public function backup(): JsonResponse
    {
        try {
            $result = $this->updateService->createBackup();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => [
                    'file' => $result['file'] ?? null,
                    'size' => $result['size'] ?? null,
                    'timestamp' => $result['timestamp'] ?? null,
                ],
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Backup failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List available backups
     */
    public function listBackups(): JsonResponse
    {
        try {
            $backups = $this->updateService->listBackups();

            return response()->json([
                'success' => true,
                'data' => [
                    'backups' => $backups,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('List backups failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to list backups: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore from backup
     */
    public function restore(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'backup' => 'required|string',
            ]);

            $result = $this->updateService->restoreBackup($request->input('backup'));

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Restore failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Restore failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run database migrations
     */
    public function migrate(): JsonResponse
    {
        try {
            $result = $this->updateService->runMigrations();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => [
                    'output' => $result['output'] ?? null,
                ],
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Migration failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check system requirements
     */
    public function checkRequirements(Request $request): JsonResponse
    {
        try {
            $minPhpVersion = $request->input('min_php_version', '8.1');
            $result = $this->updateService->checkSystemRequirements($minPhpVersion);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Check requirements failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to check requirements: ' . $e->getMessage(),
            ], 500);
        }
    }
}
