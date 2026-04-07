<?php

namespace App\Http\Resources\Catalogue;

use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\ReviewableRatingStat;
use App\Models\CRM\WebUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewsResource extends JsonResource
{
    public static function collectionWithTabMeta(LengthAwarePaginator $reviews, ProductCategory $family): AnonymousResourceCollection
    {
        return self::collection($reviews)->additional([
            'stats'     => self::getStats($family),
            'customers' => self::getReviewCustomers($family),
        ]);
    }

    public function toArray($request): array
    {
        $contactName = $this->contact_name ?? $this->customer?->contact_name ?? $this->customer_name ?? $this->customer?->name;
        $imageThumbnails = $this->relationLoaded('media')
            ? $this->media
                ->sortBy('sort_order')
                ->map(fn ($media) => $media->imageSources(48, 48, 'media'))
                ->filter()
                ->values()
                ->all()
            : [];

        return [
            'id'                   => $this->id,
            'customer_id'          => $this->customer_id,
            'contact_name'         => $contactName,
            'customer_name'        => $contactName,
            'status'               => $this->status?->value ?? $this->status,
            'rating'               => (int) $this->rating,
            'message'              => $this->message,
            'like_count'           => (int) $this->like_count,
            'image_thumbnail'      => $imageThumbnails[0] ?? null,
            'image_thumbnails'     => $imageThumbnails,
            'update_route'         => [
                'name'       => 'grp.models.review.update',
                'parameters' => ['review' => $this->id],
                'method'     => 'patch',
            ],
            'delete_route'         => [
                'name'       => 'grp.models.review.delete',
                'parameters' => ['review' => $this->id],
                'method'     => 'delete',
            ],
            'created_at'           => $this->created_at,
        ];
    }

    private static function getStats(ProductCategory $family): array
    {
        $reviewableStat = ReviewableRatingStat::query()
            ->where('reviewable_type', $family->getMorphClass())
            ->where('reviewable_id', $family->id)
            ->first();

        return [
            'total'                   => (int) ($reviewableStat?->reviews_count ?? 0),
            'average_rating'          => (float) ($reviewableStat?->rating_average ?? 0),
            'verified'                => (int) ($reviewableStat?->verified_reviews_count ?? 0),
            'like_count'              => (int) ($reviewableStat?->number_reviews_like ?? 0),
            'status_approved'         => (int) ($reviewableStat?->number_reviews_state_approved ?? 0),
            'status_pending'          => (int) ($reviewableStat?->number_reviews_state_pending ?? 0),
            'status_rejected'         => (int) ($reviewableStat?->number_reviews_state_rejected ?? 0),
            'number_reviews_rating_1' => (int) ($reviewableStat?->number_reviews_rating_1 ?? 0),
            'number_reviews_rating_2' => (int) ($reviewableStat?->number_reviews_rating_2 ?? 0),
            'number_reviews_rating_3' => (int) ($reviewableStat?->number_reviews_rating_3 ?? 0),
            'number_reviews_rating_4' => (int) ($reviewableStat?->number_reviews_rating_4 ?? 0),
            'number_reviews_rating_5' => (int) ($reviewableStat?->number_reviews_rating_5 ?? 0),
        ];
    }

    private static function getReviewCustomers(ProductCategory $family): array
    {
        return self::paginateReviewCustomers($family, 1, 20);
    }

    public static function paginateReviewCustomers(ProductCategory $family, int $page = 1, int $perPage = 20, ?string $search = null): array
    {
        $baseQuery = WebUser::query()
            ->leftJoin('customers', 'customers.id', '=', 'web_users.customer_id')
            ->where('web_users.shop_id', $family->shop_id)
            ->whereNotNull('web_users.customer_id')
            ->selectRaw("
                web_users.customer_id as customer_id,
                COALESCE(MAX(customers.name), NULLIF(MIN(web_users.contact_name), ''), MIN(web_users.username)) as label,
                MIN(web_users.contact_name) as contact_name,
                MIN(web_users.username) as username,
                MIN(web_users.email) as email
            ")
            ->groupBy('web_users.customer_id')
            ->orderBy('label')
            ->orderBy('web_users.customer_id');

        if ($search) {
            $baseQuery->where(function ($query) use ($search) {
                $query
                    ->where('customers.name', 'ilike', "%$search%")
                    ->orWhere('web_users.contact_name', 'ilike', "%$search%")
                    ->orWhere('web_users.username', 'ilike', "%$search%");
            });
        }

        $total = (clone $baseQuery)->getQuery()->getCountForPagination();
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
                    ? route('grp.models.review.customers', [
                        'productCategory' => $family->id,
                        'page' => (int) $page + 1,
                        'per_page' => (int) $perPage,
                        'filter' => ['global' => $search],
                    ])
                    : null,
            ],
        ];
    }
}
