<?php

namespace App\Actions\Reviews\Iris\Traits;

use App\Enums\Catalogue\Review\ReviewReactionTargetEnum;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\WebUser;
use App\Models\Reviews\Review;
use App\Services\CustomSort\RandomSort;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

trait WithGetIrisReviewsTrait
{
    public function getBaseQuery(Product|ProductCategory|Shop $parent): QueryBuilder
    {
        $scopes = [];
        if ($parent instanceof Shop) {
            $shop = $parent;
            $scopes = [ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER, ReviewScopeEnum::PRODUCT, ReviewScopeEnum::FAMILY];
            $setting = Arr::get($shop->settings, 'reviews.validation_scope.shop', []);
        } else {
            $shop = $parent->shop;

            if ($parent instanceof Product) {
                $scopes = [ReviewScopeEnum::PRODUCT];
                $setting = Arr::get($shop->settings, 'reviews.validation_scope.product', []);
            } elseif ($parent instanceof ProductCategory) {
                $scopes = [ReviewScopeEnum::FAMILY];
                $setting = Arr::get($shop->settings, 'reviews.validation_scope.family', []);
            }
        }

        $minRating = Arr::get($shop->settings, 'reviews.minimum_rating_to_show', 3);

        $queryBuilder = QueryBuilder::for(Review::class);

        if (Arr::get($setting, 'enabled', false)) {
            if (Arr::get($setting, 'scope') == 'group') {
                $queryBuilder->where('reviews.group_id', $parent->group_id);
            } else {
                $queryBuilder->where('reviews.organisation_id', $parent->organisation_id);
            }

            if ($parent instanceof Product) {
                $queryBuilder->where('reviews.master_product_id', $parent->master_product_id);
            } elseif ($parent instanceof ProductCategory) {
                $queryBuilder->where('reviews.master_product_category_id', $parent->master_product_category_id);
            }
        } else {
            $queryBuilder->where('reviews.shop_id', $shop->id);

            if ($parent instanceof Product) {
                $queryBuilder->where('reviews.product_id', $parent->id);
            } elseif ($parent instanceof ProductCategory) {
                $queryBuilder->where('reviews.product_category_id', $parent->id);
            }
        }

        return $queryBuilder->where('reviews.rating_main', '>=', $minRating)
            ->where('reviews.state', ReviewStateEnum::PUBLISHED)
            ->where('reviews.is_public', true)
            ->where('reviews.review_status', ReviewStatusEnum::APPROVED)
            ->whereIn('reviews.scope', $scopes);
    }

    public function getIrisReviews(Product|ProductCategory|Shop $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $shop = $parent instanceof Shop ? $parent : $parent->shop;

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('reviews.message', $value);
            });
        });

        $queryBuilder = $this->getBaseQuery($parent);

        $allowedSort = [
            'rating_main',
        ];

        $select = [
            'reviews.id',
            'customers.contact_name',
            'customers.location',
            'reviews.rating_main',
            'reviews.message',
            'reviews.translations',
            'reviews.published_at',
            'reviews.web_images',
            'reviews.likes',
            'reviews.dislikes',
            'reviews.replay_likes',
            'reviews.replay_dislikes',
            'reviews.reply_message as reply',
            'reply_users.contact_name as reply_by',
            'reviews.created_at',
            DB::raw("'$shop->language_id' as language_id")
        ];

        if (auth()->check()) {
            /** @var WebUser $webUser */
            $webUser = auth()->user();
            if ($webUser->customer) {
                $select[] = 'review_reactions.type as review_reaction';
                $select[] = 'reply_reactions.type as reply_reaction';

                $queryBuilder
                    ->leftJoin('review_reactions', function ($join) use ($webUser) {
                        $join->on('review_reactions.review_id', 'reviews.id')
                            ->where('review_reactions.customer_id', $webUser->customer->id)
                            ->where('review_reactions.target', ReviewReactionTargetEnum::REVIEW);
                    })
                    ->leftJoin('review_reactions as reply_reactions', function ($join) use ($webUser) {
                        $join->on('reply_reactions.review_id', 'reviews.id')
                            ->where('reply_reactions.customer_id', $webUser->customer->id)
                            ->where('reply_reactions.target', ReviewReactionTargetEnum::REVIEW_REPLY);
                    });
            }
        }

        $queryBuilder
            ->leftJoin('customers', 'customers.id', '=', 'reviews.customer_id')
            ->leftJoin('users as reply_users', 'reviews.reply_by', '=', 'reply_users.id')
            ->select($select);

        // if ($parent instanceof Shop) {
        //     $randomSort = AllowedSort::custom('random', new RandomSort());
        //     array_push($allowedSort, $randomSort);
        //     $queryBuilder
        //         ->defaultSort($randomSort);

        // } else {
            $queryBuilder
                ->defaultSort('-created_at');
        // }

        return $queryBuilder
            ->allowedSorts($allowedSort)
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }
}
