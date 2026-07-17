<?php

namespace App\Http\Resources\Catalogue;

use App\Enums\Catalogue\Review\ReviewContextEnum;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Reviews\ReviewRatingLabel;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $replied
 */
class ReviewsResource extends JsonResource
{
    public static function ratingLabelsFor(ProductCategory|Product|Shop $reviewable): array
    {
        return self::getRatingLabels($reviewable);
    }

    public function toArray($request): array
    {
        $canManage = $request->routeIs('grp.*');
        $hasReply  = (bool)$this->replied;

        return [
            'id'               => $this->id,
            'customer_id'      => $this->customer_id,
            'scope'            => $this->scope,
            'product_code'     => $this->product_code ?? null,
            'family_code'     => $this->family_code ?? null,
            'customer_name'    => $this->resolveCustomerName(),
            'customer_route'   => ($canManage && $this->customer_id && $this->customer_slug) ? [
                'name'       => 'grp.org.shops.show.crm.customers.show',
                'parameters' => [
                    'organisation' => $request->route('organisation')->slug,
                    'shop'         => $request->route('shop')->slug,
                    'customer'     => $this->customer_slug,
                ],
            ] : null,
            'status'           => $this->status,
            'state_icon'       => [
                'icon'    => 'fal fa-broadcast-tower',
                'tooltip' => $this->state === ReviewStateEnum::PUBLISHED ? __('Published') : __('Not published'),
                'class'   => $this->state === ReviewStateEnum::PUBLISHED ? 'text-green-500' : 'text-gray-400',
            ],
            'rating'           => (int)round((float)($this->rating_main ?? $this->rating ?? 0)),
            'rating_a'         => $this->rating_a !== null ? (int)$this->rating_a : null,
            'rating_b'         => $this->rating_b !== null ? (int)$this->rating_b : null,
            'rating_c'         => $this->rating_c !== null ? (int)$this->rating_c : null,
            'rating_d'         => $this->rating_d !== null ? (int)$this->rating_d : null,
            'rating_e'         => $this->rating_e !== null ? (int)$this->rating_e : null,
            'message'          => $this->message,
            'likes'            => (int)$this->likes,
            'dislikes'         => (int)$this->dislikes,
            'has_reply'        => $hasReply,
            'reply_status'     => $hasReply ? 'Yes' : 'No',
            'existing_reply'   => $hasReply ? [
                'id'         => $this->id,
                'body'       => $this->reply_message,
                'created_at' => $this->reply_at,
                'updated_at' => $this->reply_at,
            ] : null,
            'update_route'     => $canManage ? [
                'name'       => 'grp.models.review.update',
                'parameters' => [
                    'review'          => $this->id,
                    'reviewable_type' => $this->getTable(),
                ],
                'method'     => 'patch',
            ] : null,
            'approve_route'    => ($canManage && $this->status !== ReviewStatusEnum::APPROVED->value) ? [
                'name'       => 'grp.models.review.approve',
                'parameters' => [
                    'review' => $this->id,
                ],
                'method'     => 'patch',
            ] : null,
            'reject_route'     => ($canManage && $this->status !== ReviewStatusEnum::REJECTED->value) ? [
                'name'       => 'grp.models.review.reject',
                'parameters' => [
                    'review' => $this->id,
                ],
                'method'     => 'patch',
            ] : null,
            'delete_route'     => $canManage ? [
                'name'       => 'grp.models.review.delete',
                'parameters' => [
                    'review'          => $this->id,
                    'reviewable_type' => $this->getTable(),
                ],
                'method'     => 'delete',
            ] : null,
            'created_at'       => $this->created_at,
        ];
    }

    private function resolveCustomerName(): ?string
    {
        if (!empty($this->customer_name)) {
            return $this->customer_name;
        }

        if ($this->relationLoaded('customer') && $this->customer) {
            $contactName = trim((string)$this->customer->contact_name);

            return $contactName !== '' ? $contactName : $this->customer->name;
        }

        return null;
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
                'dimension'   => $reviewRatingLabel->dimension?->value ?? (string)$reviewRatingLabel->dimension,
                'label'       => (string)$reviewRatingLabel->label,
                'is_required' => (bool)$reviewRatingLabel->is_required,
                'weight'      => (float)$reviewRatingLabel->weight,
            ])
            ->values()
            ->all();
    }

    public static function paginateReviewCustomers(ProductCategory|Product|Shop $reviewable, int $page = 1, int $perPage = 20, ?string $search = null): array
    {
        $baseQuery = Customer::query()
            ->join('web_users', 'web_users.customer_id', '=', 'customers.id')
            ->where('web_users.shop_id', self::shopId($reviewable))
            ->selectRaw(
                "
                customers.id as customer_id,
                COALESCE(NULLIF(MAX(customers.contact_name), ''), MAX(customers.name), MIN(web_users.username)) as label,
                MIN(web_users.contact_name) as contact_name,
                MIN(web_users.username) as username,
                MIN(web_users.email) as email
            "
            )
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
        $rows  = (clone $baseQuery)->forPage($page, $perPage)->get();

        $items = $rows
            ->map(fn ($item): array => [
                'customer_id'  => (int)data_get($item, 'customer_id'),
                'label'        => (string)data_get($item, 'label'),
                'contact_name' => data_get($item, 'contact_name'),
                'username'     => data_get($item, 'username'),
                'email'        => data_get($item, 'email'),
            ])
            ->values()
            ->all();

        $hasMore = ($page * $perPage) < $total;

        return [
            'data'  => $items,
            'meta'  => [
                'current_page' => (int)$page,
                'per_page'     => (int)$perPage,
                'next_page'    => $hasMore ? (int)$page + 1 : null,
                'has_more'     => $hasMore,
            ],
            'links' => [
                'next' => $hasMore
                    ? route(self::reviewCustomersRouteName($reviewable), [
                        self::reviewCustomersRouteParamKey($reviewable) => $reviewable->id,
                        'page'                                          => (int)$page + 1,
                        'per_page'                                      => (int)$perPage,
                        'filter'                                        => ['global' => $search],
                    ])
                    : null,
            ],
        ];
    }

    private static function reviewContext(ProductCategory|Product|Shop $reviewable): ReviewContextEnum
    {
        if ($reviewable instanceof Product) {
            return ReviewContextEnum::PRODUCT;
        }

        if ($reviewable instanceof Shop) {
            return ReviewContextEnum::ORDER;
        }

        return ReviewContextEnum::FAMILY;
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
