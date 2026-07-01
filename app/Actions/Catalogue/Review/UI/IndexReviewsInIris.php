<?php

/*
 * Author Louis Perez
 * Created on 30-06-2026-13h-36m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

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
use Spatie\QueryBuilder\AllowedSort;

class IndexReviewsInIris extends OrgAction
{
    public function handle(Shop|ProductCategory|Product $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customers.contact_name', $value);
            });
        });

        $isLoggedIn = auth()->check();

        $select = [
                'reviews.id',
                'reviews.rating_main',
                'customers.contact_name',
                'reviews.message',
                'reviews.published_at',
                'reviews.likes',
                'reviews.dislikes',
                'reviews.replay_likes',
                'reviews.replay_dislikes',
                'reviews.web_images',
                'reviews.reply_message as reply',
                'reply_users.contact_name as reply_by',
            ];

        $query = QueryBuilder::for(Review::class)
            ->leftJoin('customers', 'customers.id', 'reviews.customer_id')
            ->leftJoin('users as reply_users', 'reviews.reply_by', '=', 'reply_users.id');
        if ($isLoggedIn) {
            $user = auth()->user();

            if ($user->customer) {
                array_push($select, 'review_reactions.type as review_reaction'); // Like/Dislike
                array_push($select, 'reply_reactions.type as reply_reaction'); // Like/Dislike Reply

                $query
                    ->leftJoin('review_reactions', function ($join) use ($user) {
                        $join->on('review_reactions.review_id', 'reviews.id')
                            ->where('review_reactions.customer_id', $user->customer?->id)
                            ->where('review_reactions.target', ReviewReactionTargetEnum::REVIEW);
                    })
                    ->leftJoin('review_reactions as reply_reactions', function ($join) use ($user) {
                        $join->on('reply_reactions.review_id', 'reviews.id')
                            ->where('reply_reactions.customer_id', $user->customer?->id)
                            ->where('reply_reactions.target', ReviewReactionTargetEnum::REVIEW_REPLY);
                    });
            }
        }

        $query = $this->whereQuery($parent, $query)
            ->select($select);

        return $query
            ->allowedSorts([
                'id',
                AllowedSort::field('recent', 'reviews.published_at'),
                AllowedSort::field('rating', 'reviews.rating_main'),
                AllowedSort::field('helpful', 'reviews.likes'),
            ])
            ->allowedFilters([
                $globalSearch,
                AllowedFilter::callback('rating', function ($query, $value) {
                    $query->whereRaw('FLOOR(reviews.rating_main) = ?', [(int) $value]);
                }),
                AllowedFilter::callback('product', function ($query, $value) {
                    $query->where('reviews.product_id', (int) $value);
                }),
            ])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    private function whereQuery(Shop|ProductCategory|Product $parent, Builder|QueryBuilder $query): Builder|QueryBuilder
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

    public function avgReview(Shop|ProductCategory|Product $parent): string|null
    {
        $query = Review::query();

        return $this->whereQuery($parent, $query)
            ->avg('rating_main');
    }

    public function handleProductScopeReviews(Shop $shop, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customers.contact_name', $value);
            });
        });

        $isLoggedIn = auth()->check();

        $select = [
            'reviews.id',
            'reviews.rating_main',
            'customers.contact_name',
            'reviews.message',
            'reviews.published_at',
            'reviews.likes',
            'reviews.dislikes',
            'reviews.replay_likes',
            'reviews.replay_dislikes',
            'reviews.web_images',
            'reviews.reply_message as reply',
            'reply_users.contact_name as reply_by',
            'products.name as product_name',
            'products.slug as product_slug',
        ];

        $query = QueryBuilder::for(Review::class)
            ->leftJoin('customers', 'customers.id', 'reviews.customer_id')
            ->leftJoin('users as reply_users', 'reviews.reply_by', '=', 'reply_users.id')
            ->leftJoin('products', 'products.id', '=', 'reviews.product_id');

        if ($isLoggedIn) {
            $user = auth()->user();
            if ($user->customer) {
                array_push($select, 'review_reactions.type as review_reaction');
                array_push($select, 'reply_reactions.type as reply_reaction');

                $query
                    ->leftJoin('review_reactions', function ($join) use ($user) {
                        $join->on('review_reactions.review_id', 'reviews.id')
                            ->where('review_reactions.customer_id', $user->customer?->id)
                            ->where('review_reactions.target', ReviewReactionTargetEnum::REVIEW);
                    })
                    ->leftJoin('review_reactions as reply_reactions', function ($join) use ($user) {
                        $join->on('reply_reactions.review_id', 'reviews.id')
                            ->where('reply_reactions.customer_id', $user->customer?->id)
                            ->where('reply_reactions.target', ReviewReactionTargetEnum::REVIEW_REPLY);
                    });
            }
        }

        $minRating = Arr::get($shop->settings, 'reviews.minimum_rating_to_show', 3);

        $query->select($select)
            ->where('reviews.shop_id', $shop->id)
            ->where('reviews.scope', ReviewScopeEnum::PRODUCT)
            ->where('reviews.rating_main', '>=', $minRating)
            ->where('reviews.state', ReviewStateEnum::PUBLISHED)
            ->where('reviews.is_public', true)
            ->where('reviews.review_status', ReviewStatusEnum::APPROVED);

        return $query
            ->allowedSorts([
                'id',
                AllowedSort::field('recent', 'reviews.published_at'),
                AllowedSort::field('rating', 'reviews.rating_main'),
                AllowedSort::field('helpful', 'reviews.likes'),
            ])
            ->allowedFilters([
                $globalSearch,
                AllowedFilter::callback('rating', function ($query, $value) {
                    $query->whereRaw('FLOOR(reviews.rating_main) = ?', [(int) $value]);
                }),
                AllowedFilter::callback('product', function ($query, $value) {
                    $query->where('reviews.product_id', (int) $value);
                }),
            ])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function avgProductScopeReview(Shop $shop): string|null
    {
        return Review::query()
            ->where('shop_id', $shop->id)
            ->where('scope', ReviewScopeEnum::PRODUCT)
            ->where('state', ReviewStateEnum::PUBLISHED)
            ->where('is_public', true)
            ->where('review_status', ReviewStatusEnum::APPROVED)
            ->avg('rating_main');
    }
}
