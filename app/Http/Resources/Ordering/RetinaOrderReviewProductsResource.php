<?php

namespace App\Http\Resources\Ordering;

use App\Http\Resources\Helpers\ImageResource;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;

class RetinaOrderReviewProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        $orderId = (int) ($this->order_id ?? 0);
        $shopId = (int) ($this->shop_id ?? 0);
        $productId = (int) ($this->product_id ?? $this->id ?? 0);
        $productCategoryId = (int) ($this->product_category_id ?? 0);

        $buildReviewPayload = fn (string $reviewableType, int $reviewableId): array => [
            'reviewable_type' => $reviewableType,
            'reviewable_id' => $reviewableId,
            'order_id' => $orderId,
        ];

        $imageData = is_string($this->product_image_data)
            ? json_decode($this->product_image_data, true)
            : (array) ($this->product_image_data ?? []);
        $productMedia = $imageData ? Media::hydrate([$imageData])->first() : null;

        $reviewMediaData = is_string($this->product_review_media_data)
            ? json_decode($this->product_review_media_data, true)
            : ($this->product_review_media_data ?? []);
        $reviewImages = $reviewMediaData
            ? Media::hydrate($reviewMediaData)->map(fn ($m) => ImageResource::make($m)->getArray())->values()->all()
            : [];

        return [
            'id' => $productId,
            'image' => $productMedia ? ImageResource::make($productMedia)->getArray() : null,
            'asset_code' => $this->asset_code ?? $this->product_code,
            'asset_name' => $this->asset_name ?? $this->product_name,
            'product_id' => $productId,
            'product_slug' => $this->product_slug,
            'product_code' => $this->product_code,
            'product_name' => $this->product_name,
            'product_category_id' => $productCategoryId ?: null,
            'product_category_name' => $this->product_category_name,
            'quantity_ordered' => $this->quantity_ordered !== null ? (float) $this->quantity_ordered : 0,
            'product_review_id' => $this->product_review_id ? (int) $this->product_review_id : null,
            'product_review_rating' => $this->product_review_rating !== null ? (float) $this->product_review_rating : null,
            'product_review_status' => $this->product_review_status,
            /* 'payload' => $buildReviewPayload('Product', $productId), */
            'reviews' => [
                'product' => [
                    'review_id' => $this->product_review_id ? (int) $this->product_review_id : null,
                    'status' => $this->product_review_status,
                    'rating' => $this->product_review_rating !== null ? (float) $this->product_review_rating : null,
                    'rating_a' => $this->product_review_rating_a !== null ? (int) $this->product_review_rating_a : null,
                    'rating_b' => $this->product_review_rating_b !== null ? (int) $this->product_review_rating_b : null,
                    'rating_c' => $this->product_review_rating_c !== null ? (int) $this->product_review_rating_c : null,
                    'rating_d' => $this->product_review_rating_d !== null ? (int) $this->product_review_rating_d : null,
                    'rating_e' => $this->product_review_rating_e !== null ? (int) $this->product_review_rating_e : null,
                    'message' => $this->product_review_message,
                    'review_images' => $reviewImages,
                    ...$buildReviewPayload('Product', $productId)
                ],
            ],
        ];
    }
}
