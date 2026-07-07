<?php

/*
 * Author Louis Perez
 * Created on 30-06-2026-13h-36m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Reviews\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Review\ReviewReactionTargetEnum;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\WebUser;
use App\Models\Reviews\Review;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexReviewsInIris extends OrgAction
{
    private Shop|ProductCategory|Product $parent;

    public static function make(Shop|ProductCategory|Product|null $parent = null): static
    {
        $instance = app(static::class);

        if ($parent) {
            $instance->parent = $parent;
        }

        return $instance;
    }

    public function handle(Shop|ProductCategory|Product $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $this->parent = $parent;
        $query        = $this->baseQuery();
        $this->applyWhereQuery($parent, $query);

        return $this->applyQueryOptions($query, $prefix);
    }

    private function scopeSetting(Shop $shop, string $type): array
    {
        return Arr::get($shop->settings, "reviews.validation_scope.{$type}", []);
    }

    private function applyOwnerFilter(EloquentBuilder|QueryBuilder $query, Shop $shop, array $setting): void
    {
        $enabled = Arr::get($setting, 'enabled', false);

        if ($enabled && Arr::get($setting, 'scope') == 'group') {
            $query->where('reviews.group_id', $shop->group_id);
        } elseif ($enabled) {
            $query->where('reviews.organisation_id', $shop->organisation_id);
        } else {
            $query->where('reviews.shop_id', $shop->id);
        }
    }

    protected function getElementGroups(Shop $shop, array $scopes, ?callable $extraConditions = null, array $setting = []): array
    {
        $minRating  = Arr::get($shop->settings, 'reviews.minimum_rating_to_show', 3);
        $parent     = $this->parent;
        $enabled    = Arr::get($setting, 'enabled', false);

        $countQuery = Review::query()
            ->selectRaw('FLOOR(rating_main) as star, COUNT(*) as count');

        $this->applyOwnerFilter($countQuery, $shop, $setting);

        if ($parent instanceof Product) {
            if ($enabled && $parent->master_product_id) {
                $countQuery->where('reviews.master_product_id', $parent->master_product_id);
            } else {
                $countQuery->where('reviews.product_id', $parent->id);
            }
        } elseif ($parent instanceof ProductCategory) {
            if ($enabled && $parent->master_product_category_id) {
                $countQuery->where('reviews.master_product_category_id', $parent->master_product_category_id);
            } else {
                $countQuery->where('reviews.product_category_id', $parent->id);
            }
        }

        $countQuery->whereIn('reviews.scope', $scopes)
            ->where('reviews.rating_main', '>=', $minRating)
            ->where('reviews.state', ReviewStateEnum::PUBLISHED)
            ->where('reviews.is_public', true)
            ->where('reviews.review_status', ReviewStatusEnum::APPROVED);

        if ($extraConditions) {
            $extraConditions($countQuery);
        }

        $counts = $countQuery->groupByRaw('FLOOR(rating_main)')
            ->pluck('count', 'star')
            ->toArray();

        $elements = [];
        foreach (range(5, 1) as $star) {
            $label                   = $star === 1 ? __('1 Star') : $star.' '.__('Stars');
            $elements[(string)$star] = [$label, (int)($counts[$star] ?? 0)];
        }

        return [
            'rating' => [
                'label'    => __('Rating'),
                'elements' => $elements,
                'engine'   => function ($query, $elements) {
                    $query->whereRaw(
                        'FLOOR(reviews.rating_main) IN ('.implode(',', array_fill(0, count($elements), '?')).')',
                        array_map('intval', $elements)
                    );
                },
            ],
        ];
    }

    private function applyElementGroups(QueryBuilder $query, Shop $shop, array $scopes, ?string $prefix, ?callable $extraConditions = null, array $setting = []): void
    {
        foreach ($this->getElementGroups($shop, $scopes, $extraConditions, $setting) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }
    }

    public function handleCompanyScopeReviews(Shop $shop, ?string $prefix = null): LengthAwarePaginator
    {
        $this->parent = $shop;
        $scopes       = [ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER];
        $setting      = $this->scopeSetting($shop, 'shop');
        $query        = $this->baseQuery();
        $this->applyShopConditions($shop, $query, $scopes, $setting);
        $this->applyElementGroups($query, $shop, $scopes, $prefix, null, $setting);

        return $this->applyQueryOptions($query, $prefix);
    }

    public function handleProductScopeReviews(Shop $shop, ?string $prefix = null): LengthAwarePaginator
    {
        $this->parent = $shop;
        $scopes       = [ReviewScopeEnum::PRODUCT];
        $setting      = $this->scopeSetting($shop, 'product');
        $query        = $this->baseQuery(withProductJoin: true);
        $this->applyShopConditions($shop, $query, $scopes, $setting);
        $this->applyElementGroups($query, $shop, $scopes, $prefix, null, $setting);

        return $this->applyQueryOptions($query, $prefix, [
            AllowedFilter::callback('product', fn ($q, $v) => $q->where('reviews.product_id', (int) $v)),
        ], ['products.name']);
    }

    public function handleFamilyScopeReviews(Shop $shop, ?string $prefix = null): LengthAwarePaginator
    {
        $this->parent = $shop;
        $scopes       = [ReviewScopeEnum::FAMILY];
        $setting      = $this->scopeSetting($shop, 'family');
        $query        = $this->baseQuery(withFamilyJoin: true);
        $this->applyShopConditions($shop, $query, $scopes, $setting);
        $this->applyElementGroups($query, $shop, $scopes, $prefix, null, $setting);

        return $this->applyQueryOptions($query, $prefix, [
            AllowedFilter::callback('family', fn ($q, $v) => $q->where('reviews.product_category_id', (int) $v)),
        ], ['product_categories.name']);
    }

    public function handleSpecificProductReviews(Product $product, ?string $prefix = null): LengthAwarePaginator
    {
        $this->parent = $product;
        $shop         = $product->shop;
        $scopes       = [ReviewScopeEnum::PRODUCT];
        $setting      = $this->scopeSetting($shop, 'product');
        $enabled      = Arr::get($setting, 'enabled', false);
        $usesMaster   = $enabled && $product->master_product_id;
        $query        = $this->baseQuery(withProductJoin: true);
        $this->applyShopConditions($shop, $query, $scopes, $setting);

        if ($usesMaster) {
            $extraConditions = fn ($q) => $q->where('reviews.master_product_id', $product->master_product_id);
            $query->where('reviews.master_product_id', $product->master_product_id);
        } else {
            $extraConditions = fn ($q) => $q->where('reviews.product_id', $product->id);
            $query->where('reviews.product_id', $product->id);
        }

        $this->applyElementGroups($query, $shop, $scopes, $prefix, $extraConditions, $setting);

        return $this->applyQueryOptions($query, $prefix);
    }

    public function handleSpecificFamilyReviews(ProductCategory $family, ?string $prefix = null): LengthAwarePaginator
    {
        $this->parent = $family;
        $shop         = $family->shop;
        $scopes       = [ReviewScopeEnum::FAMILY];
        $setting      = $this->scopeSetting($shop, 'family');
        $enabled      = Arr::get($setting, 'enabled', false);
        $usesMaster   = $enabled && $family->master_product_category_id;
        $query        = $this->baseQuery(withFamilyJoin: true);
        $this->applyShopConditions($shop, $query, $scopes, $setting);

        if ($usesMaster) {
            $extraConditions = fn ($q) => $q->where('reviews.master_product_category_id', $family->master_product_category_id);
            $query->where('reviews.master_product_category_id', $family->master_product_category_id);
        } else {
            $extraConditions = fn ($q) => $q->where('reviews.product_category_id', $family->id);
            $query->where('reviews.product_category_id', $family->id);
        }

        $this->applyElementGroups($query, $shop, $scopes, $prefix, $extraConditions, $setting);

        return $this->applyQueryOptions($query, $prefix);
    }

    public function handleProductsInFamilyReviews(ProductCategory $family, ?string $prefix = null): LengthAwarePaginator
    {
        $this->parent = $family;
        $shop         = $family->shop;
        $scopes       = [ReviewScopeEnum::PRODUCT];
        $setting      = $this->scopeSetting($shop, 'family');
        $enabled      = Arr::get($setting, 'enabled', false);
        $usesMaster   = $enabled && $family->master_product_category_id;
        $query        = $this->baseQuery(withProductJoin: true);
        $this->applyShopConditions($shop, $query, $scopes, $setting);

        if ($usesMaster) {
            $extraConditions = fn ($q) => $q
                ->join('products as p_count', 'p_count.id', '=', 'reviews.product_id')
                ->join('product_categories as f_count', 'f_count.id', '=', 'p_count.family_id')
                ->where('f_count.master_product_category_id', $family->master_product_category_id);
            $query->join('product_categories as review_family', 'review_family.id', '=', 'products.family_id')
                ->where('review_family.master_product_category_id', $family->master_product_category_id);
        } else {
            $extraConditions = fn ($q) => $q
                ->join('products as p_count', 'p_count.id', '=', 'reviews.product_id')
                ->where('p_count.family_id', $family->id);
            $query->where('products.family_id', $family->id);
        }

        $this->applyElementGroups($query, $shop, $scopes, $prefix, $extraConditions, $setting);

        return $this->applyQueryOptions($query, $prefix, [
            AllowedFilter::callback('product', fn ($q, $v) => $q->where('reviews.product_id', (int) $v)),
        ], ['products.name']);
    }

    public function handleAllScopeReviews(Shop $shop, ?string $prefix = null, array $setting = []): LengthAwarePaginator
    {
        $this->parent = $shop;
        $scopes       = [ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER, ReviewScopeEnum::PRODUCT, ReviewScopeEnum::FAMILY];
        $query        = $this->baseQuery(withProductJoin: true, withFamilyJoin: true);
        $this->applyShopConditions($shop, $query, $scopes, $setting);
        $this->applyElementGroups($query, $shop, $scopes, $prefix, null, $setting);

        return $this->applyQueryOptions($query, $prefix, [], ['products.name', 'product_categories.name']);
    }

    private function baseQuery(bool $withProductJoin = false, bool $withFamilyJoin = false): QueryBuilder
    {
        if ($this->parent instanceof Shop) {
            $language = $this->parent->language;
        } else {
            $language = $this->parent->shop?->language;
        }

        $select = [
            'reviews.id',
            'reviews.scope',
            'reviews.rating_main',
            'reviews.is_public',
            'customers.contact_name',
            'customers.location',
            'reviews.message',
            'reviews.published_at',
            'reviews.likes',
            'reviews.dislikes',
            'reviews.replay_likes',
            'reviews.replay_dislikes',
            'reviews.web_images',
            'reviews.reply_message',
            'reviews.reply_at',
            'reviews.translations',
            'reply_users.contact_name as reply_by',
        ];

        if ($language) {
            $select[] = DB::raw("'$language->id' as language_id");
        }

        $query = QueryBuilder::for(Review::class)
            ->leftJoin('customers', 'customers.id', 'reviews.customer_id')
            ->leftJoin('users as reply_users', 'reviews.reply_by', '=', 'reply_users.id');

        if ($withProductJoin) {
            $scopeCondition = $withFamilyJoin ? ReviewScopeEnum::PRODUCT : null;
            $query->leftJoin('products', function ($join) use ($scopeCondition) {
                $join->on('products.id', '=', 'reviews.product_id');
                if ($scopeCondition) {
                    $join->where('reviews.scope', $scopeCondition);
                }
            });
        }

        if ($withFamilyJoin) {
            $scopeCondition = $withProductJoin ? ReviewScopeEnum::FAMILY : null;
            $query->leftJoin('product_categories', function ($join) use ($scopeCondition) {
                $join->on('product_categories.id', '=', 'reviews.product_category_id');
                if ($scopeCondition) {
                    $join->where('reviews.scope', $scopeCondition);
                }
            });
        }

        if ($withProductJoin) {
            $select[] = 'products.name as product_name';
            $select[] = 'products.slug as product_slug';
            $select[] = 'products.code as product_code';
        }

        if ($withFamilyJoin) {
            $select[] = 'product_categories.name as family_name';
            $select[] = 'product_categories.code as family_code';
            $select[] = 'product_categories.slug as family_slug';
        }

        if (auth()->check()) {
            /** @var WebUser $webUser */
            $webUser = auth()->user();
            if ($webUser->customer) {
                $select[] = 'review_reactions.type as review_reaction';
                $select[] = 'reply_reactions.type as reply_reaction';

                $query
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

        return $query->select($select);
    }

    private function applyShopConditions(Shop $shop, QueryBuilder $query, array $scopes, array $setting = []): void
    {
        $minRating = Arr::get($shop->settings, 'reviews.minimum_rating_to_show', 3);

        $this->applyOwnerFilter($query, $shop, $setting);

        $query->whereIn('reviews.scope', $scopes)
            ->where('reviews.rating_main', '>=', $minRating)
            ->where('reviews.state', ReviewStateEnum::PUBLISHED)
            ->where('reviews.is_public', true)
            ->where('reviews.review_status', ReviewStatusEnum::APPROVED);
    }

    private function applyQueryOptions(QueryBuilder $query, ?string $prefix, array $extraFilters = [], array $extraSearchColumns = []): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return $query
            ->allowedSorts([
                'id',
                AllowedSort::field('created_at', 'reviews.published_at'),
                AllowedSort::field('recent', 'reviews.published_at'),
                AllowedSort::field('rating', 'reviews.rating_main'),
                AllowedSort::field('helpful', 'reviews.likes'),
            ])
            ->allowedFilters([
                AllowedFilter::callback('global', function ($query, $value) use ($extraSearchColumns) {
                    $query->where(function ($query) use ($value, $extraSearchColumns) {
                        $query->whereAnyWordStartWith('customers.contact_name', $value);
                        foreach ($extraSearchColumns as $column) {
                            $query->orWhereAnyWordStartWith($column, $value);
                        }
                    });
                }),
                AllowedFilter::callback('rating', function ($query, $value) {
                    $query->whereRaw('FLOOR(reviews.rating_main) = ?', [(int) $value]);
                }),
                ...$extraFilters,
            ])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    private function applyWhereQuery(Shop|ProductCategory|Product $parent, EloquentBuilder|QueryBuilder $query): void
    {
        $shop    = $parent instanceof Shop ? $parent : $parent->shop;
        $enabled = false;

        if ($parent instanceof Product) {
            $setting  = $this->scopeSetting($shop, 'product');
            $enabled  = Arr::get($setting, 'enabled', false);
            $query->where('reviews.scope', ReviewScopeEnum::PRODUCT);
            $this->applyOwnerFilter($query, $shop, $setting);
            if ($enabled && $parent->master_product_id) {
                $query->where('reviews.master_product_id', $parent->master_product_id);
            } else {
                $query->where('reviews.product_id', $parent->id);
            }
        } elseif ($parent instanceof ProductCategory) {
            $setting  = $this->scopeSetting($shop, 'family');
            $enabled  = Arr::get($setting, 'enabled', false);
            $query->where('reviews.scope', ReviewScopeEnum::FAMILY);
            $this->applyOwnerFilter($query, $shop, $setting);
            if ($enabled && $parent->master_product_category_id) {
                $query->where('reviews.master_product_category_id', $parent->master_product_category_id);
            } else {
                $query->where('reviews.product_category_id', $parent->id);
            }
        } else {
            $setting = $this->scopeSetting($shop, 'shop');
            $query->whereIn('reviews.scope', [ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER]);
            $this->applyOwnerFilter($query, $shop, $setting);
        }

        $minRating = Arr::get($shop->settings, 'reviews.minimum_rating_to_show', 3);

        $query->where('reviews.rating_main', '>=', $minRating)
            ->where('reviews.state', ReviewStateEnum::PUBLISHED)
            ->where('reviews.is_public', true)
            ->where('reviews.review_status', ReviewStatusEnum::APPROVED);
    }

    public function avgReview(Shop|ProductCategory|Product $parent): string|null
    {
        $query = Review::query();
        $this->applyWhereQuery($parent, $query);

        return $query->avg('rating_main');
    }

    public function avgByScopeReview(Shop $shop, array $scopes, array $setting = []): string|null
    {
        $minRating = Arr::get($shop->settings, 'reviews.minimum_rating_to_show', 3);

        $query = Review::query()
            ->whereIn('scope', $scopes)
            ->where('rating_main', '>=', $minRating)
            ->where('state', ReviewStateEnum::PUBLISHED)
            ->where('is_public', true)
            ->where('review_status', ReviewStatusEnum::APPROVED);

        $this->applyOwnerFilter($query, $shop, $setting);

        return $query->avg('rating_main');
    }

    public function tableStructure(?string $prefix = null, ?Shop $shop = null, array $scopes = [], ?callable $extraConditions = null, array $setting = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $shop, $scopes, $extraConditions, $setting) {
            if ($prefix) {
                $table->name($prefix)->pageName("{$prefix}Page");
            }

            $table->withGlobalSearch();

            $table->column(key: 'name', label: __('Reviewer'), canBeHidden: false)
                ->column(key: 'review', label: __('Review'), canBeHidden: false)
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true);

            if ($shop && !empty($scopes)) {
                foreach ($this->getElementGroups($shop, $scopes, $extraConditions, $setting) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }
        };
    }
}
