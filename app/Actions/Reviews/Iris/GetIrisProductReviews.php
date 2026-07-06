<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Jul 2026 13:52:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews\Iris;

use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Reviews\Review;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\QueryBuilder\AllowedFilter;

class GetIrisProductReviews
{
    use asObject;

    public function handle(Product $product, $prefix = null): LengthAwarePaginator
    {
        $setting = Arr::get($product->shop->settings, 'reviews.validation_scope.product', []);

        $queryBuilder = QueryBuilder::for(Review::class);
        if (Arr::get($setting, 'enabled', false)) {
            if (Arr::get($setting, 'scope') == 'group') {
                $queryBuilder->where('reviews.group_id', $product->group_id);
            } else {
                $queryBuilder->where('reviews.organisation_id', $product->organisation_id);
            }
            $queryBuilder->where('master_product_id', $product->master_product_id);
        } else {
            $queryBuilder->where('shop_id', $product->shop_id);
            $queryBuilder->where('product_id', $product->id);
        }

        $minRating = Arr::get($product->shop->settings, 'reviews.minimum_rating_to_show', 3);

        $queryBuilder->where('reviews.rating_main', '>=', $minRating)
            ->where('reviews.state', ReviewStateEnum::PUBLISHED)
            ->where('reviews.is_public', true)
            ->where('reviews.review_status', ReviewStatusEnum::APPROVED)
            ->where('reviews.scope', ReviewScopeEnum::PRODUCT);

        $allowedSort = [
            'rating_main',
        ];

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('reviews.message', $value);
            });
        });

        return $queryBuilder
            ->select([
                'reviews.id',
                'customers.contact_name',
            ])
            ->leftJoin('customers', 'customers.id', '=', 'reviews.customer_id')
            ->defaultSort('-reviews.created_at')
            ->allowedSorts($allowedSort)
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


}
