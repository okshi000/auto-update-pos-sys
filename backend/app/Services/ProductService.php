<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function __construct(
        protected SkuService $skuService,
        protected BarcodeService $barcodeService
    ) {}

    /**
     * Get paginated products.
     */
    public function getPaginated(
        int $perPage = 15,
        ?string $search = null,
        ?int $categoryId = null,
        ?bool $activeOnly = null,
        ?bool $lowStockOnly = null,
        ?string $sortBy = 'name',
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $query = Product::with(['category', 'taxClass', 'primaryImage', 'stockLevels']);

        if ($search) {
            $query->search($search);
        }

        if ($categoryId) {
            $query->inCategory($categoryId);
        }

        if ($activeOnly === true) {
            $query->active();
        } elseif ($activeOnly === false) {
            $query->where('is_active', false);
        }

        if ($lowStockOnly) {
            $query->where('stock_tracked', true)
                ->whereHas('stockLevels', function ($q) {
                    $q->whereRaw('quantity <= (SELECT min_stock_level FROM products WHERE products.id = stock_levels.product_id)');
                });
        }

        $allowedSortColumns = ['name', 'sku', 'price', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'name';
        }

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    /**
     * Create a new product.
     */
    public function create(array $data): Product
    {
        // Generate SKU if not provided
        if (empty($data['sku'])) {
            $categoryCode = null;
            if (!empty($data['category_id'])) {
                $category = \App\Models\Category::find($data['category_id']);
                $categoryCode = $category?->slug;
            }
            $data['sku'] = $this->skuService->generate($categoryCode, $data['name'] ?? null);
        }

        // Validate barcode if provided
        if (!empty($data['barcode']) && !$this->barcodeService->validate($data['barcode'])) {
            throw new \InvalidArgumentException('Invalid barcode format');
        }

        return Product::create($data);
    }

    /**
     * Update a product.
     */
    public function update(Product $product, array $data): Product
    {
        // Validate barcode if provided
        if (!empty($data['barcode']) && !$this->barcodeService->validate($data['barcode'])) {
            throw new \InvalidArgumentException('Invalid barcode format');
        }

        $product->update($data);
        return $product->fresh();
    }

    /**
     * Delete a product (soft delete).
     */
    public function delete(Product $product): bool
    {
        return $product->delete();
    }

    /**
     * Find product by barcode.
     */
    public function findByBarcode(string $barcode): ?Product
    {
        return Product::with(['category', 'taxClass', 'stockLevels'])
            ->byBarcode($barcode)
            ->first();
    }

    /**
     * Find product by SKU.
     */
    public function findBySku(string $sku): ?Product
    {
        return Product::with(['category', 'taxClass', 'stockLevels'])
            ->where('sku', $sku)
            ->first();
    }

    /**
     * Search products by barcode, SKU, or name.
     */
    public function quickSearch(string $query, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Product::with(['category', 'primaryImage', 'stockLevels'])
            ->active()
            ->where(function ($q) use ($query) {
                $q->where('barcode', $query)
                    ->orWhere('sku', $query)
                    ->orWhere('name', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Upload product image.
     */
    public function uploadImage(Product $product, UploadedFile $file, bool $isPrimary = false): ProductImage
    {
        $path = $file->store('products/' . $product->id, 'public');

        // If this is the first image or marked as primary, set it as primary
        $shouldBePrimary = $isPrimary || $product->images()->count() === 0;

        return $product->images()->create([
            'image_path' => $path,
            'is_primary' => $shouldBePrimary,
            'sort_order' => $product->images()->max('sort_order') + 1,
        ]);
    }

    /**
     * Delete product image.
     */
    public function deleteImage(ProductImage $image): bool
    {
        $wasPrimary = $image->is_primary;
        $productId = $image->product_id;

        $deleted = $image->delete();

        // If deleted image was primary, set next image as primary
        if ($deleted && $wasPrimary) {
            $nextImage = ProductImage::where('product_id', $productId)
                ->orderBy('sort_order')
                ->first();

            if ($nextImage) {
                $nextImage->update(['is_primary' => true]);
            }
        }

        return $deleted;
    }

    /**
     * Set primary image.
     */
    public function setPrimaryImage(ProductImage $image): bool
    {
        $image->is_primary = true;
        return $image->save();
    }

    /**
     * Reorder product images.
     */
    public function reorderImages(Product $product, array $imageIds): void
    {
        foreach ($imageIds as $index => $imageId) {
            ProductImage::where('id', $imageId)
                ->where('product_id', $product->id)
                ->update(['sort_order' => $index]);
        }
    }

    /**
     * Toggle product active status.
     */
    public function toggleActive(Product $product): Product
    {
        $product->is_active = !$product->is_active;
        $product->save();
        return $product;
    }

    /**
     * Duplicate a product.
     */
    public function duplicate(Product $product): Product
    {
        $newProduct = $product->replicate();
        $newProduct->name = $product->name . ' (Copy)';
        $newProduct->slug = null; // Reset slug to trigger auto-generation
        $newProduct->sku = $this->skuService->generate(
            $newProduct->name,
            $product->category?->slug
        );
        $newProduct->barcode = null; // Clear barcode to avoid duplicates
        $newProduct->save();

        return $newProduct;
    }
}
