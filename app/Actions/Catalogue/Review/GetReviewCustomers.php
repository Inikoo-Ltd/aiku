<?php

namespace App\Actions\Catalogue\Review;

use App\Http\Resources\Catalogue\ReviewsResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetReviewCustomers
{
    use AsAction;

    public function handle(ProductCategory|Product $reviewable, int $page = 1, int $perPage = 50, ?string $search = null): array
    {
        return ReviewsResource::paginateReviewCustomers($reviewable, $page, $perPage, $search);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): JsonResponse
    {
        $request->validate([
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filter'   => ['sometimes', 'array'],
            'filter.global' => ['sometimes', 'string', 'max:255'],
        ]);

        $page = max((int) $request->input('page', 1), 1);
        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);
        $search = data_get($request->input('filter', []), 'global');

        $data = $this->handle(
            $productCategory,
            $page,
            $perPage,
            is_string($search) ? $search : null,
        );

        return response()->json($data);
    }

    public function asControllerProduct(Product $product, ActionRequest $request): JsonResponse
    {
        $request->validate([
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filter'   => ['sometimes', 'array'],
            'filter.global' => ['sometimes', 'string', 'max:255'],
        ]);

        $page = max((int) $request->input('page', 1), 1);
        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);
        $search = data_get($request->input('filter', []), 'global');

        $data = $this->handle(
            $product,
            $page,
            $perPage,
            is_string($search) ? $search : null,
        );

        return response()->json($data);
    }
}
