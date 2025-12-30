<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Exception;

class SystemUpdateService
{
    /**
     * Version file path
     */
    protected string $versionFile;

    /**
     * Update server URL (configure in .env)
     */
    protected string $updateServerUrl;

    /**
     * Backups directory
     */
    protected string $backupsPath;

    /**
     * Logs directory  
     */
    protected string $logsPath;

    /**
     * Update progress cache key
     */
    protected const PROGRESS_CACHE_KEY = 'system_update_progress';

    /**
     * Progress cache TTL (10 minutes)
     */
    protected const PROGRESS_TTL = 600;

    public function __construct()
    {
        $this->versionFile = base_path('version.json');
        $this->updateServerUrl = config('app.update_server_url', 'https://releases.example.com/api/v1');
        $this->backupsPath = base_path('../backups');
        $this->logsPath = base_path('../logs');
    }

    /**
     * Get current system version info
     */
    public function getCurrentVersion(): array
    {
        $default = [
            'version' => '1.0.0',
            'build' => 'dev',
            'release_date' => null,
        ];

        if (!File::exists($this->versionFile)) {
            return $default;
        }

        try {
            $content = File::get($this->versionFile);
            $data = json_decode($content, true);
            return array_merge($default, $data ?? []);
        } catch (Exception $e) {
            Log::warning('Failed to read version file: ' . $e->getMessage());
            return $default;
        }
    }

    /**
     * Check for available updates from the release server
     */
    public function checkForUpdates(): array
    {
        $currentVersion = $this->getCurrentVersion();

        // For local/offline mode, simulate version check
        // In production, this would call the actual update server
        if (config('app.env') === 'local' || empty($this->updateServerUrl) || $this->updateServerUrl === 'https://releases.example.com/api/v1') {
            return $this->getOfflineVersionInfo($currentVersion);
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-License-Key' => config('app.license_key', ''),
                    'X-Current-Version' => $currentVersion['version'],
                ])
                ->get($this->updateServerUrl . '/releases/latest');

            if ($response->successful()) {
                $latestRelease = $response->json();
                
                return [
                    'current_version' => $currentVersion['version'],
                    'latest_version' => $latestRelease['version'] ?? $currentVersion['version'],
                    'is_update_available' => version_compare(
                        $latestRelease['version'] ?? $currentVersion['version'],
                        $currentVersion['version'],
                        '>'
                    ),
                    'release_date' => $latestRelease['release_date'] ?? null,
                    'changelog' => $latestRelease['changelog'] ?? [],
                    'download_size' => $latestRelease['download_size'] ?? null,
                    'requires_migration' => $latestRelease['requires_migration'] ?? false,
                    'min_php_version' => $latestRelease['min_php_version'] ?? '8.1',
                    'breaking_changes' => $latestRelease['breaking_changes'] ?? [],
                ];
            }

