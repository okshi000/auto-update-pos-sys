<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of roles.
     *
     * GET /api/roles
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('roles.view') && !$request->user()->isAdmin()) {
            return $this->forbidden('You do not have permission to view roles.');
        }

        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return $this->success(
            RoleResource::collection($roles),
            'Roles retrieved successfully'
        );
    }

    /**
     * Store a newly created role.
     *
     * POST /api/roles
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('roles.create') && !$request->user()->isAdmin()) {
            return $this->forbidden('You do not have permission to create roles.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:roles,name',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        if (!empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        $role->load('permissions');

        $this->auditService->logCreate($role);

        return $this->created(
            new RoleResource($role),
            'Role created successfully'
        );
    }

    /**
     * Display the specified role.
     *
     * GET /api/roles/{role}
     */
    public function show(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->hasPermission('roles.view') && !$request->user()->isAdmin()) {
            return $this->forbidden('You do not have permission to view roles.');
        }

        $role->load('permissions');
        $role->loadCount('users');

        return $this->success(
            new RoleResource($role),
            'Role retrieved successfully'
        );
    }

    /**
     * Update the specified role.
     *
     * PUT/PATCH /api/roles/{role}
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->hasPermission('roles.update') && !$request->user()->isAdmin()) {
            return $this->forbidden('You do not have permission to update roles.');
        }

        if ($role->is_system) {
            return $this->error('System roles cannot be modified.', 422);
        }

        $validated = $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $oldValues = $role->getAttributes();

        if (isset($validated['display_name'])) {
            $role->display_name = $validated['display_name'];
        }

        if (array_key_exists('description', $validated)) {
            $role->description = $validated['description'];
        }

        $role->save();

        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        $role->load('permissions');

        $this->auditService->logUpdate($role, $oldValues);

        return $this->success(
            new RoleResource($role),
            'Role updated successfully'
        );
    }

    /**
     * Remove the specified role.
     *
     * DELETE /api/roles/{role}
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->hasPermission('roles.delete') && !$request->user()->isAdmin()) {
            return $this->forbidden('You do not have permission to delete roles.');
        }

        if ($role->is_system) {
            return $this->error('System roles cannot be deleted.', 422);
        }

        if ($role->users()->count() > 0) {
            return $this->error('Cannot delete role with assigned users.', 422);
        }

        $this->auditService->logDelete($role);

        $role->permissions()->detach();
        $role->delete();

        return $this->success(null, 'Role deleted successfully');
    }

    /**
     * Get all available permissions.
     *
     * GET /api/permissions
     */
    public function permissions(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('roles.view') && !$request->user()->isAdmin()) {
            return $this->forbidden('You do not have permission to view permissions.');
        }

        $permissions = Permission::orderBy('group')->orderBy('name')->get();

        // Group permissions by their group field
        $grouped = $permissions->groupBy('group')->map(function ($items) {
            return PermissionResource::collection($items);
        });

        return $this->success($grouped, 'Permissions retrieved successfully');
    }
}
