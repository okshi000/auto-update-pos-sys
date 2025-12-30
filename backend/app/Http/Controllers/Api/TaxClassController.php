<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaxClassResource;
use App\Models\TaxClass;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxClassController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of tax classes.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TaxClass::query();

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $taxClasses = $query->orderBy('name')->get();

        return $this->success(TaxClassResource::collection($taxClasses));
    }

    /**
     * Store a newly created tax class.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:tax_classes,code',
            'rate' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $taxClass = TaxClass::create($validated);

        $this->auditService->logCreate($taxClass);

        return $this->created(
            new TaxClassResource($taxClass),
            'Tax class created successfully'
        );
    }

    /**
     * Display the specified tax class.
     */
    public function show(TaxClass $taxClass): JsonResponse
    {
        return $this->success(new TaxClassResource($taxClass));
    }

    /**
     * Update the specified tax class.
     */
    public function update(Request $request, TaxClass $taxClass): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50|unique:tax_classes,code,' . $taxClass->id,
            'rate' => 'sometimes|required|numeric|min:0|max:100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $oldValues = $taxClass->toArray();
        $taxClass->update($validated);

        $this->auditService->logUpdate($taxClass, $oldValues);

        return $this->success(
            new TaxClassResource($taxClass),
            'Tax class updated successfully'
        );
    }

    /**
     * Remove the specified tax class.
     */
    public function destroy(TaxClass $taxClass): JsonResponse
    {
        // Check if tax class is used by products
        if ($taxClass->products()->count() > 0) {
            return $this->error(
                'Cannot delete tax class with associated products.',
                400
            );
        }

        // Check if it's the default
        if ($taxClass->is_default) {
            return $this->error('Cannot delete the default tax class.', 400);
        }

        $this->auditService->logDelete($taxClass);
        $taxClass->delete();

        return $this->success(null, 'Tax class deleted successfully');
    }
}
