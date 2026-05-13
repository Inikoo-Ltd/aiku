<?php

namespace App\Http\Resources\Catalogue;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Enums\Catalogue\Review\ReviewContextEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Reviews\ReviewRatingLabel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewsResource extends JsonResource
{
    public static function collectionWithTabMeta(LengthAwarePaginator $reviews, ProductCategory|Product|Shop $reviewable): AnonymousResourceCollection
    {
        $ratingLabels = self::getRatingLabels($reviewable);

        return self::collection($reviews)->additional([
            'stats'     => self::getStats($reviewable, $ratingLabels),
            'customers' => self::getReviewCustomers($reviewable),
            'rating_labels' => $ratingLabels,
        ]);
    }

    public static function ratingLabelsFor(ProductCategory|Product|Shop $reviewable): array
    {
        return self::getRatingLabels($reviewable);
    }

    public function toArray($request): array
    {
        $contactName = $this->contact_name ?? $this->customer?->contact_name ?? $this->customer_name ?? $this->customer?->name;
        $canManage = $request->routeIs('grp.*');
        $merchantReply = null;
        if ($this->relationLoaded('replies')) {
            $merchantReply = $this->replies->first(function ($reply) {
                $replierType = $reply->replier_type?->value ?? $reply->replier_type;
                return $replierType === 'merchant';
            });
        }
        $imageThumbnails = $this->relationLoaded('media')
            ? $this->media
                ->sortBy('order_column')
                ->map(fn ($media) => GetPictureSources::run($media->getImage()->resize(48, 48)))
                ->filter()
                ->values()
                ->all()
            : [];
        $imageGallery = $this->relationLoaded('media')
            ? $this->media
                ->sortBy('order_column')
                ->map(fn ($media) => GetPictureSources::run($media->getImage()))
                ->filter()
                ->values()
                ->all()
            : [];

        return [
            'id'                   => $this->id,
            'customer_id'          => $this->customer_id,
            'reviewable_type'      => $this->getTable(),
            'contact_name'         => $contactName,
            'customer_name'        => $contactName,
            'status'               => $this->status?->value ?? $this->status,
            'rating'               => (int) round((float) ($this->rating_main ?? $this->rating ?? 0)),
            'rating_a'             => $this->rating_a !== null ? (int) $this->rating_a : null,
            'rating_b'             => $this->rating_b !== null ? (int) $this->rating_b : null,
            'rating_c'             => $this->rating_c !== null ? (int) $this->rating_c : null,
            'rating_d'             => $this->rating_d !== null ? (int) $this->rating_d : null,
            'rating_e'             => $this->rating_e !== null ? (int) $this->rating_e : null,
            'message'              => $this->message,
            'like_count'           => (int) $this->like_count,
            'image_thumbnail'      => $imageThumbnails[0] ?? null,
            'image_thumbnails'     => $imageThumbnails,
            'image_gallery'        => $imageGallery,
            'has_reply'            => (bool) $merchantReply,
            'reply_status'         => $merchantReply ? 'Yes' : 'No',
            'existing_reply'       => $merchantReply ? [
                'id' => $merchantReply->id,
                'body' => $merchantReply->body,
                'is_public' => (bool) $merchantReply->is_public,
                'status' => $merchantReply->status?->value ?? $merchantReply->status,
                'replier_type' => $merchantReply->replier_type?->value ?? $merchantReply->replier_type,
                'created_at' => $merchantReply->created_at,
                'updated_at' => $merchantReply->updated_at,
            ] : null,
            'update_route'         => $canManage ? [
                'name'       => 'grp.models.review.update',
                'parameters' => [
                    'review' => $this->id,
                    'reviewable_type' => $this->getTable(),
                ],
                'method'     => 'patch',
            ] : null,
            'delete_route'         => $canManage ? [
                'name'       => 'grp.models.review.delete',
                'parameters' => [
                    'review' => $this->id,
                    'reviewable_type' => $this->getTable(),
                ],
                'method'     => 'delete',
            ] : null,
            'created_at'           => $this->created_at,
        ];
    }

    private static function getStats(ProductCategory|Product|Shop $reviewable, array $ratingLabels = []): array
    {
        $reviewStat = $reviewable->reviewStats()->first();

        $averageByDimension = [
            'a' => round((float) ($reviewStat?->average_rating_a ?? 0), 2),
            'b' => round((float) ($reviewStat?->average_rating_b ?? 0), 2),
            'c' => round((float) ($reviewStat?->average_rating_c ?? 0), 2),
            'd' => round((float) ($reviewStat?->average_rating_d ?? 0), 2),
            'e' => round((float) ($reviewStat?->average_rating_e ?? 0), 2),
        ];

        $categoryRatings = collect($ratingLabels)
            ->map(function (array $label) use ($averageByDimension): array {
                $dimension = strtolower((string) data_get($label, 'dimension'));

                return [
                    'dimension' => $dimension,
                    'label' => (string) data_get($label, 'label', strtoupper($dimension)),
                    'average' => (float) ($averageByDimension[$dimension] ?? 0),
                ];
            })
            ->filter(fn (array $item): bool => in_array($item['dimension'], ['a', 'b', 'c', 'd', 'e'], true))
            ->values()
            ->all();

        return [
            'total'                   => (int) ($reviewStat?->number_reviews ?? 0),
            'average_rating'          => (float) ($reviewStat?->average_rating_main ?? 0),
            'status_approved'         => (int) ($reviewStat?->number_reviews_approved ?? 0),
            'status_pending'          => (int) ($reviewStat?->number_reviews_pending ?? 0),
            'status_rejected'         => (int) ($reviewStat?->number_reviews_rejected ?? 0),
            'number_reviews_rating_1' => (int) ($reviewStat?->number_rating_1 ?? 0),
            'number_reviews_rating_2' => (int) ($reviewStat?->number_rating_2 ?? 0),
            'number_reviews_rating_3' => (int) ($reviewStat?->number_rating_3 ?? 0),
            'number_reviews_rating_4' => (int) ($reviewStat?->number_rating_4 ?? 0),
            'number_reviews_rating_5' => (int) ($reviewStat?->number_rating_5 ?? 0),
            'category_ratings'        => $categoryRatings,
        ];
    }

