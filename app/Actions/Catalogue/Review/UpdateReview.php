<?php

namespace App\Actions\Catalogue\Review;

use App\Actions\Catalogue\Review\Traits\HasReviewHydrators;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Models\Reviews\Review;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateReview extends OrgAction
{
    use HasReviewHydrators;

    public function prepareForValidation(ActionRequest $request): void
    {
        $nullableKeys = ['rating_a', 'rating_b', 'rating_c', 'rating_d', 'rating_e', 'customer_id'];

        $updates = [];
        foreach ($nullableKeys as $key) {
            if (!$request->has($key)) {
                continue;
            }

            $value = $request->input($key);
            if ($value === '' || $value === 'null') {
                $updates[$key] = null;
            }
        }

        if ($updates !== []) {
            $request->merge($updates);
        }
    }

    /**
     * @throws \Throwable
     */
    public function handle(Review $review, array $modelData): Review
    {
        $updatedReview = DB::transaction(function () use ($review, $modelData) {
            $images = Arr::pull($modelData, 'images');
            $videos = Arr::pull($modelData, 'videos');

            $ratingMain = $this->resolveRatingMain($review, $modelData);
            $review->update([
                'customer_id'          => data_get($modelData, 'customer_id', $review->customer_id),
                'status'               => data_get($modelData, 'status', $review->status?->value),
                'rating_main'          => $ratingMain,
                'rating_a'             => data_get($modelData, 'rating_a', $review->rating_a),
                'rating_b'             => data_get($modelData, 'rating_b', $review->rating_b),
                'rating_c'             => data_get($modelData, 'rating_c', $review->rating_c),
                'rating_d'             => data_get($modelData, 'rating_d', $review->rating_d),
                'rating_e'             => data_get($modelData, 'rating_e', $review->rating_e),
                'title'                => data_get($modelData, 'title', $review->title),
                'message'              => data_get($modelData, 'message', $review->message),
                'show_after'           => data_get($modelData, 'show_after', $review->show_after),
                'is_public'            => data_get($modelData, 'is_public', $review->is_public),
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

    /**
     * @throws \Throwable
     */
    public function asController(Review $review, ActionRequest $request): Review
    {
        $this->initialisationFromShop($review->shop, $request);

        return $this->handle($review, $this->validatedData);
    }

    public function jsonResponse(Review $review): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $review->load('media'),
        ]);
    }

    public function htmlResponse(Review $review, ActionRequest $request): RedirectResponse
    {
        $request->route()?->getName();

        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Review updated successfully.'),
        ]);
    }

    public function rules(): array
    {
        return [
            'reviewable_type'        => ['required', Rule::in(['Product', 'ProductCategory', 'Shop', 'product_reviews', 'product_category_reviews', 'shop_reviews'])],
            'customer_id'           => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'status'                => ['sometimes', Rule::enum(ReviewStatusEnum::class)],
            'rating'                => ['sometimes', 'numeric', 'min:1', 'max:5'],
            'rating_a'              => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_b'              => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_c'              => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_d'              => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_e'              => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'title'                 => ['sometimes', 'nullable', 'string', 'max:255'],
            'message'               => ['sometimes', 'nullable', 'string', 'max:5000'],
            'show_after'            => ['sometimes', 'nullable', 'date'],
            'is_public'             => ['sometimes', 'boolean'],
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



    private function resolveRatingMain(Review $review, array $modelData): float
    {
        $dimensionKeys = ['rating_a', 'rating_b', 'rating_c', 'rating_d', 'rating_e'];
        $dimensionsWereProvided = collect($dimensionKeys)->contains(fn (string $key): bool => array_key_exists($key, $modelData));

        $rating = data_get($modelData, 'rating');
        $ratingWasProvided = array_key_exists('rating', $modelData) && is_numeric($rating);

        if (!$dimensionsWereProvided && !$ratingWasProvided) {
            return (float) ($review->rating_main ?? 0);
        }

        $detailedRatings = collect($dimensionKeys)
            ->map(fn (string $key) => data_get($modelData, $key))
            ->filter(fn ($value): bool => is_numeric($value))
            ->map(fn ($value): float => (float) $value)
            ->values();

        if ($detailedRatings->isNotEmpty()) {
            return round((float) $detailedRatings->avg(), 2);
        }

        if ($ratingWasProvided) {
            return round((float) $rating, 2);
        }

        return (float) ($review->rating_main ?? 0);
    }
}
