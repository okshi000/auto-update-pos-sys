<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    /**
     * Get audit logs with filters and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'string|nullable',
            'action' => 'string|nullable|in:create,update,delete,login,logout,view',
            'entity_type' => 'string|nullable',
            'date_from' => 'date|nullable',
            'date_to' => 'date|nullable',
        ]);

        $query = AuditLog::query()
            ->with(['user:id,name,email'])
            ->orderBy('created_at', 'desc');

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('auditable_type', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Action filter
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        // Entity type filter
        if ($entityType = $request->input('entity_type')) {
            $query->where('auditable_type', $entityType);
        }

        // Date range filter
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $perPage = $request->input('per_page', 25);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => __('messages.audit_logs_retrieved'),
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
        ]);
    }

    /**
     * Get a single audit log
     */
    public function show(int $id): JsonResponse
    {
        $log = AuditLog::with(['user:id,name,email'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => __('messages.audit_log_retrieved'),
            'data' => $log,
        ]);
    }

    /**
     * Get unique entity types for filter dropdown
     */
    public function entityTypes(): JsonResponse
    {
        $entityTypes = AuditLog::select('auditable_type')
            ->distinct()
            ->whereNotNull('auditable_type')
            ->pluck('auditable_type')
            ->map(function ($type) {
                // Convert full class name to short name
                return class_basename($type);
            })
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $entityTypes,
        ]);
    }

    /**
     * Get available actions for filter dropdown
     */
    public function actions(): JsonResponse
    {
        $actions = ['create', 'update', 'delete', 'login', 'logout', 'view'];

        return response()->json([
            'success' => true,
            'data' => $actions,
        ]);
    }
}
