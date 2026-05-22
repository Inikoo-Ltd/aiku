<?php

namespace App\Actions\Catalogue\Review;

use App\Actions\Catalogue\Review\Traits\HasReviewHydrators;
use App\Models\Reviews\ProductCategoryReview;
use App\Models\Reviews\ProductReview;
use App\Models\Reviews\ShopReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteReview
{
    use AsAction;
    use HasReviewHydrators;

    public function handle(ProductReview|ProductCategoryReview|ShopReview $review): bool
    {
        $isDeleted = DB::transaction(function () use ($review): bool {
            $isDeleted = $review->delete();

            return (bool) $isDeleted;
        });

        if ($isDeleted) {
            $this->reviewHydrators($review);
        }

        return $isDeleted;
    }

    public function asController(ActionRequest $request): JsonResponse|RedirectResponse
    {
        $reviewId = (int) $request->route('review');
        $reviewableType = $request->validated('reviewable_type');

        $review = $reviewableType
            ? $this->resolveReview((string) $reviewableType, $reviewId)
            : $this->resolveReviewWithoutType($reviewId);

        $isDeleted = $this->handle($review);

        if (!$request->expectsJson()) {
            return redirect()->back();
        }

        return response()->json([
            'status' => $isDeleted ? 'success' : 'failed',
            'message' => $isDeleted ? __('Review deleted successfully.') : __('Failed to delete review.'),
        ], $isDeleted ? 200 : 422);
    }

    public function rules(): array
    {
        return [
            'reviewable_type' => ['sometimes', Rule::in(['Product', 'ProductCategory', 'Shop', 'product_reviews', 'product_category_reviews', 'shop_reviews'])],
        ];
    }

    private function resolveReview(string $reviewableType, int $reviewId): ProductReview|ProductCategoryReview|ShopReview
    {
        return match ($reviewableType) {
            'Product', 'product_reviews' => ProductReview::query()->findOrFail($reviewId),
            'ProductCategory', 'product_category_reviews' => ProductCategoryReview::query()->findOrFail($reviewId),
            'Shop', 'shop_reviews' => ShopReview::query()->findOrFail($reviewId),
        };
    }

    private function resolveReviewWithoutType(int $reviewId): ProductReview|ProductCategoryReview|ShopReview
    {
        $matches = [];

        $productCategoryReview = ProductCategoryReview::query()->find($reviewId);
        if ($productCategoryReview) {
            $matches[] = $productCategoryReview;
        }

        $productReview = ProductReview::query()->find($reviewId);
        if ($productReview) {
            $matches[] = $productReview;
        }

        $shopReview = ShopReview::query()->find($reviewId);
        if ($shopReview) {
            $matches[] = $shopReview;
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            throw ValidationException::withMessages([
                'reviewable_type' => __('Please provide reviewable_type to delete this review safely.'),
            ]);
        }

        abort(404);
    }
}
