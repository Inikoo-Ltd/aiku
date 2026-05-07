<?php

namespace App\Actions\Catalogue\Review;

use App\Actions\Catalogue\Review\Traits\HasReviewHydrators;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Reviews\ProductCategoryReview;
use App\Models\Reviews\ProductReview;
use App\Models\Reviews\ShopReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreReview
{
    use AsAction;
    use HasReviewHydrators;

    public function handle(Product|ProductCategory|Shop $reviewable, array $modelData): ProductReview|ProductCategoryReview|ShopReview
    {
        $review = DB::transaction(function () use ($reviewable, $modelData) {
            $images = data_get($modelData, 'images', []);
            $videos = data_get($modelData, 'videos', []);
            $reviewData = [
                'group_id' => $reviewable->group_id,
                'organisation_id' => $reviewable->organisation_id,
                'customer_id' => data_get($modelData, 'customer_id'),
                'order_id' => data_get($modelData, 'order_id'),
                'rating_main' => $this->resolveRatingMain($modelData),
                'rating_a' => data_get($modelData, 'rating_a'),
                'rating_b' => data_get($modelData, 'rating_b'),
                'rating_c' => data_get($modelData, 'rating_c'),
                'rating_d' => data_get($modelData, 'rating_d'),
                'rating_e' => data_get($modelData, 'rating_e'),
                'show_after' => data_get($modelData, 'show_after'),
                'status' => data_get($modelData, 'status', ReviewStatusEnum::Pending->value),
                'title' => data_get($modelData, 'title'),
                'message' => data_get($modelData, 'message'),
                'like_count' => data_get($modelData, 'like_count', 0),
                'meta' => data_get($modelData, 'meta', []),
            ];

            $review = match (true) {
                $reviewable instanceof Product => ProductReview::query()->create([
                    ...$reviewData,
                    'master_product_id' => $reviewable->master_product_id,
                    'product_id' => $reviewable->id,
                ]),
                $reviewable instanceof ProductCategory => ProductCategoryReview::query()->create([
                    ...$reviewData,
                    'master_product_category_id' => $reviewable->master_product_category_id,
                    'product_category_id' => $reviewable->id,
                ]),
                default => ShopReview::query()->create([
                    ...$reviewData,
                    'shop_id' => $reviewable->id,
                ]),
            };

            if (!empty($images)) {
                $this->storeUploadedImages($review, $images);
            }

            if (!empty($videos)) {
                $this->storeUploadedVideos($review, $videos);
            }

            return $review->refresh();
        });

        $this->reviewHydrators($review);

        return $review;
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $reviewable = $this->resolveReviewable(
            $request->validated('reviewable_type'),
            (int) $request->validated('reviewable_id')
        );

        $review = $this->handle($reviewable, $request->validated());

        return response()->json([
            'status' => 'success',
            'data'   => $review->load('media'),
        ]);
    }

    public function rules(): array
    {
        return [
            'reviewable_type' => ['required', Rule::in(['Product', 'ProductCategory', 'Shop'])],
            'reviewable_id' => ['required', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'status' => ['sometimes', Rule::enum(ReviewStatusEnum::class)],
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5', 'required_without:rating_main'],
            'rating_main' => ['sometimes', 'numeric', 'min:1', 'max:5', 'required_without:rating'],
            'rating_a' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_b' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_c' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_d' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_e' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'message' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'show_after' => ['sometimes', 'nullable', 'date'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'like_count' => ['sometimes', 'integer', 'min:0'],
            'meta' => ['sometimes', 'array'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['sometimes', File::image()->max(50 * 1024)],
            'videos' => ['sometimes', 'array'],
            'videos.*' => ['sometimes', File::types(['mp4', 'webm'])->max(50 * 1024)],
        ];
    }

    private function storeUploadedImages(Model $review, array $images): void
    {
        foreach ($images as $image) {
            if (!$image instanceof UploadedFile) {
                continue;
            }

            $imageData = [
                'path'         => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension'    => $image->getClientOriginalExtension(),
                'checksum'     => md5_file($image->getPathName()),
            ];

            StoreMediaFromFile::run($review, $imageData, 'review_images', 'image');
        }
    }

    private function storeUploadedVideos(Model $review, array $videos): void
    {
        foreach ($videos as $video) {
            if (!$video instanceof UploadedFile) {
                continue;
            }

            $videoData = [
                'path'         => $video->getPathName(),
                'originalName' => $video->getClientOriginalName(),
                'extension'    => $video->getClientOriginalExtension(),
                'checksum'     => md5_file($video->getPathName()),
            ];

            StoreMediaFromFile::run($review, $videoData, 'review_videos', 'file');
        }
    }

    private function resolveReviewable(string $reviewableType, int $reviewableId): Product|ProductCategory|Shop
    {
        return match ($reviewableType) {
            'Product' => Product::query()->findOrFail($reviewableId),
            'ProductCategory' => ProductCategory::query()->findOrFail($reviewableId),
            'Shop' => Shop::query()->findOrFail($reviewableId),
        };
    }

    private function resolveRatingMain(array $modelData): float
    {
        $detailedRatings = collect([
            data_get($modelData, 'rating_a'),
            data_get($modelData, 'rating_b'),
            data_get($modelData, 'rating_c'),
            data_get($modelData, 'rating_d'),
            data_get($modelData, 'rating_e'),
        ])->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value)->values();

        if ($detailedRatings->isNotEmpty()) {
            return round((float) $detailedRatings->avg(), 2);
        }

        $ratingMain = data_get($modelData, 'rating_main');
        if (is_numeric($ratingMain)) {
            return round((float) $ratingMain, 2);
        }

        $rating = data_get($modelData, 'rating');
        if (is_numeric($rating)) {
            return round((float) $rating, 2);
        }

        return 5;
    }
}
