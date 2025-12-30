<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List all suppliers.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query();

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Search by name, code, email, or phone
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = $request->input('per_page', 15);
        $suppliers = $query->paginate($perPage);

        return $this->success([
            'data' => $suppliers->items(),
            'meta' => [
                'current_page' => $suppliers->currentPage(),
                'last_page' => $suppliers->lastPage(),
                'per_page' => $suppliers->perPage(),
                'total' => $suppliers->total(),
            ],
        ]);
    }

    /**
     * Create a new supplier.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:suppliers,code',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        // Generate code if not provided
        if (empty($validated['code'])) {
            $validated['code'] = Supplier::generateCode();
        }

        $supplier = Supplier::create($validated);

        $this->auditService->logCreate($supplier);

        return $this->created($supplier, 'Supplier created successfully');
    }

    /**
     * Show a single supplier.
     */
    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load(['purchaseOrders' => function ($query) {
            $query->latest()->limit(10);
        }]);

        return $this->success($supplier);
    }

    /**
     * Update a supplier.
     */
    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('suppliers')->ignore($supplier->id)],
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        $oldData = $supplier->toArray();
        $supplier->update($validated);

        $this->auditService->logUpdate($supplier, $oldData);

        return $this->success($supplier, 'Supplier updated successfully');
    }

    /**
     * Delete a supplier (soft delete).
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        if (!$supplier->canDelete()) {
            return $this->error('Cannot delete supplier with existing purchase orders', 422);
        }

        $supplier->delete();

        $this->auditService->logDelete($supplier);

        return $this->success(null, 'Supplier deleted successfully');
    }

    /**
     * Restore a soft-deleted supplier.
     */
    public function restore(int $id): JsonResponse
    {
        $supplier = Supplier::withTrashed()->findOrFail($id);
        $supplier->restore();

        $this->auditService->logUpdate($supplier, ['deleted_at' => $supplier->deleted_at]);

        return $this->success($supplier, 'Supplier restored successfully');
    }
}
