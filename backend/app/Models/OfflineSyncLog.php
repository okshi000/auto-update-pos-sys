<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfflineSyncLog extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SYNCED = 'synced';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DUPLICATE = 'duplicate';

    public const ENTITY_SALE = 'sale';

    protected $fillable = [
        'client_uuid',
        'idempotency_key',
        'entity_type',
        'entity_id',
        'status',
        'request_payload',
        'response_data',
        'error_message',
        'has_conflicts',
        'conflicts',
        'synced_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_data' => 'array',
        'has_conflicts' => 'boolean',
        'conflicts' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * Check if sync log exists for idempotency key.
     */
    public static function existsByIdempotencyKey(string $key): bool
    {
        return static::where('idempotency_key', $key)->exists();
    }

    /**
     * Find by idempotency key.
     */
    public static function findByIdempotencyKey(string $key): ?self
    {
        return static::where('idempotency_key', $key)->first();
    }

    /**
     * Mark as synced.
     */
    public function markAsSynced(int $entityId, array $responseData = []): void
    {
        $this->update([
            'status' => self::STATUS_SYNCED,
            'entity_id' => $entityId,
            'response_data' => $responseData,
            'synced_at' => now(),
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark as duplicate.
     */
    public function markAsDuplicate(int $existingEntityId): void
    {
        $this->update([
            'status' => self::STATUS_DUPLICATE,
            'entity_id' => $existingEntityId,
            'synced_at' => now(),
        ]);
    }

    /**
     * Record conflicts.
     */
    public function recordConflicts(array $conflicts): void
    {
        $this->update([
            'has_conflicts' => true,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Scope to pending syncs.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to syncs with conflicts.
     */
    public function scopeWithConflicts($query)
    {
        return $query->where('has_conflicts', true);
    }

    /**
     * Scope by client UUID.
     */
    public function scopeForClient($query, string $clientUuid)
    {
        return $query->where('client_uuid', $clientUuid);
    }
}
