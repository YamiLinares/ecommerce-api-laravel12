<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\IndexProductRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    /** Listado público con filtros y paginación. Los inactivos solo los ve un administrador. */
    public function index(IndexProductRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $isAdmin = $this->isAdmin($request);

        $query = Product::query()
            ->when(! ($isAdmin && $request->boolean('include_inactive')), fn ($q) => $q->active())
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('description', 'like', $term));
            })
            ->when(isset($filters['min_price']), fn ($q) => $q->where('price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($q) => $q->where('price', '<=', $filters['max_price']))
            ->when($request->has('in_stock'), fn ($q) => $request->boolean('in_stock')
                ? $q->where('stock', '>', 0)
                : $q->where('stock', 0));

        $sort = $filters['sort'] ?? 'name';
        $query->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')->orderBy('id');

        return ProductResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString()
        );
    }

    public function show(Request $request, Product $product): ProductResource
    {
        abort_if(! $product->is_active && ! $this->isAdmin($request), 404);

        return new ProductResource($product);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product->refresh());
    }

    /** Borrado lógico: conserva la integridad del historial de órdenes. */
    public function destroy(Product $product): Response
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return response()->noContent();
    }

    /** La ruta es pública; el token es opcional y solo amplía la visibilidad para administradores. */
    private function isAdmin(Request $request): bool
    {
        return (bool) $request->user('api')?->isAdmin();
    }
}