            Log::warning('Failed to check for updates: ' . $response->status());
            return $this->getOfflineVersionInfo($currentVersion);

        } catch (Exception $e) {
            Log::warning('Update check failed: ' . $e->getMessage());
            return $this->getOfflineVersionInfo($currentVersion);
        }
    }

    /**
     * Get version info for offline/local mode
     */
    protected function getOfflineVersionInfo(array $currentVersion): array
    {
        return [
            'current_version' => $currentVersion['version'],
            'latest_version' => $currentVersion['version'],
            'is_update_available' => false,
            'release_date' => $currentVersion['release_date'] ?? now()->toISOString(),
            'changelog' => [],
            'download_size' => null,
            'requires_migration' => false,
            'min_php_version' => '8.1',
            'breaking_changes' => [],
        ];
    }

    /**
     * Get update progress
     */
    public function getUpdateProgress(): array
    {
        return Cache::get(self::PROGRESS_CACHE_KEY, [
            'stage' => 'idle',
            'progress' => 0,
            'message' => '',
            'details' => null,
        ]);
    }

    /**
     * Set update progress
     */
    public function setUpdateProgress(string $stage, int $progress, string $message, ?string $details = null): void
    {
        Cache::put(self::PROGRESS_CACHE_KEY, [
            'stage' => $stage,
            'progress' => $progress,
            'message' => $message,
            'details' => $details,
            'updated_at' => now()->toISOString(),
        ], self::PROGRESS_TTL);
    }

    /**
     * Clear update progress
     */
    public function clearUpdateProgress(): void
    {
        Cache::forget(self::PROGRESS_CACHE_KEY);
    }

    /**
     * Create database backup before update
     */
    public function createBackup(): array
    {
        try {
            // Ensure backups directory exists
            if (!File::exists($this->backupsPath)) {
                File::makeDirectory($this->backupsPath, 0755, true);
            }

            $timestamp = now()->format('Y-m-d-H-i-s');
            $backupFile = $this->backupsPath . "/backup-{$timestamp}.sql";

            // Get database connection info
            $connection = config('database.default');
            $database = config("database.connections.{$connection}.database");
            $host = config("database.connections.{$connection}.host");
            $username = config("database.connections.{$connection}.username");
            $password = config("database.connections.{$connection}.password");

            if ($connection === 'mysql') {
                // MySQL backup
                $command = sprintf(
                    'mysqldump --host=%s --user=%s --password=%s %s > %s 2>&1',
                    escapeshellarg($host),
                    escapeshellarg($username),
                    escapeshellarg($password),
                    escapeshellarg($database),
                    escapeshellarg($backupFile)
                );

                exec($command, $output, $returnCode);

                if ($returnCode !== 0) {
                    throw new Exception('MySQL backup failed: ' . implode("\n", $output));
                }
            } elseif ($connection === 'sqlite') {
                // SQLite backup - just copy the file
                $sqlitePath = config("database.connections.{$connection}.database");
                if (File::exists($sqlitePath)) {
                    File::copy($sqlitePath, $backupFile);
                }
            } else {
                // Try using Laravel's backup command if available
                try {
                    Artisan::call('db:backup');
                    return [
                        'success' => true,
                        'message' => 'Backup created successfully',
                        'file' => 'Using Laravel backup command',
                    ];
                } catch (Exception $e) {
                    throw new Exception('Unsupported database connection for backup: ' . $connection);
                }
            }

            $fileSize = File::exists($backupFile) ? $this->formatFileSize(File::size($backupFile)) : 'Unknown';

            Log::info("Database backup created: {$backupFile}");

            return [
                'success' => true,
                'message' => 'Backup created successfully',
                'file' => basename($backupFile),
                'path' => $backupFile,
                'size' => $fileSize,
                'timestamp' => $timestamp,
            ];

        } catch (Exception $e) {
            Log::error('Backup failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * List available backups
     */
    public function listBackups(): array
    {
        $backups = [];

        if (!File::exists($this->backupsPath)) {
            return $backups;
        }

        $files = File::glob($this->backupsPath . '/*.sql');
        
        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'size' => $this->formatFileSize(filesize($file)),
                'path' => $file,
            ];
        }

        // Sort by date descending
        usort($backups, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return $backups;
    }

    /**
     * Restore from backup
     */
    public function restoreBackup(string $backupName): array
    {
        try {
            $backupFile = $this->backupsPath . '/' . $backupName;

            if (!File::exists($backupFile)) {
                return [
                    'success' => false,
                    'message' => 'Backup file not found: ' . $backupName,
                ];
            }

            // Get database connection info
            $connection = config('database.default');
            $database = config("database.connections.{$connection}.database");
            $host = config("database.connections.{$connection}.host");
            $username = config("database.connections.{$connection}.username");
            $password = config("database.connections.{$connection}.password");

            if ($connection === 'mysql') {
                // MySQL restore
                $command = sprintf(
                    'mysql --host=%s --user=%s --password=%s %s < %s 2>&1',
                    escapeshellarg($host),
                    escapeshellarg($username),
                    escapeshellarg($password),
                    escapeshellarg($database),
                    escapeshellarg($backupFile)
                );

                exec($command, $output, $returnCode);

                if ($returnCode !== 0) {
                    throw new Exception('MySQL restore failed: ' . implode("\n", $output));
                }
            } elseif ($connection === 'sqlite') {
                // SQLite restore - copy file back
                $sqlitePath = config("database.connections.{$connection}.database");
                File::copy($backupFile, $sqlitePath);
            } else {
                throw new Exception('Unsupported database connection for restore: ' . $connection);
            }

            Log::info("Database restored from backup: {$backupFile}");

            return [
                'success' => true,
                'message' => 'Database restored successfully from: ' . $backupName,
            ];

        } catch (Exception $e) {
            Log::error('Restore failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Restore failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Execute system update
     */
    public function executeUpdate(): array
    {
        try {
            $this->setUpdateProgress('backing_up', 10, 'Creating backup...');

            // Step 1: Create backup
            $backupResult = $this->createBackup();
            if (!$backupResult['success']) {
                $this->setUpdateProgress('error', 0, 'Backup failed', $backupResult['message']);
                return $backupResult;
            }

            $this->setUpdateProgress('downloading', 30, 'Preparing update...');

            // Step 2: Execute update script
            $basePath = base_path('..');
            $updateBatPath = $basePath . DIRECTORY_SEPARATOR . 'update.bat';

            if (!File::exists($updateBatPath)) {
                $this->setUpdateProgress('error', 0, 'Update script not found');
                return [
                    'success' => false,
                    'message' => 'Update script not found. Please contact support.',
                ];
            }

            $this->setUpdateProgress('installing', 50, 'Installing update...');

            // Log update attempt
            $this->logUpdate('Update started', [
                'backup' => $backupResult['file'] ?? 'N/A',
                'user_id' => auth()->id(),
            ]);

            // Execute update script in background
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $command = 'start /B cmd /c "' . $updateBatPath . '"';
                pclose(popen($command, 'r'));
            } else {
                $command = 'nohup sh ' . escapeshellarg($updateBatPath) . ' > /dev/null 2>&1 &';
                exec($command);
            }

            $this->setUpdateProgress('completing', 90, 'Finalizing update...');

            Log::info('System update triggered by user: ' . auth()->id());

            return [
                'success' => true,
                'message' => 'System update started. The application will restart automatically.',
                'backup' => $backupResult['file'] ?? null,
            ];

        } catch (Exception $e) {
            Log::error('System update failed: ' . $e->getMessage());
            $this->setUpdateProgress('error', 0, 'Update failed', $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Rollback to previous version
     */
    public function rollback(): array
    {
        try {
            // Get the latest backup
            $backups = $this->listBackups();
            
            if (empty($backups)) {
                return [
                    'success' => false,
                    'message' => 'No backups available for rollback.',
                ];
            }

            // Restore the most recent backup
            $latestBackup = $backups[0];
            $restoreResult = $this->restoreBackup($latestBackup['name']);

            if ($restoreResult['success']) {
                Log::info('System rollback completed using backup: ' . $latestBackup['name']);
            }

            return $restoreResult;

        } catch (Exception $e) {
            Log::error('Rollback failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Rollback failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Run database migrations
     */
    public function runMigrations(): array
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            Log::info('Migrations run successfully');

            return [
                'success' => true,
                'message' => 'Migrations completed successfully.',
                'output' => $output,
            ];

        } catch (Exception $e) {
            Log::error('Migration failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Clear all caches
     */
    public function clearAllCaches(): array
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            
            // Clear compiled classes
            if (File::exists(base_path('bootstrap/cache/packages.php'))) {
                File::delete(base_path('bootstrap/cache/packages.php'));
            }
            if (File::exists(base_path('bootstrap/cache/services.php'))) {
                File::delete(base_path('bootstrap/cache/services.php'));
            }

            Log::info('All caches cleared by user: ' . auth()->id());

            return [
                'success' => true,
                'message' => 'All caches cleared successfully.',
            ];

        } catch (Exception $e) {
            Log::error('Cache clear failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get update logs
     */
    public function getUpdateLogs(): array
    {
        $logs = [];

        if (!File::exists($this->logsPath)) {
            return $logs;
        }

        $logFiles = File::glob($this->logsPath . '/update_*.log');

        foreach ($logFiles as $file) {
            $logs[] = [
                'name' => basename($file),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'size' => $this->formatFileSize(filesize($file)),
                'content' => File::get($file),
            ];
        }

        // Sort by date descending
        usort($logs, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return $logs;
    }

    /**
     * Log update activity
     */
    protected function logUpdate(string $message, array $context = []): void
    {
        if (!File::exists($this->logsPath)) {
            File::makeDirectory($this->logsPath, 0755, true);
        }

        $logFile = $this->logsPath . '/update_' . now()->format('Y-m-d') . '.log';
        $timestamp = now()->toISOString();
        $contextString = !empty($context) ? ' ' . json_encode($context) : '';
        
        $logEntry = "[{$timestamp}] {$message}{$contextString}\n";
        
        File::append($logFile, $logEntry);
    }

    /**
     * Format file size to human readable
     */
    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Check system requirements for update
     */
    public function checkSystemRequirements(string $minPhpVersion = '8.1'): array
    {
        $requirements = [];
        $allMet = true;

        // PHP version
        $phpMet = version_compare(PHP_VERSION, $minPhpVersion, '>=');
        $requirements['php'] = [
            'name' => 'PHP Version',
            'required' => $minPhpVersion,
            'current' => PHP_VERSION,
            'met' => $phpMet,
        ];
        if (!$phpMet) $allMet = false;

        // Required PHP extensions
        $requiredExtensions = ['pdo', 'mbstring', 'openssl', 'json', 'curl'];
        foreach ($requiredExtensions as $ext) {
            $loaded = extension_loaded($ext);
            $requirements['ext_' . $ext] = [
                'name' => "PHP Extension: {$ext}",
                'required' => 'Enabled',
                'current' => $loaded ? 'Enabled' : 'Disabled',
                'met' => $loaded,
            ];
            if (!$loaded) $allMet = false;
        }

        // Disk space (at least 100MB free)
        $freeSpace = disk_free_space(base_path());
        $requiredSpace = 100 * 1024 * 1024; // 100MB
        $spaceMet = $freeSpace >= $requiredSpace;
        $requirements['disk_space'] = [
            'name' => 'Disk Space',
            'required' => $this->formatFileSize($requiredSpace),
            'current' => $this->formatFileSize($freeSpace),
            'met' => $spaceMet,
        ];
        if (!$spaceMet) $allMet = false;

        // Writable directories
        $writableDirs = [
            base_path('storage'),
            base_path('bootstrap/cache'),
        ];
        foreach ($writableDirs as $dir) {
            $writable = is_writable($dir);
            $requirements['writable_' . basename($dir)] = [
                'name' => 'Writable: ' . basename($dir),
                'required' => 'Writable',
                'current' => $writable ? 'Writable' : 'Not Writable',
                'met' => $writable,
            ];
            if (!$writable) $allMet = false;
        }

        return [
            'all_met' => $allMet,
            'requirements' => $requirements,
        ];
    }
}
