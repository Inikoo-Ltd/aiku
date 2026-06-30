<?php

namespace App\Actions\Catalogue\Review\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Review\ReviewReactionTargetEnum;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Reviews\Review;
use App\Services\QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\AllowedFilter;

class IndexReviewsInIris extends OrgAction
{
    public function handle(Shop|ProductCategory|Product $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customers.contact_name', $value);
            });
        });

        $query = QueryBuilder::for(Review::class)
            ->select([
                'reviews.id',
                'reviews.rating_main',
                'customers.contact_name',
                'reviews.message',
                'reviews.published_at',
                'reviews.likes',
                'reviews.dislikes',
                'reviews.web_images',
                // 'review_reactions.type as review_reaction', // Like/Dislike
            ])
            ->leftJoin('customers', 'customers.id', 'reviews.customer_id');
            // ->leftJoin('review_reactions', function ($join) use ($customer) {
            //     $join->on('review_reactions.review_id', 'reviews.id')
            //         ->where('review_reactions.customer_id', $customer->id)
            //         ->where('review_reactions.target', ReviewReactionTargetEnum::REVIEW);
            // });

        $query = $this->whereQuery($parent, $query);

        return $query
            ->allowedSorts(['id', 'created_at', 'rating', 'likes'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    private function whereQuery(Shop|ProductCategory|Product $parent, Builder $query): Builder
    {
        $shop = $parent;
        if ($parent instanceof Product) {
            $shop = $parent->shop;
            $query->where('reviews.scope', ReviewScopeEnum::PRODUCT)
                ->where('reviews.product_id', $parent->id);
        } elseif ($parent instanceof ProductCategory) {
            $shop = $parent->shop;
            $query->where('reviews.scope', ReviewScopeEnum::FAMILY)
                ->where('reviews.product_id', $parent->id);
        } else {
            $query->whereIn('reviews.scope', [ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER]);
        }

        $minRating = Arr::get($shop->settings, 'reviews.minimum_rating_to_show', 3);

        return $query
            ->where('reviews.shop_id', $shop->id)
            ->where('reviews.rating_main', '>=', $minRating)
            ->where('reviews.state', ReviewStateEnum::PUBLISHED)
            ->where('reviews.is_public', true)
            ->where('reviews.review_status', ReviewStatusEnum::APPROVED);
    }

    public function avgReview(Shop|ProductCategory|Product $parent): String
    {
        $query = Review::query();

        return $this->whereQuery($parent, $query)
            ->avg('rating_main');
    }
}
