<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AuditService;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService,
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of categories.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = $this->categoryService->getPaginated(
            perPage: $request->input('per_page', 15),
            search: $request->input('search'),
            parentId: $request->has('parent_id') ? (int) $request->input('parent_id') : null,
            activeOnly: $request->boolean('active_only')
        );

        return $this->success([
            'data' => CategoryResource::collection($categories),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    /**
     * Get category tree.
     */
    public function tree(Request $request): JsonResponse
    {
        $tree = $this->categoryService->getTree(
            activeOnly: $request->boolean('active_only', true)
        );

        return $this->success(CategoryResource::collection($tree));
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());

        $this->auditService->logCreate($category);

        return $this->created(
            new CategoryResource($category->load('parent')),
            'Category created successfully'
        );
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): JsonResponse
    {
        $category->load(['parent', 'children']);

        return $this->success(new CategoryResource($category));
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $oldValues = $category->toArray();

        try {
            $category = $this->categoryService->update($category, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }

        $this->auditService->logUpdate($category, $oldValues);

        return $this->success(
            new CategoryResource($category->load('parent')),
            'Category updated successfully'
        );
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category): JsonResponse
    {
        // Check if category has products
        if ($category->products()->count() > 0) {
            return $this->error(
                'Cannot delete category with associated products. Please move or delete products first.',
                400
            );
        }

        $this->auditService->logDelete($category);
        $this->categoryService->delete($category);

        return $this->success(null, 'Category deleted successfully');
    }

    /**
     * Reorder categories.
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:categories,id',
        ]);

        $this->categoryService->reorder($request->input('order'));

        return $this->success(null, 'Categories reordered successfully');
    }
}
