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

        $storeRoute = [
            'name' => 'retina.models.review.store',
            'parameters' => [],
            'method' => 'post',
        ];

        $buildUpdateRoute = fn (?int $reviewId): ?array => $reviewId
            ? [
                'name' => 'retina.models.review.update',
                'parameters' => [
                    'review' => $reviewId,
                ],
                'method' => 'patch',
            ]
            : null;

        $buildReviewPayload = fn (string $reviewableType, int $reviewableId): array => [
            'reviewable_type' => $reviewableType,
            'reviewable_id' => $reviewableId,
            'order_id' => $orderId,
        ];

        $media = null;
        if ($this->product_image_id) {
            $media = Media::find($this->product_image_id);
        }

        return [
            'id' => $productId,
            'image' => $media ? ImageResource::make($media)->getArray() : null,
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
            'actions' => [
                'product' => [
                    'store_route' => $storeRoute,
                    'update_route' => $buildUpdateRoute($this->product_review_id ? (int) $this->product_review_id : null),
                    'payload' => $buildReviewPayload('Product', $productId),
                ],
                'product_category' => [
                    'store_route' => $storeRoute,
                    'update_route' => $buildUpdateRoute($this->product_category_review_id ? (int) $this->product_category_review_id : null),
                    'payload' => $productCategoryId > 0 ? $buildReviewPayload('ProductCategory', $productCategoryId) : null,
                ],
                'shop' => [
                    'store_route' => $storeRoute,
                    'update_route' => $buildUpdateRoute($this->shop_review_id ? (int) $this->shop_review_id : null),
                    'payload' => $shopId > 0 ? $buildReviewPayload('Shop', $shopId) : null,
                ],
            ],
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
                    'store_route' => $storeRoute,
                    'update_route' => $buildUpdateRoute($this->product_review_id ? (int) $this->product_review_id : null),
                    'payload' => $buildReviewPayload('Product', $productId),
                ],
                'product_category' => [
                    'review_id' => $this->product_category_review_id ? (int) $this->product_category_review_id : null,
                    'status' => $this->product_category_review_status,
                    'rating' => $this->product_category_review_rating !== null ? (float) $this->product_category_review_rating : null,
                    'rating_a' => $this->product_category_review_rating_a !== null ? (int) $this->product_category_review_rating_a : null,
                    'rating_b' => $this->product_category_review_rating_b !== null ? (int) $this->product_category_review_rating_b : null,
                    'rating_c' => $this->product_category_review_rating_c !== null ? (int) $this->product_category_review_rating_c : null,
                    'rating_d' => $this->product_category_review_rating_d !== null ? (int) $this->product_category_review_rating_d : null,
                    'rating_e' => $this->product_category_review_rating_e !== null ? (int) $this->product_category_review_rating_e : null,
                    'message' => $this->product_category_review_message,
                    'store_route' => $storeRoute,
                    'update_route' => $buildUpdateRoute($this->product_category_review_id ? (int) $this->product_category_review_id : null),
                    'payload' => $productCategoryId > 0 ? $buildReviewPayload('ProductCategory', $productCategoryId) : null,
                ],
                'shop' => [
                    'review_id' => $this->shop_review_id ? (int) $this->shop_review_id : null,
                    'status' => $this->shop_review_status,
                    'rating' => $this->shop_review_rating !== null ? (float) $this->shop_review_rating : null,
                    'rating_a' => $this->shop_review_rating_a !== null ? (int) $this->shop_review_rating_a : null,
                    'rating_b' => $this->shop_review_rating_b !== null ? (int) $this->shop_review_rating_b : null,
                    'rating_c' => $this->shop_review_rating_c !== null ? (int) $this->shop_review_rating_c : null,
                    'rating_d' => $this->shop_review_rating_d !== null ? (int) $this->shop_review_rating_d : null,
                    'rating_e' => $this->shop_review_rating_e !== null ? (int) $this->shop_review_rating_e : null,
                    'message' => $this->shop_review_message,
                    'store_route' => $storeRoute,
                    'update_route' => $buildUpdateRoute($this->shop_review_id ? (int) $this->shop_review_id : null),
                    'payload' => $shopId > 0 ? $buildReviewPayload('Shop', $shopId) : null,
                ],
            ],
        ];
    }
}
