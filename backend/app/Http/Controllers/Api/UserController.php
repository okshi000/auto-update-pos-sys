<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\SyncRolesRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Display a listing of users.
     *
     * GET /api/users
     */
    public function index(Request $request): JsonResponse
    {
        // Check permission
        if (!$request->user()->hasPermission('users.view') && !$request->user()->isAdmin()) {
            return $this->forbidden('You do not have permission to view users.');
        }

        $users = $this->userService->getPaginated(
            $request->input('search'),
            $request->input('role'),
            $request->has('is_active') ? $request->boolean('is_active') : null,
            $request->input('sort_by', 'created_at'),
            $request->input('sort_order', 'desc'),
            (int) $request->input('per_page', 15)
        );

        return $this->success(
            UserResource::collection($users)->response()->getData(true),
            'Users retrieved successfully'
        );
    }

    /**
     * Store a newly created user.
     *
     * POST /api/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        \Log::info('UserController@store - Request data:', $request->all());
        \Log::info('UserController@store - Validated data:', $request->validated());
        
        $user = $this->userService->create($request->validated());

        return $this->created(
            new UserResource($user),
            'User created successfully'
        );
    }

    /**
     * Display the specified user.
     *
     * GET /api/users/{user}
     */
    public function show(Request $request, User $user): JsonResponse
    {
        // Check permission
        if (!$request->user()->hasPermission('users.view') && !$request->user()->isAdmin()) {
            return $this->forbidden('You do not have permission to view users.');
        }

        $user = $this->userService->getById($user->id);

        if (!$user) {
            return $this->notFound('User not found.');
        }

        return $this->success(
            new UserResource($user),
            'User retrieved successfully'
        );
    }

    /**
     * Update the specified user.
     *
     * PUT/PATCH /api/users/{user}
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->update($user, $request->validated());

        return $this->success(
            new UserResource($user),
            'User updated successfully'
        );
    }

    /**
     * Remove the specified user.
     *
     * DELETE /api/users/{user}
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Check permission
        if (!$request->user()->hasPermission('users.delete') && !$request->user()->isAdmin()) {
            return $this->forbidden('You do not have permission to delete users.');
        }

        // Prevent self-deletion
        if ($request->user()->id === $user->id) {
            return $this->error('You cannot delete your own account.', 422);
        }

        $this->userService->delete($user);

        return $this->success(null, 'User deleted successfully');
    }

    /**
     * Toggle user active status.
     *
     * PATCH /api/users/{user}/toggle-active
     */
    public function toggleActive(Request $request, User $user): JsonResponse
    {
        // Check permission
        if (!$request->user()->hasPermission('users.update') && !$request->user()->isAdmin()) {
            return $this->forbidden('You do not have permission to update users.');
        }

        // Prevent self-deactivation
        if ($request->user()->id === $user->id) {
            return $this->error('You cannot deactivate your own account.', 422);
        }

        $user = $this->userService->toggleActive($user);

        $status = $user->is_active ? 'activated' : 'deactivated';

        return $this->success(
            new UserResource($user),
            "User {$status} successfully"
        );
    }

    /**
     * Sync roles for a user.
     *
     * POST /api/users/{user}/roles
     */
    public function syncRoles(SyncRolesRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->syncRoles($user, $request->validated('roles'));

        return $this->success(
            new UserResource($user),
            'User roles updated successfully'
        );
    }
}
