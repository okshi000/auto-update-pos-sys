<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an action.
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
        ]);
    }

    /**
     * Log a create action.
     */
    public function logCreate(Model $model): AuditLog
    {
        return $this->log(
            'created',
            $model,
            null,
            $this->getAuditableAttributes($model)
        );
    }

    /**
     * Log an update action.
     */
    public function logUpdate(Model $model, array $oldValues): AuditLog
    {
        return $this->log(
            'updated',
            $model,
            $oldValues,
            $this->getAuditableAttributes($model)
        );
    }

    /**
     * Log a delete action.
     */
    public function logDelete(Model $model): AuditLog
    {
        return $this->log(
            'deleted',
            $model,
            $this->getAuditableAttributes($model),
            null
        );
    }

    /**
     * Log a login action.
     */
    public function logLogin(Model $user): AuditLog
    {
        return AuditLog::create([
            'user_id' => $user->getKey(),
            'action' => 'login',
            'auditable_type' => get_class($user),
            'auditable_id' => $user->getKey(),
            'old_values' => null,
            'new_values' => null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
        ]);
    }

    /**
     * Log a logout action.
     */
    public function logLogout(Model $user): AuditLog
    {
        return AuditLog::create([
            'user_id' => $user->getKey(),
            'action' => 'logout',
            'auditable_type' => get_class($user),
            'auditable_id' => $user->getKey(),
            'old_values' => null,
            'new_values' => null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
        ]);
    }

    /**
     * Log a failed login action.
     */
    public function logFailedLogin(string $email): AuditLog
    {
        return AuditLog::create([
            'user_id' => null,
            'action' => 'failed_login',
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => ['email' => $email],
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
        ]);
    }

    /**
     * Get auditable attributes from model.
     */
    protected function getAuditableAttributes(Model $model): array
    {
        $attributes = $model->getAttributes();

        // Remove sensitive fields
        $hidden = ['password', 'remember_token'];

        return array_diff_key($attributes, array_flip($hidden));
    }
}
