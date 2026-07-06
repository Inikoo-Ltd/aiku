<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Jul 2026 13:52:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews\Iris;

use App\Actions\Reviews\Iris\Traits\WithGetIrisReviewsTrait;
use App\Enums\Catalogue\Review\ReviewReactionTargetEnum;
use App\Models\Catalogue\Product;
use App\Models\CRM\WebUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\QueryBuilder\AllowedFilter;

class GetIrisProductReviews
{
    use AsObject;
    use WithGetIrisReviewsTrait;

    public function handle(Product $product, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('reviews.message', $value);
            });
        });

        $shop = $product->shop;

        $queryBuilder = $this->getBaseQuery($product);

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
            DB::raw("'{$shop->language_id}' as language_id")
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

        return $queryBuilder
            ->leftJoin('customers', 'customers.id', '=', 'reviews.customer_id')
            ->leftJoin('users as reply_users', 'reviews.reply_by', '=', 'reply_users.id')
            ->select($select)
            ->defaultSort('-created_at')
            ->allowedSorts($allowedSort)
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }
}
