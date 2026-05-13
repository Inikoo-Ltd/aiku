<?php

namespace App\Actions\Catalogue\Review;

use App\Actions\Catalogue\Review\Traits\HasReviewHydrators;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Models\Reviews\ProductCategoryReview;
use App\Models\Reviews\ProductReview;
use App\Models\Reviews\ShopReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateReview
{
    use AsAction;
    use HasReviewHydrators;

    public function handle(ProductReview|ProductCategoryReview|ShopReview $review, array $modelData): ProductReview|ProductCategoryReview|ShopReview
    {
        $updatedReview = DB::transaction(function () use ($review, $modelData) {
            $images = Arr::pull($modelData, 'images', null);
            $videos = Arr::pull($modelData, 'videos', null);

            $review->update([
                'customer_id'          => data_get($modelData, 'customer_id', $review->customer_id),
                'status'               => data_get($modelData, 'status', $review->status?->value),
                'rating_main'          => data_get($modelData, 'rating_main', data_get($modelData, 'rating', $review->rating_main)),
                'rating_a'             => data_get($modelData, 'rating_a', $review->rating_a),
                'rating_b'             => data_get($modelData, 'rating_b', $review->rating_b),
                'rating_c'             => data_get($modelData, 'rating_c', $review->rating_c),
                'rating_d'             => data_get($modelData, 'rating_d', $review->rating_d),
                'rating_e'             => data_get($modelData, 'rating_e', $review->rating_e),
                'title'                => data_get($modelData, 'title', $review->title),
                'message'              => data_get($modelData, 'message', $review->message),
                'show_after'           => data_get($modelData, 'show_after', $review->show_after),
                'order_id'             => data_get($modelData, 'order_id', $review->order_id),
                'like_count'           => data_get($modelData, 'like_count', $review->like_count),
                'meta'                 => data_get($modelData, 'meta', $review->meta ?? []),
            ]);

            if (is_array($images)) {
                $this->storeUploadedImages($review, $images);
            }

            if (is_array($videos)) {
                $this->storeUploadedVideos($review, $videos);
            }

            return $review->refresh()->load('media');
        });

        $this->reviewHydrators($updatedReview);

        return $updatedReview;
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $review = $this->resolveReview((string) $request->validated('reviewable_type'), (int) $request->route('review'));

        $modelData = $request->validated();
        if ($this->isCustomerRequest($request)) {
            abort_unless(auth('retina')->check(), 401);

            $customerId = auth('retina')->user()?->customer_id;
            abort_unless(is_numeric($customerId), 403);

            abort_unless((int) $review->customer_id === (int) $customerId, 403);

            $modelData['customer_id'] = (int) $customerId;
            $modelData['status'] = ReviewStatusEnum::Pending->value;
        }

        $updatedReview = $this->handle($review, $modelData);

        return response()->json([
            'status' => 'success',
            'data'   => $updatedReview,
        ]);
    }

    public function rules(): array
    {
        return [
            'reviewable_type'        => ['required', Rule::in(['Product', 'ProductCategory', 'Shop', 'product_reviews', 'product_category_reviews', 'shop_reviews'])],
            'customer_id'           => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'status'                => ['sometimes', Rule::enum(ReviewStatusEnum::class)],
            'rating'                => ['sometimes', 'integer', 'min:1', 'max:5'],
            'rating_main'           => ['sometimes', 'numeric', 'min:1', 'max:5'],
            'rating_a'              => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_b'              => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_c'              => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_d'              => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_e'              => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'title'                 => ['sometimes', 'nullable', 'string', 'max:255'],
            'message'               => ['sometimes', 'nullable', 'string', 'max:5000'],
            'show_after'            => ['sometimes', 'nullable', 'date'],
            'order_id'              => ['sometimes', 'nullable', 'integer', 'exists:orders,id'],
            'like_count'            => ['sometimes', 'integer', 'min:0'],
            'meta'                  => ['sometimes', 'array'],
            'images'                => ['sometimes', 'array'],
            'images.*'              => ['sometimes', File::image()->max(50 * 1024)],
            'videos'                => ['sometimes', 'array'],
            'videos.*'              => ['sometimes', File::types(['mp4', 'webm'])->max(50 * 1024)],
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

    private function resolveReview(string $reviewableType, int $reviewId): ProductReview|ProductCategoryReview|ShopReview
    {
        return match ($reviewableType) {
            'Product', 'product_reviews' => ProductReview::query()->findOrFail($reviewId),
            'ProductCategory', 'product_category_reviews' => ProductCategoryReview::query()->findOrFail($reviewId),
            'Shop', 'shop_reviews' => ShopReview::query()->findOrFail($reviewId),
        };
    }

    private function isCustomerRequest(ActionRequest $request): bool
    {
        return $request->routeIs('iris.models.review.*', 'retina.models.review.*');
    }
}