    private static function getReviewCustomers(ProductCategory|Product|Shop $reviewable): array
    {
        return self::paginateReviewCustomers($reviewable, 1, 20);
    }

    private static function getRatingLabels(ProductCategory|Product|Shop $reviewable): array
    {
        return ReviewRatingLabel::query()
            ->whereRaw('LOWER(model_type) = ?', ['shop'])
            ->where('model_id', self::shopId($reviewable))
            ->whereRaw('LOWER(review_context) = ?', [self::reviewContext($reviewable)->value])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('dimension')
            ->get(['dimension', 'label', 'is_required', 'weight'])
            ->map(fn (ReviewRatingLabel $reviewRatingLabel): array => [
                'dimension' => $reviewRatingLabel->dimension?->value ?? (string) $reviewRatingLabel->dimension,
                'label' => (string) $reviewRatingLabel->label,
                'is_required' => (bool) $reviewRatingLabel->is_required,
                'weight' => (float) $reviewRatingLabel->weight,
            ])
            ->values()
            ->all();
    }

    public static function paginateReviewCustomers(ProductCategory|Product|Shop $reviewable, int $page = 1, int $perPage = 20, ?string $search = null): array
    {
        $baseQuery = Customer::query()
            ->join('web_users', 'web_users.customer_id', '=', 'customers.id')
            ->where('web_users.shop_id', self::shopId($reviewable))
            ->selectRaw("
                customers.id as customer_id,
                COALESCE(NULLIF(MAX(customers.contact_name), ''), MAX(customers.name), MIN(web_users.username)) as label,
                MIN(web_users.contact_name) as contact_name,
                MIN(web_users.username) as username,
                MIN(web_users.email) as email
            ")
            ->groupBy('customers.id')
            ->orderBy('label')
            ->orderBy('customers.id');

        if ($search) {
            $baseQuery->where(function ($query) use ($search) {
                $query
                    ->where('customers.name', 'ilike', "%$search%")
                    ->orWhere('customers.contact_name', 'ilike', "%$search%")
                    ->orWhere('web_users.contact_name', 'ilike', "%$search%")
                    ->orWhere('web_users.username', 'ilike', "%$search%");
            });
        }

        $total = (clone $baseQuery)->toBase()->getCountForPagination();
        $rows = (clone $baseQuery)->forPage($page, $perPage)->get();

        $items = $rows
            ->map(fn ($item): array => [
                'customer_id' => (int) data_get($item, 'customer_id'),
                'label'       => (string) data_get($item, 'label'),
                'contact_name' => data_get($item, 'contact_name'),
                'username'    => data_get($item, 'username'),
                'email'       => data_get($item, 'email'),
            ])
            ->values()
            ->all();

        $hasMore = ($page * $perPage) < $total;

        return [
            'data' => $items,
            'meta' => [
                'current_page' => (int) $page,
                'per_page'     => (int) $perPage,
                'next_page'    => $hasMore ? (int) $page + 1 : null,
                'has_more'     => $hasMore,
            ],
            'links' => [
                'next' => $hasMore
                    ? route(self::reviewCustomersRouteName($reviewable), [
                        self::reviewCustomersRouteParamKey($reviewable) => $reviewable->id,
                        'page' => (int) $page + 1,
                        'per_page' => (int) $perPage,
                        'filter' => ['global' => $search],
                    ])
                    : null,
            ],
        ];
    }

    private static function reviewContext(ProductCategory|Product|Shop $reviewable): ReviewContextEnum
    {
        if ($reviewable instanceof Product) {
            return ReviewContextEnum::ProductReviews;
        }

        if ($reviewable instanceof Shop) {
            return ReviewContextEnum::ShopReviews;
        }

        return ReviewContextEnum::ProductCategoryReviews;
    }

    private static function reviewCustomersRouteName(ProductCategory|Product|Shop $reviewable): string
    {
        if ($reviewable instanceof Product) {
            return 'grp.models.review.customers.product';
        }

        if ($reviewable instanceof Shop) {
            return 'grp.models.review.customers.shop';
        }

        return 'grp.models.review.customers';
    }

    private static function reviewCustomersRouteParamKey(ProductCategory|Product|Shop $reviewable): string
    {
        if ($reviewable instanceof Product) {
            return 'product';
        }

        if ($reviewable instanceof Shop) {
            return 'shop';
        }

        return 'productCategory';
    }

    private static function shopId(ProductCategory|Product|Shop $reviewable): int
    {
        if ($reviewable instanceof Shop) {
            return $reviewable->id;
        }

        return $reviewable->shop_id;
    }
}
