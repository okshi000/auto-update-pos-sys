<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryService
{
    /**
     * Get paginated categories.
     */
    public function getPaginated(
        int $perPage = 15,
        ?string $search = null,
        ?int $parentId = null,
        bool $activeOnly = false
    ): LengthAwarePaginator {
        $query = Category::with('parent');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($parentId !== null) {
            if ($parentId === 0) {
                $query->root();
            } else {
                $query->where('parent_id', $parentId);
            }
        }

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('sort_order')->orderBy('name')->paginate($perPage);
    }

    /**
     * Create a new category.
     */
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    /**
     * Update a category.
     */
    public function update(Category $category, array $data): Category
    {
        // Prevent category from being its own parent
        if (isset($data['parent_id']) && $data['parent_id'] == $category->id) {
            throw new \InvalidArgumentException('A category cannot be its own parent');
        }

        // Prevent circular references
        if (isset($data['parent_id']) && $data['parent_id']) {
            $this->validateNotDescendant($category, $data['parent_id']);
        }

        $category->update($data);
        return $category->fresh();
    }

    /**
     * Delete a category.
     */
    public function delete(Category $category): bool
    {
        // Move children to parent category
        if ($category->children()->count() > 0) {
            $category->children()->update(['parent_id' => $category->parent_id]);
        }

        return $category->delete();
    }

    /**
     * Get category tree.
     */
    public function getTree(bool $activeOnly = true): \Illuminate\Database\Eloquent\Collection
    {
        return Category::getTree($activeOnly);
    }

    /**
     * Validate that the new parent is not a descendant of the category.
     */
    protected function validateNotDescendant(Category $category, int $newParentId): void
    {
        $descendantIds = $this->getDescendantIds($category);

        if (in_array($newParentId, $descendantIds)) {
            throw new \InvalidArgumentException('Cannot set a descendant category as parent');
        }
    }

    /**
     * Get all descendant IDs of a category.
     */
    protected function getDescendantIds(Category $category): array
    {
        $ids = [];

        foreach ($category->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }

        return $ids;
    }

    /**
     * Reorder categories.
     */
    public function reorder(array $order): void
    {
        foreach ($order as $index => $categoryId) {
            Category::where('id', $categoryId)->update(['sort_order' => $index]);
        }
    }
}
