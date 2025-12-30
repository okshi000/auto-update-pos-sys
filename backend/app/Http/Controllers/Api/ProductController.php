<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\AuditService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService,
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of products.
     */
    public function index(Request $request): JsonResponse
    {
        $products = $this->productService->getPaginated(
            perPage: $request->input('per_page', 15),
            search: $request->input('search'),
            categoryId: $request->input('category_id'),
            activeOnly: $request->has('active') ? $request->boolean('active') : null,
            lowStockOnly: $request->boolean('low_stock'),
            sortBy: $request->input('sort_by', 'name'),
            sortDir: $request->input('sort_dir', 'asc')
        );

        return $this->success([
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Quick search for POS.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:1',
        ]);

        $products = $this->productService->quickSearch(
            $request->input('query'),
            $request->input('limit', 10)
        );

        return $this->success(ProductResource::collection($products));
    }

    /**
     * Find by barcode.
     */
    public function findByBarcode(Request $request): JsonResponse
    {
        $request->validate([
            'barcode' => 'required|string',
        ]);

        $product = $this->productService->findByBarcode($request->input('barcode'));

        if (!$product) {
            return $this->error('Product not found', 404);
        }

        return $this->success(new ProductResource($product));
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        // Log the validated data for debugging
        \Log::info('ProductController::store - Validated data', [
            'data' => $request->validated(),
            'barcode' => $request->input('barcode'),
            'barcode_type' => gettype($request->input('barcode')),
        ]);

        try {
            $product = $this->productService->create($request->validated());
        } catch (\InvalidArgumentException $e) {
            \Log::error('ProductController::store - InvalidArgumentException', [
                'message' => $e->getMessage(),
            ]);
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            \Log::error('ProductController::store - Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error($e->getMessage(), 400);
        }

        $this->auditService->logCreate($product);
        
        // Clear POS cache
        self::clearPosCache();

        return $this->created(
            new ProductResource($product->load(['category', 'taxClass'])),
            'Product created successfully'
        );
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'taxClass', 'images', 'stockLevels.warehouse']);

        return $this->success(new ProductResource($product));
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $oldValues = $product->toArray();

        try {
            $product = $this->productService->update($product, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }

        $this->auditService->logUpdate($product, $oldValues);
        
        // Clear POS cache
        self::clearPosCache();

        return $this->success(
            new ProductResource($product->load(['category', 'taxClass'])),
            'Product updated successfully'
        );
    }

    /**
     * Remove the specified product (soft delete).
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->auditService->logDelete($product);
        $this->productService->delete($product);
        
        // Clear POS cache
        self::clearPosCache();

        return $this->success(null, 'Product deleted successfully');
    }

    /**
     * Toggle product active status.
     */
    public function toggleActive(Product $product): JsonResponse
    {
        $oldValues = $product->toArray();
        $product = $this->productService->toggleActive($product);

        $this->auditService->logUpdate($product, $oldValues);
        
        // Clear POS cache
        self::clearPosCache();

        return $this->success(new ProductResource($product));
    }

    /**
     * Duplicate a product.
     */
    public function duplicate(Product $product): JsonResponse
    {
        $newProduct = $this->productService->duplicate($product);

        $this->auditService->logCreate($newProduct);

        return $this->created(
            new ProductResource($newProduct->load(['category', 'taxClass'])),
            'Product duplicated successfully'
        );
    }

    /**
     * Upload product image.
     */
    public function uploadImage(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_primary' => 'boolean',
        ]);

        $image = $this->productService->uploadImage(
            $product,
            $request->file('image'),
            $request->boolean('is_primary')
        );

        return $this->created($image, 'Image uploaded successfully');
    }

    /**
     * Delete product image.
     */
    public function deleteImage(Product $product, int $imageId): JsonResponse
    {
        $image = $product->images()->findOrFail($imageId);
        $this->productService->deleteImage($image);

        return $this->success(null, 'Image deleted successfully');
    }

    /**
     * Set primary image.
     */
    public function setPrimaryImage(Product $product, int $imageId): JsonResponse
    {
        $image = $product->images()->findOrFail($imageId);
        $this->productService->setPrimaryImage($image);

        return $this->success(null, 'Primary image set successfully');
    }

    /**
     * Get products for POS screen.
     * Cached for 5 minutes, invalidated on product changes.
     */
    public function posProducts(Request $request): JsonResponse
    {
        $categoryId = $request->input('category_id');
        $search = $request->input('search');
        
        // Build cache key based on filters
        $cacheKey = 'pos_products';
        if ($categoryId) {
            $cacheKey .= "_cat_{$categoryId}";
        }
        if ($search) {
            $cacheKey .= '_search_' . md5($search);
        }
        
        // For searches, don't cache (real-time results needed)
        // For category/all products, cache for 5 minutes
        $cacheTtl = $search ? 0 : 300; // 5 minutes
        
        $fetchProducts = function () use ($categoryId, $search) {
            return Product::query()
                ->where('is_active', true)
                ->when($categoryId, function ($query, $catId) {
                    return $query->where('category_id', $catId);
                })
                ->when($search, function ($query, $searchTerm) {
                    return $query->where(function ($q) use ($searchTerm) {
                        $q->where('name', 'like', "%{$searchTerm}%")
                          ->orWhere('sku', 'like', "%{$searchTerm}%")
                          ->orWhere('barcode', 'like', "%{$searchTerm}%");
                    });
                })
                ->with(['stockLevels', 'images' => function ($query) {
                    $query->where('is_primary', true)->limit(1);
                }])
                ->orderBy('name')
                ->get()
                ->map(function ($product) {
                    $primaryImage = $product->images->first();
                    return [
                        'id' => $product->id,
                        'sku' => $product->sku,
                        'barcode' => $product->barcode,
                        'name' => $product->name,
                        'sale_price' => (float) $product->price,
                        'image_url' => $primaryImage?->url ?? null,
                        'category_id' => $product->category_id,
                        'available_quantity' => $product->stockLevels->sum('quantity') ?? 0,
                        'is_active' => $product->is_active,
                    ];
                });
        };
        
        // Use cache for non-search requests
        if ($cacheTtl > 0) {
            $products = Cache::remember($cacheKey, $cacheTtl, $fetchProducts);
        } else {
            $products = $fetchProducts();
        }

        return $this->success($products);
    }
    
    /**
     * Clear POS products cache.
     * Called when products are created, updated, or deleted.
     */
    public static function clearPosCache(): void
    {
        // Clear main cache and category-specific caches
        Cache::forget('pos_products');
        
        // Clear category-specific caches (we'll use tags in production)
        // For now, use pattern-based clearing
        $categories = \App\Models\Category::pluck('id');
        foreach ($categories as $catId) {
            Cache::forget("pos_products_cat_{$catId}");
        }
    }
}
