<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get paginated users with optional filters.
     */
    public function getPaginated(
        ?string $search = null,
        ?string $role = null,
        ?bool $isActive = null,
        string $sortBy = 'created_at',
        string $sortOrder = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = User::with('roles');

        if ($search !== null) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role !== null) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create a new user.
     */
    public function create(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'locale' => $data['locale'] ?? 'en',
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (isset($data['roles'])) {
            $user->roles()->sync($data['roles']);
        }

        $user->load('roles');

        $this->auditService->logCreate($user);

        return $user;
    }

    /**
     * Update an existing user.
     */
    public function update(User $user, array $data): User
    {
        $oldValues = $user->getAttributes();

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }

        if (isset($data['phone'])) {
            $updateData['phone'] = $data['phone'];
        }

        if (isset($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        if (isset($data['locale'])) {
            $updateData['locale'] = $data['locale'];
        }

        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        if (isset($data['roles'])) {
            $user->roles()->sync($data['roles']);
        }

        $user->load('roles');

        $this->auditService->logUpdate($user, $oldValues);

        return $user;
    }

    /**
     * Delete a user.
     */
    public function delete(User $user): bool
    {
        $this->auditService->logDelete($user);

        // Revoke all tokens
        $user->tokens()->delete();

        // Soft delete the user
        return $user->delete();
    }

    /**
     * Toggle user active status.
     */
    public function toggleActive(User $user): User
    {
        $oldValues = $user->getAttributes();

        $user->update(['is_active' => !$user->is_active]);

        // If deactivated, revoke all tokens
        if (!$user->is_active) {
            $user->tokens()->delete();
        }

        $this->auditService->logUpdate($user, $oldValues);

        return $user;
    }

    /**
     * Sync roles for a user.
     */
    public function syncRoles(User $user, array $roleIds): User
    {
        $oldRoles = $user->roles->pluck('id')->toArray();

        $user->roles()->sync($roleIds);
        $user->load('roles');

        $this->auditService->log(
            'sync_roles',
            $user,
            ['roles' => $oldRoles],
            ['roles' => $roleIds]
        );

        return $user;
    }

    /**
     * Get a user by ID with roles.
     */
    public function getById(int $id): ?User
    {
        return User::with('roles')->find($id);
    }
}
